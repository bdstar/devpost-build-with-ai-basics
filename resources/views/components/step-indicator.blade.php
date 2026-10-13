@props(['current' => null])
@php
    $progress = app(\App\Services\WorkflowProgress::class);
    $steps = $progress->steps();
@endphp
<nav class="steps" aria-label="Workflow progress: {{ $progress->completed() }} of 5 steps complete">
    <ol>
        @foreach ($steps as $step)
            @php
                $label = match ($step['state']) {
                    'complete' => 'Complete',
                    'locked' => 'Locked',
                    default => 'To do',
                };
            @endphp
            <li @class(['step', 'step-'.$step['state'], 'step-here' => $current === $step['number']])
                @if ($current === $step['number']) aria-current="step" @endif
                @if ($step['reason']) title="Locked: {{ $step['reason'] }}" @endif>
                @if ($step['state'] === 'locked')
                    <span class="step-link">
                        <span class="step-num">{{ $step['number'] }}</span>
                        <span class="step-text"><span class="step-title">{{ $step['title'] }}</span><span class="step-state">Locked: {{ $step['reason'] }}</span></span>
                    </span>
                @else
                    <a href="{{ $step['url'] }}" class="step-link">
                        <span class="step-num">{{ $step['state'] === 'complete' ? '✓' : $step['number'] }}</span>
                        <span class="step-text"><span class="step-title">{{ $step['title'] }}</span><span class="step-state">{{ $label }}</span></span>
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
