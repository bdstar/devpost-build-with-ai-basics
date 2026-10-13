<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentItem extends Model
{
    public const DRAFT = 'draft';
    public const PENDING = 'pending_approval';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const SCHEDULED = 'scheduled';
    public const PUBLISHED = 'published';
    /** Kept for a future real integration; no proof-of-concept path reaches it. */
    public const PUBLISHING_FAILED = 'publishing_failed';

    public const STATUS_LABELS = [
        self::DRAFT => 'Draft',
        self::PENDING => 'Pending Approval',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Rejected',
        self::SCHEDULED => 'Scheduled',
        self::PUBLISHED => 'Published',
        self::PUBLISHING_FAILED => 'Publishing Failed',
    ];

    protected $fillable = [
        'idea_id', 'caption', 'hashtags', 'cta', 'alt_text', 'image_path', 'image_provenance', 'video_brief',
        'status', 'scheduled_at', 'due_recorded_at', 'published_at', 'publish_method', 'feedback',
        'rejection_reason', 'provenance',
    ];

    protected function casts(): array
    {
        return [
            'video_brief' => 'array',
            'scheduled_at' => 'datetime',
            'due_recorded_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(ContentIdea::class, 'idea_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StatusHistory::class)->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Scheduled time has passed but automatic publishing is disabled. */
    public function isReadyToPublish(): bool
    {
        return $this->status === self::SCHEDULED && $this->scheduled_at !== null && $this->scheduled_at->lte(now());
    }
}
