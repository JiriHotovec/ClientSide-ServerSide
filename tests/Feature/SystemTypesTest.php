<?php

use App\Models\MeasurementType;
use App\Support\Categories;
use App\Support\SystemTypes;

it('creates the built-in types as global system types', function () {
    $created = SystemTypes::sync();

    expect($created)->toBe(count(SystemTypes::LIST))->and(MeasurementType::where('is_system', true)->where('is_public', true)->count())->toBe($created);
});

it('covers weather, air, water and soil, electricity and solar power', function () {
    foreach (['temperature', 'humidity', 'pressure', 'wind_speed', 'wind_direction', 'rain', 'uv_index', 'co2', 'pm2_5', 'soil_moisture',
        'electricity_meter', 'energy_consumed', 'energy_produced', 'solar_power', 'power', 'voltage', 'gas_meter', 'water_consumption'] as $key) {
        expect(SystemTypes::LIST)->toHaveKey($key);
    }
    expect(count(SystemTypes::LIST))->toBeGreaterThanOrEqual(40);
});

it('is idempotent and keeps edits made by administrators', function () {
    SystemTypes::sync();
    MeasurementType::where('key', 'temperature')->update(['unit' => '°F', 'decimals' => 3]);
    MeasurementType::where('key', 'power')->delete();

    expect(SystemTypes::sync())->toBe(1)->and(SystemTypes::sync())->toBe(0);
    expect(MeasurementType::where('key', 'temperature')->first())->unit->toBe('°F')->decimals->toBe(3);
});

it('uses valid categories, unique keys and sane axis ranges', function () {
    foreach (SystemTypes::LIST as $key => [$name, $unit, $category, $decimals, $color, $chart, $min, $max]) {
        expect($key)->toMatch('/^[a-z0-9_]+$/')->and(strlen($key))->toBeLessThanOrEqual(40)
            ->and(Categories::keys())->toContain($category)
            ->and($decimals)->toBeBetween(0, 6)
            ->and($color)->toMatch('/^#[0-9a-f]{6}$/')
            ->and(['line', 'bar'])->toContain($chart)
            ->and(strlen($unit))->toBeLessThanOrEqual(20);
        if ($min !== null && $max !== null) {
            expect($max)->toBeGreaterThan($min);
        }
    }
});

it('has a Czech and an English name for every built-in type', function () {
    foreach (array_keys(SystemTypes::LIST) as $key) {
        $cs = require lang_path('cs/measurement.php');
        $en = require lang_path('en/measurement.php');
        expect($cs['types'])->toHaveKey($key)->and($en['types'][$key])->toBe(SystemTypes::LIST[$key][0]);
    }
});

it('shows the translated name of a built-in type', function () {
    SystemTypes::sync();
    $type = MeasurementType::where('key', 'electricity_meter')->first();

    expect($type->name)->toBe('Electricity meter reading');
    app()->setLocale('cs');
    expect($type->fresh()->name)->toBe('Stav elektroměru');
});

it('adds missing types through the artisan command', function () {
    $this->artisan('meteoview:sync-types')->expectsOutputToContain(count(SystemTypes::LIST).' measurement type(s) added.')->assertSuccessful();
    $this->artisan('meteoview:sync-types')->expectsOutputToContain('0 measurement type(s) added.');
});
