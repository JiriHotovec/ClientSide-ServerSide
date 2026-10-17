{{-- Station dashboard shared by the public and personal views. Tiles show the current state, charts are loaded
     via fetch() by dashboard.js, aggregated tables open in an htmx-loaded dialog. --}}
@php($service = app(\App\Services\MeasurementService::class))
@php($groups = $device->types->groupBy('category')->sortBy(fn ($g, $k) => array_search($k, \App\Support\Categories::keys())))
@php($snaps = $device->types->mapWithKeys(fn ($t) => [$t->id => $service->snapshotFor($device, $t)]))
@php($updated = $snaps->pluck('at')->filter()->max())
<section class="measurement-panel station" data-meteo data-endpoint="{{ $endpoint }}" aria-labelledby="dev-title-{{ $device->uuid }}">
    <header class="station-head">
        <div>
            @if(request()->routeIs('devices.show'))
                <h1 id="dev-title-{{ $device->uuid }}">{{ $device->name }}</h1>
            @else
                <h2 id="dev-title-{{ $device->uuid }}">{{ $device->name }}</h2>
            @endif
            <p>{{ $device->location ?: 'Location not specified' }}@if($device->description) – {{ $device->description }}@endif</p>
        </div>
        <p class="station-meta">
            <span class="badge">{{ $device->is_public ? 'Public' : 'Private' }}</span>
            @if($updated)<span>Updated {{ $updated->diffForHumans() }}</span>@else<span>No readings yet</span>@endif
        </p>
    </header>

    @if($device->types->isEmpty())
        <p role="status">This device has no datasets configured yet.</p>
    @else
    <section class="tiles" aria-label="Current conditions">
        @foreach($device->types as $type)
            @php($sn = $snaps[$type->id])
            <div class="tile">
                <h3>{{ $type->name }}</h3>
                @if($sn['latest'] !== null)
                    <p class="tile-value">{{ \App\Support\DisplaySettings::format($sn['latest'], $sn['display'], false) }}@if($sn['display']['unit_position'] !== 'none')<span class="tile-unit">{{ $sn['display']['unit'] }}</span>@endif</p>
                    <p class="tile-range">Low {{ \App\Support\DisplaySettings::format($sn['min'], $sn['display']) }}<span aria-hidden="true"> / </span><span class="sr-only">, </span>High {{ \App\Support\DisplaySettings::format($sn['max'], $sn['display']) }}<span class="sr-only"> in the last 24 hours</span></p>
                    <svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true"><polyline points="{{ $sn['points'] }}" fill="none" stroke="{{ $sn['display']['color'] }}" stroke-width="1.8" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round"/></svg>
                @else
                    <p class="tile-value tile-empty">No data</p>
                    <p class="tile-range">Nothing in the last 24 hours</p>
                @endif
            </div>
        @endforeach
    </section>

    <div class="toolbar">
        @if($groups->count() > 1)
        <div class="group-row" role="group" aria-label="Measurement groups of {{ $device->name }}" data-group-menu>
            @foreach($groups as $cat => $types)
                <button type="button" data-category="{{ $cat }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ \App\Support\Categories::label($cat) }}<small>{{ $types->count() }}</small><span class="sr-only"> {{ \Illuminate\Support\Str::plural('dataset', $types->count()) }}</span></button>
            @endforeach
        </div>
        @else
            <h2 class="toolbar-title">{{ \App\Support\Categories::label($groups->keys()->first()) }}</h2>
        @endif
        <div class="range-controls">
            <fieldset role="group" aria-label="Time period" class="period">
                <button type="button" data-period="24h" aria-pressed="true">24 hours</button>
                <button type="button" data-period="7d" aria-pressed="false" class="outline">7 days</button>
                <button type="button" data-period="custom" aria-pressed="false" class="outline" aria-expanded="false" aria-controls="range-{{ $device->uuid }}">Custom</button>
            </fieldset>
            <form class="range-form" id="range-{{ $device->uuid }}" data-range-form hidden>
                <label>From <input type="datetime-local" name="from" required></label>
                <label>To <input type="datetime-local" name="to" required></label>
                <button type="submit">Show</button>
                <p class="range-error" data-range-error role="alert"></p>
            </form>
        </div>
    </div>
    <input type="hidden" name="period" value="24h" data-period-state>
    <input type="hidden" name="from" value="" data-period-state>
    <input type="hidden" name="to" value="" data-period-state>
    <input type="hidden" name="tz" value="0" data-period-state>

    @foreach($groups as $cat => $types)
    <div class="group-panel chart-grid" data-group="{{ $cat }}" @if($groups->count() > 1) @endif @unless($loop->first) hidden @endunless>
        @foreach($types as $type)
        <article class="type-card" data-card data-type="{{ $type->key }}" aria-labelledby="card-{{ $device->uuid }}-{{ $type->key }}">
            <header class="card-head">
                <h3 id="card-{{ $device->uuid }}-{{ $type->key }}">{{ $type->name }}</h3>
                <div class="card-actions">
                <button type="button" class="icon-btn" data-zoom aria-label="Enlarge chart of {{ $type->name }}" hidden>
                    <svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M13 13l4.5 4.5M8.5 6v5M6 8.5h5"/></svg>
                </button>
                <button type="button" class="outline small" hx-get="{{ route('devices.table', [$device, $type->key]) }}" hx-include="[data-period-state]" hx-target="#dialog-body-{{ $device->uuid }}" hx-swap="innerHTML" hx-disabled-elt="this">Data table<span class="sr-only"> for {{ $type->name }}</span></button>
                </div>
            </header>
            <div data-status role="status" aria-live="polite" class="status"></div>
            <div data-source-warning role="alert" hidden></div>
            <dl class="stats" data-stats hidden>
                <div><dt>Lowest</dt><dd data-stat="min"></dd></div>
                <div><dt>Average</dt><dd data-stat="avg"></dd></div>
                <div><dt>Highest</dt><dd data-stat="max"></dd></div>
            </dl>
            <figure data-chart-wrap hidden>
                <canvas data-chart role="img" aria-label="Chart of {{ $type->name }}. Open the data table for the exact values."></canvas>
            </figure>
        </article>
        @endforeach
    </div>
    @endforeach

    <dialog class="data-dialog zoom-dialog" data-zoom-dialog aria-labelledby="zoom-title-{{ $device->uuid }}">
        <div class="popup-head">
            <h2 id="zoom-title-{{ $device->uuid }}" data-zoom-title tabindex="-1"></h2>
            <form method="dialog"><button class="popup-close" aria-label="Close enlarged chart">Close</button></form>
        </div>
        <dl class="stats" data-zoom-stats></dl>
        <div class="zoom-chart"><canvas data-zoom-canvas role="img" aria-label="Enlarged chart"></canvas></div>
    </dialog>

    <dialog class="data-dialog" data-dialog aria-labelledby="dialog-title-{{ $device->uuid }}">
        <div id="dialog-body-{{ $device->uuid }}" data-dialog-body></div>
    </dialog>
    @endif
</section>
