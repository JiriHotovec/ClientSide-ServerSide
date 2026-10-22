@extends('layouts.admin')
@section('title', $type ? __('Edit measurement type') : __('New measurement type'))
@section('content')
<header class="page-head">
    <h1>{{ $type ? __('Edit :name', ['name' => $type->getRawOriginal('name')]) : __('New measurement type') }}</h1>
    <p class="lead">{{ __('Defines what is measured and how values are shown. Devices can still override the display per device.') }}</p>
</header>
@include('manage._form-errors')
@php($tv = $type ? array_merge($type->only(['unit', 'unit_position', 'unit_space', 'decimals', 'color', 'chart_type', 'y_min', 'y_max']), old()) : old())
<form method="post" action="{{ $type ? route('manage.types.update', $type) : route('manage.types.store') }}" class="form-page" data-type-form hx-disabled-elt="find button[type=submit]">
    @csrf @if($type) @method('PUT') @endif
    <div class="settings-grid">
        <label>{{ __('Key') }} <input @unless($type) name="key" @endunless required maxlength="40" pattern="[a-z0-9_]+" value="{{ old('key', $type?->key) }}" @if($type) disabled @endif aria-describedby="key-help @error('key') err-key @enderror" @error('key') aria-invalid="true" @enderror>
            <small id="key-help">{{ $type ? __('The key cannot be changed.') : __('Lowercase letters, digits and underscore, for example uv_index.') }}</small>
            @include('manage._field-error', ['field' => 'key', 'id' => 'err-key'])</label>
        <label>{{ __('Name') }} <input name="name" required maxlength="80" value="{{ old('name', $type?->getRawOriginal('name')) }}" @error('name') aria-invalid="true" aria-describedby="err-name" @enderror>
            @include('manage._field-error', ['field' => 'name', 'id' => 'err-name'])</label>
    </div>
    <label>{{ __('Category') }}
        <select name="category" aria-describedby="cat-help">
            @foreach(\App\Support\Categories::ALL as $k => $l)
                <option value="{{ $k }}" @selected(old('category', $type?->category ?? 'weather') === $k)>{{ __($l) }}</option>
            @endforeach
        </select>
        <small id="cat-help">{{ __('Types in the same category are shown together on the device dashboard.') }}</small></label>
    <label>{{ __('Description') }} <input name="description" maxlength="255" value="{{ old('description', $type?->description) }}" @error('description') aria-invalid="true" aria-describedby="err-description" @enderror>
        @include('manage._field-error', ['field' => 'description', 'id' => 'err-description'])</label>

    <fieldset>
        <legend>{{ __('Display defaults') }}</legend>
        @include('manage._display-fields', ['inherit' => false, 'v' => $tv, 'name' => fn ($f) => $f])
        <p class="preview">{{ __('Preview:') }} <strong data-preview aria-live="polite"></strong></p>
    </fieldset>

    @if(auth()->user()->isAdmin())
        <input type="hidden" name="is_public" value="0">
        <label><input type="checkbox" name="is_public" value="1" role="switch" @checked(old('is_public', $type?->is_public))> {{ __('Global: available to every user. Otherwise only you can use it.') }}</label>
    @else
        <p><small>{{ __('New types are private to you. An administrator can make a type global.') }}</small></p>
    @endif

    <p class="actions-row">
        <button type="submit">{{ $type ? __('Save changes') : __('Create type') }}</button>
        <a class="btn secondary outline" href="{{ route('manage.types') }}">{{ __('Cancel') }}</a>
    </p>
</form>
@endsection
