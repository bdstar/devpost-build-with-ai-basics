@props(['title' => null])
@php
    $aiMode = \App\Services\Ai\AiMode::current();
    $syntheticLoaded = \App\Models\PerformanceDataset::where('is_synthetic', true)->exists();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'GenAI Ratel CMS' }} · GenAI Ratel CMS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <span class="brand-name">GenAI Ratel CMS</span>
            <span class="brand-sub">Aquarium Park Dhaka · PoC</span>
        </div>
        @php
            $nav = [
                ['route' => 'dashboard', 'label' => 'Dashboard', 'match' => 'dashboard'],
                ['route' => 'profile.show', 'label' => 'Business Profile', 'match' => 'profile.*'],
                ['route' => 'workflow.index', 'label' => 'Content Workflow', 'match' => ['workflow.*', 'content.*']],
                ['route' => 'performance.index', 'label' => 'Performance & Insights', 'match' => 'performance.*'],
                ['route' => 'knowledge.index', 'label' => 'Knowledge Base', 'match' => 'knowledge.*'],
            ];
        @endphp
        <nav>
            @foreach ($nav as $item)
                @if (Route::has($item['route']))
                    <a href="{{ route($item['route']) }}" @class(['nav-item', 'active' => request()->routeIs(...(array) $item['match'])])>{{ $item['label'] }}</a>
                @else
                    <span class="nav-item disabled" title="Not built yet">{{ $item['label'] }}</span>
                @endif
            @endforeach
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="topbar-title">{{ $title ?? '' }}</div>
            <div class="topbar-right">
                @if ($aiMode === 'live')
                    <span class="badge badge-live" title="AI output comes from the Gemini API">Live AI · {{ config('ai.gemini.text_model') }}</span>
                    <form method="POST" action="{{ route('ai-mode.fixtures') }}">
                        @csrf
                        <button type="submit" class="btn btn-link">Switch to Demo Fixtures</button>
                    </form>
                @else
                    <span class="badge badge-fixture" title="AI output comes from labelled demo fixtures; no API calls">Demo Fixtures</span>
                    @if (\App\Services\Ai\AiMode::isOverridden())
                        <form method="POST" action="{{ route('ai-mode.live') }}">
                            @csrf
                            <button type="submit" class="btn btn-link">Switch back to Live AI</button>
                        </form>
                    @endif
                @endif
                @if ($syntheticLoaded)
                    <span class="badge badge-synthetic" title="Performance metrics are synthetic, not real Aquarium Park analytics">Synthetic Demo Data</span>
                @endif
                <span class="user">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-link">Log out</button>
                </form>
            </div>
        </header>

        <main class="content">
            @if (session('status'))
                <div class="flash flash-ok" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="flash flash-bad" role="alert">
                    {{ session('error') }}
                    @if (session('problems'))
                        <ul>
                            @foreach (session('problems') as $problem)
                                <li>{{ $problem }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if (session('ai_failed') && $aiMode === 'live')
                        <form method="POST" action="{{ route('ai-mode.fixtures') }}" class="flash-action">
                            @csrf
                            <button type="submit" class="btn">Switch to Demo Fixtures</button>
                            <span class="small">Continues with labelled demo outputs for this session. Nothing is replaced automatically.</span>
                        </form>
                    @endif
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
