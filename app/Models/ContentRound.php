<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentRound extends Model
{
    protected $fillable = ['number', 'mode', 'profile_snapshot', 'knowledge_record_ids'];

    protected function casts(): array
    {
        return [
            'profile_snapshot' => 'array',
            'knowledge_record_ids' => 'array',
        ];
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(ContentIdea::class, 'round_id')->orderBy('id');
    }

    public static function latestNumber(int $number): ?self
    {
        return static::where('number', $number)->latest('id')->first();
    }
}
