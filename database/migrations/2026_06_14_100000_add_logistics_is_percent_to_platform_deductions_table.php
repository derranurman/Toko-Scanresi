<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah flag `logistics_is_percent` di tabel platform_deductions.
 *
 * Saat true, kolom `logistics_amount` diperlakukan sebagai PERSEN (%) dari
 * Total Jual (dihitung di OrderMetricsService). Saat false (default), tetap
 * sebagai nominal Rupiah seperti semula. Ini bikin "Biaya Logistik" bisa
 * diisi pakai % atau nominal sesuai kebutuhan platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_deductions', function (Blueprint $table) {
            $table->boolean('logistics_is_percent')->default(false)->after('logistics_amount');
        });
    }

    public function down(): void
    {
        Schema::table('platform_deductions', function (Blueprint $table) {
            $table->dropColumn('logistics_is_percent');
        });
    }
};
