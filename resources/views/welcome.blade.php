@extends('layouts.app')
@section('title', 'Welcome')
@section('hero')
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <h1>The sky, measured by people like you.</h1>
            <p class="lead">Follow live temperature, humidity and pressure from weather stations and home-built sensors. Open to everyone, no account needed. Add your own device in minutes.</p>
            <p class="actions">
                <a role="button" class="btn-sun" href="{{ route('devices.index') }}">Explore public devices</a>
                @guest<a role="button" class="btn-ghost" href="{{ route('register') }}">Create an account</a>@endguest
            </p>
        </div>
        @if($featured)
            @php($snap = $featured['snap'])
            <a class="live" href="{{ route('devices.show', $featured['device']) }}" aria-label="Open dashboard of {{ $featured['device']->name }}">
                <span class="live-name">{{ $featured['device']->name }}@if($featured['device']->location), {{ $featured['device']->location }}@endif</span>
                <span class="live-value">{{ rtrim(rtrim(number_format($snap['latest'], 1, '.', ''), '0'), '.') }}<span class="live-unit">{{ $snap['type']->unit }}</span></span>
                <span class="live-type">{{ $snap['type']->name }}, updated {{ $snap['at']->diffForHumans() }}</span>
                <svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none" role="img" aria-label="{{ $snap['type']->name }} over the last 24 hours">
                    <polyline points="{{ $snap['points'] }}" fill="none" stroke="currentColor" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round"/>
                </svg>
                <span class="live-foot">Last 24 hours</span>
            </a>
        @endif
    </div>
</section>
@endsection
@section('content')
<section class="features">
    <article>
        <h2>See the whole picture</h2>
        <p>Switch between 24 hours and 7 days. The chart and the table always show the same readings, and gaps stay gaps instead of turning into zeros.</p>
    </article>
    <article>
        <h2>Send your own readings</h2>
        <p>Post values to a simple HTTP endpoint, or let MeteoView fetch them from any JSON source on a schedule.</p>
    </article>
    <article>
        <h2>You decide who sees what</h2>
        <p>Keep a device private or make it public with one click. Private data never leaves your account.</p>
    </article>
</section>
@endsection
