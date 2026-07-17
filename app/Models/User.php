<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_PACKING = 'packing';

    protected $fillable = [
        'name',
        'email',
        'username',
        'image',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function packingLogs(): HasMany
    {
        return $this->hasMany(PackingLog::class);
    }

    public function packedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'packed_by_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPacking(): bool
    {
        return $this->role === self::ROLE_PACKING;
    }

    /**
     * URL publik foto profil. Null kalau belum upload (caller bisa
     * fallback ke initials avatar).
     */
    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return Storage::disk('public')->url($this->image);
    }

    /**
     * Inisial nama untuk avatar fallback (mis. "Derra Nurman" -> "DN",
     * "Admin" -> "A"). Maksimum 2 huruf, di-uppercase.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $parts = array_filter($parts);
        if (empty($parts)) {
            return '?';
        }
        $first = mb_strtoupper(mb_substr($parts[0], 0, 1));
        if (count($parts) === 1) {
            return $first;
        }
        $last = mb_strtoupper(mb_substr(end($parts), 0, 1));

        return $first.$last;
    }
}
