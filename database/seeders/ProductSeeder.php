<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Stir Skeleton',
                'sku' => 'STIR-SKL',
                'description' => 'Stir motor model skeleton.',
                'type' => 'Stir Motor',
                'purchase_price' => 85000,
                'reseller_price' => 110000,
                'selling_price' => 135000,
                'variants' => [
                    ['name' => 'Merah', 'sku' => 'STIR-SKL-RED', 'stock' => 20, 'min_stock' => 3],
                    ['name' => 'Hitam', 'sku' => 'STIR-SKL-BLK', 'stock' => 25, 'min_stock' => 3],
                    ['name' => 'Biru',  'sku' => 'STIR-SKL-BLU', 'stock' => 15, 'min_stock' => 3],
                ],
            ],
            [
                'name' => 'Stir Racing Sparco R13',
                'sku' => 'STIR-SPR',
                'description' => 'Stir racing import Sparco R13" Universal.',
                'type' => 'Stir Mobil',
                'purchase_price' => 250000,
                'reseller_price' => 300000,
                'selling_price' => 375000,
                'variants' => [
                    ['name' => 'Hitam', 'sku' => 'STIR-SPR-BLK', 'stock' => 20, 'min_stock' => 3],
                    ['name' => 'Merah', 'sku' => 'STIR-SPR-RED', 'stock' => 15, 'min_stock' => 3],
                ],
            ],
            [
                'name' => 'Boskit Motor',
                'sku' => 'BSK-MTR',
                'description' => 'Boskit motor universal.',
                'type' => 'Boskit',
                'purchase_price' => 45000,
                'reseller_price' => 60000,
                'selling_price' => 75000,
                'variants' => [
                    ['name' => 'Standar', 'sku' => 'BSK-MTR-STD', 'stock' => 40, 'min_stock' => 5],
                    ['name' => 'Ferio',   'sku' => 'BSK-MTR-FER', 'stock' => 25, 'min_stock' => 5],
                ],
            ],
            [
                'name' => 'Spion Bar End',
                'sku' => 'SPN-BE',
                'description' => 'Spion bar end untuk motor.',
                'type' => 'Spion',
                'purchase_price' => 65000,
                'reseller_price' => 85000,
                'selling_price' => 110000,
                'variants' => [
                    ['name' => 'Hitam', 'sku' => 'SPN-BE-BLK', 'stock' => 30, 'min_stock' => 4],
                    ['name' => 'Silver', 'sku' => 'SPN-BE-SLV', 'stock' => 18, 'min_stock' => 4],
                ],
            ],
            [
                'name' => 'Spoiler Belakang Universal',
                'sku' => 'SPL-UNV',
                'description' => 'Spoiler belakang model universal.',
                'type' => 'Spoiler',
                'purchase_price' => 120000,
                'reseller_price' => 160000,
                'selling_price' => 200000,
                'variants' => [
                    ['name' => 'Hitam Doff', 'sku' => 'SPL-UNV-BLK', 'stock' => 15, 'min_stock' => 3],
                    ['name' => 'Carbon',     'sku' => 'SPL-UNV-CBN', 'stock' => 10, 'min_stock' => 3],
                ],
            ],
            [
                'name' => 'Klakson Denso Keong',
                'sku' => 'KLK-DNS',
                'description' => 'Klakson Denso tipe keong kencang.',
                'type' => 'Klakson',
                'purchase_price' => 55000,
                'reseller_price' => 75000,
                'selling_price' => 95000,
                'variants' => [
                    ['name' => 'Merah',  'sku' => 'KLK-DNS-RED', 'stock' => 20, 'min_stock' => 4],
                    ['name' => 'Chrome', 'sku' => 'KLK-DNS-CHR', 'stock' => 15, 'min_stock' => 4],
                ],
            ],
        ];

        foreach ($products as $data) {
            $variants = $data['variants'];
            unset($data['variants']);

            $product = Product::updateOrCreate(
                ['sku' => $data['sku']],
                $data + ['is_active' => true],
            );

            foreach ($variants as $v) {
                Variant::updateOrCreate(
                    ['sku' => $v['sku']],
                    $v + ['product_id' => $product->id],
                );
            }
        }
    }
}
