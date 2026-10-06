<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderTrackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'updated_by' => User::factory(),
            'status' => fake()->randomElement(['Received', 'Washing', 'Drying', 'Ironing', 'Ready']),
            'notes' => fake()->sentence(),
        ];
    }
}
