@extends('layouts.admin')
@section('title', __('Admin panel'))
@section('content')
<header class="page-head head-row">
    <div>
        <h1>{{ auth()->user()->isAdmin() ? __('Admin panel') : __('My panel') }}</h1>
        <p class="lead">{{ $scope === 'all' ? __('All devices in MeteoView.') : __('Your devices and their latest readings.') }}</p>
    </div>
    <a role="button" href="{{ route('manage.devices.create') }}">{{ __('Add a device') }}</a>
</header>
@include('manage._device-filters')
<div id="results" hx-boost="true" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true">
@if($devices->isEmpty())
    <p class="empty" role="status">{{ __('No devices match.') }} <a href="{{ route('manage.devices.create') }}">{{ __('Add a device') }}</a> {{ __('or change the filters.') }}</p>
@else
    <ul class="device-grid" role="list">
        @foreach($devices as $device)
            @include('partials.device-card', ['device' => $device, 'snap' => $snaps[$device->id], 'href' => route('manage.devices.edit', $device), 'owner' => $scope === 'all'])
        @endforeach
    </ul>
    {{ $devices->links() }}
@endif
</div>
@endsection
