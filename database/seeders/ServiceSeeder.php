<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = Tenant::first()?->id;

        $daftar = [
            ['service_name' => 'Cuci Kering Reguler', 'price_per_unit' => 8000, 'unit_type' => 'kg', 'estimated_hours' => 48],
            ['service_name' => 'Cuci Setrika Express', 'price_per_unit' => 15000, 'unit_type' => 'kg', 'estimated_hours' => 12],
            ['service_name' => 'Setrika Saja', 'price_per_unit' => 6000, 'unit_type' => 'kg', 'estimated_hours' => 24],
            ['service_name' => 'Cuci Satuan Jas', 'price_per_unit' => 25000, 'unit_type' => 'pcs', 'estimated_hours' => 48],
        ];

        foreach ($daftar as $item) {
            Service::create($item + ['tenant_id' => $tenantId]);
        }
    }
}
