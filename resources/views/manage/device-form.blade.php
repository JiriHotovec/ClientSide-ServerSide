@extends('layouts.admin')
@section('title', $device ? __('Edit device') : __('Add a device'))
@section('content')
<header class="page-head">
    <h1>{{ $device ? __('Edit :name', ['name' => $device->name]) : __('Add a device') }}</h1>
    <p class="lead">{{ __('Choose what the device measures and how it delivers data.') }}</p>
</header>
@php($selected = $device?->types->keyBy('id') ?? collect())
@php($pickedIds = collect(old('types', $selected->keys()->all()))->map(fn ($i) => (int) $i))
@php($chartRows = collect(old('charts', $device?->charts->map(fn ($c) => ['type' => $c->measurement_type_id, 'kind' => $c->kind, 'title' => $c->title])->all() ?? [])))
@php($tabErrors = \App\Support\DeviceFormTabs::errorCounts($errors->getBag('default')))
@include('manage.device._errors')
<form method="post" action="{{ $device ? route('manage.devices.update', $device) : route('manage.devices.store') }}" class="form-page device-form" data-device-form data-error-tab="{{ array_key_first($tabErrors) }}" hx-disabled-elt="find button[type=submit]">
    @csrf @if($device) @method('PUT') @endif
    <input type="hidden" name="_form" value="1">

    <div class="form-tabs" role="tablist" aria-label="{{ __('Sections of the device form') }}" data-form-tabs>
        @foreach(\App\Support\DeviceFormTabs::TABS as $key => $label)
            <button type="button" role="tab" class="form-tab" id="tab-{{ $key }}" data-tab="{{ $key }}" aria-controls="panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}">
                {{ __($label) }}
                @if($key === 'datasets')<span class="tab-count" data-count="datasets">{{ $pickedIds->count() ?: '' }}</span>@endif
                @if($key === 'charts')<span class="tab-count" data-count="charts">{{ $chartRows->count() ?: '' }}</span>@endif
                @if(! empty($tabErrors[$key]))<span class="tab-error" title="{{ __('This section has errors') }}">!<span class="sr-only"> {{ __('This section has errors') }}</span></span>@endif
            </button>
        @endforeach
    </div>

    @foreach(\App\Support\DeviceFormTabs::TABS as $key => $label)
        <section class="form-section" id="panel-{{ $key }}" data-panel="{{ $key }}" role="tabpanel" aria-labelledby="tab-{{ $key }}" tabindex="0">
            <h2>{{ __($label) }}</h2>
            @include('manage.device._'.($key === 'source' ? 'source' : $key))
        </section>
    @endforeach

    <div class="form-bar">
        <button type="submit">{{ $device ? __('Save changes') : __('Create device') }}</button>
        <a role="button" class="secondary outline" href="{{ route('manage.devices') }}">{{ __('Cancel') }}</a>
    </div>
</form>
{{-- Outside the device form: HTML does not allow nested forms (the dialog has its own search and close forms). --}}
<dialog class="data-dialog map-dialog" hx-boost="false" id="map-dialog-{{ $device?->uuid ?? 'new' }}" data-map-dialog aria-labelledby="map-dialog-title-{{ $device?->uuid ?? 'new' }}">
    <div class="popup-head">
        <h2 id="map-dialog-title-{{ $device?->uuid ?? 'new' }}" tabindex="-1">{{ __('Pick the location') }}</h2>
        <form method="dialog"><button class="popup-close">{{ __('Close') }}</button></form>
    </div>
    <form class="map-search" data-map-search role="search" aria-label="{{ __('Search for a place') }}">
        <div class="map-search-field">
            <label>{{ __('Search for a place') }} <input type="search" data-map-search-input placeholder="{{ __('Town, address, landmark') }}" autocomplete="off" aria-expanded="false"></label>
            {{-- Rolls out right under the field and overlays the map, so nothing below it moves. --}}
            <ul class="map-results" data-map-results></ul>
        </div>
        <button type="submit">{{ __('Search') }}</button>
    </form>
    <p class="map-message" data-map-message role="status" aria-live="polite"></p>
            <div class="map-box" data-map-canvas role="application" aria-label="{{ __('Map. Click to place the pin.') }}"></div>
    <div class="map-coords">
        <label>{{ __('Latitude') }} <input type="number" step="any" min="-90" max="90" data-dlg-lat></label>
        <label>{{ __('Longitude') }} <input type="number" step="any" min="-180" max="180" data-dlg-lng></label>
        <button type="button" data-map-confirm>{{ __('Use this location') }}</button>
    </div>
    <p><small>{{ __('Click the map, drag the pin or type the coordinates.') }}</small></p>
</dialog>
@endsection
