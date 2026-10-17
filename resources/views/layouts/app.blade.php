<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="htmx-config" content='{"allowEval":false,"includeIndicatorStyles":false}'>
    <title>@yield('title', 'MeteoView') – MeteoView</title>
    @php($pageDescription = trim($__env->yieldContent('description')) ?: __('Open weather & sensor data.').' '.__('Small places. Real observations.'))
    @php($indexable = \App\Support\Locales::isPrefixed(request()) && ! $__env->hasSection('noindex'))
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="robots" content="{{ $indexable ? 'index,follow' : 'noindex,follow' }}">
    @if($indexable)
        @php($alternates = \App\Support\Locales::alternates(request()))
        <link rel="canonical" href="{{ $alternates[app()->getLocale()] }}">
        @foreach($alternates as $code => $href)
            <link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ $alternates[\App\Support\Locales::DEFAULT] }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="MeteoView">
        <meta property="og:title" content="@yield('title', 'MeteoView')">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ $alternates[app()->getLocale()] }}">
        <meta property="og:locale" content="{{ ['cs' => 'cs_CZ', 'en' => 'en_US'][app()->getLocale()] ?? 'en_US' }}">
        @foreach(array_keys($alternates) as $code)@if($code !== app()->getLocale())<meta property="og:locale:alternate" content="{{ ['cs' => 'cs_CZ', 'en' => 'en_US'][$code] ?? $code }}">@endif @endforeach
    @endif
    <meta name="theme-color" content="#f8f5ef">
    <link rel="preload" href="{{ asset('fonts/instrument.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="{{ request()->routeIs('manage.*') ? 'manage-page' : (request()->routeIs('login', 'register') ? 'auth-page' : '') }}" hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>
<a class="skip-link" href="#main">{{ __('Skip to main content') }}</a>
<header class="site-header">
  <div class="container">
    <nav aria-label="{{ __('Main') }}">
        <ul><li><a href="{{ route('welcome') }}" class="brand" aria-label="{{ __('MeteoView home') }}"><svg class="logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg><span>Meteo<span class="brand-light">View</span></span></a></li></ul>
        <ul>
            <li><a href="{{ route('devices.index') }}" @if(request()->routeIs('devices.*')) aria-current="page" @endif>{{ __('Public devices') }}</a></li>
            @auth
                @unless(auth()->user()->isAdmin())
                    <li><a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard*')) aria-current="page" @endif>{{ __('My dashboard') }}</a></li>
                @endunless
                <li><a href="{{ route('manage.overview') }}" @if(request()->routeIs('manage.*')) aria-current="page" @endif>{{ auth()->user()->isAdmin() ? __('Admin panel') : __('Manage') }}</a></li>
                <li>
                    <form method="post" action="{{ route('logout') }}" class="inline">@csrf
                        <button type="submit" class="outline secondary">{{ __('Log out (:name)', ['name' => auth()->user()->name]) }}</button>
                    </form>
                </li>
            @else
                <li><a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>{{ __('Log in') }}</a></li>
                <li><a href="{{ route('register') }}" role="button" class="outline">{{ __('Register') }}</a></li>
            @endauth
            <li>@include('partials.lang-switch')</li>
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
<footer class="site-footer"><div class="container footer-grid"><div><a class="brand footer-brand" href="{{ route('welcome') }}">Meteo<span class="brand-light">View</span></a><p>{{ __('Small places. Real observations.') }}</p></div><nav aria-label="{{ __('Footer') }}"><a href="{{ route('devices.index') }}">{{ __('Public devices') }}</a>@guest<a href="{{ route('register') }}">{{ __('Join the network') }}</a>@else<a href="{{ route('manage.overview') }}">{{ __('Your workspace') }}</a>@endguest</nav><small>{{ __('Open weather & sensor data.') }}<br>{{ __('Measured by people.') }}</small></div></footer>
@include('partials.i18n')
<script src="{{ asset('vendor/htmx.min.js') }}"></script>
<script src="{{ asset('js/forms.js') }}"></script>
<script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/dashboard.js') }}"></script>
@auth<script src="{{ asset('js/manage.js') }}"></script>@endauth
</body>
</html>
