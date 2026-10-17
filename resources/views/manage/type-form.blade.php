@extends('layouts.admin')
@section('title', $type ? 'Edit measurement type' : 'New measurement type')
@section('content')
<header class="page-head">
    <h1>{{ $type ? 'Edit '.$type->name : 'New measurement type' }}</h1>
    <p class="lead">Defines what is measured and how values are shown. Devices can still override the display per device.</p>
</header>
@include('manage._form-errors')
@php($tv = $type ? array_merge($type->only(['unit', 'unit_position', 'unit_space', 'decimals', 'color', 'chart_type', 'y_min', 'y_max']), old()) : old())
<form method="post" action="{{ $type ? route('manage.types.update', $type) : route('manage.types.store') }}" class="form-page" data-type-form hx-disabled-elt="find button[type=submit]">
    @csrf @if($type) @method('PUT') @endif
    <div class="settings-grid">
        <label>Key <input @unless($type) name="key" @endunless required maxlength="40" pattern="[a-z0-9_]+" value="{{ old('key', $type?->key) }}" @if($type) disabled @endif aria-describedby="key-help">
            <small id="key-help">{{ $type ? 'The key cannot be changed.' : 'Lowercase letters, digits and underscore, for example uv_index.' }}</small></label>
        <label>Name <input name="name" required maxlength="80" value="{{ old('name', $type?->name) }}"></label>
    </div>
    <label>Category
        <select name="category" aria-describedby="cat-help">
            @foreach(\App\Support\Categories::ALL as $k => $l)
                <option value="{{ $k }}" @selected(old('category', $type?->category ?? 'weather') === $k)>{{ $l }}</option>
            @endforeach
        </select>
        <small id="cat-help">Types in the same category share a tab on the device dashboard.</small></label>
    <label>Description <input name="description" maxlength="255" value="{{ old('description', $type?->description) }}"></label>

    <fieldset>
        <legend>Display defaults</legend>
        @include('manage._display-fields', ['inherit' => false, 'v' => $tv, 'name' => fn ($f) => $f])
        <p class="preview">Preview: <strong data-preview aria-live="polite"></strong></p>
    </fieldset>

    @if(auth()->user()->isAdmin())
        <input type="hidden" name="is_public" value="0">
        <label><input type="checkbox" name="is_public" value="1" role="switch" @checked(old('is_public', $type?->is_public))> Global: available to every user. Otherwise only you can use it.</label>
    @else
        <p><small>New types are private to you. An administrator can make a type global.</small></p>
    @endif

    <p class="actions-row">
        <button type="submit">{{ $type ? 'Save changes' : 'Create type' }}</button>
        <a role="button" class="secondary outline" href="{{ route('manage.types') }}">Cancel</a>
    </p>
</form>
@endsection
