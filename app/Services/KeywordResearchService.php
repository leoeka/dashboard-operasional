<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Logika inti pipeline analisis SEO & Backlink (baca website client → AI
 * simpulkan topik → cari kompetitor → fetch konten kompetitor paralel →
 * AI perluas kandidat keyword → cek performa pencarian → AI ranking 25
 * kandidat keyword untuk direview & dipilih manual oleh tim).
 *
 * PERUBAHAN: tahap akhir sebelumnya "AI tentukan 10 keyword final" (AI
 * memutuskan sepihak). Sekarang jadi "AI ranking 25 kandidat" — AI cuma
 * membantu urutkan berdasar relevansi+performa, keputusan pilih mana yang
 * dipakai ada di tangan tim lewat halaman Workspace (field `selected`
 * per keyword, diupdate lewat request biasa — TIDAK memanggil AI lagi).
 * Proposal PDF membaca keyword yang `selected == true` langsung dari
 * data tersimpan, jadi generate proposal tidak menambah biaya token.
 *
 * Diekstrak jadi service terpisah supaya bisa dipakai di DUA mode:
 * - Mode PROJECT (GenerateKeywordRecommendationsJob): Project sudah
 *   tersimpan di database, hasil disimpan balik ke seo_requirements.
 * - Mode PREVIEW (RunKeywordPreviewAnalysisJob): dipanggil SEBELUM
 *   client/project dibuat — $context di sini adalah objek Project yang
 *   BELUM disimpan (->exists tetap false), cuma dipakai sebagai "wadah"
 *   konteks (nama bisnis, tipe, lokasi) karena AnalisisGeminiService memang
 *   menerima objek Project, bukan string lepas. Hasilnya disimpan oleh
 *   caller ke Cache, bukan ke database.
 */
class KeywordResearchService
{
    /**
     * Jumlah kandidat keyword yang ditampilkan ke tim untuk direview &
     * dipilih manual di halaman Workspace (bukan jumlah yang otomatis
     * dipakai — itu ditentukan tim lewat field `selected`).
     */
    private const CANDIDATE_COUNT = 25;

    public function __construct(
        private AnalisisGeminiService $aiService,
        private CompetitorContentFetcher $fetcher,
        private CompetitorDiscoveryService $discovery,
        private SearchConsoleService $searchConsole,
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

        // TAHAP 5 — Gemini perluas kandidat.
        // Kandidat mentah dari AnalisisGeminiService sebaiknya > CANDIDATE_COUNT
        // (mis. 40-an) supaya tahap 7 punya cukup bahan untuk ranking,
        // bukan cuma pas-pasan 25. Kalau expandSeedKeywords() saat ini
        // dibatasi hasilkan persis ~10-15, itu perlu diubah juga di
        // AnalisisGeminiService (di luar file ini) supaya jumlah kandidat mentahnya
        // lebih besar dari 25.
        $report('running', 60, 'AI memperluas daftar kandidat keyword...');
        $candidates = $this->aiService->expandSeedKeywords($context, $seedKeywords, $competitorContents);

        // TAHAP 6 — cek performa pencarian (Search Console: query yang
        // sudah membawa traffic organik ke situs client)
        $report('running', 78, 'Mengecek performa pencarian asli via Search Console...');
        $volumeData = $this->searchConsole->getTopQueries($websiteUrl);

        // TAHAP 7 — Gemini RANKING kandidat (bukan lagi "tentukan final").
        // PENTING: struktur $finalResult TETAP sama seperti sebelumnya
        // (main_keywords, related_keywords, summary, data_source) supaya
        // kompatibel dengan Workspace blade & Proposal blade yang sudah
        // ada. Yang berubah HANYA isi 'main_keywords': sekarang bisa
        // sampai 25 item (bukan 10), masing-masing dapat tambahan flag
        // 'selected' => false — tim yang menentukan pilihan lewat halaman
        // Workspace (update flag ini langsung, TIDAK memanggil AI lagi).
        $report('running', 90, 'AI mengurutkan ' . self::CANDIDATE_COUNT . ' kandidat keyword teratas...');
        $finalResult = $this->aiService->selectFinalKeywords(
            $context,
            $candidates,
            $volumeData,
            $competitorContents,
            self::CANDIDATE_COUNT
        );

        $finalResult['main_keywords'] = collect($finalResult['main_keywords'] ?? [])
            ->take(self::CANDIDATE_COUNT)
            ->map(function ($item) {
                if (is_array($item)) {
                    $item['selected'] = $item['selected'] ?? false;
                }
                return $item;
            })
            ->values()
            ->all();

        return [
            'topics' => $topics,
            'recommendations' => $finalResult,
            'competitor_urls' => $allCompetitorUrls,
            'competitor_analyzed_count' => count($competitorContents),
        ];
    }
}