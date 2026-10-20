<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Measurement;
use App\Models\MeasurementType;
use App\Models\User;
use App\Support\SystemTypes;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SystemTypes::sync();

        // Demo curve per type: base value and amplitude of the daily-ish wave.
        $curves = ['temperature' => [20, 6], 'humidity' => [60, 15], 'pressure' => [1013, 8], 'wind_speed' => [4, 3], 'co2' => [600, 120],
            'solar_power' => [3, 3], 'voltage' => [230, 3], 'energy_produced' => [1.2, 1.1], 'sap_flow' => [40, 15]];
        $types = MeasurementType::all()->keyBy('key');

        $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        $alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

        // A private type owned by Alice (only she, and admins, can see/use it).
        $types['sap_flow'] = MeasurementType::create(['key' => 'sap_flow', 'name' => 'Sap flow', 'unit' => 'mL/h', 'decimals' => 0, 'color' => '#7a9a01', 'category' => 'water']);
        $types['sap_flow']->forceFill(['created_by' => $alice->id])->save();

        $defs = [
            [$alice, 'Garden station', 'Prague', true, ['temperature', 'humidity', 'pressure']],
            [$alice, 'Attic sensor', 'Prague', false, ['temperature', 'humidity']],
            [$bob, 'Balcony weather', 'Brno', true, ['temperature', 'wind_speed']],
            [$bob, 'Office air quality', 'Brno', false, ['co2', 'temperature']],
            [$alice, 'Rooftop solar plant', 'Prague', true, ['solar_power', 'energy_produced', 'voltage']],
        ];

        foreach ($defs as [$owner, $name, $location, $public, $keys]) {
            $device = Device::factory()->create([
                'user_id' => $owner->id, 'name' => $name, 'location' => $location, 'is_public' => $public,
                'latitude' => $location === 'Prague' ? 50.0755 + mt_rand(-40, 40) / 1000 : 49.1951 + mt_rand(-40, 40) / 1000,
                'longitude' => $location === 'Prague' ? 14.4378 + mt_rand(-40, 40) / 1000 : 16.6068 + mt_rand(-40, 40) / 1000,
            ]);
            $device->types()->attach(collect($keys)->map(fn ($k) => $types[$k]->id));

            $rows = [];
            $start = now()->subDays(7)->startOfHour();
            foreach ($keys as $k) {
                for ($t = $start->copy(), $i = 0; $t <= now(); $t->addMinutes(30), $i++) {
                    // deliberate gaps: ~5% null values and a 6h outage on day 3
                    $gap = $t->between(now()->subDays(4), now()->subDays(4)->addHours(6));
                    if ($gap) {
                        continue;
                    }
                    [$base, $amp] = $curves[$k] ?? [10, 4];
                    $value = mt_rand(0, 99) < 5
                        ? null
                        : round($base + sin($i / 8) * $amp + mt_rand(-10, 10) / 10, 1);
                    if ($value !== null && in_array($k, ['solar_power', 'energy_produced'], true)) {
                        $value = max(0, $value); // a plant never produces a negative amount
                    }
                    $rows[] = [
                        'device_id' => $device->id, 'measurement_type_id' => $types[$k]->id,
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
