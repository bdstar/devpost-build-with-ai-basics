@props(['level', 'n', 'cv' => null])
@php
    $labels = ['insufficient' => 'Insufficient Evidence', 'low' => 'Low Confidence', 'medium' => 'Medium Confidence'];
    $text = ($labels[$level] ?? $level).' · n='.$n;
    if ($level !== 'insufficient') {
        $text .= ' · CV='.($cv === null ? 'N/A' : number_format($cv, 2));
    }
@endphp
<span {{ $attributes->merge(['class' => "chip chip-{$level}"]) }}>{{ $text }}</span>
