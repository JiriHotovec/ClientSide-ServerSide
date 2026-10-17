@extends('layouts.admin')
@section('title', __('Devices and data sources'))
@section('content')
<header class="page-head head-row">
    <div>
        <h1>{{ __('Devices and data sources') }}</h1>
        <p class="lead">{{ __('Visibility, how each device gets its data, and whether the last update worked.') }}</p>
    </div>
</header>
@include('manage._device-filters', ['withSource' => true])
<div id="results" hx-boost="true" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true">
@if($devices->isEmpty())
    <p class="empty" role="status">{{ __('No devices match.') }} {{ __('Change the filters.') }}</p>
@else
<div class="table-wrap">
<table>
    <caption class="sr-only">{{ __('Devices you can manage') }}</caption>
    <thead><tr>
        <th scope="col">{{ __('Device') }}</th>
        @if($scope === 'all')<th scope="col">{{ __('Owner') }}</th>@endif
        <th scope="col">{{ __('Datasets') }}</th><th scope="col">{{ __('Visibility') }}</th><th scope="col">{{ __('Data source') }}</th><th scope="col">{{ __('Actions') }}</th>
    </tr></thead>
    <tbody>
    @foreach($devices as $device)
        <tr>
            <th scope="row">{{ $device->name }}<br><small>{{ $device->location }}</small></th>
            @if($scope === 'all')<td>{{ $device->owner->name }}</td>@endif
            <td>{{ $device->types->pluck('name')->join(', ') ?: __('None') }}</td>
            <td>
                <span class="badge {{ $device->is_public ? '' : 'badge-private' }}">{{ $device->is_public ? __('Public') : __('Private') }}</span>
                <form method="post" action="{{ route('manage.devices.visibility', $device) }}" class="inline-form">@csrf @method('PATCH')
                    <input type="hidden" name="is_public" value="{{ $device->is_public ? 0 : 1 }}">
                    <button type="submit" class="outline secondary small">{{ $device->is_public ? __('Make private') : __('Make public') }}<span class="sr-only"> {{ $device->name }}</span></button>
                </form>
            </td>
            <td>
                @if($device->last_error)<span class="error-text">{{ __('Last update failed: :error', ['error' => $device->lastErrorText()]) }}</span>
                @elseif($device->source_url){{ __('Pulled every :min min', ['min' => $device->fetch_interval_min]) }}{{ $device->last_fetched_at ? ', '.__('last :time', ['time' => $device->last_fetched_at->diffForHumans()]) : '' }}
                @else {{ __('Receives pushed readings') }} @endif
            </td>
            <td class="row-actions">
                <a role="button" class="outline small" href="{{ route('manage.devices.edit', $device) }}" hx-boost="false">{{ __('Edit') }}<span class="sr-only"> {{ $device->name }}</span></a>
                <form method="post" action="{{ route('manage.devices.token', $device) }}" class="inline-form" hx-confirm="{{ __('Generate a new ingest token? The previous token stops working.') }}">@csrf
                    <button type="submit" class="outline small">{{ __('New token') }}<span class="sr-only"> {{ __('for :name', ['name' => $device->name]) }}</span></button>
                </form>
                <form method="post" action="{{ route('manage.devices.destroy', $device) }}" class="inline-form" hx-confirm="{{ __('Delete device :name and all its measurements? This cannot be undone.', ['name' => $device->name]) }}">@csrf @method('DELETE')
                    <button type="submit" class="outline contrast small">{{ __('Delete') }}<span class="sr-only"> {{ $device->name }}</span></button>
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
