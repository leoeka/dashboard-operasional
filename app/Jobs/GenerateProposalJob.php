<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\GenerateMockupGptService;
use App\Services\AnalisisGeminiService;
use App\Services\CompetitorDiscoveryService;
use App\Services\CompetitorContentFetcher;
use App\Http\Controllers\WebsiteBuilderController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateProposalJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const LOCK_SECONDS = 1200;

    // 15 menit — foto mockup kini dikirim mengikuti batas 5 gambar/menit
    // akun OpenAI (MockupAssetService), jadi butuh waktu lebih. Catatan: di Windows ini sebenarnya
    // simbolis (Laravel butuh extension pcntl buat "menyela" job yang
    // melewati timeout secara graceful, dan pcntl tidak ada di build PHP
    // Windows) — batas yang benar-benar berlaku di Windows adalah flag
    // --timeout pada `queue:listen` di composer.json, yang juga sudah
    // dinaikkan supaya sinkron dengan angka ini.
    public int $timeout = 900;
    public int $tries = 1;      // Biar tidak auto-retry kalau API timeout
    public int $uniqueFor = self::LOCK_SECONDS;

    public function __construct(public Project $project)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->project->getKey();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('proposal-generation:'.$this->uniqueId()))
                ->dontRelease()
                ->expireAfter(self::LOCK_SECONDS),
        ];
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
