<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="htmx-config" content='{"allowEval":false,"includeIndicatorStyles":false}'>
    <title>@yield('title', 'MeteoView') – MeteoView</title>
    <meta name="theme-color" content="#f8f5ef">
    <link rel="preload" href="{{ asset('fonts/instrument.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="{{ request()->routeIs('manage.*') ? 'manage-page' : (request()->routeIs('login', 'register') ? 'auth-page' : '') }}" hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
  <div class="container">
    <nav aria-label="Main">
        <ul><li><a href="{{ route('welcome') }}" class="brand" aria-label="MeteoView home"><svg class="logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg><span>Meteo<span class="brand-light">View</span></span></a></li></ul>
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
<main id="main" tabindex="-1">
    <div role="alert" data-htmx-error class="status status-error container"></div>
    @yield('hero')
    <div class="container page-content @hasSection('hero') home-content @endif">
        @if(request()->routeIs('manage.*'))
        @endif
        @yield('content')
    </div>
</main>
<footer class="site-footer"><div class="container footer-grid"><div><a class="brand footer-brand" href="{{ route('welcome') }}">Meteo<span class="brand-light">View</span></a><p>Small places. Real observations.</p></div><nav aria-label="Footer"><a href="{{ route('devices.index') }}">Public devices</a>@guest<a href="{{ route('register') }}">Join the network</a>@else<a href="{{ route('manage.overview') }}">Your workspace</a>@endguest</nav><small>Open weather & sensor data.<br>Measured by people.</small></div></footer>
<script src="{{ asset('vendor/htmx.min.js') }}"></script>
<script src="{{ asset('js/forms.js') }}"></script>
<script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/dashboard.js') }}"></script>
@auth<script src="{{ asset('js/manage.js') }}"></script>@endauth
</body>
</html>
