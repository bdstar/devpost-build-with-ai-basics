<?php

namespace App\Services;

use App\Models\BusinessProfile;
use App\Models\ContentIdea;
use App\Models\ContentItem;
use App\Models\ContentRound;
use App\Models\KnowledgeRecord;
use App\Models\PerformanceDataset;

/**
 * Works out each of the five steps' state from the database, for the Dashboard and the step indicator.
 * A step is "locked" only when the app actually refuses its action; the reason says what to do first.
 */
class WorkflowProgress
{
    public const COMPLETE = 'complete';
    public const OPEN = 'open';
    public const LOCKED = 'locked';

    private ?array $steps = null;

    /**
     * @return list<array{number:int, title:string, state:string, reason:?string, summary:string, url:string, action:string}>
     */
    public function steps(): array
    {
        if ($this->steps !== null) {
            return $this->steps;
        }

        $profile = BusinessProfile::current();
        $confirmed = (bool) $profile?->isConfirmed();
        $roundOne = ContentRound::latestNumber(1);
        $roundTwo = ContentRound::latestNumber(2);
        $selectedOne = $roundOne ? $roundOne->ideas()->where('selected', true)->count() : 0;
        $anySelected = ContentIdea::where('selected', true)->exists();
        $items = ContentItem::count();
        $approved = ContentItem::whereIn('status', [ContentItem::APPROVED, ContentItem::SCHEDULED, ContentItem::PUBLISHED])->count();
        $dataset = PerformanceDataset::currentSynthetic();
        $knowledge = KnowledgeRecord::count();
        $profileLock = $confirmed ? null : 'Confirm the business profile first.';

        $this->steps = [
            [
                'number' => 1,
                'title' => 'Business Profile',
                'state' => $confirmed ? self::COMPLETE : self::OPEN,
                'reason' => null,
                'summary' => match (true) {
                    $confirmed => 'Confirmed.',
                    $profile !== null => 'Draft: review and confirm it.',
                    default => 'Not started.',
                },
                'url' => route('profile.show'),
                'action' => $profile ? 'Review and confirm the business profile' : 'Build the business profile',
            ],
            [
                'number' => 2,
                'title' => 'Round-One Ideas',
                'state' => $profileLock ? self::LOCKED : ($roundOne && $selectedOne > 0 ? self::COMPLETE : self::OPEN),
                'reason' => $profileLock,
                'summary' => $roundOne ? $roundOne->ideas()->count()." ideas, {$selectedOne} selected." : 'No ideas yet.',
                'url' => route('workflow.index').'#round-1',
                'action' => $roundOne ? 'Select round-one ideas' : 'Generate round-one ideas',
            ],
            [
                'number' => 3,
                'title' => 'Generate, Review and Approve',
                'state' => $profileLock || ! $anySelected ? self::LOCKED : ($approved > 0 ? self::COMPLETE : self::OPEN),
                'reason' => $profileLock ?? ($anySelected ? null : 'Select at least one idea in Step 2 first.'),
                'summary' => $items ? "{$items} content item(s), {$approved} approved or later." : 'No content yet.',
                'url' => route('content.index'),
                'action' => $items ? 'Review and approve content' : 'Generate content for the selected ideas',
            ],
            [
                'number' => 4,
                'title' => 'Load and Analyse Performance',
                'state' => $dataset ? self::COMPLETE : self::OPEN,
                'reason' => null,
                'summary' => $dataset ? 'Synthetic dataset loaded ('.$dataset->records()->count().' records).' : 'No performance data loaded.',
                'url' => route('performance.index'),
                'action' => 'Load the synthetic performance data',
            ],
            [
                'number' => 5,
                'title' => 'Knowledge and Round Two',
                'state' => match (true) {
                    $profileLock !== null, ! $roundOne => self::LOCKED,
                    $roundTwo !== null => self::COMPLETE,
                    default => self::OPEN,
                },
                'reason' => $profileLock ?? ($roundOne ? null : 'Generate round-one ideas first, so round two has something to be compared with.'),
                'summary' => match (true) {
                    $roundTwo !== null => $roundTwo->ideas()->count().' round-two ideas, '.$roundTwo->ideas()->where('knowledge_driven', true)->count().' knowledge-driven.',
                    $knowledge > 0 => "{$knowledge} knowledge records saved; round two not generated.",
                    default => 'No knowledge records yet.',
                },
                'url' => $knowledge > 0 ? route('workflow.index').'#round-2' : route('performance.index'),
                'action' => $knowledge > 0 ? 'Generate round two' : 'Save insights as knowledge records',
            ],
        ];

        return $this->steps;
    }

    public function completed(): int
    {
        return collect($this->steps())->where('state', self::COMPLETE)->count();
    }

    /** The first incomplete step, or null when every step is complete. */
    public function current(): ?array
    {
        return collect($this->steps())->first(fn ($s) => $s['state'] !== self::COMPLETE);
    }

    /** @return array{label:string, url:string} */
    public function next(): array
    {
        $step = $this->current();
        if (! $step) {
            return ['label' => 'Review the round-one vs round-two comparison', 'url' => route('workflow.index').'#comparison'];
        }

        return ['label' => "Step {$step['number']} — {$step['action']}", 'url' => $step['url']];
    }
}
