<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_seos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('target_url')->nullable();
            $table->string('location')->nullable();
            $table->text('keywords')->nullable();
            $table->text('competitors')->nullable();
            $table->string('cms_platform')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('project_backlinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('target_url')->nullable();
            $table->string('quantity', 50)->nullable();
            $table->text('anchor_text')->nullable();
            $table->string('niche')->nullable();
            $table->json('anchor_type')->nullable();
            $table->string('priority', 50)->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // Pindahkan data existing jika ada kolom seo_requirements / backlink_requirements di projects
        if (Schema::hasColumn('projects', 'seo_requirements')) {
            $projects = DB::table('projects')->whereNotNull('seo_requirements')->get();
            foreach ($projects as $project) {
                $seo = is_string($project->seo_requirements) ? json_decode($project->seo_requirements, true) : (array) $project->seo_requirements;
                if (!empty($seo)) {
                    DB::table('project_seos')->insert([
                        'project_id' => $project->id,
                        'target_url' => $seo['target_url'] ?? null,
                        'location' => $seo['location'] ?? null,
                        'keywords' => is_array($seo['keywords'] ?? null) ? implode(', ', $seo['keywords']) : ($seo['keywords'] ?? null),
                        'competitors' => is_array($seo['competitors'] ?? null) ? implode("\n", $seo['competitors']) : ($seo['competitors'] ?? null),
                        'cms_platform' => $seo['cms_platform'] ?? null,
                        'data' => json_encode($seo),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (Schema::hasColumn('projects', 'backlink_requirements')) {
            $projects = DB::table('projects')->whereNotNull('backlink_requirements')->get();
            foreach ($projects as $project) {
                $bl = is_string($project->backlink_requirements) ? json_decode($project->backlink_requirements, true) : (array) $project->backlink_requirements;
                if (!empty($bl)) {
                    DB::table('project_backlinks')->insert([
                        'project_id' => $project->id,
                        'target_url' => $bl['target_url'] ?? null,
                        'quantity' => isset($bl['quantity']) ? (string) $bl['quantity'] : null,
                        'anchor_text' => is_array($bl['anchor_text'] ?? null) ? implode(', ', $bl['anchor_text']) : ($bl['anchor_text'] ?? null),
                        'niche' => $bl['niche'] ?? null,
                        'anchor_type' => isset($bl['anchor_type']) ? json_encode($bl['anchor_type']) : null,
                        'priority' => $bl['priority'] ?? null,
                        'data' => json_encode($bl),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_backlinks');
        Schema::dropIfExists('project_seos');
    }
};

