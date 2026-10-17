<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Measurement;
use App\Models\MeasurementType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // key, name, unit, base, amplitude, decimals, color, chart type
        $types = collect([
            ['temperature', 'Temperature', '°C', 20, 6, 1, '#e4572e', 'line'],
            ['humidity', 'Relative humidity', '%', 60, 15, 0, '#0077b6', 'line'],
            ['pressure', 'Atmospheric pressure', 'hPa', 1013, 8, 1, '#6a4c93', 'line'],
            ['wind_speed', 'Wind speed', 'm/s', 4, 3, 1, '#2a9d8f', 'bar'],
            ['co2', 'CO₂ concentration', 'ppm', 600, 120, 0, '#8d6e63', 'line'],
        ])->mapWithKeys(function ($t) {
            $m = MeasurementType::create(['key' => $t[0], 'name' => $t[1], 'unit' => $t[2], 'decimals' => $t[5], 'color' => $t[6], 'chart_type' => $t[7]]);
            $m->forceFill(['is_system' => true, 'is_public' => true])->save();

            return [$t[0] => ['model' => $m, 'base' => $t[3], 'amp' => $t[4]]];
        });

        $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        $alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

        // A private type owned by Alice (only she, and admins, can see/use it).
        MeasurementType::create(['key' => 'soil_moisture', 'name' => 'Soil moisture', 'unit' => '%', 'decimals' => 0, 'color' => '#7a9a01'])
            ->forceFill(['created_by' => $alice->id])->save();

        $defs = [
            [$alice, 'Garden station', 'Prague', true, ['temperature', 'humidity', 'pressure']],
            [$alice, 'Attic sensor', 'Prague', false, ['temperature', 'humidity']],
            [$bob, 'Balcony weather', 'Brno', true, ['temperature', 'wind_speed']],
            [$bob, 'Office air quality', 'Brno', false, ['co2', 'temperature']],
        ];

        foreach ($defs as [$owner, $name, $location, $public, $keys]) {
            $device = Device::factory()->create([
                'user_id' => $owner->id, 'name' => $name, 'location' => $location, 'is_public' => $public,
            ]);
            $device->types()->attach(collect($keys)->map(fn ($k) => $types[$k]['model']->id));

            $rows = [];
            $start = now()->subDays(7)->startOfHour();
            foreach ($keys as $k) {
                for ($t = $start->copy(), $i = 0; $t <= now(); $t->addMinutes(30), $i++) {
                    // deliberate gaps: ~5% null values and a 6h outage on day 3
                    $gap = $t->between(now()->subDays(4), now()->subDays(4)->addHours(6));
                    if ($gap) {
                        continue;
                    }
                    $value = mt_rand(0, 99) < 5
                        ? null
                        : round($types[$k]['base'] + sin($i / 8) * $types[$k]['amp'] + mt_rand(-10, 10) / 10, 1);
                    $rows[] = [
                        'device_id' => $device->id, 'measurement_type_id' => $types[$k]['model']->id,
                        'value' => $value, 'measured_at' => $t->toDateTimeString(), 'source' => 'seed',
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                Measurement::insert($chunk);
            }
        }
    }
}
