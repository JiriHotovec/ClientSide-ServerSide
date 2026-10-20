<?php

use App\Models\Device;
use App\Models\Measurement;
use App\Models\User;

it('shows welcome, device list and login to guests', function () {
    $this->get('/en')->assertOk()->assertSee('MeteoView');
    $this->get('/en/login')->assertOk();
    $this->get('/en/devices')->assertOk();
});

it('previews three public stations and features a reading beyond stations with no data', function () {
    $this->freezeTime();
    $empty = Device::factory()->count(3)->create(['is_public' => true]);
    [$device, $type] = deviceWithData(['is_public' => true, 'name' => 'Garden with readings']);
    Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => 21.5, 'measured_at' => now()->subMinute(), 'source' => 'ingest']);

    $response = $this->get('/en');

    $response->assertOk()->assertSee('Garden with readings')->assertSee('21.5')
        ->assertSee('No recent readings')
        ->assertViewHas('stations', fn ($stations) => $stations->pluck('device.id')->all() === $empty->modelKeys());
});

it('shows the homepage empty state without exposing private observations', function () {
    $this->freezeTime();
    [$device, $type] = deviceWithData(['is_public' => false, 'name' => 'Hidden greenhouse']);
    Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => 32.8, 'measured_at' => now()->subMinute(), 'source' => 'ingest']);

    $response = $this->get('/en');

    $response->assertOk()->assertSee('No recent public readings yet.')
        ->assertDontSee('Hidden greenhouse')->assertDontSee('32.8');
});

it('escapes station names and locations in homepage previews and the featured reading', function () {
    $this->freezeTime();
    $name = '<script>alert("station")</script>';
    $location = '<img src=x onerror=alert("location")>';
    [$device, $type] = deviceWithData(['is_public' => true, 'name' => $name, 'location' => $location]);
    Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => 18.6, 'measured_at' => now()->subMinute(), 'source' => 'ingest']);

    $response = $this->get('/en');

    $response->assertOk()->assertSee($name)->assertSee($location)
        ->assertDontSee($name, false)->assertDontSee($location, false);
});

it('lists only public devices to guests (A7)', function () {
    [$pub] = deviceWithData(['is_public' => true, 'name' => 'Open one']);
    [$priv] = deviceWithData(['is_public' => false, 'name' => 'Secret one']);

    $this->get('/en/devices')->assertSee('Open one')->assertDontSee('Secret one');
    $this->getJson('/api/public/devices')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pub->uuid);
});

it('lets guests open a public dashboard and its measurements', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => 21.5, 'measured_at' => now()->subHour(), 'source' => 'ingest']);

    $this->get("/en/devices/{$device->uuid}")->assertOk()->assertSee($device->name);
    $this->getJson("/api/public/devices/{$device->uuid}/measurements?period=24h&type=temperature")
        ->assertOk()->assertJsonPath('summary.count', 1)->assertJsonPath('data.0.unit', '°C')->assertJsonPath('data.0.source', 'ingest');
});

it('hides private devices and their data from guests and other users (A7)', function () {
    [$device] = deviceWithData(['is_public' => false]);

    $this->get("/en/devices/{$device->uuid}")->assertNotFound();
    $this->getJson("/api/public/devices/{$device->uuid}")->assertNotFound();
    $this->getJson("/api/public/devices/{$device->uuid}/measurements")->assertNotFound();

    $this->actingAs(User::factory()->create());
    $this->getJson("/api/public/devices/{$device->uuid}/measurements")->assertNotFound();
    $this->getJson("/api/devices/{$device->uuid}/measurements")->assertForbidden();
});

it('does not expose owner-only configuration on public endpoints', function () {
    deviceWithData(['is_public' => true, 'source_url' => 'https://example.com/x.json']);

    $json = $this->getJson('/api/public/devices')->assertOk()->json('data.0');

    expect($json)->not->toHaveKeys(['source_url', 'last_error', 'owner_id', 'has_ingest_token']);
});

it('rejects an unknown period', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $this->getJson("/api/public/devices/{$device->uuid}/measurements?period=30d")->assertUnprocessable();
});

it('returns 404 for a dataset the device does not have', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $this->getJson("/api/public/devices/{$device->uuid}/measurements?type=co2")->assertNotFound();
});

it('addresses devices by UUIDv7 and never by the sequential id', function () {
    [$device] = deviceWithData(['is_public' => true]);

    expect($device->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    $this->get("/en/devices/{$device->uuid}")->assertOk();
    $this->get("/en/devices/{$device->id}")->assertNotFound();
    $this->getJson("/api/public/devices/{$device->id}")->assertNotFound();
    $this->getJson("/api/public/devices/{$device->uuid}")->assertOk()->assertJsonPath('data.id', $device->uuid);
    $this->get('/en/devices')->assertSee("/devices/{$device->uuid}", false);
});
