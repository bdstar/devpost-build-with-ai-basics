<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceDataset extends Model
{
    protected $fillable = ['key', 'name', 'version', 'is_synthetic', 'metadata', 'source_path', 'loaded_at'];

    protected function casts(): array
    {
        return [
            'is_synthetic' => 'boolean',
            'metadata' => 'array',
            'loaded_at' => 'datetime',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(PerformanceRecord::class, 'dataset_id');
    }

    public static function currentSynthetic(): ?self
    {
        return static::where('key', config('performance.synthetic_dataset_key'))
            ->where('is_synthetic', true)
            ->latest('loaded_at')
            ->first();
    }
}
