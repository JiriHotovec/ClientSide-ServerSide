@extends('layouts.app')
@section('title', 'Public devices')
@section('content')
<header class="page-head">
    <h1>Public devices</h1>
    <p class="lead">Stations shared by their owners. Pick one to see its live readings and history.</p>
</header>
<form method="get" class="filters" role="search" aria-label="Search devices"
      hx-get="{{ route('devices.index') }}" hx-trigger="input changed delay:600ms from:find input" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true" hx-sync="this:replace">
    <label>Search <input type="search" name="q" value="{{ $search }}" placeholder="Name or location"></label>
    <noscript><button type="submit">Search</button></noscript>
</form>
<div id="results" hx-boost="true" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true">
@if($devices->isEmpty())
    <p role="status" class="empty">{{ $search !== '' ? 'No public device matches your search.' : 'No public devices yet.' }} @guest<a href="{{ route('register') }}">Create an account</a> and share one.@endguest</p>
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
