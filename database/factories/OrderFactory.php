<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::first() ?? Tenant::factory()->create();
        $service = Service::where('tenant_id', $tenant->id)->inRandomOrder()->first()
            ?? Service::factory()->create(['tenant_id' => $tenant->id]);
        $weight = fake()->randomFloat(1, 1, 10);

        return [
            'invoice_number' => $tenant->prefix.'-'.now()->format('Ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create(['tenant_id' => $tenant->id])->id,
            'service_id' => $service->id,
            'weight_or_qty' => $weight,
            'total_price' => $weight * (float) $service->price_per_unit,
            'payment_status' => fake()->randomElement(['unpaid', 'paid']),
            'current_status' => fake()->randomElement(['Received', 'Washing', 'Drying', 'Ironing', 'Ready']),
        ];
    }
}
