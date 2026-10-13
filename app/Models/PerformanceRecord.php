<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceRecord extends Model
{
    public const METRICS = ['impressions', 'reach', 'likes', 'comments', 'shares', 'link_clicks', 'saves'];

    protected $fillable = [
        'dataset_id', 'post_id', 'published_date', 'platform', 'content_format', 'post_theme',
        'caption_excerpt', 'campaign_goal', 'impressions', 'reach', 'likes', 'comments', 'shares',
        'link_clicks', 'saves', 'data_source', 'is_real_performance_data',
    ];

    protected function casts(): array
    {
        return [
            'published_date' => 'date',
            'is_real_performance_data' => 'boolean',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(PerformanceDataset::class, 'dataset_id');
    }
}
