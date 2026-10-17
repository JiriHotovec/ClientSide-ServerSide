@extends('layouts.app')
@section('title', $type ? 'Edit measurement type' : 'New measurement type')
@section('content')
<header class="page-head">
    <h1>{{ $type ? 'Edit '.$type->name : 'New measurement type' }}</h1>
    <p class="lead">Defines what is measured and how values are shown. Devices can still override the display per device.</p>
</header>
@include('manage._subnav')
<form data-action="save-type" @if($type) data-id="{{ $type->id }}" @endif data-redirect="{{ route('manage.types') }}" class="form-page">
    <div class="settings-grid">
        <label>Key <input name="key" required maxlength="40" pattern="[a-z0-9_]+" value="{{ $type?->key }}" @if($type) readonly @endif aria-describedby="key-help">
            <small id="key-help">{{ $type ? 'The key cannot be changed.' : 'Lowercase letters, digits and underscore, for example uv_index.' }}</small></label>
        <label>Name <input name="name" required maxlength="80" value="{{ $type?->name }}"></label>
    </div>
    <label>Description <input name="description" maxlength="255" value="{{ $type?->description }}"></label>

    <fieldset>
        <legend>Display defaults</legend>
        @include('manage._display-fields', ['inherit' => false, 'v' => $type ? $type->only(['unit', 'unit_position', 'unit_space', 'decimals', 'color', 'chart_type', 'y_min', 'y_max']) : [], 'name' => fn ($f) => $f])
        <p class="preview">Preview: <strong data-preview aria-live="polite"></strong></p>
    </fieldset>

    @if(auth()->user()->isAdmin())
        <label><input type="checkbox" name="is_public" value="1" role="switch" @checked($type?->is_public)> Global: available to every user. Otherwise only you can use it.</label>
    @else
        <p><small>New types are private to you. An administrator can make a type global.</small></p>
    @endif

    <div data-form-errors role="alert"></div>
    <p class="actions-row">
        <button type="submit">{{ $type ? 'Save changes' : 'Create type' }}</button>
        <a role="button" class="secondary outline" href="{{ route('manage.types') }}">Cancel</a>
    </p>
</form>
@endsection
