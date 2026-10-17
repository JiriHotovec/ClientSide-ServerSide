<?php

use App\Models\Device;
use App\Models\Measurement;
use App\Models\User;

it('shows welcome, device list and login to guests', function () {
    $this->get('/')->assertOk()->assertSee('MeteoView');
    $this->get('/login')->assertOk();
    $this->get('/devices')->assertOk();
});

it('lists only public devices to guests (A7)', function () {
    [$pub] = deviceWithData(['is_public' => true, 'name' => 'Open one']);
    [$priv] = deviceWithData(['is_public' => false, 'name' => 'Secret one']);

    $this->get('/devices')->assertSee('Open one')->assertDontSee('Secret one');
    $this->getJson('/api/public/devices')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pub->id);
});

it('lets guests open a public dashboard and its measurements', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => 21.5, 'measured_at' => now()->subHour(), 'source' => 'ingest']);

    $this->get("/devices/{$device->id}")->assertOk()->assertSee($device->name);
    $this->getJson("/api/public/devices/{$device->id}/measurements?period=24h&type=temperature")
        ->assertOk()->assertJsonPath('summary.count', 1)->assertJsonPath('data.0.unit', '°C')->assertJsonPath('data.0.source', 'ingest');
});

it('hides private devices and their data from guests and other users (A7)', function () {
    [$device] = deviceWithData(['is_public' => false]);

    $this->get("/devices/{$device->id}")->assertNotFound();
    $this->getJson("/api/public/devices/{$device->id}")->assertNotFound();
    $this->getJson("/api/public/devices/{$device->id}/measurements")->assertNotFound();

    $this->actingAs(User::factory()->create());
    $this->getJson("/api/public/devices/{$device->id}/measurements")->assertNotFound();
    $this->getJson("/api/devices/{$device->id}/measurements")->assertForbidden();
});

it('does not expose owner-only configuration on public endpoints', function () {
    deviceWithData(['is_public' => true, 'source_url' => 'https://example.com/x.json']);

    $json = $this->getJson('/api/public/devices')->assertOk()->json('data.0');

    expect($json)->not->toHaveKeys(['source_url', 'last_error', 'owner_id', 'has_ingest_token']);
});

it('rejects an unknown period', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $this->getJson("/api/public/devices/{$device->id}/measurements?period=30d")->assertUnprocessable();
});

it('returns 404 for a dataset the device does not have', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $this->getJson("/api/public/devices/{$device->id}/measurements?type=co2")->assertNotFound();
});
