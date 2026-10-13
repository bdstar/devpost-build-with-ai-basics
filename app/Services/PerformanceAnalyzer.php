<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Deterministic evidence analysis (no AI). Groups comparable posts, computes engagement
 * rate, its consistency (CV) and CTR, then applies the agreed evidence ladder.
 */
class PerformanceAnalyzer
{
    public const INSUFFICIENT = 'insufficient';
    public const LOW = 'low';
    public const MEDIUM = 'medium';

    public const ER_FORMULA = 'ER = (likes + comments + shares) / reach × 100';
    public const CTR_FORMULA = 'CTR = link_clicks / impressions × 100';
    public const CV_FORMULA = 'CV = population standard deviation of ER / mean ER';

    public const DIMENSIONS = [
        'content_format' => ['label' => 'Content format', 'key_prefix' => 'format'],
        'campaign_goal' => ['label' => 'Campaign goal', 'key_prefix' => 'goal'],
        'platform_format' => ['label' => 'Platform + format', 'key_prefix' => 'platform_format'],
    ];

    /**
     * @param  iterable<array|object>  $records  performance records (models or arrays)
     * @return array<string, array> keyed by dimension
     */
    public function analyze(iterable $records): array
    {
        $records = collect($records)->map(fn ($r) => $this->normalise($r));
        $valid = $records->filter(fn ($r) => $r['er'] !== null);

        $result = [];
        foreach (self::DIMENSIONS as $dimension => $meta) {
            $groups = $records->groupBy(fn ($r) => $this->groupValue($r, $dimension))
                ->map(fn (Collection $group, string $value) => $this->analyseGroup($group, $value, $dimension, $meta, $valid))
                ->sortByDesc(fn ($g) => $g['mean_er'] ?? -1)
                ->values()
                ->all();

            $result[$dimension] = [
                'dimension' => $dimension,
                'label' => $meta['label'],
                'groups' => $groups,
                'n_valid' => $valid->count(),
                'n_excluded' => $records->count() - $valid->count(),
                'max_mean_er' => collect($groups)->max('mean_er') ?? 0,
            ];
        }

        return $result;
    }

    public function confidence(int $n, ?float $cv): string
    {
        if ($n < config('performance.min_sample')) {
            return self::INSUFFICIENT;
        }
        if ($n < config('performance.medium_sample')) {
            return self::LOW;
        }

        return $cv !== null && $cv <= config('performance.medium_max_cv') ? self::MEDIUM : self::LOW;
    }

    public function rationale(int $n, ?float $cv, string $confidence): string
    {
        $min = config('performance.min_sample');
        $medium = config('performance.medium_sample');
        $maxCv = config('performance.medium_max_cv');
        $cvText = $cv === null ? 'CV undefined (mean ER is 0)' : 'CV='.number_format($cv, 2);

        $reason = match (true) {
            $confidence === self::INSUFFICIENT => "n={$n} is below the minimum of {$min} comparable posts, so no recommendation is made.",
            $n < $medium => "n={$n} is between {$min} and ".($medium - 1).': preliminary observation with a small-sample warning.',
            $confidence === self::MEDIUM => "n={$n} (≥ {$medium}) and {$cvText} (≤ {$maxCv}): results are reasonably consistent.",
            default => "n={$n} (≥ {$medium}) but {$cvText} exceeds {$maxCv}: results vary substantially, so confidence stays Low.",
        };

        return $reason.' High confidence is never assigned automatically.';
    }

    private function analyseGroup(Collection $group, string $value, string $dimension, array $meta, Collection $allValid): array
    {
        $validGroup = $group->filter(fn ($r) => $r['er'] !== null);
        $ers = $validGroup->pluck('er')->all();
        $n = count($ers);

        $mean = $n ? array_sum($ers) / $n : null;
        $sd = $n ? sqrt(array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $ers)) / $n) : null;
        $cv = ($mean !== null && $mean > 0) ? $sd / $mean : null;

        $ctrs = $validGroup->pluck('ctr')->filter(fn ($c) => $c !== null);
        $meanCtr = $ctrs->isNotEmpty() ? $ctrs->avg() : null;

        $postIds = $validGroup->pluck('post_id')->values()->all();
        $rest = $allValid->reject(fn ($r) => in_array($r['post_id'], $postIds, true));
        $restMean = $rest->isNotEmpty() ? $rest->avg('er') : null;

        $confidence = $this->confidence($n, $cv);
        $difference = ($mean !== null && $restMean !== null) ? $mean - $restMean : null;

        return [
            'key' => $meta['key_prefix'].':'.$value,
            'dimension' => $dimension,
            'value' => $value,
            'n_valid' => $n,
            'n_excluded' => $group->count() - $n,
            'post_ids' => $postIds,
            'excluded_post_ids' => $group->filter(fn ($r) => $r['er'] === null)->pluck('post_id')->values()->all(),
            'mean_er' => $mean,
            'sd_er' => $sd,
            'cv' => $cv,
            'mean_ctr' => $meanCtr,
            'n_ctr' => $ctrs->count(),
            'rest_mean_er' => $restMean,
            'rest_n' => $rest->count(),
            'difference_pp' => $difference,
            'direction' => $difference === null ? null : ($difference >= 0 ? 'above' : 'below'),
            'confidence' => $confidence,
            'rationale' => $this->rationale($n, $cv, $confidence),
            'observation' => $confidence === self::INSUFFICIENT ? null : $this->observation($meta['label'], $value, $n, $mean, $restMean, $rest->count(), $confidence),
            'recommendation' => $confidence === self::INSUFFICIENT || $difference === null ? null : $this->recommendation($meta['label'], $value, $difference),
        ];
    }

    private function observation(string $label, string $value, int $n, float $mean, ?float $restMean, int $restN, string $confidence): string
    {
        $prefix = $confidence === self::LOW && $n < config('performance.medium_sample')
            ? 'Preliminary observation (small sample): '
            : 'Simulated sample-level observation: ';

        $text = $prefix.sprintf('in the synthetic dataset, %s "%s" posts averaged an engagement rate of %.2f%% (n=%d)', strtolower($label), $value, $mean, $n);

        if ($restMean === null) {
            return $text.'.';
        }

        $diff = $mean - $restMean;

        return $text.sprintf(', %.2f percentage points %s the other %d comparable posts (%.2f%%).', abs($diff), $diff >= 0 ? 'above' : 'below', $restN, $restMean);
    }

    private function recommendation(string $label, string $value, float $difference): string
    {
        return $difference >= 0
            ? sprintf('Give %s "%s" more weight in the next content round, and keep measuring.', strtolower($label), $value)
            : sprintf('Use %s "%s" less in the next round, or keep it only as a clearly labelled test.', strtolower($label), $value);
    }

    private function groupValue(array $r, string $dimension): string
    {
        return $dimension === 'platform_format'
            ? $r['platform'].' / '.$r['content_format']
            : (string) $r[$dimension];
    }

    private function normalise(array|object $r): array
    {
        $get = fn (string $k) => data_get($r, $k);
        $num = fn (string $k) => is_numeric($get($k)) ? (float) $get($k) : null;

        $reach = $num('reach');
        $likes = $num('likes');
        $comments = $num('comments');
        $shares = $num('shares');
        $impressions = $num('impressions');
        $clicks = $num('link_clicks');

        $er = ($reach !== null && $reach > 0 && $likes !== null && $comments !== null && $shares !== null)
            ? ($likes + $comments + $shares) / $reach * 100
            : null;
        $ctr = ($impressions !== null && $impressions > 0 && $clicks !== null)
            ? $clicks / $impressions * 100
            : null;

        return [
            'post_id' => (string) $get('post_id'),
            'platform' => (string) $get('platform'),
            'content_format' => (string) $get('content_format'),
            'campaign_goal' => (string) $get('campaign_goal'),
            'er' => $er,
            'ctr' => $ctr,
        ];
    }
}
