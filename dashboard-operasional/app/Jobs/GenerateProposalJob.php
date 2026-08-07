<?php

namespace App\Jobs;

use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Job ini SENGAJA dibikin "kosong" (tidak ada logika bisnis di dalamnya).
 * Satu-satunya tugasnya: jalankan ProjectController::runProposalGeneration()
 * di background lewat queue, supaya progress bar bisa di-poll.
 *
 * Logika generate proposal (buatan teman kamu) TETAP satu-satunya salinan,
 * hidup di ProjectController — TIDAK diduplikasi ke sini. Kalau nanti
 * logikanya diubah/diupdate di controller, Job ini otomatis ikut pakai versi
 * terbaru tanpa perlu disentuh sama sekali.
 */
class GenerateProposalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 240;

    public function __construct(public Project $project)
    {
    }

    public function handle(): void
    {
        // app()->call() otomatis resolve dependency yang di-type-hint di
        // signature method (AiServices, ZipWpMcpService) lewat container,
        // sama seperti kalau method itu dipanggil langsung dari route.
        app()->call(
            [app(ProjectController::class), 'runProposalGeneration'],
            ['project' => $this->project]
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateProposalJob failed: ' . $exception->getMessage(), [
            'project_id' => $this->project->id,
        ]);

        Cache::put(
            "proposal_progress:{$this->project->id}",
            ['status' => 'failed', 'progress' => 0, 'message' => 'Terjadi kesalahan tak terduga. Silakan coba lagi.'],
            now()->addMinutes(10)
        );
    }
}