@extends('layouts.app')
@section('title', __('Weather, closer to home'))
@section('description', __('The temperature in your garden. The air in your greenhouse. Your surroundings, captured in data.'))
@section('hero')
<section class="hero" aria-labelledby="welcome-title">
    <div class="container hero-grid">
        <div class="hero-copy">
            <h1 id="welcome-title">{!! __('Outside, there\'s<br>always a little<br><em>more going on.</em>') !!}</h1>
            <p class="lead">{{ __('The temperature in your garden. The air in your greenhouse. Your surroundings, captured in data.') }}</p>
            <a class="text-action" href="#stations">{{ __('Discover the stations') }} <span aria-hidden="true">↘</span></a>
        </div>
        @if($featured)
            @php($snap = $featured['snap'])
            <a class="live" href="{{ route('devices.show', $featured['device']) }}">
                <span class="live-top"><span>{{ $featured['device']->location ?: $featured['device']->name }}</span><span aria-hidden="true">↗</span></span>
                <span class="live-reading"><span class="live-value">{{ \App\Support\DisplaySettings::format($snap['latest'], $snap['display'], false) }}</span>@if($snap['display']['unit_position'] !== 'none')<span class="live-unit {{ $snap['display']['unit_position'] === 'before' ? 'unit-before' : '' }}">{{ $snap['display']['unit'] }}</span>@endif</span>
                <span class="live-type">{{ $snap['type']->name }}</span>
                <svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none" role="img" aria-label="{{ __(':name over the last 24 hours', ['name' => $snap['type']->name]) }}"><polyline points="{{ $snap['points'] }}" fill="none" stroke="currentColor" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round"/></svg>
                <span class="live-bottom"><span>{{ $featured['device']->name }}<br>{{ __('Updated :time', ['time' => $snap['at']->diffForHumans()]) }}</span><span>{{ __('Public station') }} <span aria-hidden="true">↗</span></span></span>
                <span class="sr-only">{{ __('Open dashboard of :name', ['name' => $featured['device']->name]) }}</span>
            </a>
        @else
            <div class="live live-empty">
                <span class="live-top">{{ __('Room for your perspective') }} <span aria-hidden="true">↗</span></span>
                <span class="empty-sun" aria-hidden="true">☼</span>
                <h2>{!! __('Every observation<br>starts somewhere.') !!}</h2>
                <p>{{ __('No recent public readings yet. Connect a sensor and let your surroundings tell their story.') }}</p>
                <a class="text-action" href="{{ route('manage.devices.create') }}">{{ __('Connect your first device') }} <span aria-hidden="true">↗</span></a>
            </div>
        @endif
    </div>
</section>
@endsection
@section('content')
<section id="stations" class="home-section" aria-labelledby="stations-title">
    <header class="section-head"><div><h2 id="stations-title">{!! __('Small places. <em>Real data.</em>') !!}</h2></div><a class="text-action" href="{{ route('devices.index') }}">{{ __('All public devices') }} <span aria-hidden="true">↗</span></a></header>
    @if($stations->isEmpty())
        <p class="empty">{{ __('The network is waiting for its first public station.') }} <a href="{{ route('manage.devices.create') }}">{{ __('Add yours') }}</a> {{ __('and share a view of your surroundings.') }}</p>
    @else
        <ul class="device-grid home-stations" role="list">
            @foreach($stations as $station)
                @include('partials.device-card', ['device' => $station['device'], 'snap' => $station['snap'], 'href' => route('devices.show', $station['device']), 'owner' => false])
            @endforeach
        </ul>
    @endif
</section>
<section class="home-section" id="how-it-works" aria-labelledby="how-title">
    <header class="section-head"><div><h2 id="how-title">{!! __('A reading is just <em>the beginning.</em>') !!}</h2></div><p class="section-intro">{{ __('From a quick glance to the bigger picture. Follow the places you care about, at your own pace.') }}</p></header>
    <div class="features">
        <article><span class="feature-number">01 — {{ __('Explore') }}</span><h3>{{ __('Find your point of view.') }}</h3><p>{{ __('Browse stations shared by the community. Open their readings and history without creating an account.') }}</p></article>
        <article><span class="feature-number">02 — {{ __('Observe') }}</span><h3>{{ __('Watch the patterns unfold.') }}</h3><p>{{ __('Look back over 24 hours, a full week or any dates you choose. Switch between datasets and open the exact values in a data table.') }}</p></article>
        <article><span class="feature-number">03 — {{ __('Understand') }}</span><h3>{{ __('See what is really there.') }}</h3><p>{{ __('Every reading has a time and a source. Missing measurements stay missing, so an empty moment never becomes a misleading zero.') }}</p></article>
    </div>
</section>
<section class="connect-section home-section" aria-labelledby="connect-title">
    <div class="connect-copy"><h2 id="connect-title">{!! __('Your sensor.<br>A new <em>perspective.</em>') !!}</h2><p>{{ __('A home-built weather station, a greenhouse monitor, or a sensor on your balcony. Give your measurements a place to live.') }}</p><a class="btn" href="{{ route('manage.devices.create') }}">{{ __('Connect a device') }} <span aria-hidden="true">↗</span></a></div>
    <ol class="connect-steps"><li><span class="step-number" aria-hidden="true">01</span><div><h3>{{ __('Make it your own.') }}</h3><p>{{ __('Name your device, add its location, and choose what it measures.') }}</p></div></li><li><span class="step-number" aria-hidden="true">02</span><div><h3>{{ __('Let the readings flow.') }}</h3><p>{{ __('Send measurements directly, or fetch them from an external JSON source on a schedule.') }}</p></div></li><li><span class="step-number" aria-hidden="true">03</span><div><h3>{{ __('Share on your terms.') }}</h3><p>{{ __('Keep your device private, or make it public for everyone to explore. You choose.') }}</p></div></li></ol>
</section>
<section class="home-section faq-section" aria-labelledby="faq-title">
    <div><h2 id="faq-title">{!! __('A few things,<br><em>before you begin.</em>') !!}</h2></div>
    <div class="faq-list"><details><summary>{{ __('Do I need an account to explore?') }}</summary><p>{{ __('No. Public stations and their measurement history are open to everyone. Create an account when you want to add and manage your own devices.') }}</p></details><details><summary>{{ __('Can I keep my measurements private?') }}</summary><p>{{ __('Yes. A private device and its measurements are available only to you and administrators. You can change a device\'s visibility in your workspace.') }}</p></details><details><summary>{{ __('What can I measure?') }}</summary><p>{{ __('Temperature, humidity, pressure, or your own measurement types. Choose units, decimal places, chart colors, and display settings for each dataset.') }}</p></details><details><summary>{{ __('How do I connect a sensor?') }}</summary><p>{{ __('Create a device in your workspace. Send readings using its ingest token, or configure an external JSON endpoint for MeteoView to fetch on a schedule.') }}</p></details></div>
</section>
<section class="join-section" aria-labelledby="join-title"><h2 id="join-title">{!! __('The world outside.<br><em>A little closer.</em>') !!}</h2><div class="actions"><a class="btn" href="{{ route('devices.index') }}">{{ __('Explore public devices') }} <span aria-hidden="true">↗</span></a>@guest<a class="text-action" href="{{ route('register') }}">{{ __('Create an account') }} <span aria-hidden="true">↗</span></a>@else<a class="text-action" href="{{ route('manage.overview') }}">{{ __('Open your workspace') }} <span aria-hidden="true">↗</span></a>@endguest</div></section>
@endsection
