<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sisa data fitur web builder yang dihapus total 21 Oktober 2026
 * (dibangun ulang dari nol menyusul, rancangan milik pemilik):
 *  - proposals: proposal PDF + pilihan mockup klien (0 baris saat dihapus)
 *  - project_bundles: antrean & hasil build ZIP WordPress (0 baris)
 *  - pipeline_checkpoints: titik baca-ulang tiap tahap pembangkitan (3 baris)
 *  - kolom jejak mockup_template_id / ai_generated_content di projects
 * Guard hasTable/hasColumn membuat ini no-op di basis data yang sudah
 * bersih (mis. basis uji sqlite, atau bekas purge_zipwp_remnants).
 * Arsip kode: storage/app/backup/web-builder-dihapus-20261021.tar.gz
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['proposals', 'project_bundles', 'pipeline_checkpoints', 'mockup_templates'] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasColumn('projects', 'mockup_template_id')) {
            Schema::table('projects', function (Blueprint $t) {
                $t->dropForeign(['mockup_template_id']);
                $t->dropColumn('mockup_template_id');
            });
        }

        if (Schema::hasColumn('projects', 'ai_generated_content')) {
            Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('ai_generated_content'));
        }
    }

    public function down(): void
    {
        // Sengaja tanpa jalan balik: datanya kosong, kodenya diarsip.
    }
};
