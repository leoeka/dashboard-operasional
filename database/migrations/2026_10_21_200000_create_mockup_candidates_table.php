<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per kandidat mockup. Regenerasi menambah baris, bukan menimpa,
 * jadi (project_id, candidate_number) sengaja TIDAK unik: angka 1-3 dimulai
 * ulang pada tiap generasi, dan kandidat lama tetap hidup sebagai jejak.
 *
 * Baris lahir di awal loop AI sebagai klaim kerja (status draft), bukan hanya
 * setelah sukses - kalau loop gagal, barisnya yang menyimpan alasan gagalnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mockup_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('candidate_number');
            $table->string('idea_sentence')->nullable();
            // Jalur folder relatif terhadap storage/app, bukan absolut.
            $table->string('folder')->nullable();
            // draft -> awaiting_review -> team_approved -> sent -> chosen|rejected
            $table->string('status', 20)->default('draft');
            $table->string('png_path')->nullable();
            // Perbaikan = berapa ronde kritik yang sudah dijalani kandidat ini.
            $table->unsignedTinyInteger('critique_rounds')->default(0);
            $table->string('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mockup_candidates');
    }
};
