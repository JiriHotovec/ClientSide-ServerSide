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
