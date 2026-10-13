<?php

namespace Tests\Feature;

use App\Models\PerformanceDataset;
use App\Models\PerformanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SyntheticDatasetLoadingTest extends TestCase
{
    use RefreshDatabase;

    private const BANNER = 'Synthetic Demo Data — Not Real Aquarium Park Analytics';

    private function canonicalPath(): string
    {
        return base_path(config('performance.synthetic_path'));
    }

    public function test_loads_30_records_idempotently_without_modifying_the_source_file(): void
    {
        $hashBefore = hash_file('sha256', $this->canonicalPath());
        $mtimeBefore = filemtime($this->canonicalPath());
        $this->actingAs(User::factory()->create());

        $this->get('/performance')->assertOk()->assertSee(self::BANNER)->assertSee('No performance data loaded yet');

        $this->post('/performance/load')->assertRedirect('/performance');
        $this->assertSame(30, PerformanceRecord::count());

        $page = $this->get('/performance')->assertOk()->assertSee(self::BANNER)->assertSee('AP-SYN-001')->assertSee('AP-SYN-030');
        $page->assertSee('Synthetic Demo Data');

        $this->post('/performance/load')->assertRedirect('/performance');
        $this->assertSame(30, PerformanceRecord::count());
        $this->assertSame(1, PerformanceDataset::count());

        clearstatcache();
        $this->assertSame($hashBefore, hash_file('sha256', $this->canonicalPath()));
        $this->assertSame($mtimeBefore, filemtime($this->canonicalPath()));
    }

    public function test_every_record_keeps_its_synthetic_provenance(): void
    {
        $this->actingAs(User::factory()->create())->post('/performance/load');

        $dataset = PerformanceDataset::sole();
        $this->assertTrue($dataset->is_synthetic);
        $this->assertSame('aquariumpark_synthetic_demo', $dataset->key);
        $this->assertSame('1.0.0', $dataset->version);

        $this->assertSame(30, PerformanceRecord::where('data_source', 'SYNTHETIC_DEMO')->where('is_real_performance_data', false)->count());
        $this->assertSame(0, PerformanceRecord::where('is_real_performance_data', true)->count());
    }

    public function test_invalid_dataset_reports_problems_and_loads_nothing(): void
    {
        $json = json_decode(File::get($this->canonicalPath()), true);
        $json['posts'][0]['is_real_performance_data'] = true;
        $json['posts'][1]['reach'] = -5;
        unset($json['posts'][2]['platform']);

        $tmp = 'storage/framework/testing/invalid_dataset.json';
        File::ensureDirectoryExists(base_path(dirname($tmp)));
        File::put(base_path($tmp), json_encode($json));
        config(['performance.synthetic_path' => $tmp]);

        try {
            $this->actingAs(User::factory()->create())
                ->from('/performance')
                ->post('/performance/load')
                ->assertRedirect('/performance')
                ->assertSessionHas('error')
                ->assertSessionHas('problems', fn ($problems) => count($problems) === 3);

            $this->assertSame(0, PerformanceRecord::count());
            $this->assertSame(0, PerformanceDataset::count());
        } finally {
            File::delete(base_path($tmp));
        }
    }

    public function test_missing_dataset_file_is_reported(): void
    {
        config(['performance.synthetic_path' => 'does/not/exist.json']);

        $this->actingAs(User::factory()->create())
            ->from('/performance')
            ->post('/performance/load')
            ->assertSessionHas('error', 'Dataset file not found: does/not/exist.json');
    }
}
