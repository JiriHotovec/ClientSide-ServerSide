<li>
    <a class="device-card" href="{{ $href }}" hx-boost="false">
        <span class="dc-title">{{ $device->name }}</span>
        <span class="dc-loc">{{ $device->location ?: __('Location not specified') }}@if(!empty($owner)), {{ $device->owner->name }}@endif</span>
        @if(auth()->check())<span><span class="badge {{ $device->is_public ? '' : 'badge-private' }}">{{ $device->is_public ? __('Public') : __('Private') }}</span></span>@endif
        @if($snap && $snap['latest'] !== null)
            <span class="dc-value">{{ $snap['formatted'] }}</span>
            <span class="dc-type">{{ $snap['type']->name }}, {{ $snap['at']->diffForHumans() }}</span>
            <svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true"><polyline points="{{ $snap['points'] }}" fill="none" stroke="currentColor" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round"/></svg>
        @else
            <span class="dc-type">{{ __('No recent readings') }}</span>
        @endif
        <span class="dc-types">{{ $device->types->pluck('name')->join(', ') ?: __('No datasets configured') }}</span>
        <span class="sr-only">{{ $linkLabel ?? __('Open :name', ['name' => $device->name]) }}</span>
    </a>
</li>
