<?php

namespace App\Http\Controllers;

use App\Models\PerformanceDataset;
use App\Services\DatasetException;
use App\Services\SyntheticDatasetLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function index(): View
    {
        $dataset = PerformanceDataset::currentSynthetic();

        return view('performance.index', [
            'dataset' => $dataset,
            'records' => $dataset?->records()->orderBy('published_date')->orderBy('post_id')->get() ?? collect(),
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
