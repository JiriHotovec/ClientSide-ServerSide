@php($pull = old('source_mode') ? old('source_mode') === 'pull' : filled(old('source_url', $device?->source_url)))
@php($mapping = $device?->source_mapping ?? [])
@php($intervals = [5 => __('Every 5 minutes'), 15 => __('Every 15 minutes'), 30 => __('Every 30 minutes'), 60 => __('Every hour'), 360 => __('Every 6 hours'), 1440 => __('Once a day')])
@php($interval = (int) old('fetch_interval_min', $device?->fetch_interval_min ?? 15))

<p class="hint">{{ __('How does the device deliver its measurements?') }}</p>
<div class="choice-cards" role="radiogroup" aria-label="{{ __('How does the device deliver its measurements?') }}">
    <label class="choice">
        <input type="radio" name="source_mode" value="push" data-source-mode @checked(! $pull)>
        <strong>{{ __('I send the data myself') }}</strong>
        <span>{{ __('The sensor or your script posts readings to MeteoView with a secret token.') }}</span>
    </label>
    <label class="choice">
        <input type="radio" name="source_mode" value="pull" data-source-mode @checked($pull)>
        <strong>{{ __('Fetch from a URL') }}</strong>
        <span>{{ __('MeteoView downloads a JSON document on a schedule, for example from a weather API.') }}</span>
    </label>
</div>

<div class="source-panel" data-source-panel="push" @if($pull) data-inactive @endif>
    <h3>{{ __('Send readings') }}</h3>
    <p>{{ __('Post JSON to this address with the token in the Authorization header:') }}</p>
    <pre class="code-sample"><code>curl -X POST {{ url('/api/ingest') }} \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"measurements":[{"type":"{{ $device?->types->first()?->key ?? 'temperature' }}","value":21.4}]}'</code></pre>
    @if($device)
        <button type="button" class="outline small" hx-post="{{ route('manage.devices.token', $device) }}" hx-target="#token-box" hx-select="unset" hx-push-url="false" hx-params="none" hx-swap="innerHTML" hx-confirm="{{ __('Generate a new ingest token? The previous token stops working.') }}">{{ __('Generate a new token') }}</button>
        <div id="token-box" aria-live="polite">
            <p><small>{{ $device->ingest_token_hash ? __('This device already has a token. Generating a new one replaces it.') : __('This device has no token yet.') }}</small></p>
        </div>
    @else
        <p class="hint">{{ __('You get the token right after you save the device.') }}</p>
    @endif
</div>

<div class="source-panel" data-source-panel="pull" @unless($pull) data-inactive @endunless>
    <h3>{{ __('Fetch settings') }}</h3>
    <div class="settings-grid">
        <label>{{ __('Source URL') }} <input type="url" name="source_url" maxlength="500" value="{{ old('source_url', $device?->source_url) }}" placeholder="https://api.example.com/current.json" @error('source_url') aria-invalid="true" @enderror></label>
        <label>{{ __('How often') }}
            <select name="fetch_interval_min">
                @unless(array_key_exists($interval, $intervals))<option value="{{ $interval }}" selected>{{ __('Every :min minutes', ['min' => $interval]) }}</option>@endunless
                @foreach($intervals as $min => $label)<option value="{{ $min }}" @selected($interval === $min)>{{ $label }}</option>@endforeach
            </select></label>
    </div>

    <h4>{{ __('Where is each value in the JSON?') }}</h4>
    <p class="hint">{{ __('Write the path to the value, for example current.temperature_2m. Leave a dataset empty to skip it.') }}</p>
    <div class="mapping-rows">
        @foreach($types as $t)
            <label class="mapping-row {{ $pickedIds->contains($t->id) ? 'is-on' : '' }}" data-map-row="{{ $t->id }}">
                <span>{{ $t->name }}</span>
                <input name="map[{{ $t->id }}]" value="{{ old("map.{$t->id}", $mapping['values'][$t->key] ?? '') }}" placeholder="current.{{ $t->key }}" spellcheck="false" autocomplete="off">
            </label>
        @endforeach
        <p class="empty" data-map-empty hidden>{{ __('Select datasets on the Datasets tab first.') }}</p>
    </div>
    <div class="settings-grid">
        <label>{{ __('Time of the reading (path, optional)') }} <input name="map_timestamp" value="{{ old('map_timestamp', $mapping['timestamp'] ?? '') }}" placeholder="current.time" spellcheck="false"></label>
        <label>{{ __('List of readings (path, optional)') }} <input name="map_items" value="{{ old('map_items', $mapping['items_path'] ?? '') }}" placeholder="hourly" spellcheck="false"></label>
    </div>
    <details class="more" @if(old('source_mapping')) open @endif>
        <summary>{{ __('Advanced: edit the mapping as JSON') }}</summary>
        <label>{{ __('Mapping (JSON)') }} <textarea name="source_mapping" rows="5" spellcheck="false" aria-describedby="map-help" placeholder='{"timestamp":"current.time","values":{"temperature":"current.temperature_2m"}}'>{{ old('source_mapping') }}</textarea>
            <small id="map-help">{{ __('When filled, this replaces the fields above. Optional "items_path" for lists.') }}</small></label>
    </details>

    <button type="button" class="outline small" hx-post="{{ route('manage.devices.test-source') }}" hx-include="[data-source-panel='pull'], [name='types[]']:checked" hx-target="#source-preview" hx-select="unset" hx-push-url="false" hx-params="not _method" hx-swap="innerHTML" hx-disabled-elt="this">{{ __('Test fetch') }}</button>
    <div id="source-preview" class="source-preview" aria-live="polite"></div>
</div>
