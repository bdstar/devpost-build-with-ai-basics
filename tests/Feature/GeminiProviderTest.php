<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\ContentIdea;
use App\Models\ContentItem;
use App\Models\ContentRound;
use App\Models\KnowledgeRecord;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\GeminiProvider;
use App\Services\KnowledgeBuilder;
use App\Services\SyntheticDatasetLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    use RefreshDatabase;

    private const TEXT = '*gemini-3.8-flash:generateContent';

    private const IMAGE = '*gemini-2.5-flash-image:generateContent';

    private string $imageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageDir = storage_path('framework/testing/generated');
        config([
            'ai.mode' => 'live',
            'ai.gemini.api_key' => 'test-key-not-real',
            'ai.gemini.text_model' => 'gemini-3.8-flash',
            'ai.gemini.image_model' => 'gemini-2.5-flash-image',
            'ai.gemini.image_dir' => $this->imageDir,
            'ai.gemini.image_url' => '/generated',
        ]);
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->imageDir);
        parent::tearDown();
    }

    private function gemini(array $data): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($data)]]], 'finishReason' => 'STOP']]]);
    }

    private function profile(): BusinessProfile
    {
        return BusinessProfile::create([
            'website_url' => 'https://aquariumpark.com.bd/',
            'fields' => [
                'business_name' => ['value' => 'Aquarium Park', 'provenance' => 'verified'],
                'overview' => ['value' => 'Indoor aquarium in Dhaka.', 'provenance' => 'manual'],
            ],
            'status' => BusinessProfile::CONFIRMED,
            'confirmed_at' => now(),
        ]);
    }

    private function idea(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Feeding time', 'topic' => 'Fish feeding', 'platform' => 'Instagram', 'format' => 'Reel',
            'campaign_goal' => 'Experience', 'audience' => 'Families', 'objective' => 'Show feeding', 'cta' => 'Plan your visit',
            'hook' => 'Guess who is hungry?', 'key_message' => 'Feeding is a highlight.', 'duration_s' => 25,
        ];
    }

    private function sixIdeas(array $extra = []): array
    {
        return ['ideas' => [
            $this->idea($extra), $this->idea(['format' => 'Image', 'platform' => 'Facebook'] + $extra),
            $this->idea(['format' => 'Text'] + $extra), $this->idea(['format' => 'Carousel', 'campaign_goal' => 'Education'] + $extra),
            $this->idea(['campaign_goal' => 'Awareness'] + $extra), $this->idea(['format' => 'Image', 'campaign_goal' => 'Visit intent'] + $extra),
        ]];
    }

    public function test_valid_round_one_response_is_saved_as_live_ai_with_model(): void
    {
        $this->profile();
        Http::fake([self::TEXT => $this->gemini($this->sixIdeas())]);

        $this->post('/workflow/round-one')->assertSessionHas('status');

        $round = ContentRound::latestNumber(1);
        $this->assertSame('live', $round->mode);
        $this->assertSame(6, $round->ideas()->count());
        $this->assertTrue($round->ideas->every(fn ($i) => $i->provenance === 'live_ai:gemini-3.8-flash'));
        $this->assertSame(25, $round->ideas->firstWhere('format', 'Reel')->duration_s);
        $this->assertNull($round->ideas->firstWhere('format', 'Text')->duration_s);

        Http::assertSent(fn (Request $r) => $r->hasHeader('x-goog-api-key', 'test-key-not-real')
            && ! str_contains($r->url(), 'key=')
            && $r['generationConfig']['responseMimeType'] === 'application/json'
            && isset($r['generationConfig']['responseSchema']));
        $this->get('/workflow')->assertSee('Live AI')->assertSee('gemini-3.8-flash');
    }

    public function test_invalid_json_saves_nothing_and_offers_switch_to_fixtures(): void
    {
        $this->profile();
        Http::fake([self::TEXT => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'not json {']]]]]])]);

        $this->post('/workflow/round-one')->assertSessionHas('error', fn ($m) => str_contains($m, 'not valid JSON'))->assertSessionHas('ai_failed');

        $this->assertSame(0, ContentRound::count());
        $this->assertSame(0, ContentIdea::count());
    }

    public function test_wrong_shape_saves_nothing(): void
    {
        $this->profile();
        Http::fake([self::TEXT => $this->gemini($this->sixIdeas(['platform' => 'MySpace']))]);

        $this->post('/workflow/round-one')->assertSessionHas('error', fn ($m) => str_contains($m, 'unsupported platform'));
        $this->assertSame(0, ContentIdea::count());
    }

    public function test_rate_limit_is_mapped_and_saves_nothing(): void
    {
        $this->profile();
        Http::fake([self::TEXT => Http::response(['error' => ['message' => 'Resource exhausted']], 429)]);

        $this->post('/workflow/round-one')->assertSessionHas('error', fn ($m) => str_contains($m, 'rate limit or quota reached (HTTP 429)'));
        $this->assertSame(0, ContentRound::count());
    }

    public function test_timeout_is_mapped_and_saves_nothing(): void
    {
        $this->profile();
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->post('/workflow/round-one')->assertSessionHas('error', fn ($m) => str_contains($m, 'did not respond within 60 seconds'));
        $this->assertSame(0, ContentRound::count());
    }

    public function test_missing_model_and_bad_key_are_explained_without_leaking_the_key(): void
    {
        $this->profile();
        Http::fake([self::TEXT => Http::sequence()
            ->push(['error' => ['message' => 'models/gemini-3.8-flash is not found']], 404)
            ->push(['error' => ['message' => 'Permission denied']], 403)]);

        $this->post('/workflow/round-one')->assertSessionHas('error', fn ($m) => str_contains($m, 'Model gemini-3.8-flash is not available to this API key (HTTP 404)'));
        $this->post('/workflow/round-one')->assertSessionHas('error', fn ($m) => str_contains($m, 'refused the API key (HTTP 403)') && ! str_contains($m, 'test-key-not-real'));
    }

    public function test_live_mode_without_a_key_explains_and_calls_nothing(): void
    {
        config(['ai.gemini.api_key' => null]);
        $this->profile();
        Http::fake();

        $this->post('/workflow/round-one')->assertSessionHas('error', fn ($m) => str_contains($m, 'needs GEMINI_API_KEY'));
        Http::assertNothingSent();
    }

    public function test_round_two_prompt_contains_knowledge_and_citations_are_checked(): void
    {
        $this->profile();
        app(KnowledgeBuilder::class)->build(app(SyntheticDatasetLoader::class)->load());
        ContentRound::create(['number' => 1, 'mode' => 'fixtures', 'profile_snapshot' => []]);
        $reel = KnowledgeRecord::where('key', 'format:Reel')->firstOrFail();

        $ideas = $this->sixIdeas(['influenced_by' => [$reel->id], 'why' => 'Reels did better in format:Reel (synthetic).']);
        $ideas['ideas'][2] = $this->idea(['format' => 'Text', 'influenced_by' => [], 'why' => 'A deliberate test of text posts.']);
        Http::fake([self::TEXT => Http::sequence()
            ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode($ideas)]]]]]])
            ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode($this->sixIdeas(['influenced_by' => [99999], 'why' => 'x']))]]]]]])]);

        $this->post('/workflow/round-two')->assertSessionHas('status');

        Http::assertSent(fn (Request $r) => str_contains($r['contents'][0]['parts'][0]['text'], 'format:Reel')
            && str_contains($r['contents'][0]['parts'][0]['text'], 'SYNTHETIC'));
        $round = ContentRound::latestNumber(2);
        $driven = $round->ideas->where('knowledge_driven', true);
        $this->assertCount(5, $driven);
        $this->assertSame([$reel->id], $driven->first()->influenced_by);
        $exploratory = $round->ideas->firstWhere('knowledge_driven', false);
        $this->assertStringStartsWith('Not knowledge-driven: ', $exploratory->why);
        $this->get('/workflow')->assertSee("Gemini's explanation", false)->assertSee('format:Reel');

        $this->post('/workflow/round-two')->assertSessionHas('error', fn ($m) => str_contains($m, 'do not exist'));
        $this->assertSame($round->id, ContentRound::latestNumber(2)->id);
    }

    public function test_image_content_saves_a_generated_image_with_its_model(): void
    {
        $this->profile();
        $round = ContentRound::create(['number' => 1, 'mode' => 'live', 'profile_snapshot' => []]);
        $idea = $round->ideas()->create($this->idea(['format' => 'Image', 'duration_s' => null]) + ['provenance' => 'live_ai:gemini-3.8-flash', 'selected' => true]);
        $png = base64_encode("\x89PNG\r\n\x1a\nfake-image-bytes");
        Http::fake([
            self::TEXT => $this->gemini(['caption' => 'Come see the fish', 'hashtags' => ['AquariumPark', '#Dhaka'], 'cta' => 'Plan your visit', 'alt_text' => 'Fish in a tank', 'image_prompt' => 'Colourful fish']),
            self::IMAGE => Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => $png]]]]]]]),
        ]);

        $this->post('/content/generate')->assertSessionHas('status');

        $item = ContentItem::firstOrFail();
        $this->assertSame('live_ai:gemini-3.8-flash', $item->provenance);
        $this->assertSame('live_ai:gemini-2.5-flash-image', $item->image_provenance);
        $this->assertSame('#AquariumPark #Dhaka', $item->hashtags);
        $this->assertStringStartsWith('/generated/', $item->image_path);
        $this->assertFileExists($this->imageDir.'/'.basename($item->image_path));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'gemini-2.5-flash-image') && $r['generationConfig']['responseModalities'] === ['IMAGE']);
        $this->get("/content/{$item->id}")->assertSee('AI-generated image (Live AI · gemini-2.5-flash-image)');
        $this->assertSame($idea->id, $item->idea_id);
    }

    public function test_reel_content_gets_a_brief_and_no_image_call(): void
    {
        $this->profile();
        $round = ContentRound::create(['number' => 1, 'mode' => 'live', 'profile_snapshot' => []]);
        $round->ideas()->create($this->idea(['aspect_ratio' => '9:16']) + ['provenance' => 'live_ai:gemini-3.8-flash', 'selected' => true]);
        Http::fake([self::TEXT => $this->gemini([
            'caption' => 'Feeding time!', 'hashtags' => ['#FishFeeding'], 'cta' => 'Plan your visit',
            'video_brief' => ['script' => [['label' => 'Hook', 'direction' => 'Close-up of food hitting water']], 'shot_list' => ['Wide tank'], 'on_screen_text' => ['Feeding time'], 'notes' => 'Vertical'],
        ])]);

        $this->post('/content/generate')->assertSessionHas('status');

        $item = ContentItem::firstOrFail();
        $this->assertNull($item->image_path);
        $this->assertSame('Close-up of food hitting water', $item->video_brief['script'][0]['direction']);
        Http::assertSentCount(1);
        $this->get("/content/{$item->id}")->assertSee('Video Not Generated — Planning Preview Only')->assertDontSee('<video', false);
    }

    public function test_failed_image_generation_saves_no_content(): void
    {
        $this->profile();
        $round = ContentRound::create(['number' => 1, 'mode' => 'live', 'profile_snapshot' => []]);
        $round->ideas()->create($this->idea(['format' => 'Image', 'duration_s' => null]) + ['provenance' => 'live_ai:gemini-3.8-flash', 'selected' => true]);
        Http::fake([
            self::TEXT => $this->gemini(['caption' => 'c', 'hashtags' => ['#a'], 'cta' => 'x', 'alt_text' => 'a', 'image_prompt' => 'p']),
            self::IMAGE => Http::response(['candidates' => [['content' => ['parts' => []], 'finishReason' => 'IMAGE_SAFETY']]]),
        ]);

        $this->post('/content/generate')->assertSessionHas('error', fn ($m) => str_contains($m, 'no image (reason: IMAGE_SAFETY)'));
        $this->assertSame(0, ContentItem::count());
    }

    public function test_profile_assumptions_are_labelled_live_with_model(): void
    {
        Http::fake([self::TEXT => $this->gemini([
            'overview' => 'An indoor aquarium.', 'audience' => ['Families', 'Schools'], 'content_opportunities' => ['Feeding Reels'],
            'posting_times' => ['Evenings'], 'recommended_platforms' => ['Facebook'],
        ])]);

        $draft = (new GeminiProvider(config('ai.gemini')))->profile(['business_name' => ['value' => 'Aquarium Park', 'source_url' => 'https://x/', 'retrieved_at' => now()->toIso8601String()]], 'Welcome');

        $this->assertSame('live_ai', $draft->provenance);
        $this->assertSame('gemini-3.8-flash', $draft->model);
        $this->assertSame("Families\nSchools", $draft->fields['audience']);
    }

    public function test_switch_to_demo_fixtures_stops_live_calls_for_the_session(): void
    {
        $this->profile();
        Http::fake();

        $this->get('/')->assertSee('Live AI · gemini-3.8-flash')->assertSee('Switch to Demo Fixtures');
        $this->from('/workflow')->post('/ai-mode/fixtures')->assertRedirect('/workflow');

        $this->post('/workflow/round-one')->assertSessionHas('status');
        Http::assertNothingSent();
        $this->assertSame('fixtures', ContentRound::latestNumber(1)->mode);
        $this->assertTrue(ContentRound::latestNumber(1)->ideas->every(fn ($i) => $i->provenance === 'demo_fixture'));
        $this->get('/')->assertSee('Demo Fixtures')->assertSee('Switch back to Live AI');

        $this->post('/ai-mode/live');
        $this->get('/')->assertSee('Live AI · gemini-3.8-flash');
    }

    public function test_provider_refuses_to_start_without_a_key(): void
    {
        $this->expectException(AiException::class);
        new GeminiProvider(['api_key' => ''] + config('ai.gemini'));
    }
}
