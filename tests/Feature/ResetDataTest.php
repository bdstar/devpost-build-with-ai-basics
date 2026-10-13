<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\ContentIdea;
use App\Models\ContentItem;
use App\Models\ContentRound;
use App\Models\KnowledgeRecord;
use App\Models\PerformanceDataset;
use App\Models\PerformanceRecord;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ResetDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.mode' => 'fixtures']);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function fillEveryArea(): void
    {
        $this->post('/profile/manual', [
            'website_url' => 'https://aquariumpark.com.bd/',
            'fields' => ['business_name' => 'Aquarium Park', 'overview' => 'Indoor aquarium in Dhaka.'],
        ]);
        $this->post('/profile/confirm');
        $this->post('/workflow/round-one');
        $round = ContentRound::latestNumber(1);
        $this->post("/workflow/rounds/{$round->id}/selection", ['selected' => [$round->ideas()->first()->id]]);
        $this->post('/content/generate');
        $this->post('/content/'.ContentItem::first()->id.'/submit');
        $this->post('/performance/load');
        $this->post('/knowledge/build');
    }

    private function counts(): array
    {
        return [
            BusinessProfile::count(), ContentRound::count(), ContentIdea::count(), ContentItem::count(),
            StatusHistory::count(), PerformanceDataset::count(), PerformanceRecord::count(), KnowledgeRecord::count(),
        ];
    }

    public function test_reset_clears_every_area_and_keeps_the_login_and_dataset_source(): void
    {
        $this->fillEveryArea();
        $this->assertNotContains(0, $this->counts());

        $source = base_path(config('performance.synthetic_path'));
        $hashBefore = hash_file('sha256', $source);

        $this->post('/reset', ['confirmation' => 'RESET'])
            ->assertRedirect('/')
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'All project data was reset'));

        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 0], $this->counts());
        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
        $this->assertSame($hashBefore, hash_file('sha256', $source));
        $this->get('/')->assertOk()->assertSee('Continue: Step 1')->assertSee('0 of 5 steps complete');

        // The journey can start again, including reloading the dataset.
        $this->post('/performance/load')->assertRedirect('/performance');
        $this->assertSame(30, PerformanceRecord::count());
    }

    public function test_reset_without_the_exact_confirmation_deletes_nothing(): void
    {
        $this->fillEveryArea();
        $before = $this->counts();

        foreach ([[], ['confirmation' => 'reset'], ['confirmation' => 'yes']] as $payload) {
            $this->post('/reset', $payload)->assertRedirect('/')->assertSessionHas('error');
        }

        $this->assertSame($before, $this->counts());
    }

    public function test_reset_deletes_generated_images_only_inside_the_configured_folder(): void
    {
        $dir = storage_path('framework/testing/generated-'.uniqid());
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/one.png", 'x');
        File::put("{$dir}/two.png", 'x');
        config(['ai.gemini.image_dir' => $dir]);

        $this->post('/reset', ['confirmation' => 'RESET'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, '2 generated images'));

        $this->assertSame([], File::files($dir));
        File::deleteDirectory($dir);
    }

    public function test_reset_requires_login(): void
    {
        auth()->logout();
        $this->post('/reset', ['confirmation' => 'RESET'])->assertRedirect('/login');
    }

    public function test_dashboard_shows_the_reset_panel(): void
    {
        $this->get('/')->assertSee('Reset all project data')->assertSee('Type RESET to confirm');
    }
}
