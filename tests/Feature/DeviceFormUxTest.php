<?php

use App\Models\Device;
use App\Models\MeasurementType;
use App\Models\User;
use App\Support\DeviceFormTabs;
use App\Support\SourceMapping;
use App\Support\UrlGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;

beforeEach(function () {
    $this->temp = MeasurementType::factory()->create(['key' => 'temperature', 'name' => 'Temperature', 'unit' => '°C', 'category' => 'weather', 'color' => '#aa0000', 'is_public' => true]);
    $this->co2 = MeasurementType::factory()->create(['key' => 'co2', 'name' => 'CO2', 'unit' => 'ppm', 'category' => 'air', 'is_public' => true]);
    $this->user = User::factory()->create();
    // Deterministic DNS for the SSRF guard: everything resolves to a public address except internal.test.
    app()->bind(UrlGuard::class, fn () => new class extends UrlGuard
    {
        protected function resolve(string $host): array
        {
            return $host === 'internal.test' ? ['10.0.0.5'] : ['93.184.216.34'];
        }
    });
});

function pullForm(array $extra = []): array
{
    return ['_form' => 1, 'name' => 'Roof', 'source_mode' => 'pull', 'source_url' => 'https://api.example.test/now', 'fetch_interval_min' => 15] + $extra;
}

it('renders the form as ARIA tabs with a stacked no-JS fallback', function () {
    $html = $this->actingAs($this->user)->get('/manage/devices/create')->getContent();

    expect($html)->toContain('data-device-form')->toContain('role="tablist"')
        ->and(substr_count($html, 'role="tab"'))->toBe(4)
        ->and(substr_count($html, 'role="tabpanel"'))->toBe(4)
        ->and($html)->toContain('aria-controls="panel-datasets"')->toContain('aria-labelledby="tab-source"')
        ->toContain('<h2>Basics</h2>')->toContain('<h2>Data source</h2>')   // visible headings when the tabs are not activated
        ->toContain('class="form-bar"')->toContain('js/device-form.js');
});

it('groups datasets by category with a search field and chips of the selected ones', function () {
    $device = Device::factory()->for($this->user, 'owner')->create();
    $device->types()->attach($this->temp);

    $html = $this->actingAs($this->user)->get("/manage/devices/{$device->uuid}/edit")->getContent();

    expect($html)->toContain('data-type-filter')->toContain('data-type-group')
        ->and(strpos($html, 'Weather'))->toBeLessThan(strpos($html, 'Air quality'))
        ->and($html)->toContain('data-search="temperature temperature °c"')->toContain('data-chip="'.$this->temp->id.'"')
        ->and($html)->toContain('<span class="tab-count" data-count="datasets">1</span>');
});

it('keeps the appearance of a dataset collapsed and says whether it is customized', function () {
    $device = Device::factory()->for($this->user, 'owner')->create();
    $device->types()->attach($this->temp->id, ['decimals' => 3]);
    $device->types()->attach($this->co2->id);

    $html = $this->actingAs($this->user)->get("/manage/devices/{$device->uuid}/edit")->getContent();

    expect($html)->toContain('data-appearance-toggle')->toContain('Customize appearance')->toContain('data-defaults=')
        ->and(substr_count($html, 'Customized'))->toBeGreaterThanOrEqual(2) // data label + the customized badge text
        ->and($html)->toMatch('/class="badge "[^>]*data-appearance-state[^>]*>Customized</')
        ->and($html)->toMatch('/class="badge badge-private"[^>]*data-appearance-state[^>]*>Default appearance</');
});

it('shows only unit, decimals and color directly and hides the rest under "More options"', function () {
    $html = $this->actingAs($this->user)->get('/manage/devices/create')->getContent();
    [$basic, $more] = explode('More options', $html, 2);

    $inBasic = substr($basic, -2500);
    expect($inBasic)->toContain('data-field="unit"')->toContain('data-field="decimals"')->toContain('data-field="color"')
        ->not->toContain('data-field="y_min"')->not->toContain('data-field="chart_type"');
    expect(substr($more, 0, 3500))->toContain('data-field="unit_position"')->toContain('data-field="y_min"')->toContain('data-field="chart_type"');
});

it('still shows all display fields in the measurement type form', function () {
    $html = $this->actingAs($this->user)->get('/manage/types/create')->getContent();

    foreach (['unit', 'unit_position', 'unit_space', 'decimals', 'chart_type', 'color', 'y_min', 'y_max'] as $field) {
        expect($html)->toContain("data-field=\"{$field}\"");
    }
});

it('builds the pull mapping from one path per dataset', function () {
    $this->actingAs($this->user)->post('/manage/devices', pullForm([
        'types' => [$this->temp->id, $this->co2->id],
        'map' => [$this->temp->id => ' current.temperature_2m ', $this->co2->id => ''],
        'map_timestamp' => 'current.time', 'map_items' => '',
    ]))->assertRedirect(route('manage.devices'));

    $device = Device::firstWhere('name', 'Roof');
    expect($device->source_url)->toBe('https://api.example.test/now')
        ->and($device->source_mapping)->toEqual(['values' => ['temperature' => 'current.temperature_2m'], 'timestamp' => 'current.time']);
});

it('ignores paths of datasets that are not ticked', function () {
    $this->actingAs($this->user)->post('/manage/devices', pullForm(['types' => [$this->temp->id], 'map' => [$this->temp->id => 'a.b', $this->co2->id => 'c.d']]));

    expect(Device::firstWhere('name', 'Roof')->source_mapping['values'])->toBe(['temperature' => 'a.b']);
});

it('lets raw JSON in the advanced box override the builder', function () {
    $this->actingAs($this->user)->post('/manage/devices', pullForm([
        'types' => [$this->temp->id], 'map' => [$this->temp->id => 'ignored.path'],
        'source_mapping' => '{"values":{"temperature":"raw.path"},"items_path":"hourly"}',
    ]));

    expect(Device::firstWhere('name', 'Roof')->source_mapping)->toEqual(['values' => ['temperature' => 'raw.path'], 'items_path' => 'hourly']);
});

it('rejects a pull source without any mapping and invalid raw JSON', function () {
    $this->actingAs($this->user)->from('/manage/devices/create')->post('/manage/devices', pullForm(['types' => [$this->temp->id]]))
        ->assertSessionHasErrors('source_mapping');
    $this->post('/manage/devices', pullForm(['types' => [$this->temp->id], 'source_mapping' => '{broken']))->assertSessionHasErrors('source_mapping');
    expect(Device::count())->toBe(0);
});

it('drops the pull setup when the device is switched to "I send the data myself"', function () {
    $device = Device::factory()->for($this->user, 'owner')->create(['source_url' => 'https://api.example.test/x', 'source_mapping' => ['values' => ['temperature' => 'a']]]);
    $device->types()->attach($this->temp);

    $this->actingAs($this->user)->put("/manage/devices/{$device->uuid}", ['_form' => 1, 'name' => 'D', 'source_mode' => 'push', 'source_url' => 'https://api.example.test/x', 'types' => [$this->temp->id]]);

    expect($device->fresh()->source_url)->toBeNull()->and($device->fresh()->source_mapping)->toBeNull();
});

it('prefills the mapping builder, the interval and the source mode when editing', function () {
    $device = Device::factory()->for($this->user, 'owner')->create(['source_url' => 'https://api.example.test/x', 'fetch_interval_min' => 45,
        'source_mapping' => ['values' => ['temperature' => 'current.temperature_2m'], 'timestamp' => 'current.time', 'items_path' => 'hourly']]);
    $device->types()->attach($this->temp);

    $html = $this->actingAs($this->user)->get("/manage/devices/{$device->uuid}/edit")->getContent();

    expect($html)->toContain('value="current.temperature_2m"')->toContain('value="current.time"')->toContain('value="hourly"')
        ->toContain('<option value="45" selected>Every 45 minutes</option>')
        ->and($html)->toMatch('/value="pull" data-source-mode\s+checked/');
});

it('defaults a new device to sending the data itself and offers presets for the interval', function () {
    $html = $this->actingAs($this->user)->get('/manage/devices/create')->getContent();

    expect($html)->toMatch('/value="push" data-source-mode\s+checked/')->toContain('Every 15 minutes')->toContain('Once a day')
        ->toContain('curl -X POST')->toContain('/api/ingest')->toContain('You get the token right after you save the device.')->not->toContain('token-box');
});

it('shows the curl example with a dataset of the device and a token button when editing', function () {
    $device = Device::factory()->for($this->user, 'owner')->create();
    $device->types()->attach($this->co2);

    $html = $this->actingAs($this->user)->get("/manage/devices/{$device->uuid}/edit")->getContent();

    expect($html)->toContain('"type":"co2"')->toContain('id="token-box"')->toContain('hx-post="'.route('manage.devices.token', $device).'"');
});

it('returns the new token in place for the device form and flashes it for the list', function () {
    $device = Device::factory()->for($this->user, 'owner')->create();
    $this->actingAs($this->user);

    $res = $this->post("/manage/devices/{$device->uuid}/token", [], ['HX-Request' => 'true', 'HX-Target' => 'token-box'])->assertOk();
    $res->assertSee('New token (shown only once')->assertSee('mv_')->assertDontSee('<html', false);
    expect(Device::hashToken(trim(preg_match('/mv_[A-Za-z0-9]+/', $res->getContent(), $m) ? $m[0] : '')))->toBe($device->fresh()->ingest_token_hash);

    $this->from('/manage/devices')->post("/manage/devices/{$device->uuid}/token")->assertRedirect('/manage/devices')->assertSessionHas('status');
});

it('tests a pull source without saving anything', function () {
    Http::fake(['api.example.test/*' => Http::response(['current' => ['time' => '2026-10-17T10:00:00Z', 'temp' => 18.25]])]);

    $res = $this->actingAs($this->user)->post('/manage/devices/test-source', [
        'source_url' => 'https://api.example.test/now', 'types' => [$this->temp->id], 'map' => [$this->temp->id => 'current.temp'], 'map_timestamp' => 'current.time',
    ], ['HX-Request' => 'true']);

    $res->assertOk()->assertSee('The source works')->assertSee('Temperature')->assertSee('18.3')->assertSee('17. 10. 2026 10:00 UTC')->assertDontSee('<html', false);
    expect(Device::count())->toBe(0);
});

it('shows missing values in the source test and honours raw JSON mappings', function () {
    Http::fake(['*' => Http::response(['current' => ['x' => 1]])]);

    $this->actingAs($this->user)->post('/manage/devices/test-source', ['source_url' => 'https://api.example.test/now', 'types' => [$this->temp->id], 'source_mapping' => '{"values":{"temperature":"current.nothing"}}'])
        ->assertSee('Missing value');
});

it('explains problems of the source test instead of failing', function (array $input, string $expected) {
    Http::fake(['*' => Http::response('boom', 503)]);

    $this->actingAs($this->user)->post('/manage/devices/test-source', $input + ['types' => [$this->temp->id], 'map' => [$this->temp->id => 'a']])->assertOk()->assertSee($expected);
})->with([
    'HTTP error' => [['source_url' => 'https://api.example.test/now'], 'Data source responded with HTTP 503.'],
    'no url' => [['source_url' => ''], 'Enter a valid http(s) address first.'],
    'not http' => [['source_url' => 'ftp://example.test/x'], 'Enter a valid http(s) address first.'],
    'no path' => [['source_url' => 'https://api.example.test/now', 'map' => []], 'Add the path of at least one dataset first.'],
    'private address' => [['source_url' => 'http://internal.test/x'], 'URL points to a private or reserved address.'],
]);

it('says so when the mapping finds nothing in a working source', function () {
    Http::fake(['*' => Http::response(['current' => []])]);

    $this->actingAs($this->user)->post('/manage/devices/test-source', ['source_url' => 'https://api.example.test/now', 'types' => [$this->temp->id], 'map' => [$this->temp->id => 'a'], 'source_mapping' => '{"values":{}}'])
        ->assertSee('Add the path of at least one dataset first.');
});

it('protects the source test: login, throttling', function () {
    $this->postJson('/manage/devices/test-source', ['source_url' => 'https://api.example.test/now'])->assertUnauthorized();

    RateLimiter::clear('source-test');
    Http::fake(['*' => Http::response(['a' => 1])]);
    $this->actingAs($this->user);
    foreach (range(1, 10) as $_) {
        $this->post('/manage/devices/test-source', ['source_url' => 'https://api.example.test/now', 'types' => [$this->temp->id], 'map' => [$this->temp->id => 'a']]);
    }
    $this->post('/manage/devices/test-source', ['source_url' => 'https://api.example.test/now'])->assertStatus(429);
});

it('opens the tab with the first error and marks tabs that contain errors', function () {
    $this->actingAs($this->user)->from('/manage/devices/create')->post('/manage/devices', [
        '_form' => 1, 'name' => '', 'types' => [$this->temp->id], 'charts' => [['type' => $this->temp->id, 'kind' => 'pie']],
    ]);

    $html = $this->get('/manage/devices/create')->getContent();

    expect($html)->toContain('data-error-tab="basics"')->toContain('data-goto-tab="basics"')->toContain('data-goto-tab="charts"')
        ->and(substr_count($html, 'class="tab-error"'))->toBe(2)
        ->and($html)->toContain('aria-invalid="true"');
});

it('maps errors to the tab that holds the field', function (string $field, string $tab) {
    expect(DeviceFormTabs::tabFor($field))->toBe($tab);
})->with([
    ['name', 'basics'], ['latitude', 'basics'], ['is_public', 'basics'],
    ['types.0', 'datasets'], ['type_settings.3.decimals', 'datasets'], ['ts.3.unit', 'datasets'],
    ['charts.1.kind', 'charts'],
    ['source_url', 'source'], ['source_mapping', 'source'], ['map.4', 'source'], ['map_timestamp', 'source'], ['fetch_interval_min', 'source'],
]);

it('counts errors per tab', function () {
    $bag = new MessageBag(['name' => ['a', 'b'], 'charts.0.kind' => ['c'], 'source_url' => ['d']]);

    expect(DeviceFormTabs::errorCounts($bag))->toBe(['basics' => 2, 'charts' => 1, 'source' => 1]);
});

it('preselects dataset and style in a chart row and offers the styles as radio cards', function () {
    $res = $this->actingAs($this->user)->get('/manage/devices/chart-row?types[]='.$this->temp->id.'&types[]='.$this->co2->id.'&pick='.$this->co2->id.'&kind=gauge', ['HX-Request' => 'true']);

    $res->assertOk()->assertSee('<option value="'.$this->co2->id.'" selected>CO2</option>', false)->assertSee('type="radio"', false)->assertSee('class="style-card"', false);
    expect($res->getContent())->toMatch('/value="gauge"\s+checked/')->and(substr_count($res->getContent(), 'type="radio"'))->toBe(7);
});

it('offers the one-chart-per-dataset shortcut and an orphan warning in the chart tab', function () {
    $html = $this->actingAs($this->user)->get('/manage/devices/create')->getContent();

    expect($html)->toContain('data-chart-autofill')->toContain('One chart per dataset')->toContain('data-chart-empty');
});

it('translates the form to Czech', function () {
    $this->actingAs($this->user)->get('/manage/devices/create', ['Accept-Language' => 'cs'])
        ->assertSee('Základy')->assertSee('Data posílám sám')->assertSee('Vyzkoušet stažení')->assertSee('Jeden graf pro každou sadu')->assertSee('Hledat datové sady')->assertSee('Každých 15 minut');
});

it('builds mappings from form fields', function () {
    $keys = [1 => 'temperature', 2 => 'humidity'];

    expect(SourceMapping::fromForm(['map' => [1 => ' a.b ', 2 => '', 9 => 'x'], 'map_timestamp' => ' t ', 'map_items' => ''], $keys))->toBe(['values' => ['temperature' => 'a.b'], 'timestamp' => 't'])
        ->and(SourceMapping::fromForm(['map' => [1 => '']], $keys))->toBeNull()
        ->and(SourceMapping::fromForm([], $keys))->toBeNull();
});

it('puts the location field and the map button on one row', function () {
    $html = $this->actingAs($this->user)->get('/manage/devices/create')->getContent();

    expect($html)->toMatch('/class="location-row">\s*<label>Location <input name="location".*?data-location-picker.*?data-map-open>Pick on map<\/button>/s');
});
