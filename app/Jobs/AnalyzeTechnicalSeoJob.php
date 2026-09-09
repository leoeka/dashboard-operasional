<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\TechnicalSeoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** SEO teknis tingkat-situs (robots/sitemap/HTTPS/link mati). Hasil ke seo_requirements['technical_seo']. */
class AnalyzeTechnicalSeoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 150;

    public function __construct(public Project $project)
    {
    }

    public static function cacheKey(int $projectId): string
    {
        return "technical_seo_progress:{$projectId}";
    }

    private function report(string $status, int $progress, string $message): void
    {
        Cache::put(self::cacheKey($this->project->id), compact('status', 'progress', 'message'), now()->addMinutes(10));
    }

    public function handle(TechnicalSeoService $service): void
    {
        $this->report('running', 25, 'Memeriksa robots.txt, sitemap, HTTPS, link...');

        $url = $this->project->seo_requirements['target_url']
            ?? $this->project->backlink_requirements['target_url']
            ?? null;
        if (!$url) {
            $this->report('failed', 0, 'URL website klien belum tersedia.');
            return;
        }

        $result = $service->analyze($url);

        if ($result['score'] === null) {
            $this->report('failed', 0, $result['recommendation']);
            return;
        }

        $seo = $this->project->fresh()->seo_requirements ?? [];
        $seo['technical_seo'] = $result;
        $this->project->update(['seo_requirements' => $seo]);

        $fails = count(array_filter($result['checks'], fn ($c) => $c['status'] === 'fail'));
        $this->project->logActivity(
            $fails > 0 ? "Cek SEO teknis selesai — {$fails} masalah serius" : 'Cek SEO teknis selesai — fondasi teknis sehat'
        );

        $this->report('done', 100, 'Cek SEO teknis selesai.');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeTechnicalSeoJob failed: ' . $exception->getMessage(), ['project_id' => $this->project->id]);
        $this->report('failed', 0, 'Terjadi kesalahan tak terduga. Silakan coba lagi.');
    }
}
