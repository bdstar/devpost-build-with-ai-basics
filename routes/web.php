<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile/analyse', [ProfileController::class, 'analyse'])->name('profile.analyse');
    Route::post('/profile/manual', [ProfileController::class, 'manual'])->name('profile.manual');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/confirm', [ProfileController::class, 'confirm'])->name('profile.confirm');

    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::post('/performance/load', [PerformanceController::class, 'load'])->name('performance.load');

    Route::get('/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::post('/knowledge/build', [KnowledgeController::class, 'build'])->name('knowledge.build');
    Route::get('/knowledge/{record}', [KnowledgeController::class, 'show'])->name('knowledge.show');
});
