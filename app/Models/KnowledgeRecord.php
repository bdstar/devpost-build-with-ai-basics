<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class KnowledgeRecord extends Model
{
    protected $fillable = [
        'key', 'category', 'observation', 'recommendation', 'dimension', 'group_value',
        'supporting_post_ids', 'sample_size', 'excluded_count', 'metrics', 'formula', 'cv',
        'confidence', 'rationale', 'evidence_type', 'source_dataset', 'dataset_version', 'analysed_at',
    ];

    protected function casts(): array
    {
        return [
            'supporting_post_ids' => 'array',
            'metrics' => 'array',
            'cv' => 'float',
            'analysed_at' => 'datetime',
        ];
    }

    public function isSynthetic(): bool
    {
        return $this->evidence_type === 'synthetic';
    }

    public function dataset(): ?PerformanceDataset
    {
        return PerformanceDataset::where('key', $this->source_dataset)->where('version', $this->dataset_version)->first();
    }

    /** @return Collection<int, PerformanceRecord> */
    public function supportingRecords(): Collection
    {
        $dataset = $this->dataset();

        return $dataset
            ? $dataset->records()->whereIn('post_id', $this->supporting_post_ids)->orderBy('post_id')->get()
            : collect();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('dimension')->orderByDesc('sample_size')->orderBy('key');
    }
}
