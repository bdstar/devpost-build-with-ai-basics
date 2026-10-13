@props(['item'])
@if ($item->isReadyToPublish())
    <span {{ $attributes->merge(['class' => 'pill pill-ready']) }}>Ready to Publish — Manual Action Required</span>
@else
    <span {{ $attributes->merge(['class' => 'pill pill-'.$item->status]) }}>{{ $item->statusLabel() }}</span>
@endif
