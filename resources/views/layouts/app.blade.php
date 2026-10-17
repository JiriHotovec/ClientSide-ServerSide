<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="htmx-config" content='{"allowEval":false,"includeIndicatorStyles":false}'>
    <title>@yield('title', 'MeteoView') – MeteoView</title>
    <link rel="stylesheet" href="{{ asset('vendor/pico.min.css') }}">
    <link rel="preload" href="{{ asset('fonts/bricolage.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header @hasSection('hero') on-hero @endif">
  <div class="container">
    <nav aria-label="Main">
        <ul><li><a href="{{ route('welcome') }}" class="brand"><svg class="logo" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4.2" fill="currentColor"/><path d="M2 17c4-5 8-5 10-2s6 2 10-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>MeteoView</a></li></ul>
        <ul>
            <li><a href="{{ route('devices.index') }}" @if(request()->routeIs('devices.*')) aria-current="page" @endif>Public devices</a></li>
            @auth
                @unless(auth()->user()->isAdmin())
                    <li><a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard*')) aria-current="page" @endif>My dashboard</a></li>
                @endunless
                <li><a href="{{ route('manage.overview') }}" @if(request()->routeIs('manage.*')) aria-current="page" @endif>{{ auth()->user()->isAdmin() ? 'Admin panel' : 'Manage' }}</a></li>
                <li>
                    <form method="post" action="{{ route('logout') }}" class="inline">@csrf
                        <button type="submit" class="outline secondary">Log out ({{ auth()->user()->name }})</button>
                    </form>
                </li>
            @else
                <li><a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Log in</a></li>
                <li><a href="{{ route('register') }}" role="button" class="outline">Register</a></li>
            @endauth
        </ul>
    </nav>
  </div>
</header>
@yield('hero')
<main id="main" class="container" tabindex="-1">
    @yield('content')
</main>
<footer class="site-footer"><div class="container"><small>MeteoView. Open weather and sensor data.</small></div></footer>
<script src="{{ asset('vendor/htmx.min.js') }}"></script>
<script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/dashboard.js') }}"></script>
@auth<script src="{{ asset('js/manage.js') }}"></script>@endauth
</body>
</html>
