<?php

namespace App\Services\Ai;

use App\Models\BusinessProfile;
use App\Models\KnowledgeRecord;
use Illuminate\Support\Collection;

/**
 * Demo Fixtures mode: deterministic, pre-written outputs from resources/fixtures. No API calls.
 * Every output is tagged provenance=demo_fixture so the UI can label it.
 */
class FixtureProvider implements AiProvider
{
    public const PROVENANCE = 'demo_fixture';

    private const IDEA_FIELDS = [
        'fixture_key', 'title', 'topic', 'platform', 'format', 'campaign_goal', 'audience', 'objective',
        'cta', 'hook', 'key_message', 'duration_s', 'aspect_ratio',
    ];

    public function profile(array $facts, string $text): ProfileDraft
    {
        $fixture = $this->fixture('profile.json');
        $name = $facts['business_name']['value'] ?? 'the business';

        $fields = [];
        foreach ($fixture['assumptions'] as $key => $value) {
            $fields[$key] = str_replace('{business_name}', $name, $value);
        }

        return new ProfileDraft($fields, self::PROVENANCE);
    }

    public function ideas(BusinessProfile $profile, Collection $knowledge, int $round): array
    {
        $name = $profile->value('business_name') ?: 'the business';

        if ($round === 1) {
            return array_map(fn ($idea) => $this->idea($idea, $name) + [
                'why' => null, 'influenced_by' => [], 'knowledge_driven' => false,
            ], $this->fixture('ideas_round1.json')['ideas']);
        }

        return $this->roundTwo($this->fixture('ideas_round2.json'), $knowledge, $name);
    }

    /**
     * Rule-based selection: an idea qualifies when one of its insight_keys matches a knowledge record
     * whose group performed above its comparison group. Ranked by the strongest matching difference.
     */
    private function roundTwo(array $pool, Collection $knowledge, string $name): array
    {
        $positive = $knowledge
            ->filter(fn (KnowledgeRecord $r) => ($r->metrics['direction'] ?? null) === 'above')
            ->keyBy('key');

        $candidates = [];
        foreach ($pool['tagged'] as $order => $idea) {
            $matches = collect($idea['insight_keys'])->map(fn ($key) => $positive->get($key))->filter()->values();
            if ($matches->isEmpty()) {
                continue;
            }
            $candidates[] = [
                'idea' => $idea,
                'matches' => $matches,
                'strength' => $matches->max(fn ($r) => $r->metrics['difference_pp']),
                'order' => $order,
            ];
        }

        usort($candidates, fn ($a, $b) => [$b['strength'], $a['order']] <=> [$a['strength'], $b['order']]);
        $selected = array_slice($candidates, 0, $pool['max_knowledge_driven']);

        $ideas = array_map(fn ($c) => $this->idea($c['idea'], $name) + [
            'why' => $this->why($c['matches']),
            'influenced_by' => $c['matches']->pluck('id')->all(),
            'knowledge_driven' => true,
        ], $selected);

        foreach ($pool['untagged'] as $idea) {
            $ideas[] = $this->idea($idea, $name) + [
                'why' => $idea['why'],
                'influenced_by' => [],
                'knowledge_driven' => false,
            ];
        }

        return $ideas;
    }

    /** @param Collection<int, KnowledgeRecord> $records */
    private function why(Collection $records): string
    {
        return $records->map(function (KnowledgeRecord $r) {
            $m = $r->metrics;
            $confidence = ucfirst($r->confidence).' Confidence · n='.$r->sample_size.' · CV='.($r->cv === null ? 'N/A' : number_format($r->cv, 2));

            return sprintf(
                'Knowledge record %s: %s posts averaged %.2f%% engagement rate, %+.2f pp vs the other %d posts (%.2f%%) [%s]. Supporting posts: %s. Simulated Insight from %s data.',
                $r->key, $r->group_value, $m['mean_er'], $m['difference_pp'], $m['comparison_n'], $m['comparison_mean_er'],
                $confidence, implode(', ', $r->supporting_post_ids), $r->evidence_type,
            );
        })->implode("\n");
    }

    private function idea(array $fixture, string $name): array
    {
        $idea = ['provenance' => self::PROVENANCE];
        foreach (self::IDEA_FIELDS as $field) {
            $value = $fixture[$field] ?? null;
            $idea[$field] = is_string($value) ? str_replace('{business_name}', $name, $value) : $value;
        }

        return $idea;
    }

    protected function fixture(string $file): array
    {
        $path = resource_path('fixtures/'.$file);
        $data = is_file($path) ? json_decode(file_get_contents($path), true) : null;
        if (! is_array($data)) {
            throw new AiException("Demo fixture {$file} is missing or invalid.");
        }

        return $data;
    }
}
