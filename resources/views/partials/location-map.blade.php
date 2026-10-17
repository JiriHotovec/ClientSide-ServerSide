{{-- Map of a device: one more group of the dashboard (hidden until its tab is chosen) or the only content. --}}
<div class="group-panel location-panel" data-group="location" @if($hidden) hidden @endif>
    <div class="map-box" data-static-map data-lat="{{ $device->latitude }}" data-lng="{{ $device->longitude }}" data-name="{{ $device->name }}" role="region" aria-label="{{ __('Map showing :name', ['name' => $device->name]) }}"></div>
    <p class="location-meta">
        @if($device->location){{ $device->location }}<span aria-hidden="true"> · </span>@endif<span class="coords">{{ number_format($device->latitude, 5, '.', '') }}, {{ number_format($device->longitude, 5, '.', '') }}</span>
        <span aria-hidden="true"> · </span><a href="https://www.openstreetmap.org/?mlat={{ $device->latitude }}&amp;mlon={{ $device->longitude }}#map=14/{{ $device->latitude }}/{{ $device->longitude }}" target="_blank" rel="noopener">{{ __('Open in OpenStreetMap') }}<span class="sr-only"> {{ __('(opens in a new tab)') }}</span></a>
    </p>
</div>
