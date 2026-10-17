@extends('layouts.app')
@section('title', $device->name)
@section('description', trim($device->name.($device->location ? ', '.$device->location : '').' – '.$device->types->pluck('name')->join(', ')))
@section('content')
@include('partials.device-panel', ['device' => $device, 'endpoint' => url("/api/public/devices/{$device->uuid}/measurements")])
@endsection
