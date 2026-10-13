<?php

namespace App\Services\Ai;

use App\Models\BusinessProfile;
use App\Models\ContentIdea;
use App\Models\KnowledgeRecord;
use Illuminate\Support\Collection;

/**
 * Provider-independent AI layer. Implementations: FixtureProvider (Demo Fixtures) and, later, GeminiProvider (Live AI).
 * Implementations must throw AiException on failure and never substitute another provider's output.
 */
interface AiProvider
{
    /**
     * Draft the assumption fields of a business profile from verified facts and cleaned page text.
     *
     * @param  array<string, array{value: string, source_url: string, retrieved_at: string}>  $facts
     */
    public function profile(array $facts, string $text): ProfileDraft;

    /**
     * Generate content ideas. Round 1 uses only the profile; round 2 also receives the knowledge records
     * and must return, per idea, the IDs of the records that influenced it (or knowledge_driven=false).
     *
     * @param  Collection<int, KnowledgeRecord>  $knowledge
     * @return list<array<string, mixed>> attributes for ContentIdea (without round_id)
     */
    public function ideas(BusinessProfile $profile, Collection $knowledge, int $round): array;

    /**
     * Draft post content for one idea: caption, hashtags, CTA and alt text, plus an image (image formats)
     * or a video production brief (video formats). Video files are never generated.
     */
    public function content(ContentIdea $idea, BusinessProfile $profile): ContentDraft;
}
