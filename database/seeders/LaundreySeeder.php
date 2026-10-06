<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderTrack;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LaundreySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['prefix' => 'INV'],
            ['name' => 'Laundrey']
        );

        $this->call(ServiceSeeder::class);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Laundrey',
            'email' => 'admin@laundrey.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'phone' => '081111111111',
        ]);

        $pelanggan = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Budi Pelanggan',
            'email' => 'budi@laundrey.test',
            'password' => Hash::make('password123'),
            'role' => 'pelanggan',
            'phone' => '083333333333',
        ]);

        $services = Service::where('tenant_id', $tenant->id)->get();

        foreach (range(1, 8) as $i) {
            $service = $services->random();
            $weight = fake()->randomFloat(1, 1, 6);

            $order = Order::create([
                'invoice_number' => 'INV-'.now()->format('Ymd').'-00'.$i,
                'tenant_id' => $tenant->id,
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

        $this->demoTenant();
    }

    private function demoTenant(): void
    {
        User::firstOrCreate(
            ['email' => 'super@laundrey.test'],
            [
                'tenant_id' => null,
                'name' => 'Platform Admin',
                'password' => Hash::make('password123'),
                'role' => 'superadmin',
                'phone' => null,
            ]
        );

        $tenant = Tenant::firstOrCreate(
            ['prefix' => 'KLN'],
            ['name' => 'Klin Laundry']
        );

        User::firstOrCreate(
            ['email' => 'klin@laundrey.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Bu Klin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'phone' => '084444444444',
            ]
        );

        if (Service::where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $express = Service::create([
            'tenant_id' => $tenant->id,
            'service_name' => 'Cuci Express',
            'price_per_unit' => 12000,
            'unit_type' => 'kg',
            'estimated_hours' => 12,
        ]);

        $customer = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Rina Demo',
            'email' => 'walkin-demo@laundrey.local',
            'password' => Hash::make(str()->random(32)),
            'role' => 'pelanggan',
            'phone' => '085555555555',
        ]);

        $order = Order::create([
            'invoice_number' => Order::generateInvoiceNumber($tenant->prefix, $tenant->id),
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
            'service_id' => $express->id,
            'weight_or_qty' => 3,
            'total_price' => 36000,
            'payment_status' => 'unpaid',
            'current_status' => 'Washing',
        ]);

        OrderTrack::create([
            'order_id' => $order->id,
            'updated_by' => User::where('tenant_id', $tenant->id)->where('role', 'admin')->first()->id,
            'status' => 'Received',
            'notes' => 'Order received at counter',
        ]);

        OrderTrack::create([
            'order_id' => $order->id,
            'updated_by' => User::where('tenant_id', $tenant->id)->where('role', 'admin')->first()->id,
            'status' => 'Washing',
            'notes' => null,
        ]);
    }
}
