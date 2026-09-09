<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\AiCrawlerAccessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * GEO — cek apakah robots.txt situs klien mengizinkan crawler mesin AI
 * (GPTBot, PerplexityBot, Google-Extended, dst). Hasil disimpan ke
 * seo_requirements['ai_crawler_access']; PDF laporan cuma membaca data ini.
 *
 * Ringan (1-2 request HTTP), tapi tetap lewat queue supaya situs klien
 * yang lambat/hang tidak memblokir request web — pola yang sama dengan
 * AnalyzePageSpeedJob.
 */
class AnalyzeAiCrawlerAccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public Project $project)
    {
    }

    public static function cacheKey(int $projectId): string
    {
        return "ai_crawler_access_progress:{$projectId}";
    }

    private function report(string $status, int $progress, string $message): void
    {
        Cache::put(self::cacheKey($this->project->id), [
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
        ], now()->addMinutes(10));
    }

    /** Sama seperti job SEO lain — URL klien dari seo_requirements / backlink_requirements. */
    private function resolveWebsiteUrl(): ?string
    {
        return $this->project->seo_requirements['target_url']
            ?? $this->project->backlink_requirements['target_url']
            ?? null;
    }

    public function handle(AiCrawlerAccessService $service): void
    {
        $this->report('running', 20, 'Mengecek robots.txt & llms.txt...');

        $url = $this->resolveWebsiteUrl();
        if (!$url) {
            $this->report('failed', 0, 'URL website klien belum tersedia.');
            return;
        }

        $result = $service->analyze($url);

        if ($result['robots_status'] === 'error') {
            $this->report('failed', 0, $result['recommendation']);
            return;
        }

        $existing = $this->project->fresh()->seo_requirements ?? [];
        $existing['ai_crawler_access'] = $result;
        $this->project->update(['seo_requirements' => $existing]);

        $blocked = $result['summary']['blocked'];
        $this->project->logActivity(
            $blocked > 0
                ? "Cek akses AI crawler selesai — {$blocked} crawler AI diblokir robots.txt"
                : 'Cek akses AI crawler selesai — semua crawler AI diizinkan'
        );

        $this->report('done', 100, 'Cek akses AI crawler selesai.');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeAiCrawlerAccessJob failed: ' . $exception->getMessage(), ['project_id' => $this->project->id]);
        $this->report('failed', 0, 'Terjadi kesalahan tak terduga. Silakan coba lagi.');
    }
}
