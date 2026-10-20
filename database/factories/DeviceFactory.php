<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Device> */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'location' => fake()->city(),
            'is_public' => false,
            'fetch_interval_min' => 15,
        ];
    }

    public function public(): static
    {
        return $this->state(['is_public' => true]);
    }

    public function private(): static
    {
        return $this->state(['is_public' => false]);
    }
}
