<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'sku',
        'description',
        'type',
        'purchase_price',
        'reseller_price',
        'selling_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'purchase_price' => 'decimal:2',
            'reseller_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    public function totalStock(): int
    {
        return (int) $this->variants()->sum('stock');
    }

    /**
     * Profit kotor = Harga Jual - Harga Beli.
     */
    public function grossProfit(): float
    {
        return (float) $this->selling_price - (float) $this->purchase_price;
    }

    /**
     * URL publik gambar (pakai disk "public"). Null kalau belum upload.
     */
    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return Storage::disk('public')->url($this->image);
    }
}
