<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::first()?->id ?? Tenant::factory()->create()->id,
            'service_name' => fake()->randomElement(['Cuci Kering Reguler', 'Cuci Setrika Express', 'Setrika Saja', 'Cuci Karpet', 'Cuci Sepatu']),
            'price_per_unit' => fake()->randomElement([8000, 10000, 12000, 15000, 25000]),
            'unit_type' => fake()->randomElement(['kg', 'pcs']),
            'estimated_hours' => fake()->randomElement([6, 12, 24, 48]),
        ];
    }
}
