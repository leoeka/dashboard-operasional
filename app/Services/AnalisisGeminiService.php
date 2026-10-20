<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use App\Models\Project;
use App\Models\Client;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use App\Support\JsonRepair;

/**
 * The Gemini calls in this app.
 *
 * In the website pipeline Gemini does exactly one thing now: read the User
 * Story and state the target market the client is aiming at, which the SEO
 * side needs and the content stage builds on. Writing the website itself —
 * analysis, sitemap, copy, CTAs — used to be Gemini's job too and moved to
 * GPT (see GenerateMockupGptService::writeWebsiteContent), so one model owns
 * what the site says and how it looks.
 *
 * The keyword-research helpers at the bottom belong to the SEO feature
 * (KeywordResearchService, RunKeywordPreviewAnalysisJob), not to the website
 * pipeline, and are unchanged.
 */
class AnalisisGeminiService
{
    /**
     * Reads the target market out of the User Story, plus the short search
     * terms used to find real businesses serving that same market.
     *
     * The team usually already wrote the audience inside the User Story ("my
     * market is X, Y, Z"); this puts that into one field the rest of the
     * pipeline can use, and fills it in for the projects where the story only
     * implies one.
     *
     * Fails soft on purpose: this is enrichment, and a Gemini outage must not
     * stop a proposal that the team can still write by hand.
     *
     * @return array{target_market: string, business_type: string, topics: array<int,string>, from_team: bool}
     */
    public function deriveTargetMarket(Project $project, ?Client $client = null): array
    {
        $given = trim((string) ($project->target_market ?? ''));

        try {
            $result = $this->callGeminiJson('gemini-3.6-flash', $this->targetMarketPrompt($project, $client));
        } catch (\Throwable $e) {
            Log::warning('deriveTargetMarket: Gemini gagal, pakai isian tim apa adanya.', [
                'project_id' => $project->id,
                'error' => ProviderException::sanitise($e->getMessage()),
            ]);

            return [
                'target_market' => $given,
                'business_type' => (string) ($project->type ?? ''),
                'topics' => [],
                'from_team' => $given !== '',
            ];
        }

        return [
            // An audience the team typed is a fact from the client; a derived
            // one is a reading of the brief. The team's words always win.
            'target_market' => $given !== '' ? $given : trim((string) ($result['target_market'] ?? '')),
            'business_type' => $result['business_type'] ?? ($project->type ?? ''),
            'topics' => is_array($result['topics'] ?? null) ? $result['topics'] : [],
            'from_team' => $given !== '',
        ];
    }

    private function targetMarketPrompt(Project $project, ?Client $client): string
    {
        $location = trim((string) ($client?->address ?? data_get($project->seo_requirements, 'location') ?? ''));
        $locationLine = $location !== '' ? "\nOperating Location/Area: {$location}" : '';

        return <<<PROMPT
You are a Market Research Assistant for a web agency serving Indonesian businesses.

Read the brief below and state WHO this website is for: the audience the client
wants to reach, in 2-4 sentences. Then give short search terms that find REAL
competing businesses serving that same market.

Rules:
- The User Story is the only evidence about this client. If it names its target market (like "target pasarnya X, Y, Z" or "my audience is ..."), return those segments in those words and add nothing the client did not name.
- If the story only implies an audience, infer the single most plausible one and keep it concrete for THIS business. Never generic filler like "young professionals who value quality".
- target_market: who the site is for, or an empty string if the brief genuinely does not say.
- business_type: 2-4 word industry category usable in a search query, e.g. 'specialty coffee shop'.
- topics: 3-6 short keyword phrases a searcher would type to find a business like this.

Website Name: {$project->name}
Website Category: {$project->type}
Client: {$project->client_name}{$locationLine}
User Story (written by our team from what the client asked for): {$project->description}

Return ONLY valid JSON, no markdown:
{ "target_market": "...", "business_type": "...", "topics": ["..."] }
PROMPT;
    }

    /**
     * Helper generik untuk memanggil Gemini dan mem-parsing respons JSON-nya,
     * dengan retry untuk error sementara dan auto-repair kalau JSON kepotong
     * — dipakai oleh alur rekomendasi keyword SEO & Backlink di bawah, yang
     * sebelumnya memanggil Gemini langsung TANPA retry sama sekali.
     * Melempar RuntimeException kalau semua percobaan gagal.
     */
    /**
     * Pembungkus publik tipis untuk callGeminiJson — dipakai service lain
     * (mis. ContentExtractabilityService) yang butuh satu panggilan Gemini
     * "prompt masuk, JSON keluar" tanpa mengulang logika retry/repair di
     * sini. Model mengikuti pemanggil callGeminiJson lain di kelas ini.
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
     * TAHAP 3: Dari kandidat + data volume Google Ads (kalau tersedia), pilih
     * 10 keyword utama final + related keywords. AI di sini menganalisis DATA
     * (kalau ada), bukan menebak dari nol lagi.
     */
    public function selectFinalKeywords(Project $project, array $candidates, array $volumeData, array $competitorContents = [], int $count = 25): array
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
{$competitorSummary}{$locationSummary}
 
Urutkan berdasarkan: relevansi dengan bisnis, volume pencarian (kalau
data tersedia), tingkat persaingan, dan peluang ranking realistis untuk
bisnis skala ini. Sertakan juga related keywords terpisah untuk
memperluas coverage konten (di luar {$count} keyword utama).

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
  \"data_source\": \"google_ads_api atau ai_estimate\",
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
