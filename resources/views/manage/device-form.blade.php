@extends('layouts.admin')
@section('title', $device ? __('Edit device') : __('Add a device'))
@section('content')
<header class="page-head">
    <h1>{{ $device ? __('Edit :name', ['name' => $device->name]) : __('Add a device') }}</h1>
    <p class="lead">{{ __('Choose what the device measures and how it delivers data.') }}</p>
</header>
@php($selected = $device?->types->keyBy('id') ?? collect())
@php($pickedIds = collect(old('types', $selected->keys()->all()))->map(fn ($i) => (int) $i))
@include('manage._form-errors')
<form method="post" action="{{ $device ? route('manage.devices.update', $device) : route('manage.devices.store') }}" class="form-page" hx-disabled-elt="find button[type=submit]">
    @csrf @if($device) @method('PUT') @endif
    <input type="hidden" name="_form" value="1">
    <div class="settings-grid">
        <label>{{ __('Name') }} <input name="name" required maxlength="100" value="{{ old('name', $device?->name) }}"></label>
        <label>{{ __('Location') }} <input name="location" maxlength="100" value="{{ old('location', $device?->location) }}"></label>
    </div>
    <div class="location-field" data-location-picker data-search-url="{{ route('geocode.search') }}" data-reverse-url="{{ route('geocode.reverse') }}">
        <input type="hidden" name="latitude" value="{{ old('latitude', $device?->latitude) }}" data-lat>
        <input type="hidden" name="longitude" value="{{ old('longitude', $device?->longitude) }}" data-lng>
        <button type="button" class="outline small" data-map-open>{{ __('Pick on map') }}</button>
        <span class="coords" data-coords aria-live="polite"></span>
        <button type="button" class="outline secondary small" data-map-clear hidden>{{ __('Remove pin') }}</button>
        <dialog class="data-dialog map-dialog" data-map-dialog aria-labelledby="map-dialog-title-{{ $device?->uuid ?? 'new' }}">
            <div class="popup-head">
                <h2 id="map-dialog-title-{{ $device?->uuid ?? 'new' }}" tabindex="-1">{{ __('Pick the location') }}</h2>
                <form method="dialog"><button class="popup-close">{{ __('Close') }}</button></form>
            </div>
            <form class="map-search" data-map-search role="search" aria-label="{{ __('Search for a place') }}">
                <label>{{ __('Search for a place') }} <input type="search" data-map-search-input placeholder="{{ __('Town, address, landmark') }}" autocomplete="off"></label>
                <button type="submit">{{ __('Search') }}</button>
            </form>
            <p class="map-message" data-map-message role="status" aria-live="polite"></p>
            <ul class="map-results" data-map-results></ul>
            <div class="map-box" data-map-canvas role="application" aria-label="{{ __('Map. Click to place the pin.') }}"></div>
            <div class="map-coords">
                <label>{{ __('Latitude') }} <input type="number" step="any" min="-90" max="90" data-dlg-lat></label>
                <label>{{ __('Longitude') }} <input type="number" step="any" min="-180" max="180" data-dlg-lng></label>
                <button type="button" data-map-confirm>{{ __('Use this location') }}</button>
            </div>
            <p><small>{{ __('Click the map, drag the pin or type the coordinates.') }}</small></p>
        </dialog>
    </div>
    <label>{{ __('Description') }} <textarea name="description" maxlength="500" rows="2">{{ old('description', $device?->description) }}</textarea></label>
    <input type="hidden" name="is_public" value="0">
    <label><input type="checkbox" name="is_public" value="1" role="switch" @checked(old('is_public', $device?->is_public))> {{ __('Public: visible to everyone, including visitors without an account') }}</label>

    <fieldset>
        <legend>{{ __('Datasets this device reports') }}</legend>
        <p><small>{{ __('Need a type that is not listed?') }} <a href="{{ route('manage.types.create') }}">{{ __('Create a measurement type') }}</a> {{ __('first.') }}</small></p>
        @forelse($types as $t)
            @php($on = $pickedIds->contains($t->id))
            @php($pv = old("ts.{$t->id}", $selected->has($t->id) ? $selected[$t->id]->pivot->only(\App\Support\DisplaySettings::FIELDS) : []))
            <div class="type-pick">
                <label><input type="checkbox" name="types[]" value="{{ $t->id }}" data-type-toggle @checked($on)> {{ $t->name }} <small>({{ $t->unit }}{{ $t->is_public ? '' : ', '.__('private') }})</small></label>
                <details data-type-settings="{{ $t->id }}">
                    <summary>{{ __('Display settings for :name', ['name' => $t->name]) }}</summary>
                    <p><small>{{ __('Only change what should differ from the type defaults.') }}</small></p>
                    @include('manage._display-fields', ['inherit' => true, 'v' => $pv, 'defaults' => \App\Support\DisplaySettings::for($t), 'name' => fn ($f) => "ts[{$t->id}][{$f}]"])
                </details>
            </div>
        @empty
            <p class="empty">{{ __('No measurement types are available yet.') }} <a href="{{ route('manage.types.create') }}">{{ __('Create one') }}</a>.</p>
        @endforelse
    </fieldset>

    @php($chartRows = collect(old('charts', $device?->charts->map(fn ($c) => ['type' => $c->measurement_type_id, 'kind' => $c->kind, 'title' => $c->title])->all() ?? [])))
    <fieldset class="charts-editor">
        <legend>{{ __('Charts') }}</legend>
        <p><small>{{ __('Arrange the charts of the device dashboard: pick a dataset and a style, add as many as you like. Without any, each dataset gets one default chart.') }}</small></p>
        <ol id="chart-rows" class="chart-rows">
            @foreach($chartRows as $i => $row)
                @include('manage._chart-row', ['key' => $i, 'row' => $row, 'options' => $types])
            @endforeach
        </ol>
        <button type="button" class="outline small" hx-get="{{ route('manage.charts.row') }}" hx-include="[name='types[]']:checked" hx-target="#chart-rows" hx-swap="beforeend">{{ __('Add a chart') }}</button>
    </fieldset>

    <details @if(old('source_url', $device?->source_url)) open @endif>
        <summary>{{ __('Pull data from an external JSON endpoint (optional)') }}</summary>
        <label>{{ __('Source URL') }} <input type="url" name="source_url" maxlength="500" value="{{ old('source_url', $device?->source_url) }}" placeholder="https://api.example.com/current.json"></label>
        <label>{{ __('Mapping (JSON)') }} <textarea name="source_mapping" rows="5" spellcheck="false" aria-describedby="map-help">{{ old('source_mapping', $device?->source_mapping ? json_encode($device->source_mapping, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
            <small id="map-help">{{ __('Example:') }} {"timestamp":"current.time","values":{"temperature":"current.temperature_2m"}}. {{ __('Optional "items_path" for lists.') }}</small></label>
        <label>{{ __('Fetch interval (minutes)') }} <input type="number" name="fetch_interval_min" min="1" max="1440" value="{{ old('fetch_interval_min', $device?->fetch_interval_min ?? 15) }}"></label>
    </details>

    <p class="actions-row">
        <button type="submit">{{ $device ? __('Save changes') : __('Create device') }}</button>
        <a role="button" class="secondary outline" href="{{ route('manage.devices') }}">{{ __('Cancel') }}</a>
    </p>
</form>
@endsection
