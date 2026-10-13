<?php

namespace App\Providers;

use App\Services\Ai\AiMode;
use App\Services\Ai\AiProvider;
use App\Services\Ai\FixtureProvider;
use App\Services\Ai\GeminiProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Resolved per use, so a session switch to Demo Fixtures takes effect immediately. Never falls back silently.
        $this->app->bind(AiProvider::class, fn () => AiMode::current() === 'live'
            ? new GeminiProvider(config('ai.gemini'))
            : new FixtureProvider);
    }

    public function boot(): void
    {
        // Stored in UTC; shown in the display timezone (Asia/Dhaka by default).
        Carbon::macro('toDisplay', function (string $format = 'j M Y, g:i A') {
            /** @var Carbon $this */
            return $this->copy()->setTimezone(config('app.display_timezone'))->format($format).' ('.config('app.display_timezone').')';
        });

        // Lets views use <x-layouts::app> while the layout stays in resources/views/layouts.
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');
    }
}
