<?php

namespace Database\Seeders;

use App\Models\ComboMapping;
use App\Models\Variant;
use Illuminate\Database\Seeder;

class ComboMappingSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            [
                'keyword' => 'Stir+Bosskit',
                'description' => 'Paket stir + boskit standar.',
                'items' => [
                    ['sku' => 'STIR-SPR-BLK', 'qty' => 1],
                    ['sku' => 'BSK-MTR-STD',  'qty' => 1],
                ],
            ],
            [
                'keyword' => '+Bosskit Ferio',
                'description' => 'Tambahan boskit Ferio dari Seller Note (T2/T16/dll).',
                'items' => [
                    ['sku' => 'BSK-MTR-FER', 'qty' => 1],
                ],
            ],
            [
                'keyword' => 'Stir+Bosskit Ferio',
                'description' => 'Paket stir + boskit Ferio (langsung combo lengkap).',
                'items' => [
                    ['sku' => 'STIR-SPR-BLK', 'qty' => 1],
                    ['sku' => 'BSK-MTR-FER',  'qty' => 1],
                ],
            ],
        ];

        foreach ($samples as $sample) {
            $mapping = ComboMapping::updateOrCreate(
                ['keyword' => $sample['keyword']],
                ['description' => $sample['description']],
            );

            $mapping->items()->delete();

            foreach ($sample['items'] as $it) {
                $variant = Variant::where('sku', $it['sku'])->first();
                if (! $variant) {
                    continue;
                }
                $mapping->items()->create([
                    'variant_id' => $variant->id,
                    'quantity' => $it['qty'],
                ]);
            }
        }
    }
}
