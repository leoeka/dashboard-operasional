<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\PageSpeedService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AnalyzePageSpeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public Project $project)
    {
    }

    public static function cacheKey(int $projectId): string
    {
        return "pagespeed_progress:{$projectId}";
    }

    private function report(string $status, int $progress, string $message): void
    {
        Cache::put(self::cacheKey($this->project->id), [
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
        ], now()->addMinutes(10));
    }

    /**
     * Sama seperti resolveWebsiteUrl() di GenerateKeywordRecommendationsJob
     * — dipakai lagi di sini karena analisis performa ini juga menganalisis
     * URL yang sama dengan analisis SEO/Backlink.
     */
    private function resolveWebsiteUrl(): ?string
    {
        $project = $this->project;
        return $project->seo_requirements['target_url']
            ?? $project->backlink_requirements['target_url']
            ?? null;
    }

    public function handle(PageSpeedService $service): void
    {
        $project = $this->project;
        $this->report('running', 10, 'Memulai analisis performa...');

        $url = $this->resolveWebsiteUrl();
        if (!$url) {
            $this->report('failed', 0, 'URL website belum tersedia.');
            return;
        }

        $this->report('running', 30, 'Menjalankan Lighthouse untuk versi mobile (bisa 15-30 detik)...');
        $mobile = $service->analyze($url, 'mobile');

        $this->report('running', 65, 'Menjalankan Lighthouse untuk versi desktop (bisa 15-30 detik)...');
        $desktop = $service->analyze($url, 'desktop');

        if (!$mobile && !$desktop) {
            $this->report('failed', 0, 'Gagal menjalankan analisis performa. Cek API key PageSpeed di .env atau coba lagi.');
            return;
        }

        $existing = $project->fresh()->seo_requirements ?? [];
        $existing['pagespeed'] = [
            'mobile' => $mobile,
            'desktop' => $desktop,
            'analyzed_at' => now()->toDateTimeString(),
        ];
        $project->update(['seo_requirements' => $existing]);

        $project->logActivity('Analisis performa website (PageSpeed) selesai');

        $this->report('done', 100, 'Analisis performa selesai.');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzePageSpeedJob failed: ' . $exception->getMessage(), ['project_id' => $this->project->id]);
        $this->report('failed', 0, 'Terjadi kesalahan tak terduga. Silakan coba lagi.');
    }
}