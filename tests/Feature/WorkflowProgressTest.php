<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\ContentRound;
use App\Models\User;
use App\Services\WorkflowProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function states(): array
    {
        return collect((new WorkflowProgress)->steps())->pluck('state', 'number')->all();
    }

    private function confirmedProfile(): void
    {
        BusinessProfile::create([
            'website_url' => 'https://aquariumpark.com.bd/',
            'fields' => ['business_name' => ['value' => 'Aquarium Park', 'provenance' => 'manual']],
            'status' => BusinessProfile::CONFIRMED,
            'confirmed_at' => now(),
        ]);
    }

    public function test_fresh_database_shows_continue_step_one_with_later_steps_locked(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Continue: Step 1 — Build the business profile')
            ->assertSee('0 of 5 steps complete')
            ->assertSee('Locked: Confirm the business profile first.');

        $this->assertSame([1 => 'open', 2 => 'locked', 3 => 'locked', 4 => 'open', 5 => 'locked'], $this->states());
    }

    public function test_step_two_is_locked_with_a_reason_until_the_profile_is_confirmed(): void
    {
        BusinessProfile::create(['website_url' => 'https://aquariumpark.com.bd/', 'fields' => [], 'status' => BusinessProfile::DRAFT]);

        $step2 = (new WorkflowProgress)->steps()[1];
        $this->assertSame('locked', $step2['state']);
        $this->assertSame('Confirm the business profile first.', $step2['reason']);
        $this->get('/')->assertSee('Continue: Step 1 — Review and confirm the business profile');

        BusinessProfile::query()->update(['status' => BusinessProfile::CONFIRMED, 'confirmed_at' => now()]);

        $this->assertSame('open', (new WorkflowProgress)->steps()[1]['state']);
        $this->get('/')->assertSee('Continue: Step 2 — Generate round-one ideas');
    }

    public function test_each_completed_step_advances_the_next_action(): void
    {
        $this->confirmedProfile();
        $this->post('/workflow/round-one');
        $this->get('/')->assertSee('Continue: Step 2 — Select round-one ideas');
        $this->assertSame('locked', $this->states()[3]);

        $round = ContentRound::latestNumber(1);
        $this->post("/workflow/rounds/{$round->id}/selection", ['selected' => [$round->ideas()->first()->id]]);
        $this->get('/')->assertSee('Continue: Step 3 — Generate content for the selected ideas');

        $this->post('/content/generate');
        $this->get('/')->assertSee('Continue: Step 3 — Review and approve content');
    }

    public function test_step_indicator_appears_on_every_step_page(): void
    {
        foreach (['/profile', '/workflow', '/content', '/performance'] as $url) {
            $this->get($url)->assertOk()->assertSee('Workflow progress: 0 of 5 steps complete');
        }
    }
}
