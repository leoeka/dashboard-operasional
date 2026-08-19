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

/**
 * Analisis PageSpeed buat kompetitor (bukan website sendiri) — dibatasi
 * MAKS 2 kompetitor teratas & cuma strategy "mobile" (bukan
 * mobile+desktop), sengaja diperkecil supaya job ini tidak jalan
 * berbelas menit (1 panggilan PageSpeed saja sudah bisa makan
 * 15-60+ detik, sudah kejadian sendiri sebelumnya).
 *
 * Hasilnya DISIMPAN ke seo_requirements['competitor_pagespeed'] — PDF
 * laporan nanti cuma BACA data yang sudah tersimpan ini, tidak
 * menganalisis ulang tiap kali diunduh (supaya download PDF tetap
 * cepat, tidak ikut nunggu PageSpeed).
 */
class AnalyzeCompetitorPageSpeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public int $projectId)
    {
    }

    public static function cacheKey(int $projectId): string
    {
        return "competitor_pagespeed_progress_{$projectId}";
    }

    public function handle(PageSpeedService $service): void
    {
        $project = Project::find($this->projectId);

        if (!$project) {
            return;
        }

        $seo = $project->seo_requirements ?? [];

        $competitorUrls = collect(explode("\n", $seo['competitors'] ?? ''))
            ->map(fn($u) => trim($u))
            ->filter()
            ->take(2)
            ->values();

        if ($competitorUrls->isEmpty()) {
            Cache::put(self::cacheKey($this->projectId), [
                'status' => 'error',
                'progress' => 0,
                'message' => 'Belum ada kompetitor yang ditemukan untuk project ini — jalankan analisis keyword dulu.',
            ], now()->addMinutes(10));
            return;
        }

        $total = $competitorUrls->count();
        $results = [];

        foreach ($competitorUrls as $index => $url) {
            Cache::put(self::cacheKey($this->projectId), [
                'status' => 'running',
                'progress' => (int) round(($index / $total) * 90),
                'message' => "Menganalisis performa kompetitor ({$url})...",
            ], now()->addMinutes(10));

            try {
                $result = $service->analyze($url, 'mobile');
            } catch (\Throwable $e) {
                Log::warning('AnalyzeCompetitorPageSpeedJob: gagal analisis 1 kompetitor, lanjut ke berikutnya.', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                $result = null;
            }

            $results[] = [
                'url' => $url,
                'scores' => $result['scores'] ?? null,
                'screenshot' => $result['screenshot'] ?? null,
            ];
        }

        $freshSeo = $project->fresh()->seo_requirements ?? [];
        $freshSeo['competitor_pagespeed'] = $results;
        $freshSeo['competitor_pagespeed_analyzed_at'] = now()->toDateTimeString();
        $project->update(['seo_requirements' => $freshSeo]);

        Cache::put(self::cacheKey($this->projectId), [
            'status' => 'done',
            'progress' => 100,
            'message' => 'Selesai.',
        ], now()->addMinutes(10));
    }
}