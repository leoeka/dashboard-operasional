<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\AiServices;
use App\Services\CompetitorContentFetcher;
use App\Services\CompetitorDiscoveryService;
use App\Services\GoogleAdsKeywordService;
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
     * Resolusi URL website client — dari seo_target_url (client punya
     * sendiri) ATAU dari mockupTemplate.source_url (kita yang bikinin).
     * Kalau dua-duanya kosong, tidak bisa lanjut.
     */
    private function resolveWebsiteUrl(): ?string
    {
        $project = $this->project;
        return $project->seo_requirements['target_url']
            ?? $project->mockupTemplate?->source_url
            ?? null;
    }

    public function handle(
        AiServices $aiService,
        CompetitorContentFetcher $fetcher,
        CompetitorDiscoveryService $discovery,
        GoogleAdsKeywordService $googleAds
    ): void {
        $project = $this->project;

        $this->report('running', 5, 'Memulai proses...');

        $websiteUrl = $this->resolveWebsiteUrl();
        if (!$websiteUrl) {
            $this->report('failed', 0, 'URL website client belum tersedia. Isi URL manual atau tunggu proses generate website selesai.');
            return;
        }

        // TAHAP 1 — baca situs client SENDIRI
        $this->report('running', 15, 'Membaca konten website client...');
        $ownContent = $fetcher->fetch($websiteUrl);

        if (!$ownContent) {
            $this->report('failed', 0, 'Gagal mengakses website client. Cek apakah URL benar dan situs bisa diakses.');
            return;
        }

        // TAHAP 2 — Gemini simpulkan topik/seed keyword dari situ
        $this->report('running', 25, 'AI menganalisis topik bisnis dari website...');
        try {
            $topics = $aiService->identifyTopicsFromWebsite($project, $ownContent);
        } catch (\Throwable $e) {
            Log::error('GenerateKeywordRecommendationsJob - identify topics gagal: ' . $e->getMessage(), ['project_id' => $project->id]);
            $this->report('failed', 0, 'Gagal menganalisis topik website: ' . $e->getMessage());
            return;
        }

        $seedKeywords = implode(', ', $topics['seed_keywords'] ?? []);

        // TAHAP 3 — cari kompetitor otomatis (Custom Search API)
        $this->report('running', 35, 'Mencari kompetitor otomatis...');
        $competitorUrls = $discovery->findCompetitors(
            $project->type ?? '',
            $topics['core_topics'] ?? [],
            $websiteUrl
        );

        // Kalau tim SUDAH pernah isi kompetitor manual sebelumnya (dari
        // form intake lama), gabungkan juga — auto-discovery MELENGKAPI,
        // bukan menghapus input manual yang sudah ada.
        $manualCompetitors = collect(explode("\n", $project->seo_requirements['competitors'] ?? ''))
            ->map(fn($u) => trim($u))
            ->filter(fn($u) => $u !== '' && filter_var($u, FILTER_VALIDATE_URL))
            ->all();

        $allCompetitorUrls = collect(array_merge($competitorUrls, $manualCompetitors))
            ->unique()
            ->take(5)
            ->values()
            ->all();

        // TAHAP 4 — fetch konten tiap kompetitor
        $competitorContents = [];
        $total = count($allCompetitorUrls);
        foreach ($allCompetitorUrls as $i => $url) {
            $pct = 40 + intval(($i / max($total, 1)) * 15);
            $this->report('running', $pct, "Mengambil konten kompetitor (" . ($i + 1) . "/{$total})...");

            $content = $fetcher->fetch($url);
            if ($content) {
                $competitorContents[] = $content;
            }
        }

        // TAHAP 5 — Gemini perluas kandidat
        $this->report('running', 60, 'AI memperluas daftar kandidat keyword...');
        try {
            $candidates = $aiService->expandSeedKeywords($project, $seedKeywords, $competitorContents);
        } catch (\Throwable $e) {
            Log::error('GenerateKeywordRecommendationsJob - expand gagal: ' . $e->getMessage(), ['project_id' => $project->id]);
            $this->report('failed', 0, 'Gagal memperluas kandidat keyword: ' . $e->getMessage());
            return;
        }

        // TAHAP 6 — Google Ads API, cek volume asli
        $this->report('running', 78, 'Mengecek volume pencarian asli via Google Ads...');
        $volumeData = $googleAds->getKeywordIdeas($candidates);

        // TAHAP 7 — Gemini tentukan 10 keyword final
        $this->report('running', 90, 'AI menentukan 10 keyword utama...');
        try {
            $finalResult = $aiService->selectFinalKeywords($project, $candidates, $volumeData, $competitorContents);
        } catch (\Throwable $e) {
            Log::error('GenerateKeywordRecommendationsJob - final gagal: ' . $e->getMessage(), ['project_id' => $project->id]);
            $this->report('failed', 0, 'Gagal menentukan keyword final: ' . $e->getMessage());
            return;
        }

        // Simpan semua hasil — timpa/lengkapi seo_requirements yang ada
        $existing = $project->fresh()->seo_requirements ?? [];
        $existing['target_url'] = $existing['target_url'] ?? $websiteUrl;
        $existing['competitors'] = implode("\n", $allCompetitorUrls);
        $existing['ai_recommendations'] = $finalResult;
        $existing['ai_identified_topics'] = $topics;
        $project->update(['seo_requirements' => $existing]);

        $project->logActivity(
            'Analisis SEO & Backlink otomatis selesai (' . count($competitorContents) . ' kompetitor dianalisis' .
            (!empty($volumeData) ? ', dengan data volume Google Ads)' : ', estimasi AI)')
        );

        $this->report('done', 100, 'Analisis SEO & Backlink selesai.');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateKeywordRecommendationsJob failed: ' . $exception->getMessage(), ['project_id' => $this->project->id]);
        $this->report('failed', 0, 'Terjadi kesalahan tak terduga. Silakan coba lagi.');
    }
}