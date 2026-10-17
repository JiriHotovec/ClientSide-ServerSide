<?php

use App\Models\Device;
use App\Models\MeasurementType;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('saves the coordinates chosen on the map with the device', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/manage/devices', ['_form' => 1, 'name' => 'Roof', 'location' => 'Prague', 'latitude' => '50.087465', 'longitude' => '14.421254'])
        ->assertRedirect(route('manage.devices'));

    $device = Device::firstWhere('name', 'Roof');
    expect($device->latitude)->toBe(50.087465)->and($device->longitude)->toBe(14.421254)->and($device->hasCoordinates())->toBeTrue();
});

it('lets a device exist without a pin and clears it again', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user, 'owner')->create(['latitude' => 50.1, 'longitude' => 14.4]);

    $this->actingAs($user)->put("/manage/devices/{$device->uuid}", ['_form' => 1, 'name' => 'D', 'latitude' => '', 'longitude' => ''])->assertRedirect();

    expect($device->fresh()->hasCoordinates())->toBeFalse();
});

it('validates the coordinates', function (array $bad, string $field) {
    $this->actingAs(User::factory()->create())->postJson('/api/devices', ['name' => 'D'] + $bad)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'latitude too big' => [['latitude' => 91, 'longitude' => 10], 'latitude'],
    'longitude too small' => [['latitude' => 10, 'longitude' => -181], 'longitude'],
    'only latitude' => [['latitude' => 10], 'longitude'],
    'only longitude' => [['longitude' => 10], 'latitude'],
    'not numbers' => [['latitude' => 'north', 'longitude' => 'east'], 'latitude'],
]);

it('exposes the pin on public pages and the public API', function () {
    $device = Device::factory()->public()->create(['latitude' => 50.1, 'longitude' => 14.4]);

    $this->getJson("/api/public/devices/{$device->uuid}")->assertJsonPath('data.latitude', 50.1)->assertJsonPath('data.longitude', 14.4);
    $this->get("/en/devices/{$device->uuid}")->assertSee('data-static-map', false)->assertSee('data-lat="50.1"', false);
});

it('shows no map on a device page without a pin', function () {
    $device = Device::factory()->public()->create();

    $this->get("/en/devices/{$device->uuid}")->assertDontSee('data-static-map', false);
});

it('puts every matching public device with a pin on the overview map, not only the current page', function () {
    Device::factory()->count(11)->public()->create(['latitude' => 50.0, 'longitude' => 14.0, 'location' => 'Zlin']);
    Device::factory()->public()->create(['name' => 'No pin']);
    Device::factory()->private()->create(['latitude' => 51.0, 'longitude' => 15.0, 'name' => 'Secret']);

    $html = $this->get('/en/devices')->assertSee('data-overview-map', false)->assertSee('data-overview-open', false)->getContent();
    preg_match('#<script type="application/json" data-map-data>(.*?)</script>#s', $html, $m);
    $points = json_decode($m[1], true);

    expect($points)->toHaveCount(11)->and($points[0])->toHaveKeys(['name', 'location', 'lat', 'lng', 'url'])
        ->and($m[1])->not->toContain('Secret')->not->toContain('No pin');
});

it('filters the overview map with the search', function () {
    Device::factory()->public()->create(['name' => 'Alpha', 'latitude' => 50.0, 'longitude' => 14.0]);
    Device::factory()->public()->create(['name' => 'Beta', 'latitude' => 49.0, 'longitude' => 16.0]);

    $html = $this->get('/en/devices?q=Alpha')->getContent();
    preg_match('#data-map-data>(.*?)</script>#s', $html, $m);

    expect(json_decode($m[1], true))->toHaveCount(1)->and($m[1])->toContain('Alpha');
});

it('does not break the map data with hostile device names', function () {
    Device::factory()->public()->create(['name' => '</script><script>alert(1)</script>', 'latitude' => 50.0, 'longitude' => 14.0]);

    $html = $this->get('/en/devices')->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>');
});

it('filters the public API by search text', function () {
    Device::factory()->public()->create(['name' => 'Alpha']);
    Device::factory()->public()->create(['name' => 'Beta']);

    $this->getJson('/api/public/devices?q=Alp')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Alpha');
});

it('renders the location picker on the device form and loads the map assets', function () {
    $html = $this->actingAs(User::factory()->create())->get('/manage/devices/create')->getContent();

    expect($html)->toContain('data-location-picker')->toContain('name="latitude"')->toContain('data-map-dialog')->toContain('data-dlg-lat')
        ->toContain('vendor/leaflet/leaflet.js')->toContain('js/map.js')->toContain(route('geocode.search'));
});

it('allows OpenStreetMap tiles in the CSP and nothing else new', function () {
    $csp = $this->get('/en')->headers->get('Content-Security-Policy');

    expect($csp)->toContain('img-src \'self\' data: https://tile.openstreetmap.org;')->toContain("script-src 'self';")->toContain("connect-src 'self'");
});

it('proxies place search to Nominatim with an identifying User-Agent and caches it', function () {
    Cache::flush();
    Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([['display_name' => 'Praha, Czechia', 'lat' => '50.0875', 'lon' => '14.4213']])]);
    $this->actingAs(User::factory()->create());

    $this->getJson('/geocode/search?q=Praha')->assertOk()->assertJsonPath('data.0', ['name' => 'Praha, Czechia', 'lat' => 50.0875, 'lng' => 14.4213]);
    $this->getJson('/geocode/search?q=praha')->assertOk(); // cached, case-insensitive

    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_contains($r->header('User-Agent')[0], 'MeteoView') && $r['q'] === 'Praha');
});

it('names a place from a pin', function () {
    Cache::flush();
    Http::fake(['nominatim.openstreetmap.org/reverse*' => Http::response(['display_name' => 'x', 'address' => ['town' => 'Kladno', 'country' => 'Česko']])]);

    $this->actingAs(User::factory()->create())->getJson('/geocode/reverse?lat=50.14&lng=14.1')->assertOk()->assertJsonPath('name', 'Kladno, Česko');
});

it('answers with 502 and a readable message when the map service fails, without caching the failure', function () {
    Cache::flush();
    $this->actingAs(User::factory()->create());

    Http::fake(['*' => Http::sequence()->push('boom', 503)->push([['display_name' => 'Brno', 'lat' => '49.2', 'lon' => '16.6']])]);
    $this->getJson('/geocode/search?q=Brno')->assertStatus(502)->assertJsonPath('message', 'The map service is not available right now. Try again later.');

    $this->getJson('/geocode/search?q=Brno')->assertOk()->assertJsonCount(1, 'data');
});

it('survives connection errors of the map service', function () {
    Cache::flush();
    Http::fake(fn () => throw new ConnectionException('down'));

    $this->actingAs(User::factory()->create())->getJson('/geocode/search?q=Brno')->assertStatus(502);
});

it('requires login and valid input for geocoding', function () {
    $this->getJson('/geocode/search?q=Praha')->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $this->getJson('/geocode/search?q=a')->assertUnprocessable();
    $this->getJson('/geocode/reverse?lat=200&lng=10')->assertUnprocessable();
});

it('throttles geocoding', function () {
    Cache::flush();
    Http::fake(['*' => Http::response([])]);
    $this->actingAs(User::factory()->create());

    foreach (range(1, 30) as $i) {
        $this->getJson("/geocode/search?q=place{$i}");
    }

    $this->getJson('/geocode/search?q=onemore')->assertStatus(429);
});

it('translates the map interface', function () {
    $this->actingAs(User::factory()->create())->get('/manage/devices/create', ['Accept-Language' => 'cs'])->assertSee('Vybrat na mapě')->assertSee('Použít tuto lokalitu');
    $this->get('/cs/devices')->assertSee('Mapa veřejných zařízení');
});

it('shows the location as the last group of the row, after the measurement groups', function () {
    [$device, $type] = deviceWithData(['is_public' => true, 'latitude' => 50.1, 'longitude' => 14.4, 'location' => 'Prague']);
    $type->update(['category' => 'weather']);
    $device->types()->attach(MeasurementType::create(['key' => 'noise', 'name' => 'Noise', 'unit' => 'dB', 'category' => 'other']));

    $html = $this->get("/en/devices/{$device->uuid}")->getContent();

    expect($html)->toContain('data-group="location"')->toContain('data-category="location"')->toContain('Open in OpenStreetMap')
        ->and(strpos($html, 'data-category="other"'))->toBeLessThan(strpos($html, 'data-category="location"'))
        ->and($html)->toMatch('/data-group="location"\s+hidden/');
});

it('offers the location tab even when all datasets share one category', function () {
    [$device] = deviceWithData(['is_public' => true, 'latitude' => 50.1, 'longitude' => 14.4]);

    $html = $this->get("/en/devices/{$device->uuid}")->getContent();

    expect($html)->toContain('data-group-menu')->toContain('data-category="location"')->not->toContain('toolbar-title');
});

it('has no location tab for a device without a pin', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $this->get("/en/devices/{$device->uuid}")->assertDontSee('data-category="location"', false);
});

it('shows the location tab on the personal dashboard too', function () {
    $user = User::factory()->create();
    [$device] = deviceWithData(['user' => $user, 'latitude' => 50.1, 'longitude' => 14.4]);

    $this->actingAs($user)->get('/dashboard')->assertSee('data-category="location"', false)->assertSee('vendor/leaflet/leaflet.js', false);
});

it('opens the sensors map from a button next to the search instead of showing it permanently', function () {
    Device::factory()->public()->create(['latitude' => 50.0, 'longitude' => 14.0]);
    Device::factory()->public()->create(['latitude' => 49.0, 'longitude' => 16.0]);

    $html = $this->get('/en/devices')->getContent();

    expect($html)->toContain('Open sensors on map')->toContain('<dialog class="data-dialog map-dialog overview-dialog" data-overview-map')
        ->toContain('data-map-count>2<')->not->toContain('overview-map" data-overview-map');
    expect($html)->not->toMatch('/data-overview-open\s+hidden/');
});

it('hides the sensors map button when no device has a pin', function () {
    Device::factory()->public()->create();

    expect($this->get('/en/devices')->getContent())->toMatch('/data-overview-open\s+hidden/');
});

it('translates the sensors map button', function () {
    $this->get('/cs/devices')->assertSee('Otevřít senzory na mapě');
});

it('loads the Leaflet stylesheet in the head of the workspace so the map does not fall apart', function () {
    $html = $this->actingAs(User::factory()->create())->get('/manage/devices/create')->getContent();

    $head = substr($html, 0, strpos($html, '</head>'));
    expect($head)->toContain('vendor/leaflet/leaflet.css')->and($html)->toContain('vendor/leaflet/leaflet.js');
});
