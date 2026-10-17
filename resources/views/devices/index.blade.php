@extends('layouts.app')
@section('title', 'Public devices')
@section('content')
<header class="page-head">
    <h1>Public devices</h1>
    <p class="lead">Stations shared by their owners. Pick one to see its live readings and history.</p>
</header>
@if($devices->isEmpty())
    <p role="status" class="empty">No public devices yet. Be the first: <a href="{{ route('register') }}">create an account</a> and share a device.</p>
@else
<ul class="device-grid" role="list">
    @foreach($devices as $device)
        @php($snap = $snaps[$device->id])
        <li>
            <a class="device-card" href="{{ route('devices.show', $device) }}">
                <span class="dc-title">{{ $device->name }}</span>
                <span class="dc-loc">{{ $device->location ?: 'Location not specified' }}</span>
                @if($snap && $snap['latest'] !== null)
                    <span class="dc-value">{{ rtrim(rtrim(number_format($snap['latest'], 1, '.', ''), '0'), '.') }}<span class="dc-unit">{{ $snap['type']->unit }}</span></span>
                    <span class="dc-type">{{ $snap['type']->name }}, {{ $snap['at']->diffForHumans() }}</span>
                    <svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true"><polyline points="{{ $snap['points'] }}" fill="none" stroke="currentColor" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round"/></svg>
                @else
                    <span class="dc-type">No recent readings</span>
                @endif
                <span class="dc-types">{{ $device->types->pluck('name')->join(', ') ?: 'No datasets configured' }}</span>
                <span class="sr-only">Open dashboard of {{ $device->name }}</span>
            </a>
        </li>
    @endforeach
</ul>
@endif
@endsection
