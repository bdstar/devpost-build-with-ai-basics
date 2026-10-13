<?php

use App\Http\Controllers\AiModeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ContentItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IdeasController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResetController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/ai-mode/fixtures', [AiModeController::class, 'fixtures'])->name('ai-mode.fixtures');
    Route::post('/ai-mode/live', [AiModeController::class, 'live'])->name('ai-mode.live');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile/analyse', [ProfileController::class, 'analyse'])->name('profile.analyse');
    Route::post('/profile/manual', [ProfileController::class, 'manual'])->name('profile.manual');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/confirm', [ProfileController::class, 'confirm'])->name('profile.confirm');

    Route::get('/workflow', [IdeasController::class, 'index'])->name('workflow.index');
    Route::post('/workflow/round-one', [IdeasController::class, 'generateRoundOne'])->name('workflow.round-one');
    Route::post('/workflow/round-two', [IdeasController::class, 'generateRoundTwo'])->name('workflow.round-two');
    Route::post('/workflow/rounds/{round}/selection', [IdeasController::class, 'select'])->name('workflow.select');

    Route::get('/content', [ContentItemController::class, 'index'])->name('content.index');
    Route::post('/content/generate', [ContentItemController::class, 'generate'])->name('content.generate');
    Route::get('/content/{item}', [ContentItemController::class, 'show'])->name('content.show');
    Route::put('/content/{item}', [ContentItemController::class, 'update'])->name('content.update');
    Route::post('/content/{item}/regenerate', [ContentItemController::class, 'regenerate'])->name('content.regenerate');
    Route::post('/content/{item}/{action}', [ContentItemController::class, 'transition'])
        ->whereIn('action', ['submit', 'approve', 'request-changes', 'reject', 'reopen', 'schedule', 'unschedule', 'simulate-publish', 'mark-published'])
        ->name('content.transition');

    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::post('/performance/load', [PerformanceController::class, 'load'])->name('performance.load');

    Route::get('/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::post('/knowledge/build', [KnowledgeController::class, 'build'])->name('knowledge.build');
    Route::get('/knowledge/{record}', [KnowledgeController::class, 'show'])->name('knowledge.show');

    Route::post('/reset', [ResetController::class, 'reset'])->name('data.reset');
});
