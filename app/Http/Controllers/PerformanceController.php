<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeRecord;
use App\Models\PerformanceDataset;
use App\Services\DatasetException;
use App\Services\PerformanceAnalyzer;
use App\Services\SyntheticDatasetLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function index(PerformanceAnalyzer $analyzer): View
    {
        $dataset = PerformanceDataset::currentSynthetic();
        $records = $dataset?->records()->orderBy('published_date')->orderBy('post_id')->get() ?? collect();

        return view('performance.index', [
            'dataset' => $dataset,
            'records' => $records,
            'analysis' => $records->isNotEmpty() ? $analyzer->analyze($records) : [],
            'knowledgeCount' => $dataset
                ? KnowledgeRecord::where('source_dataset', $dataset->key)->where('dataset_version', $dataset->version)->count()
                : 0,
        ]);
    }

    public function load(SyntheticDatasetLoader $loader): RedirectResponse
    {
        try {
            $dataset = $loader->load();
        } catch (DatasetException $e) {
            return back()->with('error', $e->getMessage())->with('problems', $e->problems);
        }

        return redirect()->route('performance.index')
            ->with('status', "Loaded {$dataset->records()->count()} synthetic records from {$dataset->source_path}. The source file was not modified.");
    }
}
