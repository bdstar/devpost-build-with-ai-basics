<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\ContentItem;
use App\Models\ContentRound;
use App\Models\KnowledgeRecord;
use App\Models\PerformanceDataset;
use App\Services\WorkflowProgress;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(WorkflowProgress $progress): View
    {
        $statusCounts = ContentItem::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('dashboard', [
            'progress' => $progress,
            'profile' => BusinessProfile::current(),
            'roundOne' => ContentRound::latestNumber(1),
            'roundTwo' => ContentRound::latestNumber(2),
            'statusCounts' => $statusCounts,
            'readyCount' => ContentItem::where('status', ContentItem::SCHEDULED)->where('scheduled_at', '<=', now())->count(),
            'dataset' => PerformanceDataset::currentSynthetic(),
            'insights' => KnowledgeRecord::ordered()->get(),
        ]);
    }
}
