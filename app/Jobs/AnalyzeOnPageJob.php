<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\OnPageAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** SEO — audit on-page homepage + halaman teratas. Hasil ke seo_requirements['onpage_audit']. */
class AnalyzeOnPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 150;

    public function __construct(public Project $project)
    {
    }

    public static function cacheKey(int $projectId): string
    {
        return "onpage_audit_progress:{$projectId}";
    }

    private function report(string $status, int $progress, string $message): void
    {
        Cache::put(self::cacheKey($this->project->id), compact('status', 'progress', 'message'), now()->addMinutes(10));
    }

    public function handle(OnPageAuditService $service): void
    {
        $this->report('running', 20, 'Mengaudit on-page tiap halaman...');

        $seo = $this->project->seo_requirements ?? [];
        $url = $seo['target_url'] ?? $this->project->backlink_requirements['target_url'] ?? null;
        if (!$url) {
            $this->report('failed', 0, 'URL website klien belum tersedia.');
            return;
        }

        $result = $service->analyze($url, AnalyzeStructuredDataJob::topPageUrls($seo));

        if (($result['summary']['pages_checked'] ?? 0) === 0) {
            $this->report('failed', 0, 'Tidak ada halaman yang bisa diambil.');
            return;
        }

        $seo = $this->project->fresh()->seo_requirements ?? [];
        $seo['onpage_audit'] = $result;
        $this->project->update(['seo_requirements' => $seo]);

        $issues = $result['summary']['pages_with_issues'] ?? 0;
        $this->project->logActivity(
            $issues > 0 ? "Audit on-page selesai — {$issues} halaman punya isu" : 'Audit on-page selesai — tidak ada isu'
        );

        $this->report('done', 100, 'Audit on-page selesai.');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeOnPageJob failed: ' . $exception->getMessage(), ['project_id' => $this->project->id]);
        $this->report('failed', 0, 'Terjadi kesalahan tak terduga. Silakan coba lagi.');
    }
}
