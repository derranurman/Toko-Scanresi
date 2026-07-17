<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `image` (path foto profil/avatar) di tabel users.
 *
 * Disimpan sebagai relative path di disk "public" (sama dengan kolom
 * `image` di products), supaya bisa di-resolve via
 * Storage::disk('public')->url(...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('image')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
