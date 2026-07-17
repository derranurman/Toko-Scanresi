<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah flag is_percent untuk tiap field potongan supaya tiap biaya
     * bisa diisi sebagai persen (%) ATAU nominal (Rp) lewat form
     * "Kelola Potongan Platform".
     *
     * Default disetel agar perilaku data lama TIDAK berubah:
     *   - Field yang dulunya persen  -> default true  (%)
     *   - Field yang dulunya nominal -> default false (Rp)
     */
    public function up(): void
    {
        Schema::table('platform_deductions', function (Blueprint $table) {
            $table->boolean('adm_is_percent')->default(true)->after('adm_percent');
            $table->boolean('cashback_is_percent')->default(true)->after('cashback_percent');
            $table->boolean('free_shipping_is_percent')->default(true)->after('free_shipping_percent');
            $table->boolean('yield_is_percent')->default(true)->after('yield_percent');
            $table->boolean('operational_is_percent')->default(true)->after('operational_percent');
            $table->boolean('tax_is_percent')->default(true)->after('tax_percent');
            $table->boolean('dynamic_commission_is_percent')->default(true)->after('dynamic_commission_percent');
            $table->boolean('platform_commission_is_percent')->default(true)->after('platform_commission_percent');

            $table->boolean('shipping_cargo_is_percent')->default(false)->after('shipping_cargo_amount');
            $table->boolean('label_is_percent')->default(false)->after('label_amount');
            $table->boolean('packaging_is_percent')->default(false)->after('packaging_amount');
            $table->boolean('service_fee_is_percent')->default(false)->after('service_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('platform_deductions', function (Blueprint $table) {
            $table->dropColumn([
                'adm_is_percent',
                'cashback_is_percent',
                'free_shipping_is_percent',
                'yield_is_percent',
                'operational_is_percent',
                'tax_is_percent',
                'dynamic_commission_is_percent',
                'platform_commission_is_percent',
                'shipping_cargo_is_percent',
                'label_is_percent',
                'packaging_is_percent',
                'service_fee_is_percent',
            ]);
        });
    }
};
