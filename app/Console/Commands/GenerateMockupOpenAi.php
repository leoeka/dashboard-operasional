<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\MockupOpenAiRunner;
use Illuminate\Console\Command;

class GenerateMockupOpenAi extends Command
{
    protected $signature = 'mockup:openai
        {project : ID proyek}
        {--candidate= : Hanya nomor kandidat ini}';

    protected $description = 'Membuat kandidat mockup melalui OpenAI Responses API';

    public function handle(MockupOpenAiRunner $runner): int
    {
        $project = Project::find((int) $this->argument('project'));

        if ($project === null) {
            $this->error('Proyek tidak ditemukan.');

            return self::FAILURE;
        }

        $candidate = $this->option('candidate');

        return $runner->run($project, $candidate === null ? null : (int) $candidate);
    }
}
