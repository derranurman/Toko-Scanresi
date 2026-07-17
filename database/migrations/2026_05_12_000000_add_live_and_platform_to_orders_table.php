<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('host_live')->nullable()->after('buyer_phone');
            $table->string('sender_name')->nullable()->after('host_live');
            $table->foreignId('platform_deduction_id')->nullable()->after('sender_name')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_deduction_id');
            $table->dropColumn(['host_live', 'sender_name']);
        });
    }
};
