<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\User;
use App\Services\WebsiteReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BusinessProfileTest extends TestCase
{
    use RefreshDatabase;

    private const HOME = <<<'HTML'
        <html><head>
          <title>Example Park | Home</title>
          <meta name="description" content="A family aquarium park in the city.">
        </head><body>
          <h1>Dive Into Aquatic Wonders</h1>
          <a href="/about">About Us</a>
          <a href="https://example-park.test/attractions/aquarium">Aquarium<span>Read More</span></a>
          <a href="/attractions/bird-aviary">Bird Aviary Read More</a>
          <a href="mailto:info@example-park.test">Email us</a>
          <a href="tel:+8801234567890">Call</a>
          <a href="https://www.facebook.com/ExamplePark/">FB</a>
          <a href="https://www.facebook.com/sharer/sharer.php?u=x">Share</a>
          <a href="https://x.com/ExamplePark">X</a>
          <a href="https://www.youtube.com/watch?v=abc123">Our video</a>
          <a href="https://other-site.test/attractions/zoo">Elsewhere</a>
          <script>var ignored = "should not appear";</script>
        </body></html>
        HTML;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());

        // Deterministic DNS: test hosts resolve to a public documentation address, "internal" hosts to private ones.
        $this->app->instance(WebsiteReader::class, new WebsiteReader(fn (string $host) => match ($host) {
            'internal.test' => ['10.0.0.5'],
            'unresolvable.test' => [],
            default => ['93.184.216.34'],
        }));
    }

    private function fakeSite(): void
    {
        Http::fake([
            'https://example-park.test/' => Http::response(self::HOME, 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'https://example-park.test/about' => Http::response('<html><body><h1>About Example Park</h1><p>Opened in 2020.</p><a href="tel:01700000000">Office</a></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://example-park.test/attractions/*' => Http::response('<html><body><h1>Attraction</h1></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);
    }

    public function test_reading_a_site_produces_verified_facts_with_sources_and_labelled_assumptions(): void
    {
        $this->fakeSite();

        $this->post('/profile/analyse', [
            'website_url' => 'https://example-park.test/',
            'social_urls' => ['instagram' => 'https://www.instagram.com/examplepark/'],
        ])->assertRedirect('/profile');

        $profile = BusinessProfile::sole();
        $this->assertSame('draft', $profile->status);
        $this->assertSame('demo_fixture', $profile->ai_provenance);

        $name = $profile->fields['business_name'];
        $this->assertSame('Example Park', $name['value']);
        $this->assertSame('verified', $name['provenance']);
        $this->assertSame('https://example-park.test/', $name['source_url']);
        $this->assertNotEmpty($name['retrieved_at']);

        $this->assertSame('verified', $profile->fields['overview']['provenance']);
        $this->assertSame(['Aquarium', 'Bird Aviary'], $profile->items('attractions'));
        $this->assertSame(['info@example-park.test'], $profile->items('contact_email'));
        $this->assertSame(['+8801234567890', '01700000000'], $profile->items('contact_phone'));
        $this->assertSame('https://example-park.test/about', $profile->fields['contact_phone']['item_sources']['01700000000']);
        $this->assertSame(['Facebook: https://www.facebook.com/ExamplePark/', 'X: https://x.com/ExamplePark'], $profile->items('social_profiles'));
        $this->assertContains('About Example Park', $profile->items('website_headlines'));

        foreach (['audience', 'content_opportunities', 'posting_times', 'recommended_platforms'] as $key) {
            $this->assertSame('assumption', $profile->fields[$key]['provenance'], $key);
            $this->assertSame('demo_fixture', $profile->fields[$key]['source'], $key);
        }

        $this->assertSame(['instagram' => 'https://www.instagram.com/examplepark/'], $profile->social_urls);
        $this->assertContains('https://example-park.test/about', $profile->sources['pages']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'other-site.test'));
        Http::assertSent(fn (Request $r) => $r->header('User-Agent')[0] === WebsiteReader::USER_AGENT);

        $this->get('/profile')->assertOk()
            ->assertSee('Verified public')
            ->assertSee('AI assumption')
            ->assertSee('Demo fixture')
            ->assertSee('Draft — not confirmed')
            ->assertSee('recorded only', false);
    }

    public function test_invalid_url_is_rejected_before_any_request(): void
    {
        Http::fake();

        $this->from('/profile')->post('/profile/analyse', ['website_url' => 'not-a-url'])
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('website_url');
        $this->from('/profile')->post('/profile/analyse', ['website_url' => 'ftp://example-park.test/'])
            ->assertSessionHasErrors('website_url');

        Http::assertNothingSent();
        $this->assertSame(0, BusinessProfile::count());
    }

    public function test_private_and_loopback_hosts_are_refused_before_any_request(): void
    {
        Http::fake();

        foreach (['http://127.0.0.1/', 'http://169.254.169.254/latest/meta-data', 'http://internal.test/', 'http://[::1]/'] as $url) {
            $this->post('/profile/analyse', ['website_url' => $url])
                ->assertRedirect('/profile')
                ->assertSessionHas('error', fn ($e) => str_contains($e, 'private, local or reserved'))
                ->assertSessionHas('show_manual', true);
        }

        Http::assertNothingSent();
        $this->assertSame(0, BusinessProfile::count());
    }

    public function test_redirect_to_a_private_address_is_not_followed(): void
    {
        Http::fake([
            'https://example-park.test/' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
            '*' => Http::response('SHOULD NOT BE REQUESTED', 200),
        ]);

        $this->post('/profile/analyse', ['website_url' => 'https://example-park.test/'])
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'private, local or reserved'));

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '169.254.169.254'));
        $this->assertSame(0, BusinessProfile::count());
    }

    public function test_safe_redirects_are_followed_up_to_the_limit(): void
    {
        Http::fake([
            'http://example-park.test/' => Http::response('', 301, ['Location' => 'https://example-park.test/']),
            'https://example-park.test/' => Http::response(self::HOME, 200, ['Content-Type' => 'text/html']),
            'https://example-park.test/*' => Http::response('<html><body></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://loop.test/*' => Http::response('', 302, ['Location' => 'https://loop.test/next']),
        ]);

        $this->post('/profile/analyse', ['website_url' => 'http://example-park.test/'])->assertSessionHas('status');
        $this->assertSame('https://example-park.test/', BusinessProfile::sole()->website_url);

        $this->post('/profile/analyse', ['website_url' => 'https://loop.test/start'])
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'redirected more than 3 times'));
    }

    public function test_server_error_shows_the_reason_and_the_manual_form(): void
    {
        Http::fake(['*' => Http::response('oops', 500)]);

        $this->post('/profile/analyse', ['website_url' => 'https://example-park.test/'])
            ->assertRedirect('/profile')
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'HTTP 500'));

        $this->get('/profile')->assertSee('Enter the profile manually')->assertSee('HTTP 500');
    }

    public function test_timeout_and_unknown_host_show_an_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->post('/profile/analyse', ['website_url' => 'https://example-park.test/'])
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'failed or timed out'))
            ->assertSessionHas('show_manual', true);

        $this->post('/profile/analyse', ['website_url' => 'https://unresolvable.test/'])
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'could not be found'));
    }

    public function test_manual_entry_saves_fields_labelled_as_manual(): void
    {
        $this->post('/profile/manual', [
            'website_url' => 'https://example-park.test/',
            'fields' => ['business_name' => 'Example Park', 'audience' => "Families\nSchools"],
        ])->assertRedirect('/profile');

        $profile = BusinessProfile::sole();
        $this->assertSame('manual', $profile->fields['business_name']['provenance']);
        $this->assertSame(['Families', 'Schools'], $profile->items('audience'));
        $this->assertArrayNotHasKey('overview', $profile->fields);
        $this->get('/profile')->assertSee('Manual entry');
    }

    public function test_confirming_then_editing_returns_the_profile_to_draft(): void
    {
        $this->fakeSite();
        $this->post('/profile/analyse', ['website_url' => 'https://example-park.test/']);

        $this->post('/profile/confirm')->assertRedirect('/profile');
        $profile = BusinessProfile::sole();
        $this->assertTrue($profile->isConfirmed());
        $this->assertNotNull($profile->confirmed_at);

        $fields = collect(BusinessProfile::FIELDS)->keys()->mapWithKeys(fn ($k) => [$k => $profile->value($k)])->all();
        $fields['audience'] = 'Families in Dhaka';

        $this->put('/profile', ['fields' => $fields])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'back in draft'));

        $profile->refresh();
        $this->assertSame('draft', $profile->status);
        $this->assertNull($profile->confirmed_at);
        $this->assertSame('edited', $profile->fields['audience']['provenance']);
        $this->assertSame('assumption', $profile->fields['audience']['previous_provenance']);
        $this->assertSame('verified', $profile->fields['business_name']['provenance']);
    }

    public function test_profile_without_a_name_cannot_be_confirmed(): void
    {
        $this->post('/profile/manual', ['website_url' => 'https://example-park.test/', 'fields' => ['audience' => 'Families']]);

        $this->post('/profile/confirm')->assertSessionHas('error');
        $this->assertSame('draft', BusinessProfile::sole()->status);
    }
}
