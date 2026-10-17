@extends('layouts.app')
@section('title', 'Register')
@section('content')
<h1>Create an account</h1>
<form method="post" action="{{ route('register') }}" class="narrow">
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
