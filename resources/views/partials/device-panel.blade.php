{{-- Dashboard panel shared by the public and personal dashboards; data is loaded via fetch() by dashboard.js --}}
<section class="measurement-panel" data-meteo data-endpoint="{{ $endpoint }}" aria-labelledby="dev-title-{{ $device->uuid }}">
    <header>
    @if(request()->routeIs('devices.show'))
        <h1 id="dev-title-{{ $device->uuid }}">{{ $device->name }}</h1>
    @else
        <h2 id="dev-title-{{ $device->uuid }}">{{ $device->name }}</h2>
    @endif
    <p>{{ $device->location ?: 'Location not specified' }}@if($device->description) – {{ $device->description }}@endif
       <span class="badge">{{ $device->is_public ? 'Public' : 'Private' }}</span></p>
    </header>

    @if($device->types->isEmpty())
        <p role="status">This device has no datasets configured yet.</p>
    @else
    <div class="controls">
        <label>Dataset
            <select data-control="type">
                @foreach($device->types as $type)
                    <option value="{{ $type->key }}">{{ $type->name }} ({{ $type->unit }})</option>
                @endforeach
            </select>
        </label>
        <fieldset role="group" aria-label="Time period">
            <button type="button" data-period="24h" aria-pressed="true">24 hours</button>
            <button type="button" data-period="7d" aria-pressed="false" class="outline">7 days</button>
        </fieldset>
    </div>

    <div data-status role="status" aria-live="polite" class="status"></div>
    <div data-source-warning role="alert" hidden></div>

    <div data-current class="current" hidden>
        Latest value: <strong data-current-value></strong> <small data-current-time></small>
    </div>

    <figure data-chart-wrap hidden>
        <canvas data-chart role="img" aria-label="Chart of the selected dataset. The same values are listed in the table below."></canvas>
    </figure>

    <div class="table-wrap" data-table-wrap hidden>
        <table>
            <caption data-caption>Measurements</caption>
            <thead><tr>
                <th scope="col">Time</th><th scope="col">Value</th><th scope="col">Unit</th><th scope="col">Source</th>
            </tr></thead>
            <tbody data-rows></tbody>
        </table>
    </div>
    @endif
</section>
