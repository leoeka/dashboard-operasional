<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\ContentExtractabilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** GEO — Gemini menilai extractability konten. Hasil ke seo_requirements['content_extractability']. */
class AnalyzeContentExtractabilityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 240;

    public function __construct(public Project $project)
    {
    }

    public static function cacheKey(int $projectId): string
    {
        return "content_extractability_progress:{$projectId}";
    }

    private function report(string $status, int $progress, string $message): void
    {
        Cache::put(self::cacheKey($this->project->id), compact('status', 'progress', 'message'), now()->addMinutes(15));
    }

    public function handle(ContentExtractabilityService $service): void
    {
        $this->report('running', 20, 'AI menilai struktur konten halaman...');

        $seo = $this->project->seo_requirements ?? [];
        $url = $seo['target_url'] ?? $this->project->backlink_requirements['target_url'] ?? null;
        if (!$url) {
            $this->report('failed', 0, 'URL website klien belum tersedia.');
            return;
        }

        $result = $service->analyze($this->project, $url, AnalyzeStructuredDataJob::topPageUrls($seo));

        if (($result['summary']['pages_checked'] ?? 0) === 0) {
            $this->report('failed', 0, 'Tidak ada halaman yang berhasil dinilai (halaman tidak terjangkau atau AI gagal).');
            return;
        }

        $seo = $this->project->fresh()->seo_requirements ?? [];
        $seo['content_extractability'] = $result;
        $this->project->update(['seo_requirements' => $seo]);

        $avg = $result['summary']['avg_score'] ?? null;
        $this->project->logActivity('Penilaian extractability konten (GEO) selesai' . ($avg !== null ? " — skor rata-rata {$avg}" : ''));

        $this->report('done', 100, 'Penilaian extractability konten selesai.');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeContentExtractabilityJob failed: ' . $exception->getMessage(), ['project_id' => $this->project->id]);
        $this->report('failed', 0, 'Terjadi kesalahan tak terduga. Silakan coba lagi.');
    }
}
