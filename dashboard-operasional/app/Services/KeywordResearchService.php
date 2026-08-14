<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Logika inti pipeline analisis SEO & Backlink (baca website client → AI
 * simpulkan topik → cari kompetitor → fetch konten kompetitor paralel →
 * AI perluas kandidat keyword → cek volume Google Ads → AI tentukan 10
 * keyword final).
 *
 * Diekstrak jadi service terpisah supaya bisa dipakai di DUA mode:
 * - Mode PROJECT (GenerateKeywordRecommendationsJob): Project sudah
 *   tersimpan di database, hasil disimpan balik ke seo_requirements.
 * - Mode PREVIEW (RunKeywordPreviewAnalysisJob): dipanggil SEBELUM
 *   client/project dibuat — $context di sini adalah objek Project yang
 *   BELUM disimpan (->exists tetap false), cuma dipakai sebagai "wadah"
 *   konteks (nama bisnis, tipe, lokasi) karena AiServices memang
 *   menerima objek Project, bukan string lepas. Hasilnya disimpan oleh
 *   caller ke Cache, bukan ke database.
 */
class KeywordResearchService
{
    public function __construct(
        private AiServices $aiService,
        private CompetitorContentFetcher $fetcher,
        private CompetitorDiscoveryService $discovery,
        private GoogleAdsKeywordService $googleAds,
    ) {
    }

    /**
     * @param Project  $context             Objek Project (boleh belum tersimpan) untuk konteks prompt AI.
     * @param string   $websiteUrl          URL website client yang akan dianalisis.
     * @param string[] $manualCompetitorUrls Kompetitor yang sudah diisi manual sebelumnya (boleh kosong).
     * @param \Closure $report              function(string $status, int $progress, string $message): void
     *
     * @return array{topics: array, recommendations: array, competitor_urls: string[], competitor_analyzed_count: int}
     *
     * @throws \RuntimeException kalau URL tidak valid/tidak aman, atau gagal fetch website utama.
     */
    public function analyze(Project $context, string $websiteUrl, array $manualCompetitorUrls, \Closure $report): array
    {
        if (!CompetitorContentFetcher::isSafeUrl($websiteUrl)) {
            throw new \RuntimeException('URL website tidak valid atau tidak boleh diakses.');
        }

        // TAHAP 1 — baca situs client SENDIRI
        $report('running', 15, 'Membaca konten website...');
        $ownContent = $this->fetcher->fetch($websiteUrl);

        if (!$ownContent) {
            throw new \RuntimeException('Gagal mengakses website. Cek apakah URL benar dan situs bisa diakses.');
        }

        // Kalau context belum punya nama bisnis (mode PREVIEW sebelum form
        // lengkap diisi), pakai judul halaman situsnya sebagai gantinya
        // supaya prompt AI tetap punya konteks nama yang masuk akal.
        if (empty($context->name) && !empty($ownContent['title'])) {
            $context->name = $ownContent['title'];
        }

        // TAHAP 2 — Gemini simpulkan topik/seed keyword
        $report('running', 25, 'AI menganalisis topik bisnis dari website...');
        $topics = $this->aiService->identifyTopicsFromWebsite($context, $ownContent);
        $seedKeywords = implode(', ', $topics['seed_keywords'] ?? []);

        // TAHAP 3 — cari kompetitor otomatis (Custom Search API)
        // FIX (dukungan lokasi luar negeri): ambil lokasi sekali di sini,
        // dipakai untuk cari kompetitor DAN cek volume Google Ads di
        // bawah — sebelumnya lokasi ini tidak disalurkan ke keduanya.
        $location = trim((string) ($context->seo_requirements['location'] ?? ''));
        $location = $location !== '' ? $location : null;

        // TAHAP 3 — cari kompetitor otomatis (Custom Search API)
        $report('running', 35, 'Mencari kompetitor otomatis...');
        $competitorUrls = $this->discovery->findCompetitors(
            $context->type ?? '',
            $topics['core_topics'] ?? [],
            $websiteUrl,
            $location
        );

        $allCompetitorUrls = collect(array_merge($competitorUrls, $manualCompetitorUrls))
            ->unique()
            ->take(5)
            ->values()
            ->filter(fn($url) => CompetitorContentFetcher::isSafeUrl($url))
            ->values()
            ->all();

        // TAHAP 4 — fetch konten tiap kompetitor SECARA PARALEL
        $competitorContents = [];
        $total = count($allCompetitorUrls);

        if ($total > 0) {
            $report('running', 42, "Mengambil konten {$total} kompetitor secara paralel...");

            $responses = Http::pool(fn($pool) => collect($allCompetitorUrls)
                ->map(fn($url, $i) => $pool->as((string) $i)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; DashboardOperasionalBot/1.0; +internal-competitor-research)',
                    ])
                    ->timeout(20)
                    ->get($url))
                ->all());

            foreach ($allCompetitorUrls as $i => $url) {
                $response = $responses[(string) $i] ?? null;

                if ($response instanceof Response && $response->successful() && trim($response->body()) !== '') {
                    $competitorContents[] = $this->fetcher->parseHtml($response->body(), $url);
                }
            }

            $report('running', 55, 'Konten kompetitor selesai diambil (' . count($competitorContents) . "/{$total} berhasil).");
        }

        // TAHAP 5 — Gemini perluas kandidat
        $report('running', 60, 'AI memperluas daftar kandidat keyword...');
        $candidates = $this->aiService->expandSeedKeywords($context, $seedKeywords, $competitorContents);

        // TAHAP 6 — Google Ads API, cek volume asli (geo-target ikut
        // lokasi client, bukan hardcode Indonesia lagi)
        $report('running', 78, 'Mengecek volume pencarian asli via Google Ads...');
        $volumeData = $this->googleAds->getKeywordIdeas($candidates, $location);

        // TAHAP 7 — Gemini tentukan 10 keyword final
        $report('running', 90, 'AI menentukan 10 keyword utama...');
        $finalResult = $this->aiService->selectFinalKeywords($context, $candidates, $volumeData, $competitorContents);

        return [
            'topics' => $topics,
            'recommendations' => $finalResult,
            'competitor_urls' => $allCompetitorUrls,
            'competitor_analyzed_count' => count($competitorContents),
        ];
    }
}