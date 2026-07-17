<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `returned_at` di tabel orders.
 *
 * Tujuan: penanda permanen bahwa pesanan pernah di-mark sebagai return.
 * Status pesanan bisa pindah (mis. dari "return" jadi "selesai_bulan_kemarin"
 * setelah barang diterima), tapi `returned_at` tetap ada — sehingga
 * Laporan Return tetap menampilkan pesanan tersebut.
 *
 * - markReturn()    -> set returned_at = now()
 * - undoReturn()    -> set returned_at = null (batal return)
 * - receiveItems()  -> JANGAN ubah returned_at (return tetap tercatat)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('returned_at')->nullable()->after('packed_at');
            $table->index('returned_at');
        });

        // Backfill: pesanan yang status-nya 'return' sekarang dianggap
        // baru saja di-return (kita tidak punya histori jam pasti).
        DB::table('orders')
            ->where('status', 'return')
            ->whereNull('returned_at')
            ->update(['returned_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['returned_at']);
            $table->dropColumn('returned_at');
        });
    }
};
