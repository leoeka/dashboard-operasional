<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZipWP sudah pensiun total. Buang sisa-sisanya:
 *  - kolom zipwp_* di tabel projects
 *  - kolom FK mockup_template_id di projects & proposals (menunjuk ke
 *    tabel galeri template ZipWP yang tidak dipakai lagi)
 *  - tabel mockup_templates (tanpa model, tanpa kode yang membacanya —
 *    MockupController mengembalikan daftar kosong)
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['projects', 'proposals'] as $table) {
            if (Schema::hasColumn($table, 'mockup_template_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['mockup_template_id']);
                    $t->dropColumn('mockup_template_id');
                });
            }
        }

        $zipwpCols = array_values(array_filter(
            ['zipwp_template_uuid', 'zipwp_template_name', 'zipwp_template_preview_url', 'zipwp_site_uuid', 'zipwp_site_url'],
            fn ($col) => Schema::hasColumn('projects', $col)
        ));

        if ($zipwpCols !== []) {
            Schema::table('projects', fn (Blueprint $t) => $t->dropColumn($zipwpCols));
        }

        Schema::dropIfExists('mockup_templates');
    }

    public function down(): void
    {
        Schema::create('mockup_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('preview_image')->nullable();
            $table->string('theme_slug')->nullable();
            $table->string('source_url')->nullable();
            $table->string('site_uuid')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('zipwp_deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('mockup_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zipwp_template_uuid')->nullable();
            $table->string('zipwp_template_name')->nullable();
            $table->string('zipwp_template_preview_url')->nullable();
            $table->string('zipwp_site_uuid')->nullable();
            $table->string('zipwp_site_url')->nullable();
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->foreignId('mockup_template_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
