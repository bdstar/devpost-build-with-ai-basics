@props(['item'])
@if ($item->isReadyToPublish())
    <span {{ $attributes->merge(['class' => 'pill pill-ready']) }}>Ready to Publish — Manual Action Required</span>
@elseif ($item->status === 'published' && $item->publish_method === 'simulated')
    <span {{ $attributes->merge(['class' => 'pill pill-simulated']) }}>Published · Simulated (demo)</span>
@elseif ($item->status === 'published')
    <span {{ $attributes->merge(['class' => 'pill pill-published']) }}>Published · Manually confirmed</span>
@else
    <span {{ $attributes->merge(['class' => 'pill pill-'.$item->status]) }}>{{ $item->statusLabel() }}</span>
@endif
