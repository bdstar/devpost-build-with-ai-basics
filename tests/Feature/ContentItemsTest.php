<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\ContentIdea;
use App\Models\ContentItem;
use App\Models\ContentRound;
use App\Models\User;
use App\Services\KnowledgeBuilder;
use App\Services\SyntheticDatasetLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function confirmedProfile(): void
    {
        BusinessProfile::create([
            'website_url' => 'https://aquariumpark.com.bd/',
            'fields' => ['business_name' => ['value' => 'Aquarium Park', 'provenance' => 'verified', 'source_url' => 'https://aquariumpark.com.bd/']],
            'status' => BusinessProfile::CONFIRMED,
            'confirmed_at' => now(),
        ]);
    }

    /** Generate round one, select every idea, and generate content. */
    private function generateAll(): ContentRound
    {
        $this->confirmedProfile();
        $this->post('/workflow/round-one');
        $round = ContentRound::latestNumber(1);
        $this->post("/workflow/rounds/{$round->id}/selection", ['selected' => $round->ideas()->pluck('id')->all()]);
        $this->post('/content/generate')->assertRedirect('/content')->assertSessionHas('status');

        return $round;
    }

    private function itemFor(string $format): ContentItem
    {
        return ContentItem::whereHas('idea', fn ($q) => $q->where('format', $format))->firstOrFail();
    }

    public function test_generation_requires_a_confirmed_profile_and_selected_ideas(): void
    {
        $this->post('/content/generate')->assertSessionHas('error');

        $this->confirmedProfile();
        $this->post('/workflow/round-one');
        $this->post('/content/generate')->assertSessionHas('error');
        $this->assertSame(0, ContentItem::count());
    }

    public function test_each_selected_idea_becomes_one_labelled_draft(): void
    {
        $round = $this->generateAll();

        $this->assertSame($round->ideas()->count(), ContentItem::count());
        $this->assertTrue(ContentItem::all()->every(fn ($i) => $i->status === ContentItem::DRAFT && $i->provenance === 'demo_fixture'));
        $this->assertSame(['Generated (Demo fixture).'], ContentItem::first()->histories->pluck('note')->all());

        $this->post('/content/generate')->assertSessionHas('error');
        $this->assertSame($round->ideas()->count(), ContentItem::count());
    }

    public function test_video_idea_gets_a_brief_and_no_video_element(): void
    {
        $this->generateAll();
        $item = $this->itemFor('Reel');

        $this->assertNull($item->image_path);
        $this->assertNotEmpty($item->video_brief['script']);
        $this->assertNotEmpty($item->video_brief['shot_list']);

        $this->get("/content/{$item->id}")->assertOk()
            ->assertSee('Video Not Generated — Planning Preview Only')
            ->assertDontSee('<video', false);
    }

    public function test_image_idea_shows_a_labelled_fixture_image_with_alt_text(): void
    {
        $this->generateAll();
        $item = $this->itemFor('Image');

        $this->assertStringStartsWith('/demo-fixtures/images/', $item->image_path);
        $this->assertFileExists(public_path(ltrim($item->image_path, '/')));
        $this->assertSame('demo_fixture', $item->image_provenance);
        $this->assertNotEmpty($item->alt_text);

        $this->get("/content/{$item->id}")->assertOk()
            ->assertSee('Demo fixture image — not generated in this session')
            ->assertSee('alt="'.e($item->alt_text).'"', false);
    }

    public function test_text_idea_has_no_image_or_brief(): void
    {
        $this->generateAll();
        $item = $this->itemFor('Text');

        $this->assertNull($item->image_path);
        $this->assertNull($item->video_brief);
        $this->get("/content/{$item->id}")->assertOk()->assertSee('Text post: no image.');
    }

    public function test_review_actions_through_http(): void
    {
        $this->generateAll();
        $item = $this->itemFor('Image');

        $this->post("/content/{$item->id}/approve")->assertSessionHas('error');
        $this->assertSame(ContentItem::DRAFT, $item->fresh()->status);

        $this->post("/content/{$item->id}/submit")->assertSessionHas('status');
        $this->post("/content/{$item->id}/reject", ['reason' => ''])->assertSessionHas('error');
        $this->post("/content/{$item->id}/request-changes", ['feedback' => 'Mention weekend hours'])->assertSessionHas('status');
        $this->assertSame(ContentItem::DRAFT, $item->fresh()->status);
        $this->get("/content/{$item->id}")->assertSee('Mention weekend hours');

        $this->post("/content/{$item->id}/submit");
        $this->post("/content/{$item->id}/approve")->assertSessionHas('status');
        $this->assertSame(ContentItem::APPROVED, $item->fresh()->status);

        $this->put("/content/{$item->id}", ['caption' => 'Updated caption'])->assertSessionHas('status');
        $this->assertSame(ContentItem::DRAFT, $item->fresh()->status);
        $this->assertSame('Updated caption', $item->fresh()->caption);

        $this->post("/content/{$item->id}/publish")->assertNotFound();
    }

    public function test_only_drafts_can_be_regenerated(): void
    {
        $this->generateAll();
        $item = $this->itemFor('Image');
        $item->update(['caption' => 'Hand edited']);

        $this->post("/content/{$item->id}/regenerate")->assertSessionHas('status');
        $this->assertNotSame('Hand edited', $item->fresh()->caption);

        $this->post("/content/{$item->id}/submit");
        $this->post("/content/{$item->id}/regenerate")->assertSessionHas('error');
        $this->assertSame(ContentItem::PENDING, $item->fresh()->status);
    }

    public function test_round_two_with_content_is_not_replaced(): void
    {
        $this->confirmedProfile();
        app(KnowledgeBuilder::class)->build(app(SyntheticDatasetLoader::class)->load());
        $this->post('/workflow/round-one');
        $this->post('/workflow/round-two');
        $round = ContentRound::latestNumber(2);
        $idea = $round->ideas()->first();
        $this->post("/workflow/rounds/{$round->id}/selection", ['selected' => [$idea->id]]);
        $this->post('/content/generate');

        $this->post('/workflow/round-two')->assertSessionHas('error');
        $this->assertSame($round->id, ContentRound::latestNumber(2)->id);
        $this->assertSame(1, ContentItem::count());
    }
}
