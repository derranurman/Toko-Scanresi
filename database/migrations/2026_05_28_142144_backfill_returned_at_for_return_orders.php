<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill `returned_at` untuk pesanan yang berstatus return/selesai_return
 * tapi `returned_at`-nya null. Ini terjadi pada pesanan yang status-nya
 * di-set ke 'return' lewat dropdown inline di halaman Pesanan (yang dulu
 * tidak meng-set `returned_at`), lalu ditandai 'selesai_return' via tombol
 * "Barang Diterima" di Kelola Return.
 *
 * Akibat dari `returned_at` null, pesanan-pesanan ini tidak muncul di
 * Laporan Return (yang query-nya memfilter berdasarkan `returned_at`).
 *
 * Backfill pakai `updated_at` sebagai approximator paling akurat — saat
 * status berubah ke return, `updated_at` ikut update. Untuk pesanan
 * tanpa `updated_at` (sangat jarang), fallback ke `created_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->whereNull('returned_at')
            ->whereIn('status', ['return', 'selesai_return'])
            ->update([
                'returned_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        // Tidak bisa di-rollback secara presisi karena kita tidak
        // mencatat mana baris yang di-touch. Best-effort no-op.
    }
};
