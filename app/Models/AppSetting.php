<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Singleton settings (1 row only). Hold app_name + logo_path (di disk
 * public). Akses lewat AppSetting::current() — di-cache forever, dan
 * cache di-bust oleh observer di AppServiceProvider tiap kali ada
 * update.
 */
class AppSetting extends Model
{
    protected $fillable = [
        'app_name',
        'logo_path',
        'sound_ok_path',
        'sound_dup_path',
        'sound_err_path',
    ];

    public const CACHE_KEY = 'app_settings:current';

    /**
     * Ambil instance setting (cached forever; di-bust saat update).
     * Kalau row belum ada (misal migrasi belum jalan), return instance
     * default in-memory supaya view tidak crash.
     */
    public static function current(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $row = static::query()->orderBy('id')->first();
            if ($row !== null) {
                return $row;
            }

            // Fallback in-memory (misal migrate belum jalan / table masih
            // kosong). Tidak di-persist ke DB di sini — kontrol penuh di
            // controller yang firstOrCreate sebelum update.
            return new self([
                'app_name' => config('app.name', 'Scaner Toko'),
                'logo_path' => null,
            ]);
        });
    }

    /**
     * Bust cache — dipanggil dari observer setelah save/delete.
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * Mapping jenis suara → kolom path-nya.
     */
    public const SOUND_KINDS = [
        'ok'  => 'sound_ok_path',
        'dup' => 'sound_dup_path',
        'err' => 'sound_err_path',
    ];

    /**
     * File suara default (di public/sounds) per jenis. Dipakai kalau
     * admin belum upload suara custom.
     */
    public const SOUND_DEFAULTS = [
        'ok'  => 'sounds/sukses.wav',
        'dup' => 'sounds/sudah.wav',
        'err' => 'sounds/gagal.wav',
    ];

    /**
     * URL suara untuk satu jenis (ok|dup|err). Pakai file custom hasil
     * upload kalau ada, kalau tidak fallback ke suara default.
     */
    public function soundUrl(string $kind): string
    {
        $column = self::SOUND_KINDS[$kind] ?? null;

        if ($column && $this->{$column}) {
            return Storage::disk('public')->url($this->{$column});
        }

        return asset(self::SOUND_DEFAULTS[$kind] ?? self::SOUND_DEFAULTS['err']);
    }

    /**
     * URL suara custom hasil upload saja (null kalau belum upload).
     * Dipakai di UI pengaturan untuk preview.
     */
    public function customSoundUrl(string $kind): ?string
    {
        $column = self::SOUND_KINDS[$kind] ?? null;

        if ($column && $this->{$column}) {
            return Storage::disk('public')->url($this->{$column});
        }

        return null;
    }

    /**
     * Inisial pertama nama untuk fallback logo (mis. "Scaner Toko" -> "S").
     */
    public function initial(): string
    {
        $name = trim((string) $this->app_name);
        if ($name === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }
}
