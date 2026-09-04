<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\GenerateMockupGptService;
use App\Services\AnalisisGeminiService;
use App\Services\CompetitorDiscoveryService;
use App\Services\CompetitorContentFetcher;
use App\Http\Controllers\WebsiteBuilderController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateProposalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 8 menit — dinaikkan dari 5 menit. Catatan: di Windows ini sebenarnya
    // simbolis (Laravel butuh extension pcntl buat "menyela" job yang
    // melewati timeout secara graceful, dan pcntl tidak ada di build PHP
    // Windows) — batas yang benar-benar berlaku di Windows adalah flag
    // --timeout pada `queue:listen` di composer.json, yang juga sudah
    // dinaikkan supaya sinkron dengan angka ini.
    public int $timeout = 480;
    public int $tries = 1;      // Biar tidak auto-retry kalau API timeout

    public function __construct(public Project $project)
    {
    }

    public function handle(
        GenerateMockupGptService $aiService,
        AnalisisGeminiService $geminiService,
        CompetitorDiscoveryService $competitorDiscovery,
        CompetitorContentFetcher $contentFetcher
    ): void {
        // TAMBAHKAN BARIS INI DI SINI
        set_time_limit(0);

        $controller = app(WebsiteBuilderController::class);
        $controller->runProposalGeneration($this->project, $aiService, $geminiService, $competitorDiscovery, $contentFetcher);
    }

    public function failed(Throwable $exception): void
    {
        // Jika job crash fatal/timeout, update cache progress ke failed
        \Illuminate\Support\Facades\Cache::put(
            "proposal_progress:{$this->project->id}",
            [
                'status' => 'failed',
                'progress' => 0,
                'message' => 'Proses terhenti: ' . $exception->getMessage()
            ],
            now()->addMinutes(10)
        );
    }
}
