<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per proposal PDF yang dihasilkan untuk klien. Regenerasi proposal
 * menambah baris - PDF lama tetap ada supaya tim bisa membandingkan versi.
 *
 * chosen_candidate_id dipilih tim di dasbor setelah klien mengembalikan PDF
 * yang sudah dicoret. nullOnDelete: kandidat boleh hilang (project dihapus)
 * tanpa menyeret riwayat proposalnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chosen_candidate_id')->nullable()->constrained('mockup_candidates')->nullOnDelete();
            // Jalur berkas relatif terhadap storage/app, bukan absolut.
            $table->string('pdf_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('chosen_at')->nullable();

            $table->timestamps();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_proposals');
    }
};
