<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot harga SATUAN (per 1 qty) saat pesanan dibuat.
     *
     * Tujuan: metrik profit/margin pesanan historis tidak ikut berubah
     * ketika harga master Product di-edit kemudian. Tanpa snapshot,
     * OrderMetricsService membaca harga Product saat ini, sehingga profit
     * pesanan lama bergeser tiap kali harga produk diperbarui.
     *
     * NULL = pesanan lama (dibuat sebelum fitur ini) → metrik fallback ke
     * harga Product saat ini, persis seperti perilaku sebelumnya.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('selling_price', 12, 2)->nullable()->after('harga_modal');
            $table->decimal('purchase_price', 12, 2)->nullable()->after('selling_price');
            $table->decimal('reseller_price', 12, 2)->nullable()->after('purchase_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['selling_price', 'purchase_price', 'reseller_price']);
        });
    }
};
