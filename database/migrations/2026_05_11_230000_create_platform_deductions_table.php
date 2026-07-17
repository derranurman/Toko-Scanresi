<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Potongan / biaya platform marketplace (TikTok, Shopee, Tokopedia, dll).
        // Admin bisa tambah / edit / hapus. Dipakai nanti buat hitung net profit
        // per pesanan (harga jual - potongan platform - HPP).
        //
        // Tipe angka:
        //   - *_percent:  disimpan sebagai decimal(8,4), artinya persen (0-100).
        //                 Contoh 8.0000 = 8%, 0.5000 = 0.5%
        //   - *_amount:   disimpan sebagai decimal(12,2), artinya nominal Rupiah.
        Schema::create('platform_deductions', function (Blueprint $table) {
            $table->id();
            $table->string('platform_name');                       // "TikTok Ranco"
            $table->decimal('adm_percent', 8, 4)->default(0);      // ADM (%)
            $table->decimal('cashback_percent', 8, 4)->default(0); // CB/BP (%)
            $table->decimal('free_shipping_percent', 8, 4)->default(0); // Ongkir Free (%)
            $table->decimal('shipping_cargo_amount', 12, 2)->default(0); // Ongkir Cargo (Rp)
            $table->decimal('label_amount', 12, 2)->default(0);    // Label (Rp)
            $table->decimal('yield_percent', 8, 4)->default(0);    // Yield (%)
            $table->decimal('packaging_amount', 12, 2)->default(0); // Plastik/Lakban/Dus (Rp)
            $table->decimal('operational_percent', 8, 4)->default(0); // Operasional (%)
            $table->decimal('service_fee_amount', 12, 2)->default(0); // Biaya Layanan (Rp)
            $table->decimal('logistics_amount', 12, 2)->default(0);   // Biaya Logistik (Rp)
            $table->decimal('tax_percent', 8, 4)->default(0);         // Pajak (%)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_deductions');
    }
};
