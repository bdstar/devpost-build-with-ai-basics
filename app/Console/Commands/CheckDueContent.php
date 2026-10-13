<?php

namespace App\Console\Commands;

use App\Models\ContentItem;
use App\Services\ContentWorkflow;
use Illuminate\Console\Command;

class CheckDueContent extends Command
{
    protected $signature = 'content:check-due';

    protected $description = 'Note scheduled content whose time has passed. Never publishes anything.';

    public function handle(): int
    {
        $workflow = new ContentWorkflow('system');
        $due = ContentItem::where('status', ContentItem::SCHEDULED)
            ->whereNull('due_recorded_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        $noted = $due->filter(fn (ContentItem $item) => $workflow->recordDue($item))->count();
        $this->info("{$noted} scheduled item(s) reached their time and are Ready to Publish (manual action required).");

        return self::SUCCESS;
    }
}
