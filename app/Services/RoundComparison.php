<?php

namespace App\Services;

use App\Models\ContentRound;

class RoundComparison
{
    public const DISCLAIMER = 'This demonstrates the feedback loop working; it does not show that real engagement improved.';

    public const DIMENSIONS = ['format' => 'Format', 'campaign_goal' => 'Campaign goal', 'platform' => 'Platform'];

    public function compare(ContentRound $one, ContentRound $two): array
    {
        $ideasOne = $one->ideas()->get();
        $ideasTwo = $two->ideas()->get();

        $dimensions = [];
        foreach (self::DIMENSIONS as $field => $label) {
            $countsOne = $ideasOne->countBy($field)->all();
            $countsTwo = $ideasTwo->countBy($field)->all();
            $values = array_unique([...array_keys($countsOne), ...array_keys($countsTwo)]);
            sort($values);

            $rows = [];
            foreach ($values as $value) {
                $a = $countsOne[$value] ?? 0;
                $b = $countsTwo[$value] ?? 0;
                $rows[] = ['value' => $value, 'round_one' => $a, 'round_two' => $b, 'delta' => $b - $a];
            }

            $dimensions[$field] = [
                'label' => $label,
                'rows' => $rows,
                'differs' => collect($rows)->contains(fn ($r) => $r['delta'] !== 0),
            ];
        }

        $ctaOne = $ideasOne->pluck('cta')->filter()->unique()->values()->all();
        $ctaTwo = $ideasTwo->pluck('cta')->filter()->unique()->values()->all();
        $topicsOne = $ideasOne->pluck('topic')->filter()->unique()->values()->all();
        $topicsTwo = $ideasTwo->pluck('topic')->filter()->unique()->values()->all();

        return [
            'counts' => ['round_one' => $ideasOne->count(), 'round_two' => $ideasTwo->count()],
            'dimensions' => $dimensions,
            'cta' => ['round_one' => $ctaOne, 'round_two' => $ctaTwo, 'differs' => $ctaOne != $ctaTwo],
            'topics' => [
                'round_one' => $topicsOne,
                'round_two' => $topicsTwo,
                'added' => array_values(array_diff($topicsTwo, $topicsOne)),
                'dropped' => array_values(array_diff($topicsOne, $topicsTwo)),
            ],
            'knowledge_driven' => $ideasTwo->where('knowledge_driven', true)->count(),
            'differing' => array_values(array_keys(array_filter($dimensions, fn ($d) => $d['differs']))),
            'disclaimer' => self::DISCLAIMER,
        ];
    }
}
