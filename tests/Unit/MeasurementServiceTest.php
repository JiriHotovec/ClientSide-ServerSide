<?php

use App\Models\Measurement;
use App\Services\MeasurementService;

it('builds a series with summary, units and source for every row', function () {
    [$device, $type] = deviceWithData();
    Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => 4.5, 'measured_at' => now()->subHour(), 'source' => 'pull']);

    $s = app(MeasurementService::class)->series($device, $type, '24h');

    expect($s['data'][0])->toMatchArray(['value' => 4.5, 'unit' => '°C', 'source' => 'pull'])
        ->and($s['summary'])->toMatchArray(['count' => 1, 'missing' => 0])
        ->and($s['source_status']['ok'])->toBeTrue();
});

it('has no latest value when every reading is missing', function () {
    [$device, $type] = deviceWithData();
    Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => null, 'measured_at' => now()->subHour(), 'source' => 'ingest']);

    $s = app(MeasurementService::class)->series($device, $type, '7d');

    expect($s['summary']['latest'])->toBeNull()->and($s['summary']['missing'])->toBe(1);
});
