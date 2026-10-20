<?php

use App\Models\Measurement;

function tokenFor($device): string
{
    return $device->regenerateIngestToken();
}

it('accepts readings published with a valid device token', function () {
    [$device] = deviceWithData(['is_public' => false]);

    $this->postJson('/api/ingest', ['measurements' => [['type' => 'temperature', 'value' => 21.3, 'measured_at' => '2026-10-17T10:00:00Z']]], ['Authorization' => 'Bearer '.tokenFor($device)])
        ->assertCreated()->assertJson(['accepted' => 1, 'rejected' => 0]);

    $this->assertDatabaseHas('measurements', ['device_id' => $device->id, 'value' => 21.3, 'source' => 'ingest']);
});

it('rejects missing, wrong or revoked tokens', function () {
    [$device] = deviceWithData();
    $old = tokenFor($device);
    tokenFor($device); // regenerate => old one is revoked
    $body = ['measurements' => [['type' => 'temperature', 'value' => 1]]];

    $this->postJson('/api/ingest', $body)->assertUnauthorized();
    $this->postJson('/api/ingest', $body, ['Authorization' => 'Bearer nope'])->assertUnauthorized();
    $this->postJson('/api/ingest', $body, ['Authorization' => 'Bearer '.$old])->assertUnauthorized();
    expect(Measurement::count())->toBe(0);
});

it('does not require a CSRF token because it is token authenticated', function () {
    [$device] = deviceWithData();

    $this->withHeaders(['Authorization' => 'Bearer '.tokenFor($device)])
        ->post('/api/ingest', ['measurements' => [['type' => 'temperature', 'value' => 1]]], ['Accept' => 'application/json'])->assertCreated();
});

it('rejects types that are not assigned to the device', function () {
    [$device] = deviceWithData();

    $this->postJson('/api/ingest', ['measurements' => [['type' => 'co2', 'value' => 400], ['type' => 'temperature', 'value' => 5]]], ['Authorization' => 'Bearer '.tokenFor($device)])
        ->assertCreated()->assertJson(['accepted' => 1, 'rejected' => 1]);
});

it('keeps an explicit null as a missing value, not zero', function () {
    [$device] = deviceWithData();

    $this->postJson('/api/ingest', ['measurements' => [['type' => 'temperature', 'value' => null, 'measured_at' => '2026-10-17T10:00:00Z']]], ['Authorization' => 'Bearer '.tokenFor($device)])->assertCreated();

    expect(Measurement::first()->value)->toBeNull();
});

it('is idempotent for the same timestamp (upsert)', function () {
    [$device] = deviceWithData();
    $h = ['Authorization' => 'Bearer '.tokenFor($device)];
    $row = fn ($v) => ['measurements' => [['type' => 'temperature', 'value' => $v, 'measured_at' => '2026-10-17T10:00:00Z']]];

    $this->postJson('/api/ingest', $row(1), $h);
    $this->postJson('/api/ingest', $row(2), $h);

    expect(Measurement::count())->toBe(1)->and(Measurement::first()->value)->toBe(2.0);
});

it('validates the payload', function () {
    [$device] = deviceWithData();
    $h = ['Authorization' => 'Bearer '.tokenFor($device)];

    $this->postJson('/api/ingest', [], $h)->assertUnprocessable();
    $this->postJson('/api/ingest', ['measurements' => [['type' => 'temperature', 'value' => 'abc']]], $h)->assertUnprocessable();
    $this->postJson('/api/ingest', ['measurements' => [['type' => 'temperature']]], $h)->assertUnprocessable(); // value must be present (null allowed)
});

it('throttles ingest requests', function () {
    [$device] = deviceWithData();
    $h = ['Authorization' => 'Bearer '.tokenFor($device)];
    $body = ['measurements' => [['type' => 'temperature', 'value' => 1]]];

    foreach (range(1, 60) as $_) {
        $this->postJson('/api/ingest', $body, $h);
    }

    $this->postJson('/api/ingest', $body, $h)->assertStatus(429);
});

it('ingested data of a private device stays hidden from the public API', function () {
    [$device] = deviceWithData(['is_public' => false]);
    $this->postJson('/api/ingest', ['measurements' => [['type' => 'temperature', 'value' => 9]]], ['Authorization' => 'Bearer '.tokenFor($device)])->assertCreated();

    $this->getJson("/api/public/devices/{$device->uuid}/measurements")->assertNotFound();
});
