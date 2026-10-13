<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentIdea extends Model
{
    public const VIDEO_FORMATS = ['Reel'];

    protected $fillable = [
        'round_id', 'fixture_key', 'title', 'topic', 'platform', 'format', 'campaign_goal', 'audience',
        'objective', 'cta', 'hook', 'key_message', 'duration_s', 'aspect_ratio', 'why', 'influenced_by',
        'knowledge_driven', 'selected', 'provenance',
    ];

    protected function casts(): array
    {
        return [
            'influenced_by' => 'array',
            'knowledge_driven' => 'boolean',
            'selected' => 'boolean',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(ContentRound::class, 'round_id');
    }

    public function isVideo(): bool
    {
        return in_array($this->format, self::VIDEO_FORMATS, true);
    }

    public function formatLabel(): string
    {
        return $this->isVideo() ? 'Video / Reel' : $this->format;
    }

    /** @return Collection<int, KnowledgeRecord> */
    public function influencingRecords(): Collection
    {
        return KnowledgeRecord::whereIn('id', $this->influenced_by ?? [])->get();
    }
}
