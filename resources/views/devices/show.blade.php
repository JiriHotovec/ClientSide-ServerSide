@extends('layouts.app')
@section('title', $device->name)
@section('content')
<nav aria-label="Breadcrumb"><ul><li><a href="{{ route('devices.index') }}">Public devices</a></li><li>{{ $device->name }}</li></ul></nav>
@include('partials.device-panel', ['device' => $device, 'endpoint' => url("/api/public/devices/{$device->id}/measurements")])
@endsection
