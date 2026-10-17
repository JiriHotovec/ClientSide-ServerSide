@extends('layouts.app')
@section('title', $device ? 'Edit device' : 'Add a device')
@section('content')
<header class="page-head">
    <h1>{{ $device ? 'Edit '.$device->name : 'Add a device' }}</h1>
    <p class="lead">Choose what the device measures and how it delivers data.</p>
</header>
@include('manage._subnav')
@php($selected = $device?->types->keyBy('id') ?? collect())
<form data-action="save-device" @if($device) data-id="{{ $device->id }}" @endif data-redirect="{{ route('manage.devices') }}" class="form-page">
    <div class="settings-grid">
        <label>Name <input name="name" required maxlength="100" value="{{ $device?->name }}"></label>
        <label>Location <input name="location" maxlength="100" value="{{ $device?->location }}"></label>
    </div>
    <label>Description <textarea name="description" maxlength="500" rows="2">{{ $device?->description }}</textarea></label>
    <label><input type="checkbox" name="is_public" value="1" role="switch" @checked($device?->is_public)> Public: visible to everyone, including visitors without an account</label>

    <fieldset>
        <legend>Datasets this device reports</legend>
        <p><small>Need a type that is not listed? <a href="{{ route('manage.types.create') }}">Create a measurement type</a> first.</small></p>
        @forelse($types as $t)
            @php($on = $selected->has($t->id))
            @php($pv = $on ? $selected[$t->id]->pivot->only(\App\Support\DisplaySettings::FIELDS) : [])
            <div class="type-pick">
                <label><input type="checkbox" name="types[]" value="{{ $t->id }}" data-type-toggle @checked($on)> {{ $t->name }} <small>({{ $t->unit }}{{ $t->is_public ? '' : ', private' }})</small></label>
                <details data-type-settings="{{ $t->id }}">
                    <summary>Display settings for {{ $t->name }}</summary>
                    <p><small>Only change what should differ from the type defaults.</small></p>
                    @include('manage._display-fields', ['inherit' => true, 'v' => $pv, 'defaults' => \App\Support\DisplaySettings::for($t), 'name' => fn ($f) => "ts[{$t->id}][{$f}]"])
                </details>
            </div>
        @empty
            <p class="empty">No measurement types are available yet. <a href="{{ route('manage.types.create') }}">Create one</a>.</p>
        @endforelse
    </fieldset>

    <details @if($device?->source_url) open @endif>
        <summary>Pull data from an external JSON endpoint (optional)</summary>
        <label>Source URL <input type="url" name="source_url" maxlength="500" value="{{ $device?->source_url }}" placeholder="https://api.example.com/current.json"></label>
        <label>Mapping (JSON) <textarea name="source_mapping" rows="5" spellcheck="false" aria-describedby="map-help">{{ $device?->source_mapping ? json_encode($device->source_mapping, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '' }}</textarea>
            <small id="map-help">Example: {"timestamp":"current.time","values":{"temperature":"current.temperature_2m"}}. Optional "items_path" for lists.</small></label>
        <label>Fetch interval (minutes) <input type="number" name="fetch_interval_min" min="1" max="1440" value="{{ $device?->fetch_interval_min ?? 15 }}"></label>
    </details>

    <div data-form-errors role="alert"></div>
    <p class="actions-row">
        <button type="submit">{{ $device ? 'Save changes' : 'Create device' }}</button>
        <a role="button" class="secondary outline" href="{{ route('manage.devices') }}">Cancel</a>
    </p>
</form>
@endsection
