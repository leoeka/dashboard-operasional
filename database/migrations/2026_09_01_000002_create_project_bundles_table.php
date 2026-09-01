<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_bundle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bundle_path')->nullable();
            $table->string('zip_path')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('built_at')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_bundles');
    }
};
