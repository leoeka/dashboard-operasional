<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\MockupCodexRunner;
use Illuminate\Console\Command;

/**
 * Titik masuk runner jalur Codex. Dipanggil oleh MockupCodexLauncher dari
 * permintaan web, dan bisa dijalankan tangan dari terminal untuk uji:
 *
 *   php artisan mockup:codex 12
 *   php artisan mockup:codex 12 --candidate=2
 */
class GenerateMockupCodex extends Command
{
    protected $signature = 'mockup:codex
        {project : ID proyek}
        {--candidate= : Hanya nomor kandidat ini}';

    protected $description = 'Membuat kandidat mockup lewat Codex CLI (mesin codex)';

    public function handle(MockupCodexRunner $runner): int
    {
        $project = Project::find((int) $this->argument('project'));

        if ($project === null) {
            $this->error('Proyek tidak ditemukan.');

            return self::FAILURE;
        }

        $hanya = $this->option('candidate');

        return $runner->run($project, $hanya === null ? null : (int) $hanya);
    }
}
