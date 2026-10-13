<?php

namespace App\Services;

use App\Models\ContentItem as Item;
use App\Models\StatusHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only place content status changes. Refuses transitions outside the table and writes a history row for each change.
 */
class ContentWorkflow
{
    /** action => [allowed from-statuses, to-status] */
    public const TRANSITIONS = [
        'submit' => [[Item::DRAFT], Item::PENDING],
        'approve' => [[Item::PENDING], Item::APPROVED],
        'request_changes' => [[Item::PENDING], Item::DRAFT],
        'reject' => [[Item::PENDING], Item::REJECTED],
        'reopen' => [[Item::REJECTED], Item::DRAFT],
        'edit' => [[Item::DRAFT, Item::APPROVED, Item::SCHEDULED], Item::DRAFT],
        'schedule' => [[Item::APPROVED, Item::SCHEDULED], Item::SCHEDULED],
        'unschedule' => [[Item::SCHEDULED], Item::APPROVED],
        'simulate_publish' => [[Item::SCHEDULED], Item::PUBLISHED],
        'mark_published' => [[Item::SCHEDULED], Item::PUBLISHED],
    ];

    public const SIMULATED_NOTE = 'Simulated — not sent to any platform.';

    public const DUE_NOTE = 'Scheduled time reached. Automatic publishing is disabled (no verified account).';

    public function __construct(private string $actor = 'admin') {}

    public function submit(Item $item): Item
    {
        return $this->transition($item, 'submit', 'Submitted for approval.', ['feedback' => null]);
    }

    public function approve(Item $item): Item
    {
        return $this->transition($item, 'approve', 'Approved.');
    }

    public function requestChanges(Item $item, ?string $feedback): Item
    {
        $feedback = $this->required($feedback, 'Feedback is required when requesting changes.');

        return $this->transition($item, 'request_changes', "Changes requested: {$feedback}", ['feedback' => $feedback]);
    }

    public function reject(Item $item, ?string $reason): Item
    {
        $reason = $this->required($reason, 'A reason is required to reject content.');

        return $this->transition($item, 'reject', "Rejected: {$reason}", ['rejection_reason' => $reason]);
    }

    public function reopen(Item $item): Item
    {
        return $this->transition($item, 'reopen', 'Reopened as a draft.', ['rejection_reason' => null]);
    }

    /**
     * Save edits. Editing approved or scheduled content returns it to Draft so it is reviewed again.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function edit(Item $item, array $attributes): Item
    {
        if ($item->fresh()?->status === Item::DRAFT) {
            return DB::transaction(function () use ($item, $attributes) {
                $item->refresh();
                $item->update($attributes);
                $this->record($item, Item::DRAFT, Item::DRAFT, 'Edited.');

                return $item;
            });
        }

        return $this->transition($item, 'edit', 'Edited after '.Item::STATUS_LABELS[$item->status].'; returned to Draft for review.', $attributes + ['scheduled_at' => null, 'due_recorded_at' => null]);
    }

    /**
     * Schedule or reschedule from a date and time entered in the display timezone (Asia/Dhaka). Stored as UTC.
     */
    public function schedule(Item $item, ?string $date, ?string $time): Item
    {
        $tz = config('app.display_timezone');
        $local = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) && preg_match('/^\d{2}:\d{2}$/', (string) $time)) {
            $local = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", $tz);
        }
        if (! $local || $local->format('Y-m-d H:i') !== "{$date} {$time}") {
            throw new WorkflowException('Enter a valid date and time.');
        }
        if ($local->lte(now())) {
            throw new WorkflowException('The scheduled time must be in the future.');
        }

        $verb = $item->fresh()?->status === Item::SCHEDULED ? 'Rescheduled' : 'Scheduled';

        return $this->transition($item, 'schedule', "{$verb} for ".$local->format('j M Y, g:i A')." ({$tz}).",
            ['scheduled_at' => $local->copy()->utc(), 'due_recorded_at' => null]);
    }

    public function unschedule(Item $item): Item
    {
        return $this->transition($item, 'unschedule', 'Unscheduled; back to Approved.', ['scheduled_at' => null, 'due_recorded_at' => null]);
    }

    public function simulatePublish(Item $item): Item
    {
        return $this->transition($item, 'simulate_publish', self::SIMULATED_NOTE, ['published_at' => now(), 'publish_method' => 'simulated']);
    }

    public function markManuallyPublished(Item $item, bool $confirmed): Item
    {
        if (! $confirmed) {
            throw new WorkflowException('Confirm that the post was actually published before marking it as published.');
        }

        return $this->transition($item, 'mark_published', 'Manually confirmed as published by the user.', ['published_at' => now(), 'publish_method' => 'manual']);
    }

    /**
     * Note that a scheduled item's time has passed. Never publishes. Returns false if already noted or not due.
     */
    public function recordDue(Item $item): bool
    {
        return DB::transaction(function () use ($item) {
            $fresh = Item::lockForUpdate()->find($item->id);
            if (! $fresh || $fresh->status !== Item::SCHEDULED || $fresh->due_recorded_at || ! $fresh->scheduled_at?->lte(now())) {
                return false;
            }
            $fresh->update(['due_recorded_at' => now()]);
            $this->record($fresh, Item::SCHEDULED, Item::SCHEDULED, self::DUE_NOTE);

            return true;
        });
    }

    public function can(Item $item, string $action): bool
    {
        return isset(self::TRANSITIONS[$action]) && in_array($item->status, self::TRANSITIONS[$action][0], true);
    }

    protected function transition(Item $item, string $action, string $note, array $attributes = []): Item
    {
        return DB::transaction(function () use ($item, $action, $note, $attributes) {
            $item->refresh();
            if (! $this->can($item, $action)) {
                $label = str_replace('_', ' ', $action);
                throw new WorkflowException("Cannot {$label} content that is ".Item::STATUS_LABELS[$item->status].'.');
            }
            $from = $item->status;
            $to = self::TRANSITIONS[$action][1];
            $item->update(['status' => $to] + $attributes);
            $this->record($item, $from, $to, $note);

            return $item;
        });
    }

    protected function record(Item $item, ?string $from, string $to, string $note): void
    {
        StatusHistory::create([
            'content_item_id' => $item->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'actor' => $this->actor,
        ]);
    }

    private function required(?string $text, string $message): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            throw new WorkflowException($message);
        }

        return $text;
    }
}
