<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\CompetitorContentFetcher;
use App\Services\KeywordResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateKeywordRecommendationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 150;

    // TIDAK PERLU parameter seed keyword / URL kompetitor manual lagi —
    // cukup Project, sisanya sistem cari sendiri.
    public function __construct(public Project $project)
    {
    }

    public static function cacheKey(int $projectId): string
    {
        return "keyword_progress:{$projectId}";
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
     * Resolusi URL website client — urutan prioritas:
     * 1. seo_requirements.target_url (URL asli client, diisi tim di form)
     * 2. backlink_requirements.target_url (kalau tim cuma pilih layanan
     *    Backlink tanpa SEO, seo_requirements bisa saja kosong)
     * 3. mockupTemplate.source_url (situs yang KITA bikinkan)
     * Kalau ketiganya kosong, tidak bisa lanjut.
     */
    private function resolveWebsiteUrl(): ?string
    {
        $project = $this->project;
        return $project->seo_requirements['target_url']
            ?? $project->backlink_requirements['target_url']
            ?? $project->mockupTemplate?->source_url
            ?? null;
    }

    public function handle(KeywordResearchService $service): void
    {
        $project = $this->project;

        $this->report('running', 5, 'Memulai proses...');

        $websiteUrl = $this->resolveWebsiteUrl();
        if (!$websiteUrl) {
            $this->report('failed', 0, 'URL website client belum tersedia. Isi URL manual atau tunggu proses generate website selesai.');
            return;
        }

        if (!CompetitorContentFetcher::isSafeUrl($websiteUrl)) {
            $this->report('failed', 0, 'URL website client tidak valid atau tidak boleh diakses.');
            return;
        }

        // Kalau tim SUDAH pernah isi kompetitor manual sebelumnya (dari
        // form intake lama), tetap dipakai — auto-discovery MELENGKAPI,
        // bukan menghapus input manual yang sudah ada.
        $manualCompetitors = collect(explode("\n", $project->seo_requirements['competitors'] ?? ''))
            ->map(fn($u) => trim($u))
            ->filter(fn($u) => $u !== '' && filter_var($u, FILTER_VALIDATE_URL))
            ->all();

        try {
            $result = $service->analyze($project, $websiteUrl, $manualCompetitors, function ($status, $progress, $message) {
                $this->report($status, $progress, $message);
            });
        } catch (\Throwable $e) {
            Log::error('GenerateKeywordRecommendationsJob gagal: ' . $e->getMessage(), ['project_id' => $project->id]);
            $this->report('failed', 0, $e->getMessage());
            return;
        }

        // Simpan semua hasil — timpa/lengkapi seo_requirements yang ada
        $existing = $project->fresh()->seo_requirements ?? [];
        $existing['target_url'] = $existing['target_url'] ?? $websiteUrl;
        $existing['competitors'] = implode("\n", $result['competitor_urls']);
        $existing['ai_recommendations'] = $result['recommendations'];
        $existing['ai_identified_topics'] = $result['topics'];
        $project->update(['seo_requirements' => $existing]);

        $project->logActivity(
            'Analisis SEO & Backlink otomatis selesai (' . $result['competitor_analyzed_count'] . ' kompetitor dianalisis)'
        );

        $this->report('done', 100, 'Analisis SEO & Backlink selesai.');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateKeywordRecommendationsJob failed: ' . $exception->getMessage(), ['project_id' => $this->project->id]);
        $this->report('failed', 0, 'Terjadi kesalahan tak terduga. Silakan coba lagi.');
    }
}