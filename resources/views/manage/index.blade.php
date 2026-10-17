@extends('layouts.app')
@section('title', auth()->user()->isAdmin() ? 'Administration' : 'My devices')
@section('content')
<h1>{{ auth()->user()->isAdmin() ? 'Administration – all devices' : 'My devices' }}</h1>
<div data-manage>
    <div role="status" aria-live="polite" data-manage-status class="status"></div>

    <section aria-labelledby="list-h">
        <h2 id="list-h">Devices</h2>
        <div id="device-list" hx-get="{{ route('manage.list') }}" hx-trigger="load, refresh">
            <p aria-busy="true">Loading devices…</p>
        </div>
    </section>

    <section aria-labelledby="form-h">
        <div id="device-form" hx-get="{{ route('manage.form') }}" hx-trigger="load, reset-form">
            <p aria-busy="true">Loading form…</p>
        </div>
    </section>

    <section aria-labelledby="types-h">
        <h2 id="types-h">Create a new measurement type</h2>
        <p>Pick existing types in the device form, or add a missing one here (e.g. "UV index").</p>
        <form data-action="create-type" class="inline-grid">
            <label>Key <input name="key" required maxlength="40" pattern="[a-z0-9_]+" placeholder="uv_index" aria-describedby="key-help"><small id="key-help">lowercase letters, digits, underscore</small></label>
            <label>Name <input name="name" required maxlength="80" placeholder="UV index"></label>
            <label>Unit <input name="unit" required maxlength="20" placeholder="UVI"></label>
            <button type="submit">Add type</button>
        </form>
    </section>
</div>
@endsection
