@extends('layouts.admin')
@section('title', 'Devices and data sources')
@section('content')
<header class="page-head head-row">
    <div>
        <h1>Devices and data sources</h1>
        <p class="lead">Visibility, how each device gets its data, and whether the last update worked.</p>
    </div>
    <a role="button" href="{{ route('manage.devices.create') }}">Add a device</a>
</header>
@include('manage._device-filters', ['withSource' => true])
<div id="results" hx-boost="true" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true">
@if($devices->isEmpty())
    <p class="empty" role="status">No devices match. <a href="{{ route('manage.devices.create') }}">Add a device</a> or change the filters.</p>
@else
<div class="table-wrap">
<table>
    <caption class="sr-only">Devices you can manage</caption>
    <thead><tr>
        <th scope="col">Device</th>
        @if($scope === 'all')<th scope="col">Owner</th>@endif
        <th scope="col">Datasets</th><th scope="col">Visibility</th><th scope="col">Data source</th><th scope="col">Actions</th>
    </tr></thead>
    <tbody>
    @foreach($devices as $device)
        <tr>
            <th scope="row">{{ $device->name }}<br><small>{{ $device->location }}</small></th>
            @if($scope === 'all')<td>{{ $device->owner->name }}</td>@endif
            <td>{{ $device->types->pluck('name')->join(', ') ?: 'None' }}</td>
            <td>
                <span class="badge {{ $device->is_public ? '' : 'badge-private' }}">{{ $device->is_public ? 'Public' : 'Private' }}</span>
                <form method="post" action="{{ route('manage.devices.visibility', $device) }}" class="inline-form">@csrf @method('PATCH')
                    <input type="hidden" name="is_public" value="{{ $device->is_public ? 0 : 1 }}">
                    <button type="submit" class="outline secondary small">Make {{ $device->is_public ? 'private' : 'public' }}<span class="sr-only"> {{ $device->name }}</span></button>
                </form>
            </td>
            <td>
                @if($device->last_error)<span class="error-text">Last update failed: {{ $device->last_error }}</span>
                @elseif($device->source_url)Pulled every {{ $device->fetch_interval_min }} min{{ $device->last_fetched_at ? ', last '.$device->last_fetched_at->diffForHumans() : '' }}
                @else Receives pushed readings @endif
            </td>
            <td class="row-actions">
                <a role="button" class="outline small" href="{{ route('manage.devices.edit', $device) }}" hx-boost="false">Edit<span class="sr-only"> {{ $device->name }}</span></a>
                <form method="post" action="{{ route('manage.devices.token', $device) }}" class="inline-form" hx-confirm="Generate a new ingest token? The previous token stops working.">@csrf
                    <button type="submit" class="outline small">New token<span class="sr-only"> for {{ $device->name }}</span></button>
                </form>
                <form method="post" action="{{ route('manage.devices.destroy', $device) }}" class="inline-form" hx-confirm="Delete device {{ $device->name }} and all its measurements? This cannot be undone.">@csrf @method('DELETE')
                    <button type="submit" class="outline contrast small">Delete<span class="sr-only"> {{ $device->name }}</span></button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
{{ $devices->links() }}
@endif
</div>
@endsection
