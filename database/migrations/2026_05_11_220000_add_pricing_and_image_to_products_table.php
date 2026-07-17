<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Path gambar produk (relatif ke disk "public", contoh: "products/xxx.jpg")
            $table->string('image')->nullable()->after('name');

            // Jenis/kategori (free text, contoh: "Aksesoris Motor", "Spare Part")
            $table->string('type')->nullable()->after('description');

            // Harga. Pakai decimal(12,2) aman sampai triliun.
            $table->decimal('purchase_price', 12, 2)->default(0)->after('type');   // Harga Beli
            $table->decimal('reseller_price', 12, 2)->default(0)->after('purchase_price'); // Harga Reseller
            $table->decimal('selling_price',  12, 2)->default(0)->after('reseller_price'); // Harga Jual

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn([
                'image',
                'type',
                'purchase_price',
                'reseller_price',
                'selling_price',
            ]);
        });
    }
};
