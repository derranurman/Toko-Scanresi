<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PACKED = 'packed';
    public const STATUS_RETURN = 'return';
    public const STATUS_SELESAI_RETURN = 'selesai_return';
    public const STATUS_SELESAI_BULAN_KEMARIN = 'selesai_bulan_kemarin';

    /** Daftar 5 status yang valid. */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PACKED,
        self::STATUS_RETURN,
        self::STATUS_SELESAI_RETURN,
        self::STATUS_SELESAI_BULAN_KEMARIN,
    ];

    /** Label yang ditampilkan di UI. */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PACKED => 'Packed',
        self::STATUS_RETURN => 'Return',
        self::STATUS_SELESAI_RETURN => 'Selesai Return',
        self::STATUS_SELESAI_BULAN_KEMARIN => 'Selesai Bulan Kemarin',
    ];

    protected $fillable = [
        'tiktok_order_id',
        'resi_number',
        'courier',
        'buyer_name',
        'buyer_phone',
        'host_live',
        'sender_name',
        'platform_deduction_id',
        'shipping_address',
        'status',
        'order_date',
        'packed_at',
        'packed_by_user_id',
        'returned_at',
        'notes',
        'total_potongan_aplikasi_override',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'packed_at' => 'datetime',
            'returned_at' => 'datetime',
            'total_potongan_aplikasi_override' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function packedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by_user_id');
    }

    public function platformDeduction(): BelongsTo
    {
        return $this->belongsTo(PlatformDeduction::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPacked(): bool
    {
        return $this->status === self::STATUS_PACKED;
    }

    public function totalQty(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
