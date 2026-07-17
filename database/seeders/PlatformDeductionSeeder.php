<?php

namespace Database\Seeders;

use App\Models\PlatformDeduction;
use Illuminate\Database\Seeder;

class PlatformDeductionSeeder extends Seeder
{
    public function run(): void
    {
        PlatformDeduction::updateOrCreate(
            ['platform_name' => 'TikTok Ranco'],
            [
                'adm_percent' => 8,
                'cashback_percent' => 2,
                'free_shipping_percent' => 5.5,
                'shipping_cargo_amount' => 10000,
                'label_amount' => 500,
                'yield_percent' => 3,
                'packaging_amount' => 2000,
                'operational_percent' => 8,
                'service_fee_amount' => 1250,
                'logistics_amount' => 5350,
                'tax_percent' => 0.5,
                'is_active' => true,
            ],
        );
    }
}
