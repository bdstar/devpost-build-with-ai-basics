<?php

namespace App\Services\Ai;

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
}
