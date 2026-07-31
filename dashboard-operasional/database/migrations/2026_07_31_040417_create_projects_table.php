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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // no. proyek, mis. RK-0142
            $table->string('client_name');              // PT ABC
            $table->string('type')->nullable();          // Company Profile, E-commerce, dll
            $table->enum('status', ['request', 'proposal', 'mockup', 'development', 'qa', 'active', 'done'])->default('request');
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->decimal('value', 15, 2)->nullable();
            $table->date('deadline')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
