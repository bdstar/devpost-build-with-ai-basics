<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeRecord;
use App\Models\PerformanceDataset;
use App\Services\KnowledgeBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KnowledgeController extends Controller
{
    public function index(): View
    {
        return view('knowledge.index', [
            'records' => KnowledgeRecord::ordered()->get()->groupBy('category'),
        ]);
    }

    public function show(KnowledgeRecord $record): View
    {
        return view('knowledge.show', [
            'record' => $record,
            'posts' => $record->supportingRecords(),
        ]);
    }

    public function build(KnowledgeBuilder $builder): RedirectResponse
    {
        $dataset = PerformanceDataset::currentSynthetic();
        if (! $dataset) {
            return redirect()->route('performance.index')->with('error', 'Load the performance dataset before generating knowledge records.');
        }

        $records = $builder->build($dataset);

        return redirect()->route('knowledge.index')
            ->with('status', "Saved {$records->count()} knowledge records from groups with at least ".config('performance.min_sample').' comparable posts. Groups below that threshold were not saved.');
    }
}
