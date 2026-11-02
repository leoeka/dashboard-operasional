<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per proses pembangunan tema WordPress. Statusnya menahan ZIP di
 * meja periksa: "ready" hanya boleh ditulis setelah hasil periksa lolos, dan
 * hasil periksa (json) disimpan apa adanya supaya tim bisa membaca alasan
 * "gagal-periksa" tanpa membuka log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_builds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mockup_candidate_id')->constrained('mockup_candidates')->cascadeOnDelete();
            // Jalur folder/berkas relatif terhadap storage/app, bukan absolut.
            $table->string('folder')->nullable();
            $table->string('zip_path')->nullable();
            // pending -> building -> inspection -> ready | inspection_failed
            $table->string('status', 20)->default('pending');
            $table->json('inspection_results')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_builds');
    }
};
