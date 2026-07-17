<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'variant_id',
        'product_name',
        'variant_name',
        'sku',
        'kelengkapan',
        'harga_modal',
        // Snapshot harga satuan saat pesanan dibuat (lihat migration
        // add_price_snapshot_to_order_items_table). NULL = pesanan lama.
        'selling_price',
        'purchase_price',
        'reseller_price',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'harga_modal' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'reseller_price' => 'decimal:2',
        ];
    }

    /**
     * Apakah item ini punya snapshot harga (dibuat setelah fitur snapshot)?
     * Kalau false, metrik fallback ke harga Product master saat ini.
     */
    public function hasPriceSnapshot(): bool
    {
        return $this->selling_price !== null
            || $this->purchase_price !== null
            || $this->reseller_price !== null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function displayName(): string
    {
        return trim($this->product_name.' — '.($this->variant_name ?? ''), ' —');
    }
}
