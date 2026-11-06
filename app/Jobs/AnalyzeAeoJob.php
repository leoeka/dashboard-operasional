<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\AeoAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** AEO — audit question-led answers, FAQ markup, citations, and freshness. */
class AnalyzeAeoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 90;

    public function __construct(public Project $project)
    {
    }

    public static function cacheKey(int $projectId): string
    {
        return "aeo_audit_progress:{$projectId}";
    }

    private function report(string $status, int $progress, string $message): void
    {
        Cache::put(self::cacheKey($this->project->id), compact('status', 'progress', 'message'), now()->addMinutes(15));
    }

    public function handle(AeoAuditService $service): void
    {
        $this->report('running', 20, 'Memeriksa kesiapan konten untuk Answer Engine...');
        $seo = $this->project->seo_requirements ?? [];
        $url = $seo['target_url'] ?? $this->project->backlink_requirements['target_url'] ?? null;
        if (!$url) {
            $this->report('failed', 0, 'URL website klien belum tersedia.');
            return;
        }

        $result = $service->analyze($url, AnalyzeStructuredDataJob::topPageUrls($seo));
        if (($result['summary']['pages_checked'] ?? 0) === 0) {
            $this->report('failed', 0, 'Tidak ada halaman yang berhasil diambil untuk audit AEO.');
            return;
        }

        $seo = $this->project->fresh()->seo_requirements ?? [];
        $seo['aeo_audit'] = $result;
        $this->project->update(['seo_requirements' => $seo]);
        $this->project->logActivity('Audit kesiapan Answer Engine (AEO) selesai — skor rata-rata ' . ($result['summary']['avg_score'] ?? '-'));
        $this->report('done', 100, 'Audit AEO selesai.');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeAeoJob failed: ' . $exception->getMessage(), ['project_id' => $this->project->id]);
        $this->report('failed', 0, 'Terjadi kesalahan tak terduga. Silakan coba lagi.');
    }
}