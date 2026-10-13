<?php

namespace App\Services;

use App\Models\BusinessProfile;
use App\Models\ContentIdea;
use App\Models\ContentItem;
use App\Models\ContentRound;
use App\Models\KnowledgeRecord;
use App\Models\PerformanceDataset;
use App\Models\PerformanceRecord;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Clears all working data so the five-step journey can start again.
 * Keeps user accounts and never touches the synthetic dataset source files.
 * Children are deleted before parents, so it works whether or not SQLite enforces foreign keys.
 */
class DemoDataReset
{
    /** @return array<string, int> rows (and generated images) removed per area */
    public function reset(): array
    {
        $counts = DB::transaction(fn () => [
            'business profile' => BusinessProfile::query()->delete(),
            'content history entries' => StatusHistory::query()->delete(),
            'content items' => ContentItem::query()->delete(),
            'ideas' => ContentIdea::query()->delete(),
            'idea rounds' => ContentRound::query()->delete(),
            'knowledge records' => KnowledgeRecord::query()->delete(),
            'performance records' => PerformanceRecord::query()->delete(),
            'performance datasets' => PerformanceDataset::query()->delete(),
        ]);

        $counts['generated images'] = $this->deleteGeneratedImages();

        return $counts;
    }

    private function deleteGeneratedImages(): int
    {
        $dir = config('ai.gemini.image_dir');
        if (! $dir || ! File::isDirectory($dir)) {
            return 0;
        }

        $files = File::files($dir);
        foreach ($files as $file) {
            File::delete($file->getPathname());
        }

        return count($files);
    }
}
