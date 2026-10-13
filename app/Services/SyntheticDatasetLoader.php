<?php

namespace App\Services;

use App\Models\PerformanceDataset;
use App\Models\PerformanceRecord;
use Illuminate\Support\Facades\DB;

/**
 * Loads the canonical synthetic JSON into SQLite. The source file is only ever read.
 */
class SyntheticDatasetLoader
{
    private const REQUIRED_FIELDS = [
        'post_id', 'published_date', 'platform', 'content_format', 'campaign_goal',
        'data_source', 'is_real_performance_data',
    ];

    public function load(): PerformanceDataset
    {
        $relativePath = config('performance.synthetic_path');
        $json = $this->readJson($relativePath);
        $this->validate($json, $relativePath);

        return DB::transaction(function () use ($json, $relativePath) {
            $dataset = PerformanceDataset::updateOrCreate(
                ['key' => config('performance.synthetic_dataset_key'), 'version' => $json['dataset_version']],
                [
                    'name' => $json['dataset_name'],
                    'is_synthetic' => true,
                    'metadata' => [
                        'warning' => $json['warning'] ?? null,
                        'business_context_source' => $json['business_context_source'] ?? null,
                        'metadata_file' => $this->readMetadata(),
                    ],
                    'source_path' => $relativePath,
                    'loaded_at' => now(),
                ],
            );

            $postIds = [];
            foreach ($json['posts'] as $post) {
                $postIds[] = $post['post_id'];
                PerformanceRecord::updateOrCreate(
                    ['dataset_id' => $dataset->id, 'post_id' => $post['post_id']],
                    [
                        'published_date' => $post['published_date'],
                        'platform' => $post['platform'],
                        'content_format' => $post['content_format'],
                        'post_theme' => $post['post_theme'] ?? null,
                        'caption_excerpt' => $post['caption_excerpt'] ?? null,
                        'campaign_goal' => $post['campaign_goal'],
                        ...collect(PerformanceRecord::METRICS)->mapWithKeys(fn ($m) => [$m => $post[$m] ?? null])->all(),
                        'data_source' => $post['data_source'],
                        'is_real_performance_data' => $post['is_real_performance_data'],
                    ],
                );
            }

            // Keep the database identical to the file if records were removed from it.
            $dataset->records()->whereNotIn('post_id', $postIds)->delete();

            return $dataset->fresh();
        });
    }

    private function readJson(string $relativePath): array
    {
        $path = base_path($relativePath);
        if (! is_file($path)) {
            throw new DatasetException("Dataset file not found: {$relativePath}");
        }

        $json = json_decode(file_get_contents($path), true);
        if (! is_array($json)) {
            throw new DatasetException("Dataset file is not valid JSON: {$relativePath} (".json_last_error_msg().')');
        }

        return $json;
    }

    private function readMetadata(): ?array
    {
        $path = base_path(config('performance.synthetic_metadata_path'));

        return is_file($path) ? json_decode(file_get_contents($path), true) : null;
    }

    private function validate(array $json, string $relativePath): void
    {
        $problems = [];

        if (($json['synthetic'] ?? null) !== true) {
            $problems[] = 'Top-level "synthetic" must be true.';
        }
        foreach (['dataset_name', 'dataset_version'] as $field) {
            if (blank($json[$field] ?? null)) {
                $problems[] = "Missing top-level field \"{$field}\".";
            }
        }

        $posts = $json['posts'] ?? null;
        if (! is_array($posts)) {
            throw new DatasetException("Dataset is invalid: {$relativePath}", [...$problems, 'Missing "posts" array.']);
        }

        $expected = config('performance.expected_records');
        if (count($posts) !== $expected) {
            $problems[] = "Expected {$expected} posts, found ".count($posts).'.';
        }

        $seen = [];
        foreach ($posts as $i => $post) {
            $label = $post['post_id'] ?? "posts[{$i}]";

            foreach (self::REQUIRED_FIELDS as $field) {
                if (! array_key_exists($field, $post) || $post[$field] === '' || $post[$field] === null) {
                    $problems[] = "{$label}: missing required field \"{$field}\".";
                }
            }
            foreach (PerformanceRecord::METRICS as $metric) {
                $value = $post[$metric] ?? null;
                if ($value !== null && (! is_numeric($value) || $value < 0)) {
                    $problems[] = "{$label}: \"{$metric}\" must be a non-negative number.";
                }
            }
            if (($post['data_source'] ?? null) !== 'SYNTHETIC_DEMO') {
                $problems[] = "{$label}: data_source must be SYNTHETIC_DEMO.";
            }
            if (($post['is_real_performance_data'] ?? null) !== false) {
                $problems[] = "{$label}: is_real_performance_data must be false.";
            }
            if (isset($post['post_id'])) {
                if (isset($seen[$post['post_id']])) {
                    $problems[] = "{$label}: duplicate post_id.";
                }
                $seen[$post['post_id']] = true;
            }
        }

        if ($problems) {
            throw new DatasetException("Dataset is invalid: {$relativePath}", $problems);
        }
    }
}
