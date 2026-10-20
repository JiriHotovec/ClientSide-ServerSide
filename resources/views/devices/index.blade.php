@extends('layouts.app')
@section('title', __('Public devices'))
@section('description', __('Stations shared by their owners. Pick one to see its live readings and history.'))
@include('partials.map-assets')
@section('content')
<header class="page-head">
    <h1>{{ __('Public devices') }}</h1>
    <p class="lead">{{ __('Stations shared by their owners. Pick one to see its live readings and history.') }}</p>
</header>
<form method="get" class="filters" role="search" aria-label="{{ __('Search devices') }}"
      hx-get="{{ route('devices.index') }}" hx-trigger="input changed delay:600ms from:find input" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true" hx-sync="this:replace">
    <label>{{ __('Search') }} <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Name or location') }}"></label>
    <button type="button" class="outline map-open" data-overview-open @if($mapDevices->isEmpty()) hidden @endif>{{ __('Open sensors on map') }} <span class="map-count" data-map-count>{{ $mapDevices->count() }}</span></button>
    <noscript><button type="submit">{{ __('Search') }}</button></noscript>
</form>
<dialog class="data-dialog map-dialog overview-dialog" data-overview-map aria-labelledby="overview-map-title">
    <div class="popup-head">
        <h2 id="overview-map-title" tabindex="-1">{{ __('Sensors on the map') }}</h2>
        <form method="dialog"><button class="popup-close">{{ __('Close') }}</button></form>
    </div>
    <div class="map-box" data-map-canvas role="region" aria-label="{{ __('Map of public devices') }}"></div>
</dialog>
<div id="results" hx-boost="true" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true">
<script type="application/json" data-map-data>{!! json_encode($mapDevices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@if($devices->isEmpty())
    <p role="status" class="empty">{{ $search !== '' ? __('No public device matches your search.') : __('No public devices yet.') }} @guest<a href="{{ route('register') }}">{{ __('Create an account') }}</a> {{ __('and share one.') }}@endguest</p>
@else
<ul class="device-grid" role="list">
    @foreach($devices as $device)
        @include('partials.device-card', ['device' => $device, 'snap' => $snaps[$device->id], 'href' => route('devices.show', $device), 'owner' => false])
    @endforeach
</ul>
{{ $devices->links() }}
@endif
</div>
@endsection
