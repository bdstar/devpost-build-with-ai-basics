<?php

namespace Tests\Feature;

use App\Models\KnowledgeRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_performance_page_shows_evidence_grades_and_insufficient_groups(): void
    {
        $this->post('/performance/load');

        $this->get('/performance')->assertOk()
            ->assertSee('Insufficient Evidence · n=2', false)
            ->assertSee('Confidence · n=11 · CV=', false)
            ->assertSee('ER = (likes + comments + shares) / reach × 100', false)
            ->assertSee('High is never assigned automatically.');
    }

    public function test_building_knowledge_is_idempotent_and_keeps_evidence_provenance(): void
    {
        $this->post('/performance/load');

        $this->post('/knowledge/build')->assertRedirect('/knowledge');
        $first = KnowledgeRecord::count();
        $this->assertGreaterThan(0, $first);

        $this->post('/knowledge/build')->assertRedirect('/knowledge');
        $this->assertSame($first, KnowledgeRecord::count());

        foreach (KnowledgeRecord::all() as $record) {
            $this->assertNotEmpty($record->supporting_post_ids, $record->key);
            $this->assertCount($record->sample_size, $record->supporting_post_ids, $record->key);
            $this->assertGreaterThanOrEqual(5, $record->sample_size, $record->key);
            $this->assertSame('synthetic', $record->evidence_type);
            $this->assertSame('aquariumpark_synthetic_demo', $record->source_dataset);
            $this->assertSame('1.0.0', $record->dataset_version);
            $this->assertContains($record->confidence, ['low', 'medium']);
            $this->assertNotEmpty($record->formula);
            $this->assertNotNull($record->analysed_at);
        }

        $this->assertNull(KnowledgeRecord::where('key', 'format:Text')->first());
        $this->assertNull(KnowledgeRecord::where('key', 'goal:Conversion')->first());
        $this->assertNotNull(KnowledgeRecord::where('key', 'format:Reel')->first());
    }

    public function test_knowledge_record_page_lists_exact_supporting_posts(): void
    {
        $this->post('/performance/load');
        $this->post('/knowledge/build');
        $record = KnowledgeRecord::where('key', 'format:Reel')->firstOrFail();

        $response = $this->get("/knowledge/{$record->id}")->assertOk()
            ->assertSee('Simulated Insight')
            ->assertSee('Supporting posts ('.count($record->supporting_post_ids).')');

        foreach ($record->supporting_post_ids as $postId) {
            $response->assertSee($postId);
        }

        $this->get('/knowledge')->assertOk()->assertSee('format:Reel');
    }

    public function test_knowledge_cannot_be_built_before_the_dataset_is_loaded(): void
    {
        $this->post('/knowledge/build')->assertRedirect('/performance')->assertSessionHas('error');
        $this->assertSame(0, KnowledgeRecord::count());
        $this->get('/knowledge')->assertOk()->assertSee('No knowledge records yet');
    }
}
