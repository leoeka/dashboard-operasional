<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_bundles') && Schema::hasColumn('project_bundles', 'template_bundle_id')) {
            Schema::table('project_bundles', function (Blueprint $table) {
                $table->dropForeign(['template_bundle_id']);
                $table->dropColumn('template_bundle_id');
            });
        }

        Schema::dropIfExists('project_contents');
        Schema::dropIfExists('project_brands');
        Schema::dropIfExists('template_bundles');
    }

    public function down(): void
    {
        // These tables were scaffolding only and are intentionally not restored.
    }
};