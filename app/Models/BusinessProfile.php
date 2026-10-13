<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    public const DRAFT = 'draft';
    public const CONFIRMED = 'confirmed';

    /** Profile fields in display order. "list" fields hold one item per line. */
    public const FIELDS = [
        'business_name' => ['label' => 'Business name', 'list' => false],
        'overview' => ['label' => 'Business overview', 'list' => false],
        'attractions' => ['label' => 'Attractions and offerings', 'list' => true],
        'contact_email' => ['label' => 'Contact email', 'list' => true],
        'contact_phone' => ['label' => 'Contact phone', 'list' => true],
        'social_profiles' => ['label' => 'Social profiles linked from the website', 'list' => true],
        'website_headlines' => ['label' => 'Website headlines', 'list' => true],
        'audience' => ['label' => 'Audience', 'list' => true],
        'content_opportunities' => ['label' => 'Content opportunities', 'list' => true],
        'posting_times' => ['label' => 'Suggested posting times', 'list' => true],
        'recommended_platforms' => ['label' => 'Recommended platforms', 'list' => true],
    ];

    public const SOCIAL_PLATFORMS = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'x' => 'X', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'];

    protected $fillable = ['website_url', 'social_urls', 'fields', 'sources', 'status', 'confirmed_at', 'ai_provenance'];

    protected function casts(): array
    {
        return [
            'social_urls' => 'array',
            'fields' => 'array',
            'sources' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::latest('id')->first();
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::CONFIRMED;
    }

    public function value(string $key): string
    {
        return (string) ($this->fields[$key]['value'] ?? '');
    }

    /** @return list<string> */
    public function items(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $this->value($key)))));
    }
}
