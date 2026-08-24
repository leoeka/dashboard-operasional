<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\AiServices;
use App\Services\ZipWpMcpService;
use App\Services\CompetitorDiscoveryService;
use App\Services\CompetitorContentFetcher;
use App\Http\Controllers\ProjectController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateProposalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 menit max
    public int $tries = 1;      // Biar tidak auto-retry kalau API timeout

    public function __construct(public Project $project)
    {
    }

    public function handle(
        AiServices $aiService,
        ZipWpMcpService $zipWp,
        CompetitorDiscoveryService $competitorDiscovery,
        CompetitorContentFetcher $contentFetcher
    ): void {
        // TAMBAHKAN BARIS INI DI SINI
        set_time_limit(0);

        $controller = app(ProjectController::class);
        $controller->runProposalGeneration($this->project, $aiService, $zipWp, $competitorDiscovery, $contentFetcher);
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