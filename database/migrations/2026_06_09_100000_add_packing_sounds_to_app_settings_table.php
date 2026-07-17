<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah path suara notifikasi packing yang bisa di-upload admin lewat
 * menu Pengaturan. Tiap kolom menyimpan path relatif di disk public
 * (sama pattern dengan logo_path). Kalau null → halaman scan otomatis
 * pakai suara default di public/sounds/*.wav.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('sound_ok_path')->nullable()->after('logo_path');
            $table->string('sound_dup_path')->nullable()->after('sound_ok_path');
            $table->string('sound_err_path')->nullable()->after('sound_dup_path');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['sound_ok_path', 'sound_dup_path', 'sound_err_path']);
        });
    }
};
