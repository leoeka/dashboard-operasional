<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('design_reference_type', 20)->nullable()->after('description');
            $table->string('design_reference_url')->nullable()->after('design_reference_type');
            $table->string('design_reference_path')->nullable()->after('design_reference_url');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['design_reference_type', 'design_reference_url', 'design_reference_path']);
        });
    }
};
