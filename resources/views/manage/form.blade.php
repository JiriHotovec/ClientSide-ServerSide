@php($selected = $device?->types->pluck('id')->all() ?? [])
<h2 id="form-h" tabindex="-1">{{ $device ? 'Edit device: '.$device->name : 'Add a device' }}</h2>
<form data-action="save-device" @if($device) data-id="{{ $device->id }}" @endif>
    <label>Name <input name="name" required maxlength="100" value="{{ $device?->name }}"></label>
    <label>Location <input name="location" maxlength="100" value="{{ $device?->location }}"></label>
    <label>Description <textarea name="description" maxlength="500" rows="2">{{ $device?->description }}</textarea></label>
    <label><input type="checkbox" name="is_public" value="1" role="switch" @checked($device?->is_public)> Public (visible to everyone, including visitors without an account)</label>

    <fieldset>
        <legend>Datasets this device reports</legend>
        @foreach($types as $t)
            <label><input type="checkbox" name="types[]" value="{{ $t->id }}" @checked(in_array($t->id, $selected))> {{ $t->name }} ({{ $t->unit }})</label>
        @endforeach
    </fieldset>

    <details @if($device?->source_url) open @endif>
        <summary>Pull data from an external JSON endpoint (optional)</summary>
        <label>Source URL <input type="url" name="source_url" maxlength="500" value="{{ $device?->source_url }}" placeholder="https://api.example.com/current.json"></label>
        <label>Mapping (JSON) <textarea name="source_mapping" rows="5" spellcheck="false" aria-describedby="map-help">{{ $device?->source_mapping ? json_encode($device->source_mapping, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '' }}</textarea>
            <small id="map-help">Example: {"timestamp":"current.time","values":{"temperature":"current.temperature_2m"}}. Optional "items_path" for lists.</small></label>
        <label>Fetch interval (minutes) <input type="number" name="fetch_interval_min" min="1" max="1440" value="{{ $device?->fetch_interval_min ?? 15 }}"></label>
    </details>

    <div data-form-errors role="alert"></div>
    <button type="submit">{{ $device ? 'Save changes' : 'Create device' }}</button>
    @if($device)<button type="button" class="secondary outline" data-action="cancel-edit">Cancel</button>@endif
</form>
<div data-token-output role="status" aria-live="polite"></div>
