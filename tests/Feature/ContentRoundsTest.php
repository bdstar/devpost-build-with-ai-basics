<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\ContentIdea;
use App\Models\ContentRound;
use App\Models\KnowledgeRecord;
use App\Models\User;
use App\Services\KnowledgeBuilder;
use App\Services\SyntheticDatasetLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentRoundsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function profile(string $status = BusinessProfile::CONFIRMED): BusinessProfile
    {
        return BusinessProfile::create([
            'website_url' => 'https://aquariumpark.com.bd/',
            'fields' => ['business_name' => ['value' => 'Aquarium Park', 'provenance' => 'verified', 'source_url' => 'https://aquariumpark.com.bd/']],
            'status' => $status,
            'confirmed_at' => $status === BusinessProfile::CONFIRMED ? now() : null,
        ]);
    }

    private function knowledge(): void
    {
        app(KnowledgeBuilder::class)->build(app(SyntheticDatasetLoader::class)->load());
    }

    /** @return list<string> */
    private function roundTwoKeys(): array
    {
        return ContentRound::latestNumber(2)->ideas()->pluck('fixture_key')->all();
    }

    public function test_round_one_requires_a_confirmed_profile(): void
    {
        $this->post('/workflow/round-one')->assertSessionHas('error');
        $this->profile(BusinessProfile::DRAFT);
        $this->post('/workflow/round-one')->assertSessionHas('error');
        $this->assertSame(0, ContentRound::count());

        $this->get('/workflow')->assertOk()->assertSee('Locked: confirm the business profile first');
    }

    public function test_round_one_generates_labelled_ideas_including_image_and_video(): void
    {
        $this->profile();

        $this->post('/workflow/round-one')->assertRedirect();
        $round = ContentRound::latestNumber(1);
        $ideas = $round->ideas()->get();

        $this->assertSame('fixtures', $round->mode);
        $this->assertSame('Aquarium Park', $round->profile_snapshot['fields']['business_name']['value']);
        $this->assertGreaterThanOrEqual(5, $ideas->count());
        $this->assertContains('Image', $ideas->pluck('format'));
        $this->assertTrue($ideas->contains(fn (ContentIdea $i) => $i->isVideo()));
        $this->assertTrue($ideas->every(fn ($i) => $i->provenance === 'demo_fixture' && ! $i->knowledge_driven && $i->influenced_by === []));
        $this->assertStringContainsString('Aquarium Park', $ideas->firstWhere('fixture_key', 'r1-aquarium-spotlight')->key_message);

        $this->post('/workflow/round-one')->assertSessionHas('error');
        $this->assertSame(1, ContentRound::count());

        $this->get('/workflow')->assertOk()->assertSee('Video / Reel')->assertSee('Demo fixture');
        $this->get('/workflow?format=Reel')->assertOk()->assertSee('A 60-second walk through the park')->assertDontSee('Ticket types explained');
    }

    public function test_ideas_can_be_selected(): void
    {
        $this->profile();
        $this->post('/workflow/round-one');
        $round = ContentRound::latestNumber(1);
        $ids = $round->ideas()->take(2)->pluck('id')->all();

        $this->post("/workflow/rounds/{$round->id}/selection", ['selected' => $ids])->assertRedirect();
        $this->assertEqualsCanonicalizing($ids, $round->ideas()->where('selected', true)->pluck('id')->all());

        $this->post("/workflow/rounds/{$round->id}/selection", ['selected' => []]);
        $this->assertSame(0, $round->ideas()->where('selected', true)->count());
    }

    public function test_round_two_requires_round_one_and_knowledge_records(): void
    {
        $this->profile();
        $this->post('/workflow/round-two')->assertSessionHas('error', fn ($e) => str_contains($e, 'round one first'));

        $this->post('/workflow/round-one');
        $this->post('/workflow/round-two')->assertSessionHas('error', fn ($e) => str_contains($e, 'needs knowledge records'));
        $this->assertNull(ContentRound::latestNumber(2));
    }

    public function test_every_round_two_idea_traces_to_existing_positive_knowledge_or_is_labelled(): void
    {
        $this->profile();
        $this->knowledge();
        $this->post('/workflow/round-one');

        $this->post('/workflow/round-two')->assertRedirect();
        $round = ContentRound::latestNumber(2);
        $this->assertEqualsCanonicalizing(KnowledgeRecord::pluck('id')->all(), $round->knowledge_record_ids);

        $ideas = $round->ideas()->get();
        $this->assertGreaterThan(0, $ideas->where('knowledge_driven', true)->count());
        $this->assertGreaterThan(0, $ideas->where('knowledge_driven', false)->count());

        foreach ($ideas as $idea) {
            if ($idea->knowledge_driven) {
                $this->assertNotEmpty($idea->influenced_by, $idea->title);
                $records = KnowledgeRecord::whereIn('id', $idea->influenced_by)->get();
                $this->assertCount(count($idea->influenced_by), $records, 'every influencing record exists');
                foreach ($records as $record) {
                    $this->assertSame('above', $record->metrics['direction']);
                    $this->assertStringContainsString($record->key, $idea->why);
                    $this->assertStringContainsString($record->supporting_post_ids[0], $idea->why);
                }
            } else {
                $this->assertSame([], $idea->influenced_by);
                $this->assertStringStartsWith('Not knowledge-driven', $idea->why);
            }
        }

        $this->get('/workflow')->assertOk()
            ->assertSee('Why this was suggested')
            ->assertSee('Simulated Insight')
            ->assertSee('Not knowledge-driven')
            ->assertSee('format:Reel');
    }

    public function test_removing_a_knowledge_record_changes_the_round_two_selection(): void
    {
        $this->profile();
        $this->knowledge();
        $this->post('/workflow/round-one');

        $this->post('/workflow/round-two');
        $before = $this->roundTwoKeys();

        KnowledgeRecord::where('key', 'format:Reel')->delete();
        $this->post('/workflow/round-two');
        $after = $this->roundTwoKeys();

        $this->assertNotEquals($before, $after);
        $this->assertContains('r2-waterfall-reel', $before);
        $this->assertNotContains('r2-waterfall-reel', $after, 'idea supported only by format:Reel disappears');
        $this->assertSame(1, ContentRound::where('number', 2)->count(), 'only the latest round two is kept');
        $this->assertSame(1, ContentRound::where('number', 1)->count(), 'round one is never replaced');
    }

    public function test_round_two_without_positive_knowledge_contains_only_exploratory_ideas(): void
    {
        $this->profile();
        $this->knowledge();
        $this->post('/workflow/round-one');
        KnowledgeRecord::all()->filter(fn ($r) => $r->metrics['direction'] === 'above')->each->delete();

        $this->post('/workflow/round-two');
        $ideas = ContentRound::latestNumber(2)->ideas()->get();

        $this->assertTrue($ideas->every(fn ($i) => ! $i->knowledge_driven));
    }

    public function test_comparison_panel_shows_differences_and_disclaimer(): void
    {
        $this->profile();
        $this->knowledge();
        $this->post('/workflow/round-one');
        $this->post('/workflow/round-two');

        $comparison = app(\App\Services\RoundComparison::class)->compare(ContentRound::latestNumber(1), ContentRound::latestNumber(2));
        $this->assertNotEmpty($comparison['differing']);
        $reel = collect($comparison['dimensions']['format']['rows'])->firstWhere('value', 'Reel');
        $this->assertGreaterThan(0, $reel['delta']);

        $this->get('/workflow')->assertOk()
            ->assertSee('Round one vs round two')
            ->assertSee('This demonstrates the feedback loop working; it does not show that real engagement improved.');
    }
}
