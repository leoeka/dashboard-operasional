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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('zipwp_template_uuid')->nullable();
            $table->string('zipwp_template_name')->nullable();
            $table->string('zipwp_template_preview_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['zipwp_template_uuid', 'zipwp_template_name', 'zipwp_template_preview_url']);
        });
    }
};
