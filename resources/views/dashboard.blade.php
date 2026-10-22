@extends('layouts.app')
@section('title', __('My dashboard'))
@include('partials.map-assets')
@section('noindex', '1')
@section('content')
<header class="page-head">
    <h1>{{ __('My dashboard') }}</h1>
    <p class="lead">{{ __('A closer look at your devices and the world around them.') }}</p>
</header>
@if($devices->isEmpty())
    <p role="status">{{ __('You have no devices yet.') }} <a href="{{ route('manage.devices') }}">{{ __('Add your first device') }}</a>.</p>
@else
    <label for="device-switch" class="dashboard-switch">{{ __('Device') }}
        <select id="device-switch" name="device"
                hx-get="{{ url('/dashboard/devices') }}" hx-trigger="change" hx-target="#panel" hx-swap="innerHTML"
                data-base="{{ url('/dashboard/devices') }}">
            @foreach($devices as $d)<option value="{{ $d->uuid }}">{{ $d->name }}{{ $d->is_public ? '' : ' ('.__('private').')' }}</option>@endforeach
        </select>
    </label>
    <div id="panel">
        @include('partials.device-panel', ['device' => $selected, 'endpoint' => url("/api/devices/{$selected->uuid}/measurements")])
    </div>
@endif
@endsection
