@extends('layouts.app')
@section('title', 'Register')
@section('content')
<h1>Make room for<br><em>your observations.</em></h1>
<p class="lead">Create an account and give your sensor data a home.</p>
<form method="post" action="{{ route('register') }}" class="narrow" hx-boost="true" hx-target="#main" hx-select="#main" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]">
    @csrf
    <label>Name
        <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name"
               @error('name') aria-invalid="true" aria-describedby="name-err" @enderror>
        @error('name')<small id="name-err" role="alert">{{ $message }}</small>@enderror
    </label>
    <label>E-mail
        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
               @error('email') aria-invalid="true" aria-describedby="email-err" @enderror>
        @error('email')<small id="email-err" role="alert">{{ $message }}</small>@enderror
    </label>
    <label>Password
        <input type="password" name="password" required autocomplete="new-password" aria-describedby="pw-help"
               @error('password') aria-invalid="true" @enderror>
        <small id="pw-help">At least 10 characters, with letters and numbers.@error('password') <span role="alert">{{ $message }}</span>@enderror</small>
    </label>
    <label>Confirm password
        <input type="password" name="password_confirmation" required autocomplete="new-password">
    </label>
    <button type="submit">Register</button>
</form>
<p>Already registered? <a href="{{ route('login') }}">Log in</a>.</p>
@endsection
