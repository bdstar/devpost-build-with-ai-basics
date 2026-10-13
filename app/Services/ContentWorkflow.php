<?php

namespace App\Services;

use App\Models\ContentItem as Item;
use App\Models\StatusHistory;
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
    ];

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
        if ($item->status === Item::DRAFT) {
            return DB::transaction(function () use ($item, $attributes) {
                $item->update($attributes);
                $this->record($item, Item::DRAFT, Item::DRAFT, 'Edited.');

                return $item;
            });
        }

        return $this->transition($item, 'edit', 'Edited after '.Item::STATUS_LABELS[$item->status].'; returned to Draft for review.', $attributes + ['scheduled_at' => null, 'due_recorded_at' => null]);
    }

    public function can(Item $item, string $action): bool
    {
        return isset(self::TRANSITIONS[$action]) && in_array($item->status, self::TRANSITIONS[$action][0], true);
    }

    protected function transition(Item $item, string $action, string $note, array $attributes = []): Item
    {
        if (! $this->can($item, $action)) {
            $label = str_replace('_', ' ', $action);
            throw new WorkflowException("Cannot {$label} content that is ".Item::STATUS_LABELS[$item->status].'.');
        }

        return DB::transaction(function () use ($item, $action, $note, $attributes) {
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
