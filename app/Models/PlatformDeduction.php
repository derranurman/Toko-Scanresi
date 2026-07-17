<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformDeduction extends Model
{
    use HasFactory;

    /**
     * Pasangan kolom nilai => kolom flag is_percent.
     * Dipakai untuk hitung total & render unit (Rp / %) secara konsisten.
     *
     * @var array<string, string>
     */
    public const FIELD_FLAGS = [
        'adm_percent' => 'adm_is_percent',
        'cashback_percent' => 'cashback_is_percent',
        'free_shipping_percent' => 'free_shipping_is_percent',
        'yield_percent' => 'yield_is_percent',
        'operational_percent' => 'operational_is_percent',
        'tax_percent' => 'tax_is_percent',
        'dynamic_commission_percent' => 'dynamic_commission_is_percent',
        'platform_commission_percent' => 'platform_commission_is_percent',
        'shipping_cargo_amount' => 'shipping_cargo_is_percent',
        'label_amount' => 'label_is_percent',
        'packaging_amount' => 'packaging_is_percent',
        'service_fee_amount' => 'service_fee_is_percent',
        'logistics_amount' => 'logistics_is_percent',
    ];

    protected $fillable = [
        'platform_name',
        'adm_percent',
        'adm_is_percent',
        'cashback_percent',
        'cashback_is_percent',
        'free_shipping_percent',
        'free_shipping_is_percent',
        'shipping_cargo_amount',
        'shipping_cargo_is_percent',
        'label_amount',
        'label_is_percent',
        'yield_percent',
        'yield_is_percent',
        'packaging_amount',
        'packaging_is_percent',
        'operational_percent',
        'operational_is_percent',
        'service_fee_amount',
        'service_fee_is_percent',
        'logistics_amount',
        'logistics_is_percent',
        'tax_percent',
        'tax_is_percent',
        'dynamic_commission_percent',
        'dynamic_commission_is_percent',
        'platform_commission_percent',
        'platform_commission_is_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'adm_percent' => 'decimal:4',
            'adm_is_percent' => 'boolean',
            'cashback_percent' => 'decimal:4',
            'cashback_is_percent' => 'boolean',
            'free_shipping_percent' => 'decimal:4',
            'free_shipping_is_percent' => 'boolean',
            'shipping_cargo_amount' => 'decimal:2',
            'shipping_cargo_is_percent' => 'boolean',
            'label_amount' => 'decimal:2',
            'label_is_percent' => 'boolean',
            'yield_percent' => 'decimal:4',
            'yield_is_percent' => 'boolean',
            'packaging_amount' => 'decimal:2',
            'packaging_is_percent' => 'boolean',
            'operational_percent' => 'decimal:4',
            'operational_is_percent' => 'boolean',
            'service_fee_amount' => 'decimal:2',
            'service_fee_is_percent' => 'boolean',
            'logistics_amount' => 'decimal:2',
            'logistics_is_percent' => 'boolean',
            'tax_percent' => 'decimal:4',
            'tax_is_percent' => 'boolean',
            'dynamic_commission_percent' => 'decimal:4',
            'dynamic_commission_is_percent' => 'boolean',
            'platform_commission_percent' => 'decimal:4',
            'platform_commission_is_percent' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Total semua potongan yang dalam mode persen (untuk ringkasan).
     */
    public function totalPercent(): float
    {
        $sum = 0.0;
        foreach (self::FIELD_FLAGS as $valueCol => $flagCol) {
            if ($this->{$flagCol}) {
                $sum += (float) $this->{$valueCol};
            }
        }

        return $sum;
    }

    /**
     * Total semua potongan yang dalam mode nominal (Rp).
     */
    public function totalAmount(): float
    {
        $sum = 0.0;
        foreach (self::FIELD_FLAGS as $valueCol => $flagCol) {
            if (! $this->{$flagCol}) {
                $sum += (float) $this->{$valueCol};
            }
        }

        return $sum;
    }
}
