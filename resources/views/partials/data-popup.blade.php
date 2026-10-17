@php($d = $agg['display'])
@php($fmt = fn ($v) => $v === null ? 'No data' : \App\Support\DisplaySettings::format($v, $d, false))
<div class="popup-head">
    <h2 id="dialog-title-{{ $device->uuid }}" tabindex="-1">{{ $type->name }} <small>({{ $d['unit'] }})</small></h2>
    <div class="popup-actions">
        <button type="button" class="popup-close" data-expand aria-pressed="false">Enlarge</button>
        <form method="dialog"><button class="popup-close" aria-label="Close data table">Close</button></form>
    </div>
</div>
<form class="popup-filters" hx-get="{{ route('devices.table', [$device, $type->key]) }}" hx-trigger="change" hx-target="closest [data-dialog-body]" hx-swap="innerHTML">
    <label>Period
        <select name="period">
            <option value="24h" @selected($period === '24h')>Last 24 hours</option>
            <option value="7d" @selected($period === '7d')>Last 7 days</option>
            <option value="custom" @selected($period === 'custom')>Custom dates</option>
        </select></label>
    @if($period === 'custom')
        <label>From <input type="datetime-local" name="from" value="{{ $from }}"></label>
        <label>To <input type="datetime-local" name="to" value="{{ $to }}"></label>
    @endif
    <input type="hidden" name="tz" value="{{ $tz }}">
    <label>Group by
        <select name="bucket">
            <option value="hour" @selected($bucketKey === 'hour')>Hour</option>
            <option value="6h" @selected($bucketKey === '6h')>6 hours</option>
            <option value="day" @selected($bucketKey === 'day')>Day</option>
        </select></label>
</form>

<dl class="popup-total">
    <div><dt>Lowest</dt><dd>{{ $fmt($agg['total']['min']) }}</dd></div>
    <div><dt>Average</dt><dd>{{ $fmt($agg['total']['avg']) }}</dd></div>
    <div><dt>Highest</dt><dd>{{ $fmt($agg['total']['max']) }}</dd></div>
</dl>

@if($agg['rows'] === [])
    <p class="empty" role="status">No measurements in this period. Missing data is never shown as zero.</p>
@else
<div class="table-wrap">
    <table>
        <caption class="sr-only">{{ $type->name }} aggregated by {{ ['hour' => 'hour', '6h' => '6 hours', 'day' => 'day'][$bucketKey] }}, newest first</caption>
        <thead><tr><th scope="col">From</th><th scope="col">Lowest</th><th scope="col">Average</th><th scope="col">Highest</th><th scope="col">Readings</th></tr></thead>
        <tbody>
        @foreach($agg['rows'] as $r)
            <tr>
                <th scope="row"><time datetime="{{ $r['start']->toIso8601String() }}" data-local>{{ $r['start']->format('j. n. H:i') }} UTC</time></th>
                <td>{{ $fmt($r['min']) }}</td><td>{{ $fmt($r['avg']) }}</td><td>{{ $fmt($r['max']) }}</td>
                <td>{{ $r['count'] }}@if($r['missing']) <small>({{ $r['missing'] }} missing)</small>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
<p class="popup-note"><small>Values are grouped from stored readings. Source: {{ $device->source_url ? 'external endpoint' : 'device' }}.</small></p>
