<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceChart;
use App\Models\MeasurementType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeviceChart> */
class DeviceChartFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'measurement_type_id' => MeasurementType::factory(),
            'kind' => 'line',
            'title' => null,
            'position' => 0,
        ];
    }
}
