<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Laundry',
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}
