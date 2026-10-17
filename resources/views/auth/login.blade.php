@extends('layouts.app')
@section('title', 'Log in')
@section('content')
<h1>Welcome <em>back.</em></h1>
<p class="lead">Log in to follow your observations and manage your devices.</p>
<form method="post" action="{{ route('login') }}" class="narrow" hx-boost="true" hx-target="#main" hx-select="#main" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]">
    @csrf
    <label>E-mail
        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus
               @error('email') aria-invalid="true" aria-describedby="email-err" @enderror>
        @error('email')<small id="email-err" role="alert">{{ $message }}</small>@enderror
    </label>
    <label>Password
        <input type="password" name="password" required autocomplete="current-password">
    </label>
    <label><input type="checkbox" name="remember" value="1"> Remember me</label>
    <button type="submit">Log in</button>
</form>
<p>No account yet? <a href="{{ route('register') }}">Register</a>.</p>
@endsection
