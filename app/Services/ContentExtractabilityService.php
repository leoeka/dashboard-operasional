<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Log;

/**
 * GEO — seberapa mudah konten sebuah halaman "dikutip" mesin jawaban AI.
 * Gemini menilai tiap halaman terhadap kriteria extractability: ada
 * jawaban ringkas per heading, TL;DR, statistik bersumber, blok FAQ, dan
 * kalimat yang mandiri (bisa dikutip tanpa konteks). Mengembalikan skor
 * + saran perbaikan konkret.
 *
 * Dibatasi beberapa halaman saja (homepage + 2) untuk menahan biaya token.
 * Gagal secara halus: kalau Gemini error, halaman itu ditandai 'error'
 * dan sisanya tetap jalan.
 */
class ContentExtractabilityService
{
    private const MAX_PAGES = 3;

    public function __construct(
        private SitePageFetcher $fetcher,
        private CompetitorContentFetcher $contentFetcher,
        private AnalisisGeminiService $gemini,
    ) {
    }

    /**
     * @param  list<string>  $extraUrls
     * @return array{checked_at:string,pages:list<array<string,mixed>>,summary:array<string,mixed>,recommendation:string}
     */
    public function analyze(Project $project, string $baseUrl, array $extraUrls = []): array
    {
        $urls = $this->fetcher->pages($baseUrl, $extraUrls, self::MAX_PAGES);
        $pages = [];

        foreach ($urls as $url) {
            $html = $this->fetcher->html($url);
            if ($html === null) {
                $pages[] = ['url' => $url, 'status' => 'error', 'score' => null, 'criteria' => [], 'suggestions' => ['Halaman tidak bisa diambil.']];
                continue;
            }

            $content = $this->contentFetcher->parseHtml($html, $url);

            try {
                $raw = $this->gemini->callJson($this->buildPrompt($project, $content));
                $pages[] = $this->normalisePageResult($url, $raw);
            } catch (\Throwable $e) {
                Log::warning('ContentExtractabilityService: penilaian Gemini gagal.', ['url' => $url, 'error' => $e->getMessage()]);
                $pages[] = ['url' => $url, 'status' => 'error', 'score' => null, 'criteria' => [], 'suggestions' => ['Penilaian AI gagal untuk halaman ini.']];
            }
        }

        return [
            'checked_at' => now()->toDateTimeString(),
            'pages' => $pages,
            'summary' => $this->summarize($pages),
            'recommendation' => $this->recommendation($pages),
        ];
    }

    /** @param array{title:string,headings:list<string>,body_text:string} $content */
    public function buildPrompt(Project $project, array $content): string
    {
        $headings = implode("\n", array_map(fn ($h) => '- ' . $h, array_slice($content['headings'] ?? [], 0, 20)));
        $body = mb_substr((string) ($content['body_text'] ?? ''), 0, 4000);

        return <<<PROMPT
Kamu adalah auditor konten untuk "Generative Engine Optimization" (GEO) —
menilai apakah sebuah halaman web mudah dikutip mesin jawaban AI
(ChatGPT, Perplexity, Google AI Overviews).

Bisnis: {$project->name} ({$project->type})
Judul halaman: {$content['title']}

Heading di halaman:
{$headings}

Cuplikan isi:
{$body}

Nilai halaman ini pada 5 kriteria (true jika terpenuhi jelas):
1. direct_answers  : setiap heading berupa pertanyaan/topik diikuti jawaban ringkas 1-3 kalimat.
2. tldr            : ada ringkasan / poin kunci di awal atau akhir.
3. stats_sourced   : ada angka/statistik konkret dengan sumbernya.
4. faq             : ada bagian tanya-jawab / FAQ.
5. quotable        : ada kalimat yang berdiri sendiri (bisa dikutip tanpa konteks tambahan).

Kembalikan HANYA JSON:
{
  "score": <0-100, penilaian keseluruhan seberapa siap konten ini dikutip AI>,
  "direct_answers": bool, "tldr": bool, "stats_sourced": bool, "faq": bool, "quotable": bool,
  "suggestions": ["<2-4 saran perbaikan konkret, spesifik untuk halaman ini>"]
}
PROMPT;
    }

    /** @return array<string,mixed> */
    public function normalisePageResult(string $url, array $raw): array
    {
        $bool = fn ($k) => (bool) ($raw[$k] ?? false);
        $criteria = [
            'direct_answers' => $bool('direct_answers'),
            'tldr' => $bool('tldr'),
            'stats_sourced' => $bool('stats_sourced'),
            'faq' => $bool('faq'),
            'quotable' => $bool('quotable'),
        ];

        $score = $raw['score'] ?? null;
        if (!is_numeric($score)) {
            // Tidak ada skor eksplisit → turunkan dari kriteria (20 poin/kriteria).
            $score = count(array_filter($criteria)) * 20;
        }

        $suggestions = array_values(array_filter(array_map(
            fn ($s) => is_string($s) ? trim($s) : null,
            (array) ($raw['suggestions'] ?? [])
        )));

        return [
            'url' => $url,
            'status' => 'ok',
            'score' => (int) max(0, min(100, round((float) $score))),
            'criteria' => $criteria,
            'suggestions' => array_slice($suggestions, 0, 4),
        ];
    }

    private function summarize(array $pages): array
    {
        $ok = array_values(array_filter($pages, fn ($p) => $p['status'] === 'ok'));
        $scores = array_column($ok, 'score');

        return [
            'pages_checked' => count($ok),
            'avg_score' => $scores === [] ? null : (int) round(array_sum($scores) / count($scores)),
        ];
    }

    private function recommendation(array $pages): string
    {
        $ok = array_values(array_filter($pages, fn ($p) => $p['status'] === 'ok'));
        if ($ok === []) {
            return 'Penilaian extractability belum bisa dilakukan.';
        }

        $missing = [];
        $labels = [
            'direct_answers' => 'jawaban ringkas di bawah tiap heading',
            'tldr' => 'ringkasan / poin kunci',
            'stats_sourced' => 'statistik dengan sumber',
            'faq' => 'bagian FAQ',
            'quotable' => 'kalimat yang bisa dikutip mandiri',
        ];
        foreach ($labels as $key => $label) {
            $have = count(array_filter($ok, fn ($p) => $p['criteria'][$key] ?? false));
            if ($have < count($ok)) {
                $missing[] = $label;
            }
        }

        return $missing === []
            ? 'Struktur konten sudah ramah kutipan AI di semua halaman yang dinilai.'
            : 'Untuk lebih mudah dikutip mesin AI, tambahkan di halaman-halaman utama: ' . implode(', ', array_slice($missing, 0, 3)) . '.';
    }
}
