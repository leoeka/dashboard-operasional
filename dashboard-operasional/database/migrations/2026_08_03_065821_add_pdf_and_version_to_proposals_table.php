<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->foreignId('mockup_template_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->string('pdf_path')->nullable()->after('status');
            $table->integer('version')->default(1)->after('pdf_path');
            $table->text('ai_reasoning')->nullable()->after('summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            //
        });
    }
};
