<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('project_files')) {
            Schema::rename('project_files', 'mockup_assets');
        }

        if (!Schema::hasTable('mockup_assets')) {
            Schema::create('mockup_assets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->string('original_name');
                $table->string('file_path');
                $table->string('category')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('mime_type')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('mockup_assets', function (Blueprint $table) {
                if (!Schema::hasColumn('mockup_assets', 'file_size')) {
                    $table->unsignedBigInteger('file_size')->nullable()->after('category');
                }
                if (!Schema::hasColumn('mockup_assets', 'mime_type')) {
                    $table->string('mime_type')->nullable()->after('file_size');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mockup_assets');
    }
};

