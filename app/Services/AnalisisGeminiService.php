<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Client;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;

/**
 * All Gemini calls: business/market analysis (grounded in real competitor
 * content when available), the sitemap + actual website copy that analysis
 * produces (headlines, CTAs, language), and keyword-research support used by
 * KeywordResearchService. See GenerateMockupGptService for the GPT/OpenAI side (mockup
 * design, PNG rendering, approved-mockup decomposition) — these two used to
 * be one class.
 */
class AnalisisGeminiService
{
    /**
     * AI 1 — business and market analysis only.  Template selection belongs
     * to neither AI stage: a WordPress theme is an implementation concern.
     */
    public function analyzeProject(Project $project, Client $client, array $competitorContents = []): array
    {
        if (!config('services.proposal_ai_enabled', true)) {
            Log::warning('AI proposal dinonaktifkan, memakai analisis fallback lokal.', [
                'project_id' => $project->id,
            ]);

            return $this->fallbackProjectAnalysis($project);
        }

        // =====================================================
        // TAHAP 1: GEMINI -> Analisis Bisnis & Target Pasar
        // =====================================================
        try {
            $businessAnalysis = $this->analyzeBusinessWithGemini($project, $client, $competitorContents);
        } catch (\Throwable $e) {
            Log::warning('Gemini tidak tersedia, memakai analisis bisnis fallback.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            $businessAnalysis = $this->fallbackProjectAnalysis($project);
        }

        return $businessAnalysis;
    }

    /**
     * Used both when Gemini is disabled entirely and when a live call fails
     * — includes a minimal `language`/`sitemap` (not just the business
     * analysis fields) since generateMockup() now reads content from here
     * unconditionally; without it, a Gemini outage would crash mockup
     * generation instead of degrading to a plain generic site.
     */
    private function fallbackProjectAnalysis(Project $project): array
    {
        $cta = 'Hubungi Kami';

        return [
            'business_analysis' => [
                'brand_identity' => $project->name,
                'value_proposition' => $project->description ?: 'Professional business website',
            ],
            'market_analysis' => [],
            'target_market' => [
                'demographics' => $project->target_market ?: null,
            ],
            'competitor_analysis' => [],
            'content_benchmark' => ['must_match' => [], 'must_exceed' => []],
            'website_objective' => [
                'primary_goal' => 'Generate qualified enquiries',
            ],
            'language' => 'id',
            'sitemap' => [
                'website_concept' => $project->description ?: 'Website profesional untuk ' . $project->name,
                'global_cta' => $cta,
                'pages' => [
                    ['name' => 'Home', 'sections' => [
                        ['type' => 'hero', 'name' => 'Hero', 'headline' => $project->name, 'description' => $project->description ?: '', 'cta' => $cta, 'items' => []],
                    ]],
                ],
                'seo' => [
                    'primary_keyword' => strtolower((string) ($project->type ?: $project->name)),
                    'meta_title' => $project->name,
                    'meta_description' => $project->description ?: '',
                ],
            ],
        ];
    }

    /**
     * TAHAP 1: Gemini menganalisis bisnis, pasar, dan kompetitor NYATA, lalu
     * dari analisis itu langsung MENULIS konten website-nya sendiri —
     * sitemap, headline, copy tiap section, CTA, dan bahasa (ID/EN). Ini
     * satu-satunya penulis konten: GPT di tahap berikutnya (generateMockup())
     * TIDAK menulis atau mengubah konten ini sama sekali, cuma menentukan
     * desain (warna/font/layout) di atasnya — supaya 3 opsi mockup yang
     * dilihat klien punya KONTEN yang identik dan hanya beda desainnya,
     * bukan malah punya teks yang berbeda-beda tiap opsi juga.
     */
    private function analyzeBusinessWithGemini(Project $project, Client $client, array $competitorContents = []): array
    {
        $targetMarket = trim((string) ($project->target_market ?? ''));
        $targetMarketLine = $targetMarket !== ''
            ? "Target Market (given directly by the team/client — treat this as ground truth for the target_market section, then elaborate on it with concrete detail): {$targetMarket}"
            : "Target Market: Not specified — infer the most plausible target market strictly from the User Story below.";

        $location = trim((string) ($client->address ?? ($project->seo_requirements['location'] ?? '')));
        $locationLine = $location !== '' ? "\nOperating Location/Area: {$location}" : '';

        $existingSiteLine = !empty($client->website) ? "\nExisting Website (if any — this may be a redesign, not a brand-new business): {$client->website}" : '';

        // Kompetitor NYATA yang ditemukan live (Google Places API) untuk
        // Target Market yang sama, lalu isinya di-fetch (bukan cuma nama)
        // — supaya competitor_analysis & target_market berbasis perbandingan
        // sungguhan, bukan tebakan AI semata. Kalau kosong (belum ada
        // Target Market, discovery gagal, atau kredensial belum di-setup),
        // bagian ini dilewati dan Gemini tetap jalan dengan cara lama.
        $competitorContext = '';
        if (!empty($competitorContents)) {
            $competitorContext = "\n\nREAL websites currently serving a similar target market (found via live search just now — use these to make target_market and competitor_analysis concrete and COMPARATIVE, not generic. Compare what they offer/emphasize against our User Story above to find real gaps/differentiation opportunities):\n";
            foreach ($competitorContents as $c) {
                $headingsText = implode(', ', array_slice($c['headings'] ?? [], 0, 8));
                $bodySnippet = mb_substr($c['body_text'] ?? '', 0, 400);
                $competitorContext .= "- {$c['url']} — Title: \"{$c['title']}\" — Headings: {$headingsText} — Excerpt: \"{$bodySnippet}\"\n";
            }
        }

        $prompt = "
You are a Senior Business & Market Analyst AND the copywriter for a real client proposal. Analyze the business below, then WRITE the actual website content grounded in that analysis — real headlines, body copy, and CTAs, not placeholders. A separate design stage will only choose colors/fonts/layout on top of what you write here; it will not add, remove, or rewrite your content, so include everything the site needs.

STRICT GROUNDING RULES:
- The User Story below is the main source of truth — it's written by our team based on what the client actually requested. Base every field on it, plus the Target Market and Website Category given below. Do not invent specific facts (e.g. real competitor names, made-up statistics) that aren't implied by the input" . (!empty($competitorContents) ? ' or the real competitor data below' : '') . ".
- The Website Name is just a branding/domain label — do NOT treat it as a reliable signal of industry or positioning; rely on the User Story for that instead.
- Do NOT write generic filler that could apply to any business (e.g. \"young professionals aged 25-40 who value quality\"). Every sentence must clearly connect back to THIS business's User Story and Target Market.
- target_market and website_objective are the most important sections. target_market must build directly on the Target Market input above (don't contradict or ignore it). website_objective.primary_goal must be INFERRED from what the User Story says the client wants the website to achieve — state it as a concrete, operational goal (e.g. \"drive online orders from the given target market\"), not a copy of the User Story text.
" . (!empty($competitorContents) ? "- You were given REAL competitor websites below (not hypothetical). competitor_analysis.likely_competitor_types and differentiation_strategy MUST be grounded in what they actually show (their headings/content), not generic assumptions.\n" : '') . "- If some information is genuinely insufficient to be specific, make a reasonable, clearly-labeled assumption based on the User Story — never leave a field as vague boilerplate.
- content_benchmark is the most important analysis output: it's your OWN build list for the sitemap you write below — every entry must show up as an actual section. For each entry, name a REAL section/content type (e.g. \"size-conversion guide\", \"live availability badge\", \"founder story video\", \"press mentions strip\") — never a vague label like \"good content\" or \"nice design\".

SITEMAP & COPYWRITING RULES (this is the actual website content, not a summary of it):
- language: pick \"id\" (Bahasa Indonesia) or \"en\" (English) based on the Target Market/Operating Location above — Indonesian local/domestic audiences get \"id\", international/English-speaking or export-facing audiences get \"en\". Write every headline/description/cta below in that language.
- sitemap.pages: include Home, About, Services (or Products), and Contact where relevant to this Website Category — same as a real small-business site would have. Home needs the most sections; other pages can be shorter.
- Every entry in content_benchmark.must_match must appear as an actual section somewhere in sitemap.pages (pick whichever page fits it best). Every entry in content_benchmark.must_exceed must also appear as a section, and that section's description must make the stated advantage concrete and visible (e.g. if the advantage is \"same-day size-exchange, competitors take a week\", say that in the copy — don't just imply quality).
- Each section needs a real \"type\" (hero, about, services, features, portfolio, testimonial, pricing, faq, cta, contact, footer, ...), a \"headline\", a \"description\" (1-3 sentences of real copy, not a placeholder), and a \"cta\" where the section calls for one. Card/grid-style sections (services, features, portfolio, pricing, testimonials, faq) also need 3-6 \"items\", each with its own \"title\" and \"description\".
- Every fact/number/name used in the copy (prices, class times, addresses, ...) must be something a reasonable business in this exact scenario would plausibly have, consistent with the User Story — never contradict it, and never invent a specific real competitor's name as if it were this client's own.

Client / Company: {$client->company_name}
Website Name: {$project->name}
Website Category: {$project->type}
User Story (what the client wants this website to do, written by our team): {$project->description}
{$targetMarketLine}{$locationLine}{$existingSiteLine}{$competitorContext}

Return a JSON object with EXACTLY these keys:
{
  \"business_analysis\": { \"brand_identity\": \"...\", \"value_proposition\": \"...\", \"revenue_model\": \"...\", \"positioning\": \"...\" },
  \"market_analysis\": { \"market_trends\": \"...\", \"swot\": { \"strengths\": [...], \"weaknesses\": [...], \"opportunities\": [...], \"threats\": [...] } },
  \"target_market\": { \"demographics\": \"specific, concrete description tied to the given Target Market\", \"psychographics\": \"...\", \"behaviors\": \"...\", \"pain_points\": [...] },
  \"competitor_analysis\": { \"likely_competitor_types\": [...], \"real_competitors_found\": [{\"url\": \"...\", \"observation\": \"what this real competitor emphasizes, based on their headings/content\"}], \"differentiation_strategy\": \"...\" },
  \"content_benchmark\": {
    \"must_match\": [{\"section\": \"concrete section/content type real competitors already have\", \"why\": \"why our target market expects this, tied to their demographics/behaviors above\"}],
    \"must_exceed\": [{\"section\": \"concrete section/content type\", \"gap_in_competitors\": \"what real competitors do poorly or skip\", \"our_advantage\": \"specifically how this site should do it better\"}]
  },
  \"website_objective\": { \"primary_goal\": \"concrete goal inferred from the User Story\", \"kpis\": [...], \"conversion_goals\": [...], \"ux_goals\": [...] },
  \"language\": \"id\" or \"en\",
  \"sitemap\": {
    \"website_concept\": \"1-2 sentence description of what this site is and does\",
    \"global_cta\": \"the one primary call-to-action used site-wide (nav button, etc)\",
    \"pages\": [
      {\"name\": \"Home\", \"sections\": [
        {\"type\": \"hero\", \"name\": \"Hero\", \"headline\": \"...\", \"description\": \"...\", \"cta\": \"...\", \"items\": []}
      ]}
    ],
    \"seo\": { \"primary_keyword\": \"...\", \"meta_title\": \"...\", \"meta_description\": \"...\" }
  }
}

If no real competitor websites were given above, return an empty array for real_competitors_found, and base content_benchmark on the general standard for this Website Category and Target Market instead (still concrete, not generic).

Respond with ONLY valid JSON, no markdown formatting, no explanation.
";

        $maxRetries = 1;
        $attempt = 1;

        while ($attempt <= $maxRetries) {
            try {
                $response = Gemini::generativeModel('gemini-3.5-flash')
                    ->withGenerationConfig(
                        generationConfig: new GenerationConfig(
                            responseMimeType: ResponseMimeType::APPLICATION_JSON,
                            // Dinaikkan karena model Gemini 3.x memakai sebagian
                            // budget ini untuk "thinking" internal sebelum menulis
                            // JSON output-nya, jadi limit kecil bisa bikin JSON
                            // kepotong tepat di penutup terakhir.
                            maxOutputTokens: 32768,
                        )
                    )
                    ->generateContent($prompt);

                $responseText = $response->text();

                if (empty($responseText)) {
                    throw new \RuntimeException('AI (Gemini) memberikan respons kosong.');
                }

                // Bersihkan kemungkinan markdown code fence (```json ... ```)
                // yang kadang masih dikirim model meski responseMimeType sudah JSON.
                $cleanedText = trim($responseText);
                $cleanedText = preg_replace('/^```(?:json)?\s*/i', '', $cleanedText);
                $cleanedText = preg_replace('/\s*```$/', '', $cleanedText);

                $result = json_decode($cleanedText, true);

                // Auto-repair: kalau JSON kepotong (biasanya cuma kurang
                // kurung kurawal/kurung siku penutup di ujung), coba tutup
                // otomatis berdasarkan selisih jumlah buka vs tutup, lalu
                // decode ulang sebelum benar-benar menyerah.
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $repaired = $this->repairTruncatedJson($cleanedText);
                    $retryResult = json_decode($repaired, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        Log::warning('Gemini Business Analysis: JSON kepotong, berhasil auto-repair', [
                            'project_id' => $project->id,
                            'attempt' => $attempt,
                        ]);
                        $result = $retryResult;
                    }
                }

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('Gemini Business Analysis: Invalid JSON, raw response logged', [
                        'project_id' => $project->id,
                        'attempt' => $attempt,
                        'json_error' => json_last_error_msg(),
                        'raw_length' => strlen($responseText),
                        'raw_response' => $responseText,
                    ]);
                    throw new \RuntimeException('Format JSON Gemini tidak valid: ' . json_last_error_msg());
                }

                // BERHASIL: Kembalikan hasil analisis
                return $result;

            } catch (\Throwable $e) {
                $errorMessage = strtolower($e->getMessage());

                // Cek apakah error termasuk error sementara (Quota, Server Sibuk, Timeout, Server Error)
                $isTransientError = str_contains($errorMessage, 'quota')
                    || str_contains($errorMessage, 'rate-limit')
                    || str_contains($errorMessage, 'high demand')
                    || str_contains($errorMessage, 'overloaded')
                    || str_contains($errorMessage, 'timed out')
                    || str_contains($errorMessage, 'curl error 28')
                    || str_contains($errorMessage, '503')
                    || str_contains($errorMessage, '500')
                    || str_contains($errorMessage, 'format json gemini tidak valid');

                // Jika error sementara dan masih ada jatah retry
                if ($isTransientError && $attempt < $maxRetries) {
                    Log::warning("Gemini Analysis Attempt {$attempt} failed: {$e->getMessage()}. Retrying...", [
                        'project_id' => $project->id
                    ]);

                    // Delay fleksibel: percobaan 1 = 3 detik, percobaan 2 = 6 detik
                    sleep($attempt * 3);
                    $attempt++;
                    continue;
                }

                // Catat error fatal (sudah habis jatah retry / error permanen)
                Log::error('Gemini Business Analysis Error Final: ' . $e->getMessage(), [
                    'project_id' => $project->id,
                    'attempts' => $attempt,
                ]);

                throw new \RuntimeException(
                    $isTransientError
                    ? 'Layanan AI Gemini sedang sibuk atau kuota habis. Silakan coba beberapa saat lagi.'
                    : 'Gagal menghubungi AI Gemini: ' . $e->getMessage()
                );
            }
        }

        throw new \RuntimeException('Gagal mendapatkan analisis bisnis dari Gemini setelah beberapa kali percobaan.');
    }

    /**
     * Perbaikan sederhana untuk JSON yang kepotong di ujung (biasanya karena
     * model kehabisan output token budget sebelum sempat menutup semua
     * kurung). Menghitung selisih kurung buka vs tutup di luar string literal,
     * lalu menambahkan penutup yang kurang di akhir teks.
     * Bukan solusi sempurna (kalau kepotongnya di tengah value/string tetap
     * gagal), tapi menyelamatkan kasus paling umum: kepotong tepat sebelum
     * kurung penutup terakhir.
     */
    private function repairTruncatedJson(string $text): string
    {
        $text = rtrim($text);

        // Kalau berakhir dengan koma sisa (trailing comma), buang dulu.
        $text = preg_replace('/,\s*$/', '', $text);

        $stack = [];
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{' || $char === '[') {
                $stack[] = $char;
            } elseif ($char === '}' || $char === ']') {
                array_pop($stack);
            }
        }

        // Kalau kepotong di tengah string literal, tutup dulu string-nya.
        if ($inString) {
            $text .= '"';
        }

        // Tutup semua kurung yang masih terbuka, urutan terbalik (LIFO).
        while (!empty($stack)) {
            $open = array_pop($stack);
            $text .= ($open === '{') ? '}' : ']';
        }

        return $text;
    }

    /**
     * Helper generik untuk memanggil Gemini dan mem-parsing respons JSON-nya,
     * dengan retry untuk error sementara dan auto-repair kalau JSON kepotong
     * — dipakai oleh alur rekomendasi keyword SEO & Backlink di bawah, yang
     * sebelumnya memanggil Gemini langsung TANPA retry sama sekali.
     * Melempar RuntimeException kalau semua percobaan gagal.
     */
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
                    $repaired = $this->repairTruncatedJson($cleanedText);
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

                Log::error('callGeminiJson gagal final, pesan asli: ' . $e->getMessage(), [
                    'model' => $model,
                    'is_transient' => $isTransientError,
                    'attempts' => $attempt,
                ]);

                throw new \RuntimeException(
                    $isTransientError
                    ? 'Layanan AI Gemini sedang sibuk atau kuota habis.'
                    : 'Gagal menghubungi AI Gemini: ' . $e->getMessage()
                );
            }
        }

        throw new \RuntimeException('Gagal mendapatkan respons Gemini setelah beberapa kali percobaan.');
    }

    /**
     * Sebelum situs client dibuat (jadi belum ada konten buat dibaca ulang),
     * ekstrak istilah pencarian singkat dari User Story — dipakai
     * CompetitorDiscoveryService buat mencari kompetitor NYATA (via Google
     * Places) yang sasarannya mirip. Gagal secara halus (fallback ke
     * Website Category) supaya proses generate proposal tetap lanjut kalau
     * Gemini lagi bermasalah.
     */
    public function extractCompetitorSearchContext(Project $project): array
    {
        $prompt = "
You are a Market Research Assistant.
From the business info below, extract SHORT search terms that could be
used to find REAL competing businesses/websites serving a similar
market on Google — this is NOT a full analysis, just search terms.

Website Name: {$project->name}
Website Category: {$project->type}
User Story: {$project->description}

Wajib kembalikan HANYA format JSON murni tanpa markdown:
{
  \"business_type\": \"2-4 word business/industry category suitable for a search query, e.g. 'specialty coffee shop'\",
  \"topics\": [\"keyword1\", \"keyword2\", ... 3-6 short topic keywords]
}
";

        try {
            $result = $this->callGeminiJson('gemini-3.6-flash', $prompt);
        } catch (\Throwable $e) {
            Log::warning('extractCompetitorSearchContext: gagal, fallback ke Website Category.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            return ['business_type' => $project->type ?? '', 'topics' => []];
        }

        return [
            'business_type' => $result['business_type'] ?? ($project->type ?? ''),
            'topics' => $result['topics'] ?? [],
        ];
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
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage(),
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
 
Wajib kembalikan HANYA format JSON murni tanpa markdown:
{
  \"main_keywords\": [
    {\"keyword\": \"...\", \"avg_monthly_searches\": angka_atau_null, \"competition\": \"LOW/MEDIUM/HIGH_atau_null\", \"reasoning\": \"alasan singkat\"},
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
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $result['generated_at'] = now()->toDateTimeString();

        return $result;
    }



}
