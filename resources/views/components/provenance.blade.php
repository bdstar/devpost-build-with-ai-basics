@props(['type', 'source' => null, 'href' => null])
@php
    $labels = [
        'verified' => 'Verified public',
        'assumption' => 'AI assumption',
        'manual' => 'Manual entry',
        'edited' => 'Edited manually',
        'synthetic' => 'SYNTHETIC',
        'real' => 'Real metric',
        'insight' => 'AI insight',
        'simulated' => 'Simulated Insight',
        'fixture' => 'Demo fixture',
        'live' => 'Live AI',
    ];
    $label = $labels[$type] ?? $type;
@endphp
<span {{ $attributes->merge(['class' => "prov prov-{$type}"]) }}>
    @if ($href)
        <a href="{{ $href }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>
    @else
        {{ $label }}
    @endif
    @if ($source)
        <span class="prov-source">· {{ $source }}</span>
    @endif
</span>
