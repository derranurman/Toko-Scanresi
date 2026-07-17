<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Kelengkapan: "Stir Saja", "Stir + Boskit", "Stir + Boskit + Spion", dll.
            $table->string('kelengkapan')->nullable()->after('sku');
            // Harga modal (snapshot saat order dibuat) agar tetap konsisten
            $table->decimal('harga_modal', 12, 2)->default(0)->after('kelengkapan');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['kelengkapan', 'harga_modal']);
        });
    }
};
