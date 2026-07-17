<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalisasi status pesanan.
 *
 * Status valid sekarang hanya 4:
 *   - pending
 *   - packed
 *   - selesai_bulan_kemarin (sebelumnya "selesai")
 *   - return
 *
 * Migrasi data:
 *   selesai   → selesai_bulan_kemarin
 *   cancelled → pending  (Cancelled dihapus dari domain status pesanan)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->where('status', 'selesai')
            ->update(['status' => 'selesai_bulan_kemarin']);

        DB::table('orders')->where('status', 'cancelled')
            ->update(['status' => 'pending']);
    }

    public function down(): void
    {
        // Best-effort rollback. "cancelled" yang sudah berubah jadi "pending"
        // tidak bisa dikembalikan persis (kita tidak tahu mana yang asal cancelled).
        DB::table('orders')->where('status', 'selesai_bulan_kemarin')
            ->update(['status' => 'selesai']);
    }
};
