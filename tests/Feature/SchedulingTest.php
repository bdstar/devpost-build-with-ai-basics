<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\ContentItem;
use App\Models\ContentRound;
use App\Models\User;
use App\Services\ContentWorkflow;
use App\Services\WorkflowException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchedulingTest extends TestCase
{
    use RefreshDatabase;

    private ContentWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->workflow = new ContentWorkflow;
        // 13 Oct 2026, 12:00 UTC = 18:00 Asia/Dhaka (UTC+6).
        $this->travelTo(Carbon::parse('2026-10-13 12:00:00', 'UTC'));
    }

    private function item(string $status = ContentItem::APPROVED): ContentItem
    {
        $round = ContentRound::create(['number' => 1, 'mode' => 'fixtures', 'profile_snapshot' => []]);
        $idea = ContentIdea::create([
            'round_id' => $round->id, 'title' => 'Aviary weekend', 'platform' => 'Facebook', 'format' => 'Image',
            'campaign_goal' => 'Awareness', 'provenance' => 'demo_fixture',
        ]);

        return ContentItem::create(['idea_id' => $idea->id, 'caption' => 'Caption', 'status' => $status, 'provenance' => 'demo_fixture']);
    }

    public function test_dhaka_input_is_stored_as_utc(): void
    {
        $item = $this->item();

        $this->post("/content/{$item->id}/schedule", ['date' => '2026-10-14', 'time' => '15:00'])->assertSessionHas('status');

        $this->assertSame(ContentItem::SCHEDULED, $item->fresh()->status);
        $this->assertSame('2026-10-14 09:00:00', DB::table('content_items')->where('id', $item->id)->value('scheduled_at'));
        $this->get("/content/{$item->id}")->assertSee('Scheduled for 14 Oct 2026, 3:00 PM (Asia/Dhaka)');
    }

    public function test_only_approved_items_can_be_scheduled(): void
    {
        foreach ([ContentItem::DRAFT, ContentItem::PENDING, ContentItem::REJECTED, ContentItem::PUBLISHED] as $status) {
            $item = $this->item($status);
            $this->post("/content/{$item->id}/schedule", ['date' => '2026-10-20', 'time' => '10:00'])->assertSessionHas('error');
            $this->assertSame($status, $item->fresh()->status);
            $this->assertNull($item->fresh()->scheduled_at);
        }
    }

    public function test_past_and_invalid_times_are_refused(): void
    {
        $item = $this->item();

        // 17:59 Dhaka is one minute before "now" (18:00 Dhaka).
        foreach ([['2026-10-13', '17:59'], ['2026-10-13', '18:00'], ['2026-02-30', '10:00'], ['', ''], ['2026-10-20', '25:00']] as [$date, $time]) {
            try {
                $this->workflow->schedule($item, $date, $time);
                $this->fail("{$date} {$time} should be refused");
            } catch (WorkflowException) {
                $this->assertSame(ContentItem::APPROVED, $item->fresh()->status);
            }
        }
    }

    public function test_past_scheduled_time_shows_ready_to_publish_but_stays_scheduled(): void
    {
        $item = $this->item();
        $this->workflow->schedule($item, '2026-10-13', '18:02');
        $this->get("/content/{$item->id}")->assertDontSee('pill-ready')->assertDontSee('is-ready');

        $this->travel(3)->minutes();

        $this->assertTrue($item->fresh()->isReadyToPublish());
        $this->assertSame(ContentItem::SCHEDULED, $item->fresh()->status);
        $this->get("/content/{$item->id}")->assertSee('pill-ready')->assertSee('Ready to Publish — Manual Action Required')
            ->assertSee('Automatic publishing is disabled: no verified account connection.');
        $this->get('/content')->assertSee('Ready to Publish — Manual Action Required');
    }

    public function test_check_due_writes_one_note_and_is_idempotent(): void
    {
        $due = $this->item();
        $later = $this->item();
        $this->workflow->schedule($due, '2026-10-13', '18:02');
        $this->workflow->schedule($later, '2026-10-20', '10:00');
        $this->travel(5)->minutes();

        $this->artisan('content:check-due')->expectsOutputToContain('1 scheduled item(s)')->assertSuccessful();
        $this->artisan('content:check-due')->expectsOutputToContain('0 scheduled item(s)')->assertSuccessful();

        $notes = $due->histories()->where('note', ContentWorkflow::DUE_NOTE)->get();
        $this->assertCount(1, $notes);
        $this->assertSame('system', $notes->first()->actor);
        $this->assertSame(ContentItem::SCHEDULED, $due->fresh()->status);
        $this->assertNotNull($due->fresh()->due_recorded_at);
        $this->assertNull($later->fresh()->due_recorded_at);
        $this->assertSame(0, ContentItem::where('status', ContentItem::PUBLISHED)->count());
    }

    public function test_check_due_is_registered_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'content:check-due'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_rescheduling_clears_the_due_note_marker(): void
    {
        $item = $this->item();
        $this->workflow->schedule($item, '2026-10-13', '18:02');
        $this->travel(5)->minutes();
        $this->artisan('content:check-due');

        $this->workflow->schedule($item, '2026-10-14', '09:00');

        $this->assertNull($item->fresh()->due_recorded_at);
        $this->assertFalse($item->fresh()->isReadyToPublish());
        $this->assertStringStartsWith('Rescheduled for 14 Oct 2026, 9:00 AM', $item->histories->last()->note);
    }

    public function test_unschedule_returns_to_approved(): void
    {
        $item = $this->item();
        $this->workflow->schedule($item, '2026-10-14', '09:00');

        $this->post("/content/{$item->id}/unschedule")->assertSessionHas('status');

        $this->assertSame(ContentItem::APPROVED, $item->fresh()->status);
        $this->assertNull($item->fresh()->scheduled_at);
    }

    public function test_manual_publish_requires_confirmation(): void
    {
        $item = $this->item();
        $this->workflow->schedule($item, '2026-10-14', '09:00');

        $this->post("/content/{$item->id}/mark-published")->assertSessionHas('error');
        $this->assertSame(ContentItem::SCHEDULED, $item->fresh()->status);

        $this->post("/content/{$item->id}/mark-published", ['confirmed' => '1'])->assertSessionHas('status');
        $item->refresh();
        $this->assertSame(ContentItem::PUBLISHED, $item->status);
        $this->assertSame('manual', $item->publish_method);
        $this->get("/content/{$item->id}")->assertSee('Published · Manually confirmed');
    }

    public function test_simulated_publish_is_labelled(): void
    {
        $item = $this->item();
        $this->workflow->schedule($item, '2026-10-14', '09:00');

        $this->post("/content/{$item->id}/simulate-publish")->assertSessionHas('status');

        $item->refresh();
        $this->assertSame(ContentItem::PUBLISHED, $item->status);
        $this->assertSame('simulated', $item->publish_method);
        $this->assertSame(ContentWorkflow::SIMULATED_NOTE, $item->histories->last()->note);
        $this->get("/content/{$item->id}")->assertSee('Published · Simulated (demo)')->assertSee('Simulated — not sent to any platform.');
    }

    public function test_approved_content_cannot_be_published_without_scheduling(): void
    {
        $item = $this->item();

        $this->post("/content/{$item->id}/simulate-publish")->assertSessionHas('error');
        $this->post("/content/{$item->id}/mark-published", ['confirmed' => '1'])->assertSessionHas('error');
        $this->assertSame(ContentItem::APPROVED, $item->fresh()->status);
    }
}
