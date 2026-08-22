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
            $table->boolean('wants_seo')->default(false)->after('description');
            $table->boolean('wants_backlink')->default(false)->after('wants_seo');
            $table->json('seo_requirements')->nullable()->after('wants_backlink');
            $table->json('backlink_requirements')->nullable()->after('seo_requirements');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['wants_seo', 'wants_backlink', 'seo_requirements', 'backlink_requirements']);
        });
    }
};
