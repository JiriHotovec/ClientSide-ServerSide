@extends('layouts.app')
@section('title', $device->name)
@section('content')
@include('partials.device-panel', ['device' => $device, 'endpoint' => url("/api/public/devices/{$device->uuid}/measurements")])
@endsection
