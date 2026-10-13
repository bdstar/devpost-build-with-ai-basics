<?php

namespace App\Services\Ai;

final class ProfileDraft
{
    /**
     * @param  array<string, string>  $fields  assumption fields keyed like BusinessProfile::FIELDS
     * @param  string  $provenance  "demo_fixture" or "live_ai"
     */
    public function __construct(
        public readonly array $fields,
        public readonly string $provenance,
        public readonly ?string $model = null,
    ) {}
}
