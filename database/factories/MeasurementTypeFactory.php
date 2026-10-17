<?php

namespace Database\Factories;

use App\Models\MeasurementType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeasurementType> */
class MeasurementTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'unit' => 'u',
            'is_system' => false,
        ];
    }
}
