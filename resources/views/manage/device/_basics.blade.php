<label>{{ __('Name') }} <input name="name" required maxlength="100" value="{{ old('name', $device?->name) }}" @error('name') aria-invalid="true" @enderror></label>
<div class="location-row">
    <label>{{ __('Location') }} <input name="location" maxlength="100" value="{{ old('location', $device?->location) }}" @error('location') aria-invalid="true" @enderror></label>
    <div class="location-field" data-location-picker data-dialog="map-dialog-{{ $device?->uuid ?? 'new' }}" data-search-url="{{ route('geocode.search') }}" data-reverse-url="{{ route('geocode.reverse') }}">
        <input type="hidden" name="latitude" value="{{ old('latitude', $device?->latitude) }}" data-lat>
        <input type="hidden" name="longitude" value="{{ old('longitude', $device?->longitude) }}" data-lng>
        <button type="button" class="outline" data-map-open>{{ __('Pick on map') }}</button>
        <button type="button" class="outline secondary" data-map-clear hidden>{{ __('Remove pin') }}</button>
    </div>
</div>
<p class="coords" data-coords aria-live="polite"></p>
<label>{{ __('Description') }} <textarea name="description" maxlength="500" rows="3">{{ old('description', $device?->description) }}</textarea></label>
<div class="visibility-choice">
    <input type="hidden" name="is_public" value="0">
    <label><input type="checkbox" name="is_public" value="1" role="switch" @checked(old('is_public', $device?->is_public))> <strong>{{ __('Public') }}</strong></label>
    <p class="hint">{{ __('Visible to everyone, including visitors without an account. Private devices are visible only to you and administrators.') }}</p>
</div>
