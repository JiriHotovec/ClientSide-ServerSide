<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="htmx-config" content='{"allowEval":false,"includeIndicatorStyles":false,"responseHandling":[{"code":"204","swap":false},{"code":"[23]..","swap":true},{"code":"422","swap":true,"error":false},{"code":"[45]..","swap":false,"error":true}]}'>
    <title>@yield('title', 'Workspace') – MeteoView</title>
    <meta name="theme-color" content="#f8f5ef">
    <link rel="preload" href="{{ asset('fonts/instrument.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin-body" hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>
@php($user = auth()->user())
<a class="skip-link" href="#main">Skip to main content</a>
<div class="admin-shell">
    <header class="admin-top">
        <a href="{{ route('manage.overview') }}" class="brand" aria-label="MeteoView workspace"><svg class="logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg><span>Meteo<span class="brand-light">View</span></span></a>
        <details class="user-menu" data-user-menu>
            <summary aria-label="Account menu for {{ $user->name }}">
                <span class="avatar" id="menu-avatar" aria-hidden="true">{{ $user->initials() }}</span>
                <span class="user-name" id="menu-name">{{ $user->name }}</span>
            </summary>
            <div class="user-panel">
                <p class="user-id"><strong id="menu-name-full">{{ $user->name }}</strong><small id="menu-email">{{ $user->email }}</small><small>{{ $user->isAdmin() ? 'Administrator' : 'Standard user' }}</small></p>
                <a href="{{ route('manage.profile') }}">Profile and password</a>
                <a href="{{ route('welcome') }}" class="exit-link">Public site <svg class="ext" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 11l6-6M6 5h5v5"/></svg><span class="sr-only">(leaves the workspace)</span></a>
                <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form>
            </div>
        </details>
    </header>

    <nav class="admin-nav" id="admin-nav" aria-label="Workspace">
        <ul>
            <li><a href="{{ route('manage.overview') }}" @if(request()->routeIs('manage.overview')) aria-current="page" @endif>Overview</a></li>
            <li><a href="{{ route('manage.devices') }}" @if(request()->routeIs('manage.devices*')) aria-current="page" @endif>Devices</a></li>
            <li><a href="{{ route('manage.types') }}" @if(request()->routeIs('manage.types*')) aria-current="page" @endif>Measurement types</a></li>
            @unless($user->isAdmin())
                <li><a href="{{ route('dashboard') }}">My dashboard</a></li>
            @endunless
            <li class="nav-end"><a href="{{ route('devices.index') }}" class="exit-link">Public devices <svg class="ext" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 11l6-6M6 5h5v5"/></svg><span class="sr-only">(leaves the workspace)</span></a></li>
        </ul>
    </nav>

    <main id="main" tabindex="-1" class="admin-main" hx-boost="true" hx-target="#main" hx-select="#main" hx-swap="outerHTML show:window:top" hx-push-url="true" hx-select-oob="#admin-nav">
        <div role="status" aria-live="polite" data-manage-status class="status">{{ session('status') }}</div>
        <div role="alert" data-htmx-error class="status status-error"></div>
        @yield('content')
    </main>
</div>
<script src="{{ asset('vendor/htmx.min.js') }}"></script>
<script src="{{ asset('js/forms.js') }}"></script>
<script src="{{ asset('js/manage.js') }}"></script>
</body>
</html>
