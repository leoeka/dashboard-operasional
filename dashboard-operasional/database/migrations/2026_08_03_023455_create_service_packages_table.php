<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('category', ['paket_utama', 'tambahan'])->default('tambahan');
            $table->decimal('price', 15, 2);
            $table->string('unit')->nullable(); // mis. "/Bulan", null kalau one-time
            $table->text('features'); // 1 baris per fitur
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_packages');
    }
};
