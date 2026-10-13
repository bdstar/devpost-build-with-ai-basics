<?php

namespace App\Services\Ai;

final class ContentDraft
{
    /**
     * @param  array<string, mixed>|null  $videoBrief  for video ideas: script, shot_list, on_screen_text, duration_s, aspect_ratio
     * @param  string|null  $imagePath  public path (e.g. /demo-fixtures/images/x.svg) or storage path for generated images
     */
    public function __construct(
        public readonly string $caption,
        public readonly string $hashtags,
        public readonly string $cta,
        public readonly ?string $altText,
        public readonly ?string $imagePath,
        public readonly ?string $imageProvenance,
        public readonly ?array $videoBrief,
        public readonly string $provenance,
        public readonly ?string $model = null,
    ) {}
}
