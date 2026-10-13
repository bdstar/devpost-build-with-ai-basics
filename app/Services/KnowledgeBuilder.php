<?php

namespace App\Services;

use App\Models\KnowledgeRecord;
use App\Models\PerformanceDataset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Saves qualifying findings (n >= the evidence minimum) as knowledge records with their evidence.
 */
class KnowledgeBuilder
{
    public function __construct(private PerformanceAnalyzer $analyzer) {}

    /** @return Collection<int, KnowledgeRecord> */
    public function build(PerformanceDataset $dataset): Collection
    {
        $analysis = $this->analyzer->analyze($dataset->records()->get());
        $analysedAt = now();

        return DB::transaction(function () use ($analysis, $dataset, $analysedAt) {
            $saved = collect();

            foreach ($analysis as $dimension) {
                foreach ($dimension['groups'] as $group) {
                    if ($group['confidence'] === PerformanceAnalyzer::INSUFFICIENT || $group['observation'] === null) {
                        continue;
                    }

                    $saved->push(KnowledgeRecord::updateOrCreate(
                        [
                            'key' => $group['key'],
                            'source_dataset' => $dataset->key,
                            'dataset_version' => $dataset->version,
                        ],
                        [
                            'category' => $dimension['label'],
                            'observation' => $group['observation'],
                            'recommendation' => $group['recommendation'],
                            'dimension' => $group['dimension'],
                            'group_value' => $group['value'],
                            'supporting_post_ids' => $group['post_ids'],
                            'sample_size' => $group['n_valid'],
                            'excluded_count' => $group['n_excluded'],
                            'metrics' => [
                                'mean_er' => round($group['mean_er'], 4),
                                'sd_er' => round($group['sd_er'], 4),
                                'mean_ctr' => $group['mean_ctr'] === null ? null : round($group['mean_ctr'], 4),
                                'n_ctr' => $group['n_ctr'],
                                'comparison_mean_er' => $group['rest_mean_er'] === null ? null : round($group['rest_mean_er'], 4),
                                'comparison_n' => $group['rest_n'],
                                'difference_pp' => $group['difference_pp'] === null ? null : round($group['difference_pp'], 4),
                                'direction' => $group['direction'],
                            ],
                            'formula' => implode('; ', [PerformanceAnalyzer::ER_FORMULA, PerformanceAnalyzer::CTR_FORMULA, PerformanceAnalyzer::CV_FORMULA]),
                            'cv' => $group['cv'],
                            'confidence' => $group['confidence'],
                            'rationale' => $group['rationale'],
                            'evidence_type' => $dataset->is_synthetic ? 'synthetic' : 'real',
                            'analysed_at' => $analysedAt,
                        ],
                    ));
                }
            }

            // Findings that no longer qualify for this dataset version are removed.
            KnowledgeRecord::where('source_dataset', $dataset->key)
                ->where('dataset_version', $dataset->version)
                ->whereNotIn('id', $saved->pluck('id'))
                ->delete();

            return $saved;
        });
    }
}
