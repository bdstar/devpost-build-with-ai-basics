<?php

namespace Tests\Unit;

use App\Models\ContentIdea;
use App\Models\ContentItem;
use App\Models\ContentRound;
use App\Services\ContentWorkflow;
use App\Services\WorkflowException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ContentWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = new ContentWorkflow;
    }

    private function item(string $status = ContentItem::DRAFT): ContentItem
    {
        $round = ContentRound::create(['number' => 1, 'mode' => 'fixtures', 'profile_snapshot' => []]);
        $idea = ContentIdea::create([
            'round_id' => $round->id, 'title' => 'Test idea', 'platform' => 'Facebook', 'format' => 'Image',
            'campaign_goal' => 'Awareness', 'provenance' => 'demo_fixture',
        ]);

        return ContentItem::create(['idea_id' => $idea->id, 'caption' => 'Caption', 'status' => $status, 'provenance' => 'demo_fixture']);
    }

    public function test_review_path_draft_to_approved_records_each_step(): void
    {
        $item = $this->item();

        $this->workflow->submit($item);
        $this->assertSame(ContentItem::PENDING, $item->status);
        $this->workflow->approve($item);
        $this->assertSame(ContentItem::APPROVED, $item->status);

        $history = $item->histories()->get();
        $this->assertSame([[ContentItem::DRAFT, ContentItem::PENDING], [ContentItem::PENDING, ContentItem::APPROVED]],
            $history->map(fn ($h) => [$h->from_status, $h->to_status])->all());
        $this->assertSame('admin', $history->first()->actor);
    }

    public function test_draft_cannot_be_approved_directly(): void
    {
        $item = $this->item();

        $this->expectException(WorkflowException::class);
        try {
            $this->workflow->approve($item);
        } finally {
            $this->assertSame(ContentItem::DRAFT, $item->fresh()->status);
            $this->assertSame(0, $item->histories()->count());
        }
    }

    public function test_rejected_content_cannot_be_approved_or_submitted(): void
    {
        $item = $this->item(ContentItem::REJECTED);

        foreach (['approve', 'submit'] as $action) {
            try {
                $this->workflow->{$action}($item);
                $this->fail("{$action} should be refused for rejected content");
            } catch (WorkflowException) {
                $this->assertSame(ContentItem::REJECTED, $item->fresh()->status);
            }
        }
        $this->assertFalse($this->workflow->can($item, 'edit'));
    }

    public function test_reject_and_request_changes_require_text(): void
    {
        $item = $this->item(ContentItem::PENDING);

        foreach ([fn () => $this->workflow->reject($item, '  '), fn () => $this->workflow->requestChanges($item, null)] as $call) {
            try {
                $call();
                $this->fail('Empty text should be refused');
            } catch (WorkflowException) {
                $this->assertSame(ContentItem::PENDING, $item->fresh()->status);
            }
        }
    }

    public function test_request_changes_returns_to_draft_with_feedback_and_submit_clears_it(): void
    {
        $item = $this->item(ContentItem::PENDING);

        $this->workflow->requestChanges($item, 'Shorter caption please');
        $this->assertSame(ContentItem::DRAFT, $item->status);
        $this->assertSame('Shorter caption please', $item->feedback);

        $this->workflow->submit($item);
        $this->assertNull($item->fresh()->feedback);
    }

    public function test_reject_then_reopen(): void
    {
        $item = $this->item(ContentItem::PENDING);

        $this->workflow->reject($item, 'Off brand');
        $this->assertSame('Off brand', $item->rejection_reason);
        $this->workflow->reopen($item);

        $this->assertSame(ContentItem::DRAFT, $item->status);
        $this->assertNull($item->rejection_reason);
        $this->assertSame(2, $item->histories()->count());
    }

    public function test_editing_approved_or_scheduled_content_returns_it_to_draft(): void
    {
        foreach ([ContentItem::APPROVED, ContentItem::SCHEDULED] as $status) {
            $item = $this->item($status);
            $item->update(['scheduled_at' => now()->addDay()]);

            $this->workflow->edit($item, ['caption' => 'New caption']);

            $item->refresh();
            $this->assertSame(ContentItem::DRAFT, $item->status);
            $this->assertSame('New caption', $item->caption);
            $this->assertNull($item->scheduled_at);
            $this->assertSame([$status, ContentItem::DRAFT], [$item->histories->last()->from_status, $item->histories->last()->to_status]);
        }
    }

    public function test_editing_a_draft_keeps_it_draft_and_records_history(): void
    {
        $item = $this->item();

        $this->workflow->edit($item, ['caption' => 'Edited']);

        $this->assertSame(ContentItem::DRAFT, $item->fresh()->status);
        $this->assertSame('Edited.', $item->histories()->first()->note);
    }

    public function test_pending_and_published_content_cannot_be_edited(): void
    {
        foreach ([ContentItem::PENDING, ContentItem::PUBLISHED] as $status) {
            $item = $this->item($status);
            try {
                $this->workflow->edit($item, ['caption' => 'Nope']);
                $this->fail("Editing {$status} content should be refused");
            } catch (WorkflowException) {
                $this->assertSame('Caption', $item->fresh()->caption);
            }
        }
    }
}
