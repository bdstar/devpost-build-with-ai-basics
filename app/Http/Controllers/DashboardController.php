<?php

namespace App\Http\Controllers;

use App\Models\PerformanceDataset;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'dataset' => PerformanceDataset::currentSynthetic(),
        ]);
    }
}
