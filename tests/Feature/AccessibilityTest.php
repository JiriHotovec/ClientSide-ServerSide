<?php

use App\Models\User;

it('has the page structure assistive technology expects (WCAG 2.2)', function (string $url) {
    $html = $this->get($url)->assertOk()->getContent();

    expect($html)->toMatch('/<html lang="(en|cs)">/')
        ->toContain('class="skip-link" href="#main"')
        ->toContain('<main id="main"')
        ->toMatch('/aria-label="(Main|Hlavní)"/')
        ->toMatch('/<title>[^<]+<\/title>/')
        ->and(substr_count($html, '<h1'))->toBe(1);
})->with(['/en', '/en/devices', '/en/login', '/en/register', '/cs']);

it('labels every form control on login and register', function (string $url) {
    $html = $this->get($url)->getContent();
    preg_match_all('/<input[^>]*type="(?:text|email|password)"/i', $html, $inputs);
    preg_match_all('/<label[^>]*>/i', $html, $labels);

    expect(count($labels[0]))->toBeGreaterThanOrEqual(count($inputs[0]));
})->with(['/login', '/register']);

it('renders the dashboard panel with live regions, a chart text alternative and a data table popup', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $html = $this->get("/en/devices/{$device->uuid}")->getContent();

    expect($html)->toContain('aria-live="polite"')
        ->toContain('<dialog')
        ->toContain('Data table')
        ->toContain('role="img" aria-label=')
        ->toContain('aria-pressed="true"');
});

it('marks the current page in the navigation', function () {
    $this->get('/en/devices')->assertSee('aria-current="page"', false);
});

it('exposes accessible names on management actions', function () {
    $user = User::factory()->create();
    \App\Models\Device::factory()->for($user, 'owner')->create(['name' => 'Roof']);

    $this->actingAs($user)->get('/manage/devices')->assertSee('<span class="sr-only"> Roof</span>', false);
});

it('reports validation errors with aria-invalid and role=alert', function () {
    $this->from('/en/register')->post('/en/register', ['name' => '', 'email' => 'bad', 'password' => 'x'])->assertRedirect('/en/register');

    $this->get('/en/register')->assertSee('aria-invalid="true"', false)->assertSee('role="alert"', false);
});

it('groups datasets by category into an inline group row with one chart card per dataset', function () {
    [$device, $temp] = deviceWithData(['is_public' => true]);
    $temp->update(['category' => 'weather']);
    $device->types()->attach(\App\Models\MeasurementType::create(['key' => 'humidity', 'name' => 'Humidity', 'unit' => '%', 'category' => 'weather']));
    $device->types()->attach(\App\Models\MeasurementType::create(['key' => 'co2', 'name' => 'CO2', 'unit' => 'ppm', 'category' => 'air']));

    $html = $this->get("/en/devices/{$device->uuid}")->getContent();

    expect(substr_count($html, 'data-group-menu'))->toBe(1)
        ->and(substr_count($html, 'data-card'))->toBe(3) // one card per dataset
        ->and($html)->toContain('class="group-row"')->toContain('Air quality')->toContain('<small>2</small>')->toContain('<small>1</small>')
        ->toContain('data-category="weather"')->toContain('data-category="air"')
        ->toContain('data-group="weather"')->toContain('data-group="air"')->toContain('aria-pressed="true"')
        ->not->toContain('role="tablist"')->not->toContain('<details class="group-menu"');
});

it('shows a plain heading instead of a menu when all datasets share one category', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $html = $this->get("/en/devices/{$device->uuid}")->getContent();

    expect($html)->not->toContain('data-group-menu')->toContain('toolbar-title')->toContain('data-card');
});

it('offers a magnifier per chart with an enlarged chart dialog and a floating custom range form', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $html = $this->get("/en/devices/{$device->uuid}")->getContent();

    expect(substr_count($html, 'data-zoom '))->toBeGreaterThanOrEqual(1)
        ->and($html)->toContain('aria-label="Enlarge chart of Temperature"')->toContain('data-zoom-dialog')->toContain('data-zoom-canvas')
        ->toContain('data-range-form')->toContain('aria-expanded="false"');
});
