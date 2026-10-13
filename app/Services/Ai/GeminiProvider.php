<?php

namespace App\Services\Ai;

use App\Models\BusinessProfile;
use App\Models\ContentIdea;
use App\Models\KnowledgeRecord;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Live AI mode: Gemini generateContent with structured JSON output. Every response is validated against
 * the same shapes the fixtures use; anything invalid throws AiException and nothing is saved.
 */
class GeminiProvider implements AiProvider
{
    public const PROVENANCE = 'live_ai';

    public const PLATFORMS = ['Facebook', 'Instagram'];

    public const FORMATS = ['Text', 'Image', 'Carousel', 'Reel'];

    public const GOALS = ['Awareness', 'Engagement', 'Education', 'Experience', 'Visit intent', 'Conversion'];

    /** Assumption fields the AI drafts; true = list field stored one item per line. */
    private const PROFILE_FIELDS = [
        'overview' => false,
        'audience' => true,
        'content_opportunities' => true,
        'posting_times' => true,
        'recommended_platforms' => true,
    ];

    private const IDEA_TEXT_FIELDS = ['title', 'topic', 'audience', 'objective', 'cta', 'hook', 'key_message'];

    private const IMAGE_MIME = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(private readonly array $config)
    {
        if (empty($config['api_key'])) {
            throw new AiException('Live AI mode needs GEMINI_API_KEY in .env.');
        }
    }

    public function profile(array $facts, string $text): ProfileDraft
    {
        $properties = [];
        foreach (self::PROFILE_FIELDS as $key => $isList) {
            $properties[$key] = $isList ? ['type' => 'ARRAY', 'items' => ['type' => 'STRING']] : ['type' => 'STRING'];
        }

        $factLines = collect($facts)->map(fn ($f, $key) => "- {$key}: ".str_replace("\n", '; ', $f['value']))->implode("\n");
        $prompt = <<<PROMPT
        You are helping a marketing manager build a business profile for social media planning.

        Verified facts read from the public website:
        {$factLines}

        Cleaned website text (may be partial):
        {$this->clip($text, 12000)}

        Draft these fields as clearly labelled ASSUMPTIONS for the manager to review:
        - overview: two or three sentences describing the business.
        - audience: 3-5 likely audience segments.
        - content_opportunities: 4-6 content themes grounded in the attractions above.
        - posting_times: 2-3 suggested posting windows in Asia/Dhaka time, framed as starting points to test.
        - recommended_platforms: 2-4 platforms, each with a short reason.
        Do not invent prices, opening hours, offers, phone numbers or statistics.
        PROMPT;

        $data = $this->json($prompt, ['type' => 'OBJECT', 'properties' => $properties, 'required' => array_keys($properties)]);

        $fields = [];
        foreach (self::PROFILE_FIELDS as $key => $isList) {
            $value = $data[$key] ?? null;
            $value = $isList
                ? (is_array($value) ? implode("\n", array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : '', $value))) : '')
                : (is_string($value) ? trim($value) : '');
            if ($value === '') {
                throw $this->invalid("the profile field \"{$key}\" is missing");
            }
            $fields[$key] = $value;
        }

        return new ProfileDraft($fields, self::PROVENANCE, $this->textModel());
    }

    public function ideas(BusinessProfile $profile, Collection $knowledge, int $round): array
    {
        $item = ['type' => 'OBJECT', 'properties' => [], 'required' => [...self::IDEA_TEXT_FIELDS, 'platform', 'format', 'campaign_goal']];
        foreach (self::IDEA_TEXT_FIELDS as $field) {
            $item['properties'][$field] = ['type' => 'STRING'];
        }
        $item['properties'] += [
            'platform' => ['type' => 'STRING', 'enum' => self::PLATFORMS],
            'format' => ['type' => 'STRING', 'enum' => self::FORMATS],
            'campaign_goal' => ['type' => 'STRING', 'enum' => self::GOALS],
            'duration_s' => ['type' => 'INTEGER'],
        ];
        if ($round === 2) {
            $item['properties']['influenced_by'] = ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']];
            $item['properties']['why'] = ['type' => 'STRING'];
            $item['required'] = [...$item['required'], 'influenced_by', 'why'];
        }

        $prompt = $round === 1 ? $this->roundOnePrompt($profile) : $this->roundTwoPrompt($profile, $knowledge);
        $data = $this->json($prompt, ['type' => 'OBJECT', 'properties' => ['ideas' => ['type' => 'ARRAY', 'items' => $item]], 'required' => ['ideas']]);

        $raw = $data['ideas'] ?? null;
        if (! is_array($raw) || count($raw) < 3 || count($raw) > 8) {
            throw $this->invalid('expected 3 to 8 ideas');
        }

        $knownIds = $knowledge->pluck('id')->all();

        return array_map(fn ($idea) => $this->idea($idea, $round, $knownIds, $knowledge), array_values($raw));
    }

    public function content(ContentIdea $idea, BusinessProfile $profile): ContentDraft
    {
        $properties = [
            'caption' => ['type' => 'STRING'],
            'hashtags' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
            'cta' => ['type' => 'STRING'],
        ];
        $wantsImage = ! $idea->isVideo() && $idea->format !== 'Text';
        if ($idea->isVideo()) {
            $properties['video_brief'] = ['type' => 'OBJECT', 'properties' => [
                'script' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => [
                    'label' => ['type' => 'STRING'], 'direction' => ['type' => 'STRING'],
                ], 'required' => ['label', 'direction']]],
                'shot_list' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'on_screen_text' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'notes' => ['type' => 'STRING'],
            ], 'required' => ['script', 'shot_list', 'on_screen_text', 'notes']];
        }
        if ($wantsImage) {
            $properties['alt_text'] = ['type' => 'STRING'];
            $properties['image_prompt'] = ['type' => 'STRING'];
        }

        $name = $profile->value('business_name') ?: 'the business';
        $kind = match (true) {
            $idea->isVideo() => "a video production brief (script scenes with labels, shot list, on-screen text, notes) for a {$idea->duration_s}-second {$idea->aspect_ratio} Reel. Do not claim a video exists",
            $wantsImage => 'alt_text describing the image for screen-reader users, and image_prompt: a description of one photo-style image for the post with no text, logos or identifiable faces',
            default => 'no image (text-only post)',
        };
        $prompt = <<<PROMPT
        Write a {$idea->platform} post for {$name}.
        Business overview: {$profile->value('overview')}
        Idea: {$idea->title} ({$idea->formatLabel()}, goal: {$idea->campaign_goal})
        Audience: {$idea->audience}
        Objective: {$idea->objective}
        Hook: {$idea->hook}
        Key message: {$idea->key_message}
        Call to action: {$idea->cta}

        Return caption, 4-8 hashtags (each starting with #), cta, and {$kind}.
        Do not invent prices, opening hours, offers, phone numbers, statistics or attractions.
        PROMPT;

        $data = $this->json($prompt, ['type' => 'OBJECT', 'properties' => $properties, 'required' => array_keys($properties)]);

        $caption = $this->string($data, 'caption');
        $hashtags = collect(is_array($data['hashtags'] ?? null) ? $data['hashtags'] : [])
            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
            ->map(fn ($t) => '#'.ltrim(str_replace(' ', '', trim($t)), '#'))
            ->unique()->implode(' ');
        if ($hashtags === '') {
            throw $this->invalid('no hashtags were returned');
        }

        $videoBrief = null;
        if ($idea->isVideo()) {
            $brief = is_array($data['video_brief'] ?? null) ? $data['video_brief'] : [];
            $script = collect($brief['script'] ?? [])->filter(fn ($s) => is_array($s) && ! empty($s['label']) && ! empty($s['direction']))
                ->map(fn ($s) => ['label' => (string) $s['label'], 'direction' => (string) $s['direction']])->values()->all();
            $shots = $this->strings($brief['shot_list'] ?? null);
            $onScreen = $this->strings($brief['on_screen_text'] ?? null);
            if (! $script || ! $shots || ! $onScreen) {
                throw $this->invalid('the video brief is incomplete');
            }
            $videoBrief = [
                'script' => $script,
                'shot_list' => $shots,
                'on_screen_text' => $onScreen,
                'duration_s' => $idea->duration_s ?? 30,
                'aspect_ratio' => $idea->aspect_ratio ?? '9:16',
                'notes' => is_string($brief['notes'] ?? null) ? $brief['notes'] : '',
            ];
        }

        $imagePath = null;
        $altText = null;
        if ($wantsImage) {
            $altText = $this->string($data, 'alt_text');
            $imagePath = $this->image($this->string($data, 'image_prompt'));
        }

        return new ContentDraft(
            caption: $caption,
            hashtags: $hashtags,
            cta: is_string($data['cta'] ?? null) && trim($data['cta']) !== '' ? trim($data['cta']) : $idea->cta,
            altText: $altText,
            imagePath: $imagePath,
            imageProvenance: $imagePath ? self::PROVENANCE.':'.$this->imageModel() : null,
            videoBrief: $videoBrief,
            provenance: self::PROVENANCE,
            model: $this->textModel(),
        );
    }

    public function textModel(): string
    {
        return $this->config['text_model'];
    }

    public function imageModel(): string
    {
        return $this->config['image_model'];
    }

    private function roundOnePrompt(BusinessProfile $profile): string
    {
        return <<<PROMPT
        Suggest 6 social media content ideas for this business, based only on its confirmed profile.

        {$this->profileText($profile)}

        Spread them across Facebook and Instagram and include at least one Text, one Image, one Carousel and one Reel idea.
        For Reel ideas set duration_s (15-60). Keep hooks under 15 words.
        Only feature attractions, facilities and activities named in the profile above; do not invent new ones.
        No performance data is available yet, so do not claim anything has performed well.
        PROMPT;
    }

    private function roundTwoPrompt(BusinessProfile $profile, Collection $knowledge): string
    {
        $records = $knowledge->map(fn (KnowledgeRecord $r) => [
            'id' => $r->id,
            'key' => $r->key,
            'group' => $r->group_value,
            'observation' => $r->observation,
            'mean_engagement_rate_pct' => $r->metrics['mean_er'] ?? null,
            'difference_pp_vs_other_posts' => $r->metrics['difference_pp'] ?? null,
            'direction' => $r->metrics['direction'] ?? null,
            'confidence' => $r->confidence,
            'sample_size' => $r->sample_size,
        ])->values()->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Suggest 6 second-round social media content ideas for this business.

        {$this->profileText($profile)}

        Knowledge records from round one (derived from SYNTHETIC demo data, not real analytics; confidence is never high):
        {$records}

        Use the records as explicit inputs: favour groups whose direction is "above" and avoid over-investing in "below" groups.
        For each idea, put in influenced_by the ids of the records that actually shaped it, and in why explain how in one or two
        sentences, naming the record key and noting that the evidence is synthetic and of limited confidence.
        Include at least one exploratory idea that is not driven by the records: give it an empty influenced_by and explain in why that it is a deliberate test.
        Spread ideas across Facebook and Instagram; for Reel ideas set duration_s (15-60).
        Only feature attractions, facilities and activities named in the profile above; do not invent new ones.
        PROMPT;
    }

    private function profileText(BusinessProfile $profile): string
    {
        return collect(BusinessProfile::FIELDS)
            ->map(fn ($meta, $key) => ($value = $profile->value($key)) !== '' ? "{$meta['label']}: ".str_replace("\n", '; ', $value) : null)
            ->filter()->implode("\n");
    }

    private function idea(mixed $raw, int $round, array $knownIds, Collection $knowledge): array
    {
        if (! is_array($raw)) {
            throw $this->invalid('an idea is not an object');
        }
        $idea = ['fixture_key' => null, 'provenance' => self::PROVENANCE.':'.$this->textModel()];
        foreach (self::IDEA_TEXT_FIELDS as $field) {
            $idea[$field] = $this->string($raw, $field);
        }
        foreach (['platform' => self::PLATFORMS, 'format' => self::FORMATS, 'campaign_goal' => self::GOALS] as $field => $allowed) {
            if (! in_array($raw[$field] ?? null, $allowed, true)) {
                throw $this->invalid("an idea has an unsupported {$field}");
            }
            $idea[$field] = $raw[$field];
        }
        $isVideo = in_array($idea['format'], ContentIdea::VIDEO_FORMATS, true);
        $idea['duration_s'] = $isVideo ? max(10, min(90, (int) ($raw['duration_s'] ?? 30) ?: 30)) : null;
        $idea['aspect_ratio'] = $isVideo ? '9:16' : null;

        if ($round === 1) {
            return $idea + ['why' => null, 'influenced_by' => [], 'knowledge_driven' => false];
        }

        $ids = array_values(array_unique(array_map('intval', is_array($raw['influenced_by'] ?? null) ? $raw['influenced_by'] : [])));
        if (array_diff($ids, $knownIds)) {
            throw new AiException('Gemini cited knowledge records that do not exist; nothing was saved.');
        }
        $why = $this->string($raw, 'why');
        if (! $ids && ! str_starts_with($why, 'Not knowledge-driven')) {
            $why = 'Not knowledge-driven: '.$why;
        }

        return $idea + ['why' => $why, 'influenced_by' => $ids, 'knowledge_driven' => $ids !== []];
    }

    /** Generate one image and save it under the public generated-images directory. Returns its URL path. */
    private function image(string $prompt): string
    {
        $data = $this->generate($this->imageModel(), [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt.' Square composition. No text, logos or watermarks.']]]],
            'generationConfig' => ['responseModalities' => ['IMAGE']],
        ]);

        $part = collect(data_get($data, 'candidates.0.content.parts', []))->first(fn ($p) => isset($p['inlineData']['data']));
        if (! $part) {
            throw new AiException('Gemini returned no image (reason: '.$this->finishReason($data).'); nothing was saved.');
        }
        $ext = self::IMAGE_MIME[$part['inlineData']['mimeType'] ?? ''] ?? null;
        $bytes = base64_decode($part['inlineData']['data'], true);
        if (! $ext || $bytes === false || $bytes === '') {
            throw new AiException('Gemini returned an image in an unsupported format; nothing was saved.');
        }

        $dir = $this->config['image_dir'];
        File::ensureDirectoryExists($dir);
        $name = Str::uuid().'.'.$ext;
        File::put($dir.DIRECTORY_SEPARATOR.$name, $bytes);

        return rtrim($this->config['image_url'], '/').'/'.$name;
    }

    /** Text call with structured output; returns the decoded JSON object. */
    private function json(string $prompt, array $schema): array
    {
        $data = $this->generate($this->textModel(), [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'responseSchema' => $schema],
        ]);

        $text = collect(data_get($data, 'candidates.0.content.parts', []))
            ->reject(fn ($p) => $p['thought'] ?? false)
            ->pluck('text')->filter()->implode('');
        if ($text === '') {
            throw new AiException('Gemini returned no content (reason: '.$this->finishReason($data).'); nothing was saved.');
        }
        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new AiException('Gemini returned output that is not valid JSON; nothing was saved.');
        }

        return $decoded;
    }

    private function generate(string $model, array $body): array
    {
        $timeout = (int) ($this->config['timeout'] ?? 60);
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->config['api_key']])
                ->acceptJson()
                ->timeout($timeout)
                ->post(rtrim($this->config['base_url'], '/')."/{$model}:generateContent", $body);
        } catch (ConnectionException) {
            throw new AiException("Gemini could not be reached or did not respond within {$timeout} seconds.");
        }

        if ($response->failed()) {
            throw new AiException($this->httpError($response, $model));
        }
        $data = $response->json();
        if (! is_array($data)) {
            throw new AiException('Gemini returned a response that could not be read; nothing was saved.');
        }

        return $data;
    }

    private function httpError(Response $response, string $model): string
    {
        $status = $response->status();
        $detail = Str::limit(str_replace($this->config['api_key'], '[redacted]', (string) $response->json('error.message')), 200);

        return match (true) {
            $status === 429 => 'Gemini rate limit or quota reached (HTTP 429). Wait a minute and try again, or switch to Demo Fixtures.',
            in_array($status, [401, 403], true) => "Gemini refused the API key (HTTP {$status}). Check GEMINI_API_KEY in .env.",
            $status === 404 => "Model {$model} is not available to this API key (HTTP 404). Check GEMINI_TEXT_MODEL / GEMINI_IMAGE_MODEL in .env. {$detail}",
            $status >= 500 => "Gemini service error (HTTP {$status}). Try again later.",
            default => "Gemini rejected the request (HTTP {$status}). {$detail}",
        };
    }

    private function finishReason(array $data): string
    {
        return (string) (data_get($data, 'promptFeedback.blockReason') ?? data_get($data, 'candidates.0.finishReason') ?? 'unknown');
    }

    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw $this->invalid("\"{$key}\" is missing");
        }

        return trim($value);
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : '', $value))) : [];
    }

    private function clip(string $text, int $max): string
    {
        return Str::limit($text, $max, ' …');
    }

    private function invalid(string $why): AiException
    {
        return new AiException("Gemini output did not match the expected shape ({$why}); nothing was saved.");
    }
}
