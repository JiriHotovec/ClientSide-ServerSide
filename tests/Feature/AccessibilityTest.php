<?php

use App\Models\User;

it('has the page structure assistive technology expects (WCAG 2.2)', function (string $url) {
    $html = $this->get($url)->assertOk()->getContent();

    expect($html)->toContain('<html lang="en">')
        ->toContain('class="skip-link" href="#main"')
        ->toContain('<main id="main"')
        ->toContain('aria-label="Main"')
        ->toMatch('/<title>[^<]+<\/title>/')
        ->and(substr_count($html, '<h1'))->toBe(1);
})->with(['/', '/devices', '/login', '/register']);

it('labels every form control on login and register', function (string $url) {
    $html = $this->get($url)->getContent();
    preg_match_all('/<input[^>]*type="(?:text|email|password)"/i', $html, $inputs);
    preg_match_all('/<label[^>]*>/i', $html, $labels);

    expect(count($labels[0]))->toBeGreaterThanOrEqual(count($inputs[0]));
})->with(['/login', '/register']);

it('renders the dashboard panel with live regions, a captioned table and a chart text alternative', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $html = $this->get("/devices/{$device->id}")->getContent();

    expect($html)->toContain('aria-live="polite"')
        ->toContain('<caption data-caption>')
        ->toContain('scope="col"')
        ->toContain('role="img" aria-label=')
        ->toContain('aria-pressed="true"');
});

it('marks the current page in the navigation', function () {
    $this->get('/devices')->assertSee('aria-current="page"', false);
});

it('exposes accessible names on management actions', function () {
    $user = User::factory()->create();
    \App\Models\Device::factory()->for($user, 'owner')->create(['name' => 'Roof']);

    $this->actingAs($user)->get('/manage/devices/list')->assertSee('<span class="sr-only"> Roof</span>', false);
});

it('reports validation errors with aria-invalid and role=alert', function () {
    $this->from('/register')->post('/register', ['name' => '', 'email' => 'bad', 'password' => 'x'])->assertRedirect('/register');

    $this->get('/register')->assertSee('aria-invalid="true"', false)->assertSee('role="alert"', false);
});
