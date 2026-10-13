<?php

namespace App\Services;

use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Reads a public website and extracts facts it can point to directly ("verified" = found on a fetched page).
 * The URL is untrusted input: every request, including each redirect hop, is checked against
 * private, loopback, link-local and reserved addresses, and the connection is pinned to the checked IP.
 */
class WebsiteReader
{
    public const USER_AGENT = 'GenAIRatelCMS/0.1 (+demo)';

    private const MAX_SUBPAGES = 8;
    private const MAX_REDIRECTS = 3;
    private const MAX_BYTES = 2_000_000;
    private const MAX_TEXT = 12_000;
    private const MAX_TEXT_PER_PAGE = 3_000;

    private const SOCIAL_HOSTS = [
        'facebook.com' => 'Facebook', 'instagram.com' => 'Instagram', 'x.com' => 'X', 'twitter.com' => 'X',
        'youtube.com' => 'YouTube', 'tiktok.com' => 'TikTok', 'linkedin.com' => 'LinkedIn',
        'threads.com' => 'Threads', 'threads.net' => 'Threads',
    ];

    /** @param  (Closure(string): list<string>)|null  $resolver  host => IP addresses */
    public function __construct(private ?Closure $resolver = null) {}

    /**
     * @return array{url: string, pages: list<string>, skipped: list<string>, facts: array, text: string, retrieved_at: string}
     */
    public function read(string $url): array
    {
        $retrievedAt = now()->toIso8601String();
        [$homeUrl, $homeHtml] = $this->fetch($url);

        $pages = [$homeUrl => $homeHtml];
        $skipped = [];
        foreach ($this->candidateLinks($homeHtml, $homeUrl) as $link) {
            if (count($pages) > self::MAX_SUBPAGES) {
                break;
            }
            try {
                [$finalUrl, $html] = $this->fetch($link);
                $pages[$finalUrl] = $html;
            } catch (WebsiteReadException $e) {
                $skipped[] = $link.' ('.$e->getMessage().')';
            }
        }

        return [
            'url' => $homeUrl,
            'pages' => array_keys($pages),
            'skipped' => $skipped,
            'facts' => $this->extractFacts($pages, $homeUrl, $retrievedAt),
            'text' => $this->extractText($pages),
            'retrieved_at' => $retrievedAt,
        ];
    }

    /**
     * Validates scheme, credentials and resolved addresses. Returns [host, port, ip].
     */
    public function assertSafeUrl(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        if (! $parts || ! in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            throw new WebsiteReadException('Only http:// or https:// website addresses can be read.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new WebsiteReadException('Website addresses containing credentials are not allowed.');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if (! $ips) {
            throw new WebsiteReadException("The website host \"{$host}\" could not be found.");
        }
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new WebsiteReadException("\"{$host}\" points to a private, local or reserved network address, so it will not be requested.");
            }
        }

        return [$host, $parts['port'] ?? ($scheme === 'https' ? 443 : 80), $ips[0]];
    }

    public static function isPublicIp(string $ip): bool
    {
        if (str_starts_with(strtolower($ip), '::ffff:') && str_contains($ip, '.')) {
            $ip = substr($ip, 7);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        // Carrier-grade NAT (100.64.0.0/10) is not covered by PHP's flags.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);

            return ! ($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255'));
        }

        return true;
    }

    /** @return array{0: string, 1: string} final URL and HTML */
    private function fetch(string $url): array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $port, $ip] = $this->assertSafeUrl($url);
            $pinnedIp = str_contains($ip, ':') ? "[{$ip}]" : $ip;

            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'text/html,application/xhtml+xml'])
                    ->timeout(15)
                    ->connectTimeout(10)
                    ->withOptions([
                        'allow_redirects' => false,
                        'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinnedIp}"]],
                    ])
                    ->get($url);
            } catch (ConnectionException) {
                throw new WebsiteReadException("Could not connect to {$host} (the request failed or timed out after 15 seconds).");
            }

            if ($response->redirect()) {
                $location = $response->header('Location');
                if (! $location) {
                    throw new WebsiteReadException("{$url} redirected without a destination.");
                }
                $url = $this->absolute($location, $url);

                continue;
            }
            if (! $response->successful()) {
                throw new WebsiteReadException("{$url} responded with HTTP {$response->status()}.");
            }
            $type = strtolower($response->header('Content-Type'));
            if ($type !== '' && ! str_contains($type, 'html')) {
                throw new WebsiteReadException("{$url} is not an HTML page ({$type}).");
            }

            return [$url, substr($response->body(), 0, self::MAX_BYTES)];
        }

        throw new WebsiteReadException('The website redirected more than '.self::MAX_REDIRECTS.' times.');
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if ($this->resolver) {
            return ($this->resolver)($host);
        }

        $ips = gethostbynamel($host) ?: [];
        $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
        foreach ($aaaa as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /** Same-site pages worth reading, most useful first. @return list<string> */
    private function candidateLinks(string $html, string $baseUrl): array
    {
        $priority = [];
        foreach ($this->anchors($html, $baseUrl) as [$href]) {
            if (! $this->sameSite($href, $baseUrl)) {
                continue;
            }
            $path = rtrim(parse_url($href, PHP_URL_PATH) ?? '/', '/');
            $rank = match (true) {
                $path === '/about' => 0,
                in_array($path, ['/visit', '/contact'], true) => 1,
                (bool) preg_match('#^/attractions/[^/]+$#', $path) => 2,
                default => null,
            };
            if ($rank !== null && $path !== '' && ! isset($priority[$href])) {
                $priority[$href] = $rank;
            }
        }
        asort($priority, SORT_NUMERIC);

        return array_slice(array_keys($priority), 0, self::MAX_SUBPAGES);
    }

    /** @param array<string, string> $pages */
    private function extractFacts(array $pages, string $homeUrl, string $retrievedAt): array
    {
        $fact = fn ($value, string $source) => ['value' => $value, 'source_url' => $source, 'retrieved_at' => $retrievedAt];
        $facts = [];

        $home = $this->xpath($pages[$homeUrl]);
        $siteName = $this->meta($home, 'og:site_name') ?: $this->cleanTitle($home->evaluate('string(//title)'));
        if ($siteName !== '') {
            $facts['business_name'] = $fact($siteName, $homeUrl);
        }
        $description = $this->meta($home, 'description') ?: $this->meta($home, 'og:description');
        if ($description !== '') {
            $facts['overview'] = $fact($description, $homeUrl);
        }

        $emails = $phones = $socials = $attractions = $headlines = [];
        foreach ($pages as $pageUrl => $html) {
            foreach ($this->anchors($html, $pageUrl) as [$href, $text]) {
                if (str_starts_with($href, 'mailto:')) {
                    $email = strtolower(strtok(substr($href, 7), '?'));
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $emails[$email] ??= $pageUrl;
                    }
                } elseif (str_starts_with($href, 'tel:')) {
                    $phone = trim(rawurldecode(substr($href, 4)));
                    if (preg_match('/^\+?[0-9 ()-]{6,}$/', $phone)) {
                        $phones[$phone] ??= $pageUrl;
                    }
                } elseif ($platform = $this->socialPlatform($href)) {
                    $clean = strtok($href, '?#');
                    $socials["{$platform}: {$clean}"] ??= $pageUrl;
                } elseif ($this->sameSite($href, $homeUrl) && preg_match('#^/attractions/([^/]+)/?$#', parse_url($href, PHP_URL_PATH) ?? '', $m)) {
                    $name = trim(preg_replace('/\s*read more\s*$/i', '', $text));
                    $attractions[strtolower($m[1])] ??= [$name !== '' ? $name : ucwords(str_replace('-', ' ', $m[1])), $pageUrl];
                }
            }

            if ($pageUrl === $homeUrl || str_ends_with(rtrim(parse_url($pageUrl, PHP_URL_PATH) ?? '', '/'), '/about')) {
                foreach ($this->xpath($html)->query('//h1|//h2') as $node) {
                    $heading = $this->squash($node->textContent);
                    if ($heading !== '' && mb_strlen($heading) <= 120) {
                        $headlines[$heading] ??= $pageUrl;
                    }
                }
            }
        }

        foreach ([
            'attractions' => array_combine(array_column($attractions, 0), array_column($attractions, 1)),
            'contact_email' => $emails,
            'contact_phone' => $phones,
            'social_profiles' => $socials,
            'website_headlines' => array_slice($headlines, 0, 8, true),
        ] as $key => $items) {
            if ($items) {
                $facts[$key] = $fact(implode("\n", array_keys($items)), reset($items)) + ['item_sources' => $items];
            }
        }

        return $facts;
    }

    /** @param array<string, string> $pages */
    private function extractText(array $pages): string
    {
        $chunks = [];
        foreach ($pages as $url => $html) {
            $xpath = $this->xpath($html);
            foreach ($xpath->query('//script|//style|//noscript|//svg|//template') as $node) {
                $node->parentNode?->removeChild($node);
            }
            $text = $this->squash($xpath->evaluate('string(//body)'));
            $chunks[] = "[Page: {$url}]\n".mb_substr($text, 0, self::MAX_TEXT_PER_PAGE);
        }

        return mb_substr(implode("\n\n", $chunks), 0, self::MAX_TEXT);
    }

    /** @return list<array{0: string, 1: string}> [absolute href, link text] */
    private function anchors(string $html, string $baseUrl): array
    {
        $links = [];
        foreach ($this->xpath($html)->query('//a[@href]') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
                continue;
            }
            $links[] = [$this->absolute($href, $baseUrl), $this->squash($a->textContent)];
        }

        return $links;
    }

    private function absolute(string $href, string $base): string
    {
        $href = strtok($href, '#') ?: '';
        if (preg_match('#^(mailto|tel):#i', $href) || preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($href, '//')) {
            return $b['scheme'].':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');

        return $origin.$dir.$href;
    }

    private function sameSite(string $url, string $base): bool
    {
        $strip = fn ($u) => preg_replace('/^www\./', '', strtolower(parse_url($u, PHP_URL_HOST) ?? ''));

        return preg_match('#^https?://#i', $url) && $strip($url) !== '' && $strip($url) === $strip($base);
    }

    private function socialPlatform(string $url): ?string
    {
        $host = preg_replace('/^(www|m)\./', '', strtolower(parse_url($url, PHP_URL_HOST) ?? ''));
        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        // Profiles only: skip share widgets and links to individual posts or videos.
        if ($path === '' || preg_match('#(^|/)(sharer|share|intent|watch|embed|shorts|status|posts|reel|reels|p|video|hashtag)(/|$|\.php)#i', $path)) {
            return null;
        }

        return self::SOCIAL_HOSTS[$host] ?? null;
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private function meta(DOMXPath $xpath, string $name): string
    {
        return $this->squash($xpath->evaluate("string(//meta[@property='{$name}' or @name='{$name}']/@content)"));
    }

    private function cleanTitle(string $title): string
    {
        return trim(preg_split('/\s+[|\-–—]\s+/u', $this->squash($title))[0] ?? '');
    }

    private function squash(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
