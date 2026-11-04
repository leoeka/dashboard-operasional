<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use App\Models\Project;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use App\Support\JsonRepair;

/**
 * The Gemini calls in this app: the SEO keyword-research helpers
 * (identifyTopicsFromWebsite, expandSeedKeywords, selectFinalKeywords —
 * dipakai KeywordResearchService & RunKeywordPreviewAnalysisJob) plus the
 * shared JSON-call wrapper they all run on.
 */
class AnalisisGeminiService
{
    /**
     * Pembungkus publik tipis untuk callGeminiJson — dipakai service lain
     * (mis. ContentExtractabilityService) dan alur rekomendasi keyword
     * SEO & Backlink yang butuh satu panggilan Gemini "prompt masuk, JSON
     * keluar" tanpa mengulang logika retry/repair di sini.
     */
    public function callJson(string $prompt): array
    {
        return $this->callGeminiJson('gemini-3.6-flash', $prompt);
    }

    private function callGeminiJson(string $model, string $prompt, int $maxRetries = 3): array
    {
        $attempt = 1;

        while ($attempt <= $maxRetries) {
            try {
                $response = Gemini::generativeModel($model)
                    ->withGenerationConfig(
                        generationConfig: new GenerationConfig(
                            responseMimeType: ResponseMimeType::APPLICATION_JSON,
                        )
                    )
                    ->generateContent($prompt);

                $responseText = $response->text();

                if (empty($responseText)) {
                    throw new \RuntimeException('AI (Gemini) memberikan respons kosong.');
                }

                $cleanedText = trim($responseText);
                $cleanedText = preg_replace('/^```(?:json)?\s*/i', '', $cleanedText);
                $cleanedText = preg_replace('/\s*```$/', '', $cleanedText);

                $result = json_decode($cleanedText, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $repaired = JsonRepair::closeTruncated($cleanedText);
                    $retryResult = json_decode($repaired, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $result = $retryResult;
                    }
                }

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('Format JSON Gemini tidak valid: ' . json_last_error_msg());
                }

                return $result;

            } catch (\Throwable $e) {
                $errorMessage = strtolower($e->getMessage());

                $isTransientError = str_contains($errorMessage, 'quota')
                    || str_contains($errorMessage, 'rate-limit')
                    || str_contains($errorMessage, 'high demand')
                    || str_contains($errorMessage, 'overloaded')
                    || str_contains($errorMessage, 'timed out')
                    || str_contains($errorMessage, 'curl error 28')
                    || str_contains($errorMessage, '503')
                    || str_contains($errorMessage, '500')
                    || str_contains($errorMessage, 'format json gemini tidak valid');

                if ($isTransientError && $attempt < $maxRetries) {
                    sleep($attempt * 3);
                    $attempt++;
                    continue;
                }

                Log::error('callGeminiJson gagal final, pesan asli: ' . ProviderException::sanitise($e->getMessage()), [
                    'model' => $model,
                    'is_transient' => $isTransientError,
                    'attempts' => $attempt,
                ]);

                throw ProviderException::fromThrowable('gemini', $e);
            }
        }

        throw new \RuntimeException('Gagal mendapatkan respons Gemini setelah beberapa kali percobaan.');
    }

    public function identifyTopicsFromWebsite(Project $project, array $siteContent): array
    {
        $headingsText = implode("\n", array_map(fn($h) => "- {$h}", array_slice($siteContent['headings'], 0, 15)));

        $location = trim((string) ($project->seo_requirements['location'] ?? ''));
        $locationLine = $location !== '' ? "Lokasi/area target bisnis: {$location}\n" : '';

        $prompt = "
You are an SEO Analyst.
Berikut konten MENTAH dari website milik client kami sendiri (bukan
kompetitor). Identifikasi topik inti dan seed keyword dari isi HALAMAN
INI, bukan dari asumsi umum.
 
Judul Halaman: {$siteContent['title']}
{$locationLine}Heading yang ditemukan:
{$headingsText}
 
Isi Teks (potongan):
" . mb_substr($siteContent['body_text'], 0, 3000) . "
 
Identifikasi:
1. Topik/layanan inti yang dibahas situs ini
2. Seed keyword awal berdasarkan topik itu (5-10 keyword). Kalau ada
   lokasi/area target, sertakan juga variasi keyword yang mengandung
   nama lokasi tersebut.
 
Wajib kembalikan HANYA format JSON murni tanpa markdown:
{
  \"core_topics\": [\"topik 1\", \"topik 2\", ...],
  \"seed_keywords\": [\"keyword 1\", \"keyword 2\", ...]
}
";

        try {
            $result = $this->callGeminiJson('gemini-3.6-flash', $prompt);
        } catch (\Throwable $e) {
            Log::warning('identifyTopicsFromWebsite: gagal setelah retry, pakai fallback.', [
                'project_id' => $project->id,
                'error' => ProviderException::sanitise($e->getMessage()),
            ]);
            $result = null;
        }

        if (!$result || empty($result['seed_keywords'])) {
            Log::warning('identifyTopicsFromWebsite: hasil tidak valid.', [
                'project_id' => $project->id,
            ]);
            // Fallback: pakai nama/tipe project sebagai pengganti
            // kalau AI gagal baca konten situs (situs kosong/gagal fetch, dst)
            return [
                'core_topics' => [$project->type ?? 'bisnis umum'],
                'seed_keywords' => array_filter(explode(' ', strtolower($project->name))),
            ];
        }

        return $result;
    }

    public function expandSeedKeywords(Project $project, string $seedKeywords, array $competitorContents = []): array
    {
        $competitorContext = '';
        if (!empty($competitorContents)) {
            $competitorContext = "\n\nKonten kompetitor (referensi, bukan untuk ditiru):\n";
            foreach ($competitorContents as $c) {
                $headingsText = implode(', ', array_slice($c['headings'], 0, 8));
                $competitorContext .= "- {$c['url']} — Judul: \"{$c['title']}\" — Heading: {$headingsText}\n";
            }
        }

        $location = trim((string) ($project->seo_requirements['location'] ?? ''));
        $locationContext = $location !== '' ? "\n\nLokasi/area target bisnis: {$location} — sertakan juga variasi keyword lokal (misal \"[layanan] {$location}\")." : '';

        $prompt = "
You are an SEO Keyword Research Assistant.
Perluas seed keyword berikut jadi daftar KANDIDAT keyword yang lebih
lengkap — termasuk variasi, sinonim, long-tail keyword, dan pertanyaan
yang mungkin dicari orang terkait bisnis ini.
 
Nama Bisnis: {$project->name}
Tipe Bisnis: {$project->type}
Seed keyword dari client: {$seedKeywords}
{$competitorContext}{$locationContext}
 
Hasilkan 25-40 kandidat keyword (kombinasi dari seed + variasi + long-tail).
Ini BARU KANDIDAT, belum keputusan final — jangan filter dulu, sebanyak
mungkin ide relevan.
 
Wajib kembalikan HANYA format JSON murni tanpa markdown:
{\"candidates\": [\"keyword 1\", \"keyword 2\", ...]}
";

        try {
            $result = $this->callGeminiJson('gemini-3.6-flash', $prompt);
        } catch (\Throwable $e) {
            Log::warning('expandSeedKeywords: gagal setelah retry, pakai fallback seed asli.', [
                'project_id' => $project->id,
                'error' => ProviderException::sanitise($e->getMessage()),
            ]);
            $result = null;
        }

        if (!$result || empty($result['candidates'])) {
            Log::warning('expandSeedKeywords: hasil tidak valid, fallback ke seed asli saja.', [
                'project_id' => $project->id,
            ]);
            // Fallback: kalau AI gagal expand, tetap lanjut pakai seed keyword
            // asli saja (dipecah per koma) daripada gagal total.
            return array_filter(array_map('trim', explode(',', $seedKeywords)));
        }

        return $result['candidates'];
    }

    /**
    * Dari kandidat dan sinyal query Search Console, urutkan keyword utama
    * serta related keywords. Search Console bukan sumber volume pasar/Ads.
     */
    public function selectFinalKeywords(Project $project, array $candidates, array $volumeData, array $competitorContents = [], int $count = 25, array $googleAdsMetrics = []): array
    {
        $volumeContext = '';
        if (!empty($volumeData)) {
            $volumeContext = "\n\nQuery pencarian ASLI yang SUDAH pernah membuat website ini tampil di Google (dari Search Console — gunakan sebagai bukti performa nyata, bukan sekadar estimasi):\n";
            foreach ($volumeData as $v) {
                $volumeContext .= "- \"{$v['keyword']}\": {$v['clicks']} klik, {$v['impressions']} tayang, posisi rata-rata {$v['position']}\n";
            }
        } else {
            $volumeContext = "\n\n(Belum ada data performa pencarian dari Search Console untuk website ini — kemungkinan situs baru/belum terindex Google. Nilai berdasarkan relevansi dan penilaian umum saja, beri tahu di summary bahwa ini estimasi kualitatif, bukan data performa nyata.)";
        }

        $googleAdsContext = '';
        if (!empty($googleAdsMetrics)) {
            $googleAdsContext = "\n\nMetrik historis aktual dari Google Ads Keyword Planner (gunakan hanya untuk keyword yang tercantum):\n";
            foreach ($googleAdsMetrics as $keyword => $metrics) {
                $googleAdsContext .= '- "' . $keyword . '": rata-rata pencarian bulanan ' . ($metrics['avg_monthly_searches'] ?? 'tidak tersedia') . ', kompetisi ' . ($metrics['competition'] ?? 'tidak tersedia') . "\n";
            }
        }

        $competitorSummary = '';
        if (!empty($competitorContents)) {
            $competitorSummary = "\n\nJumlah kompetitor yang dianalisis: " . count($competitorContents);
        }

        $location = trim((string) ($project->seo_requirements['location'] ?? ''));
        $locationSummary = $location !== '' ? "\n\nLokasi/area target bisnis: {$location} — prioritaskan keyword yang relevan untuk area ini." : '';

        $candidatesText = implode(', ', $candidates);

        $prompt = "
You are a Senior SEO Strategist.
Dari daftar kandidat keyword berikut, URUTKAN dan pilih {$count} keyword
TERBAIK yang paling worth ditarget untuk bisnis ini. Hasil ini akan
DIREVIEW oleh tim (mereka yang akan mencentang mana yang benar-benar
dipakai), jadi sertakan variasi yang cukup luas — jangan cuma keyword
paling jelas/generik saja.
 
Nama Bisnis: {$project->name}
Tipe Bisnis: {$project->type}
 
Kandidat keyword: {$candidatesText}
{$volumeContext}
{$googleAdsContext}
{$competitorSummary}{$locationSummary}
 
Urutkan berdasarkan: relevansi dengan bisnis, volume Google Ads jika
tersedia, sinyal performa Search Console jika tersedia, dan peluang
ranking realistis untuk bisnis skala ini. Search Console impressions
adalah tayangan situs selama periode laporan, BUKAN volume pencarian
bulanan pasar. Sertakan juga related keywords terpisah untuk
memperluas coverage konten (di luar {$count} keyword utama).

Gunakan angka volume pencarian dan tingkat kompetisi HANYA dari metrik
Google Ads di atas. Jika suatu keyword tidak ada di metrik tersebut,
isi avg_monthly_searches dan competition dengan null untuk keyword itu.

Untuk SETIAP keyword utama, sertakan 2-3 \"derivative_keywords\": variasi
turunan long-tail dari keyword utama itu (lebih spesifik/lebih panjang,
masih satu maksud pencarian) — ini ditampilkan ke klien sebagai kolom
\"Keyword Turunan\" di proposal, jadi harus benar-benar turunan dari
keyword utamanya, bukan keyword acak yang tidak berhubungan.

Wajib kembalikan HANYA format JSON murni tanpa markdown:
{
  \"main_keywords\": [
    {\"keyword\": \"...\", \"derivative_keywords\": [\"turunan 1\", \"turunan 2\"], \"avg_monthly_searches\": angka_atau_null, \"competition\": \"LOW/MEDIUM/HIGH_atau_null\", \"reasoning\": \"alasan singkat\"},
    ...hingga {$count} item, diurutkan dari yang paling direkomendasikan...
  ],
  \"related_keywords\": [\"keyword tambahan 1\", ...],
    \"data_source\": \"google_ads_api, search_console atau ai_estimate\",
  \"summary\": \"ringkasan strategi keyword, 2-3 kalimat\"
}
";

        try {
            $result = $this->callGeminiJson('gemini-3.6-flash', $prompt);
        } catch (\Throwable $e) {
            Log::warning('selectFinalKeywords: gagal mendapatkan hasil dari Gemini setelah retry.', [
                'project_id' => $project->id,
                'error' => ProviderException::sanitise($e->getMessage()),
            ]);
            throw ProviderException::fromThrowable('gemini', $e);
        }

        $result['generated_at'] = now()->toDateTimeString();

        return $result;
    }



}
