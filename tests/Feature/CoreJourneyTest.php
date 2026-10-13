<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\ContentRound;
use App\Models\KnowledgeRecord;
use App\Models\PerformanceDataset;
use App\Services\RoundComparison;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The whole core journey in Demo Fixtures mode, through HTTP only, from login to the round-two comparison.
 */
class CoreJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_journey_from_login_to_round_two_comparison(): void
    {
        config(['ai.mode' => 'fixtures', 'app.admin' => ['name' => 'Demo Admin', 'email' => 'admin@example.test', 'password' => 'journey-pass']]);
        $this->seed(AdminSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-13 06:00:00', 'UTC'));

        // Sign in and land on the Dashboard.
        $this->get('/')->assertRedirect('/login');
        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'journey-pass'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Continue: Step 1');

        // Step 1: manual profile (no network in tests), then confirm.
        $this->post('/profile/manual', [
            'website_url' => 'https://aquariumpark.com.bd/',
            'fields' => ['business_name' => 'Aquarium Park', 'overview' => 'Indoor aquarium and family attraction in Dhaka.'],
        ])->assertRedirect('/profile');
        $this->post('/profile/confirm')->assertRedirect('/profile');
        $this->get('/')->assertSee('Continue: Step 2');

        // Step 2: round one, select an image idea and a video idea.
        $this->post('/workflow/round-one')->assertRedirect();
        $roundOne = ContentRound::latestNumber(1);
        $picked = [
            $roundOne->ideas()->where('format', 'Image')->first()->id,
            $roundOne->ideas()->where('format', 'Reel')->first()->id,
        ];
        $this->post("/workflow/rounds/{$roundOne->id}/selection", ['selected' => $picked])->assertRedirect();
        $this->get('/')->assertSee('Continue: Step 3');

        // Step 3: generate, review, approve and schedule.
        $this->post('/content/generate')->assertSessionHas('status');
        $this->assertSame(2, ContentItem::count());
        $video = ContentItem::whereHas('idea', fn ($q) => $q->where('format', 'Reel'))->first();
        $this->get("/content/{$video->id}")->assertSee('Video Not Generated — Planning Preview Only')->assertDontSee('<video', false);

        $image = ContentItem::whereHas('idea', fn ($q) => $q->where('format', 'Image'))->first();
        $this->post("/content/{$image->id}/submit");
        $this->post("/content/{$image->id}/request-changes", ['feedback' => 'Add opening hours']);
        $this->post("/content/{$image->id}/submit");
        $this->post("/content/{$image->id}/approve")->assertSessionHas('status');
        $this->post("/content/{$image->id}/schedule", ['date' => '2026-10-14', 'time' => '10:00'])->assertSessionHas('status');
        $this->assertSame(ContentItem::SCHEDULED, $image->fresh()->status);
        $this->get('/')->assertSee('Continue: Step 4');

        // Step 4: load the synthetic dataset and see evidence grades.
        $this->post('/performance/load')->assertRedirect();
        $this->assertSame(30, PerformanceDataset::currentSynthetic()->records()->count());
        $this->get('/performance')->assertSee('Synthetic Demo Data — Not Real Aquarium Park Analytics')->assertSee('Insufficient Evidence');
        $this->get('/')->assertSee('Continue: Step 5 — Save insights as knowledge records');

        // Step 5: knowledge records, then round two with traceability and the comparison.
        $this->post('/knowledge/build')->assertRedirect();
        $this->assertGreaterThan(0, KnowledgeRecord::count());
        $this->get('/')->assertSee('Continue: Step 5 — Generate round two');
        $this->post('/workflow/round-two')->assertRedirect();

        $roundTwo = ContentRound::latestNumber(2);
        $driven = $roundTwo->ideas()->where('knowledge_driven', true)->get();
        $this->assertNotEmpty($driven);
        foreach ($driven as $idea) {
            $this->assertNotEmpty($idea->influencingRecords());
        }
        $this->get('/workflow')->assertOk()
            ->assertSee('Round one vs round two')
            ->assertSee(RoundComparison::DISCLAIMER);

        // Done: every step complete, nothing was really published.
        $this->get('/')->assertSee('All five steps complete')->assertSee('Continue: Review the round-one vs round-two comparison');
        $this->assertSame(0, ContentItem::where('status', ContentItem::PUBLISHED)->count());
    }
}
