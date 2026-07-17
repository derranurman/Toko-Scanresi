<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah 2 kolom potongan persentase di tabel platform_deductions:
 *
 *  - dynamic_commission_percent (%) — Komisi Dinamis. Persen variabel
 *    yang ditarik marketplace berdasarkan kategori / promo / event.
 *
 *  - platform_commission_percent (%) — Biaya Komisi Platform. Persen
 *    flat komisi platform per transaksi.
 *
 * Disimpan dengan precision sama seperti field persen lain
 * (decimal(8,4) — contoh 8.0000 = 8%).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_deductions', function (Blueprint $table) {
            $table->decimal('dynamic_commission_percent', 8, 4)->default(0)->after('tax_percent');
            $table->decimal('platform_commission_percent', 8, 4)->default(0)->after('dynamic_commission_percent');
        });
    }

    public function down(): void
    {
        Schema::table('platform_deductions', function (Blueprint $table) {
            $table->dropColumn(['dynamic_commission_percent', 'platform_commission_percent']);
        });
    }
};
