<?php

use App\Models\Measurement;

function point($device, $type, $value, $when)
{
    return Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => $value, 'measured_at' => $when, 'source' => 'seed']);
}

it('filters by period using one dataset for chart and table (A3)', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    point($device, $type, 1, now()->subHours(2));
    point($device, $type, 2, now()->subDays(3));
    point($device, $type, 3, now()->subDays(10));

    $this->getJson("/api/public/devices/{$device->uuid}/measurements?period=24h")->assertJsonPath('summary.count', 1);
    $this->getJson("/api/public/devices/{$device->uuid}/measurements?period=7d")->assertJsonPath('summary.count', 2);
});

it('keeps missing values as null and never as zero (A4)', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    point($device, $type, null, now()->subHours(3));
    point($device, $type, 12.5, now()->subHours(2));

    $res = $this->getJson("/api/public/devices/{$device->uuid}/measurements")->json();

    expect($res['data'][0]['value'])->toBeNull()
        ->and($res['summary']['missing'])->toBe(1)
        ->and($res['summary']['latest']['value'])->toBe(12.5);
});

it('returns an empty list for an empty period instead of zeros (A4)', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $this->getJson("/api/public/devices/{$device->uuid}/measurements")
        ->assertOk()->assertJsonPath('summary.count', 0)->assertJsonPath('summary.latest', null)->assertJsonPath('data', []);
});

it('reports an unavailable data source with an understandable message (A4)', function () {
    [$device] = deviceWithData(['is_public' => true, 'last_error' => 'Data source responded with HTTP 503.']);

    $this->getJson("/api/public/devices/{$device->uuid}/measurements")
        ->assertJsonPath('source_status.ok', false)->assertJsonPath('source_status.message', 'Data source responded with HTTP 503.');
});

it('can switch between datasets of one device (A1)', function () {
    [$device, $temp] = deviceWithData(['is_public' => true]);
    $hum = \App\Models\MeasurementType::create(['key' => 'humidity', 'name' => 'Humidity', 'unit' => '%']);
    $device->types()->attach($hum);
    point($device, $temp, 20, now()->subHour());
    point($device, $hum, 55, now()->subHour());

    $this->getJson("/api/public/devices/{$device->uuid}/measurements?type=temperature")->assertJsonPath('data.0.unit', '°C')->assertJsonPath('data.0.value', 20);
    $this->getJson("/api/public/devices/{$device->uuid}/measurements?type=humidity")->assertJsonPath('data.0.unit', '%')->assertJsonPath('data.0.value', 55);
});
