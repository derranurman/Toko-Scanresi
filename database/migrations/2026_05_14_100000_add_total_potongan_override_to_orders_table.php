<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Manual override untuk Total Potongan Aplikasi.
            // NULL = pakai hasil hitung otomatis (ADM + Bulat Max + Biaya Layanan + Biaya Logistik + Pajak).
            // Numeric = nilai manual yang dipakai (override).
            $table->decimal('total_potongan_aplikasi_override', 12, 2)->nullable()
                ->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_potongan_aplikasi_override');
        });
    }
};
