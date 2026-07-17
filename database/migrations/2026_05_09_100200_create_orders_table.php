<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('tiktok_order_id')->nullable()->index();
            $table->string('resi_number')->unique(); // nomor resi JNT
            $table->string('courier')->default('JNT');
            $table->string('buyer_name')->nullable();
            $table->string('buyer_phone')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('status')->default('pending'); // pending | packed | selesai_bulan_kemarin | return
            $table->timestamp('order_date')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->foreignId('packed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
