<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mapping dari teks "Barang" di label → daftar varian + qty
        // Contoh: keyword "Stir+Bosskit" → [Stir Sparco Hitam x1, Boskit Standar x1]
        Schema::create('combo_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->unique(); // teks persis yang muncul di label
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('combo_mapping_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_mapping_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        // Staging hasil parse PDF sebelum admin konfirmasi simpan
        Schema::create('pdf_parse_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->integer('total_pages')->default(0);
            $table->string('status')->default('draft'); // draft | committed | discarded
            $table->json('parsed_orders'); // array of parsed order hashes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_mapping_items');
        Schema::dropIfExists('combo_mappings');
        Schema::dropIfExists('pdf_parse_drafts');
    }
};
