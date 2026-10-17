@extends('layouts.admin')
@section('title', __('Profile'))
@section('content')
<header class="page-head">
    <h1>{{ __('Profile') }}</h1>
    <p class="lead">{{ __('Your account details and password.') }}</p>
</header>
<div class="profile-grid">
    @include('manage._profile-details', ['err' => $errors->getBag('details')->messages(), 'old' => old(), 'done' => session('details_done')])
    @include('manage._profile-password', ['err' => $errors->getBag('password')->messages(), 'old' => [], 'done' => session('password_done')])
</div>
@endsection
