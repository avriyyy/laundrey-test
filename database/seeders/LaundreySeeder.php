<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderTrack;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LaundreySeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ServiceSeeder::class);

        $admin = User::create([
            'name' => 'Kasir Laundrey',
            'email' => 'admin@laundrey.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'phone' => '081111111111',
        ]);

        $pelanggan = User::create([
            'name' => 'Budi Pelanggan',
            'email' => 'budi@laundrey.test',
            'password' => Hash::make('password123'),
            'role' => 'pelanggan',
            'phone' => '083333333333',
        ]);

        $services = Service::all();

        foreach (range(1, 8) as $i) {
            $service = $services->random();
            $weight = fake()->randomFloat(1, 1, 6);

            $order = Order::create([
                'invoice_number' => 'INV-'.now()->format('Ymd').'-00'.$i,
                'user_id' => $pelanggan->id,
                'service_id' => $service->id,
                'weight_or_qty' => $weight,
                'total_price' => $weight * (float) $service->price_per_unit,
                'payment_status' => $i % 3 === 0 ? 'paid' : 'unpaid',
                'current_status' => ['Received', 'Washing', 'Drying', 'Ready'][$i % 4],
            ]);

            OrderTrack::create([
                'order_id' => $order->id,
                'updated_by' => $admin->id,
                'status' => 'Received',
                'notes' => 'Order received at counter',
            ]);
        }
    }
}
