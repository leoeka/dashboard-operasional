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
        Schema::table('mockup_templates', function (Blueprint $table) {
            Schema::table('mockup_templates', function (Blueprint $table) {
                $table->string('site_uuid')->nullable()->after('source_url');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mockup_templates', function (Blueprint $table) {
            //
        });
    }
};
