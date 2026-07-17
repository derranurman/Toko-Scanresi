<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel singleton `app_settings`. Menyimpan branding aplikasi yang
 * bisa diedit oleh admin lewat menu Pengaturan: nama aplikasi (judul
 * di top-nav, login page, browser tab) dan logo (image relatif di
 * disk public, sama pattern dengan products.image / users.image).
 *
 * Hanya akan ada 1 baris (id=1). Pakai AppSetting::current() untuk
 * baca, AppSetting::current()->update(...) untuk tulis. Cache
 * di-bust otomatis oleh observer di AppServiceProvider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('Scaner Toko');
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        // Seed initial row.
        DB::table('app_settings')->insert([
            'app_name' => config('app.name', 'Scaner Toko'),
            'logo_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
