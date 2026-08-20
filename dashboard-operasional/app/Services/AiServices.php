<?php
namespace App\Services;

use App\Models\Project;
use App\Models\Client;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;


class AiServices
{
    public function analyzeProject(Project $project, Client $client, array $zipWpTemplates = []): array
    {
        if (!config('services.proposal_ai_enabled', true)) {
            Log::warning('AI proposal dinonaktifkan, memakai analisis fallback lokal.', [
                'project_id' => $project->id,
            ]);

            return $this->fallbackProjectAnalysis($project, $zipWpTemplates);
        }

        // =====================================================
        // TAHAP 1: GEMINI -> Analisis Bisnis & Target Pasar
        // =====================================================
        try {
            $businessAnalysis = $this->analyzeBusinessWithGemini($project, $client);
        } catch (\Throwable $e) {
            Log::warning('Gemini tidak tersedia, memakai analisis bisnis fallback.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            $businessAnalysis = [
                'business_analysis' => [
                    'brand_identity' => $project->name,
                    'value_proposition' => $project->description ?: 'Professional business website',
                ],
                'market_analysis' => [],
                'target_market' => [],
                'competitor_analysis' => [],
                'website_objective' => [
                    'primary_goal' => 'Generate qualified enquiries',
                ],
            ];
        }

        // =====================================================
        // TAHAP 2: GPT -> Sitemap, Struktur, Desain Web, + PILIH TEMPLATE
        // =====================================================
        try {
            $designAnalysis = $this->analyzeDesignWithGpt($project, $businessAnalysis, $zipWpTemplates);
        } catch (\Throwable $e) {
            Log::warning('GPT tidak tersedia, memakai analisis desain fallback.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            $fallbackTemplate = $this->pickBestTemplate(
                $zipWpTemplates,
                $project->type ?? 'Company Profile',
                $project->description ?? ''
            );

            $designAnalysis = [
                'sitemap' => [],
                'page_structure' => [],
                'content_strategy' => [
                    'tone_of_voice' => 'Professional and welcoming',
                ],
                'cta_strategy' => [],
                'design_direction' => [
                    'layout_style' => 'Clean, responsive and conversion-focused',
                ],
                'template_selection' => $fallbackTemplate ? [
                    'uuid' => $fallbackTemplate['uuid'] ?? null,
                    'name' => $fallbackTemplate['name'] ?? null,
                    'reason' => 'Selected by local fallback matching.',
                ] : null,
            ];
        }

        // =====================================================
        // GABUNGKAN HASIL KEDUANYA
        // =====================================================
        return array_merge($businessAnalysis, $designAnalysis);
    }

    private function fallbackProjectAnalysis(Project $project, array $zipWpTemplates): array
    {
        $fallbackTemplate = $this->pickBestTemplate(
            $zipWpTemplates,
            $project->type ?? 'Company Profile',
            $project->description ?? ''
        );

        return [
            'business_analysis' => [
                'brand_identity' => $project->name,
                'value_proposition' => $project->description ?: 'Professional business website',
            ],
            'market_analysis' => [],
            'target_market' => [],
            'competitor_analysis' => [],
            'website_objective' => [
                'primary_goal' => 'Generate qualified enquiries',
            ],
            'sitemap' => [],
            'page_structure' => [],
            'content_strategy' => [
                'tone_of_voice' => 'Professional and welcoming',
            ],
            'cta_strategy' => [],
            'design_direction' => [
                'layout_style' => 'Clean, responsive and conversion-focused',
            ],
            'template_selection' => $fallbackTemplate ? [
                'uuid' => $fallbackTemplate['uuid'] ?? null,
                'name' => $fallbackTemplate['name'] ?? null,
                'reason' => 'Selected by local fallback matching.',
            ] : null,
        ];
    }

    /**
     * TAHAP 1: Gemini fokus ke analisis bisnis, pasar, kompetitor.
     * Hasilnya JANGAN termasuk sitemap/design_direction — itu tugas GPT.
     */
    private function analyzeBusinessWithGemini(Project $project, Client $client): array
    {
        $prompt = "
You are a Senior Business & Market Analyst.
Analyze the following business and return ONLY business/market-related insights.
DO NOT include website sitemap, page structure, or visual design direction — that will be handled separately.
 
Business Name: {$project->name}
Business Description: {$project->description}
Client: {$client->name}
Business Type: {$project->type}
 
Return a JSON object with EXACTLY these keys:
{
  \"business_analysis\": { ... brand identity, value proposition, revenue model, positioning ... },
  \"market_analysis\": { ... market trends, SWOT analysis ... },
  \"target_market\": { ... demographics, psychographics, behaviors, pain points ... },
  \"competitor_analysis\": { ... direct competitors, differentiation strategy ... },
  \"website_objective\": { ... primary KPIs, conversion goals, UX goals ... }
}
 
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
     * TAHAP 2: GPT fokus ke sitemap, struktur halaman, content strategy,
     * CTA strategy, dan design direction. Menerima hasil analisis Gemini
     * sebagai konteks supaya keputusan desainnya nyambung dengan bisnisnya.
     */
    private function analyzeDesignWithGpt(Project $project, array $businessAnalysis, array $zipWpTemplates = []): array
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            Log::warning('OpenAI API Key tidak ditemukan untuk analisis desain.');
            throw new \RuntimeException('Konfigurasi AI (GPT) untuk analisis desain belum lengkap.');
        }

        $businessContext = json_encode($businessAnalysis, JSON_UNESCAPED_UNICODE);

        // Ringkas daftar template ZipWP jadi versi ringan (uuid, name,
        // categories, keywords saja) supaya prompt tidak membengkak kalau
        // template-nya banyak.
        $templateOptions = array_map(function ($tpl) {
            return [
                'uuid' => $tpl['uuid'] ?? $tpl['slug'] ?? $tpl['id'] ?? null,
                'name' => $tpl['name'] ?? null,
                'categories' => $tpl['categories'] ?? [],
                'keywords' => $tpl['keywords'] ?? [],
            ];
        }, $zipWpTemplates);
        $templateOptionsJson = json_encode($templateOptions, JSON_UNESCAPED_UNICODE);

        $templateInstruction = !empty($templateOptions)
            ? "Pilih SATU template paling cocok dari daftar TEMPLATE_OPTIONS di bawah berdasarkan business & market context di atas. Wajib pilih uuid yang benar-benar ada di daftar, jangan mengarang uuid baru.\n\nTEMPLATE_OPTIONS:\n{$templateOptionsJson}"
            : "TEMPLATE_OPTIONS kosong — set template_selection.uuid ke null.";

        $prompt = "
You are a Senior UI/UX Designer and Information Architect.
Based on the business and market analysis below, design the WEBSITE STRUCTURE and VISUAL DIRECTION.
DO NOT repeat or re-analyze the business/market — just use it as context.
 
Business & Market Context:
{$businessContext}

{$templateInstruction}
 
Return a JSON object with EXACTLY these keys:
{
  \"sitemap\": { \"primary_navigation\": [...], \"secondary_navigation\": [...], \"footer_navigation\": [...] },
  \"page_structure\": { \"homepage\": [...], \"product_detail_page\": [...], \"about_story_page\": [...] },
  \"content_strategy\": { \"tone_of_voice\": \"...\", \"key_messaging_pillars\": {...}, \"media_assets_requirements\": \"...\" },
  \"cta_strategy\": { \"primary_ctas\": {...}, \"secondary_ctas\": {...}, \"micro_conversions\": [...] },
  \"design_direction\": { \"color_palette\": {...}, \"typography\": {...}, \"layout_style\": {...} },
  \"template_selection\": { \"uuid\": \"...\", \"name\": \"...\", \"reason\": \"...\" }
}
 
Respond with ONLY valid JSON, no markdown formatting, no explanation.
";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                        'model' => 'gpt-5-mini', // sesuaikan dengan model chat/text terbaru yang kamu pakai
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'response_format' => ['type' => 'json_object'],
                    ]);

            if (!$response->successful()) {
                Log::error('GPT Design Analysis Error: ' . $response->body(), ['project_id' => $project->id]);
                throw new \RuntimeException('Gagal menghubungi layanan AI (GPT) untuk analisis desain web.');
            }

            $responseText = $response->json('choices.0.message.content');

            if (empty($responseText)) {
                Log::warning('GPT Design Analysis: Empty response', ['project_id' => $project->id]);
                throw new \RuntimeException('AI (GPT) tidak memberikan respons analisis desain web.');
            }

            $result = json_decode($responseText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('GPT Design Analysis: Invalid JSON', [
                    'project_id' => $project->id,
                    'raw' => $responseText,
                    'json_error' => json_last_error_msg(),
                ]);
                throw new \RuntimeException('Format respons analisis desain web (GPT) tidak valid.');
            }

            // Validasi: uuid yang dipilih GPT harus benar-benar ada di daftar
            // template asli (jaga-jaga kalau GPT halusinasi/mengarang uuid).
            // Kalau tidak valid, fallback ke scoring lokal pickBestTemplate().
            $chosenUuid = $result['template_selection']['uuid'] ?? null;
            $validUuids = array_column($zipWpTemplates, 'uuid');
            $matchedTemplate = null;

            if ($chosenUuid && in_array($chosenUuid, $validUuids, true)) {
                $matchedTemplate = collect($zipWpTemplates)->firstWhere('uuid', $chosenUuid);
            } else {
                if ($chosenUuid) {
                    Log::warning('GPT Design Analysis: uuid template yang dipilih tidak ditemukan di daftar, fallback ke scoring lokal', [
                        'project_id' => $project->id,
                        'chosen_uuid' => $chosenUuid,
                    ]);
                }
                $matchedTemplate = $this->pickBestTemplate(
                    $zipWpTemplates,
                    $project->type ?? 'Company Profile',
                    $project->description ?? ''
                );
            }

            $result['template_selection'] = $matchedTemplate ? [
                'uuid' => $matchedTemplate['uuid'] ?? null,
                'name' => $matchedTemplate['name'] ?? null,
                'reason' => $result['template_selection']['reason'] ?? null,
            ] : null;

            return $result;

        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GPT Design Analysis Exception: ' . $e->getMessage(), ['project_id' => $project->id]);
            throw new \RuntimeException('Gagal menghubungi layanan AI (GPT) untuk analisis desain web.');
        }
    }
    /**
     * Pilih template terbaik berdasarkan kecocokan categories & keywords.
     * Return ARRAY template lengkap (bukan string slug) supaya controller
     * bisa akses uuid, name, preview_url secara langsung.
     */
    public function pickBestTemplate(array $templates, string $projectType, string $businessDesc = ''): ?array
    {
        if (empty($templates)) {
            return null;
        }

        $projectTypeLower = strtolower($projectType);
        $descLower = strtolower($businessDesc);

        $bestMatch = null;
        $highestScore = -1;

        foreach ($templates as $template) {
            $score = 0;

            // Field asli ZipWP: 'categories' (array) dan 'keywords' (array)
            $categories = array_map('strtolower', $template['categories'] ?? []);
            $keywords = array_map('strtolower', $template['keywords'] ?? []);
            $name = strtolower($template['name'] ?? '');

            // 1. Kecocokan kategori dengan project_type (bobot tinggi)
            foreach ($categories as $category) {
                if (str_contains($category, $projectTypeLower) || str_contains($projectTypeLower, $category)) {
                    $score += 10;
                }
            }

            // 2. Kecocokan keywords dengan project_type & deskripsi bisnis
            foreach ($keywords as $keyword) {
                if (empty($keyword))
                    continue;
                if (str_contains($projectTypeLower, $keyword)) {
                    $score += 5;
                }
                if (!empty($descLower) && str_contains($descLower, $keyword)) {
                    $score += 2;
                }
            }

            // 3. Kecocokan nama template
            if (str_contains($name, $projectTypeLower)) {
                $score += 3;
            }

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $template; // simpan ARRAY LENGKAP, bukan slug
            }
        }

        // Fallback ke template pertama kalau tidak ada yang cocok sama sekali
        return $bestMatch ?? $templates[0] ?? null;
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
            // Fallback: pakai business_description project sebagai pengganti
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
    public function selectFinalKeywords(Project $project, array $candidates, array $volumeData, array $competitorContents = []): array
    {
        $volumeContext = '';
        if (!empty($volumeData)) {
            $volumeContext = "\n\nData volume pencarian ASLI dari Google Ads (gunakan ini sebagai dasar utama):\n";
            foreach ($volumeData as $v) {
                $volumeContext .= "- \"{$v['keyword']}\": ~{$v['avg_monthly_searches']} pencarian/bulan, persaingan: {$v['competition']}\n";
            }
        } else {
            $volumeContext = "\n\n(Data volume Google Ads tidak tersedia saat ini — nilai berdasarkan relevansi dan penilaian umum saja, beri tahu di summary bahwa ini estimasi kualitatif, bukan angka pasti.)";
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
Dari daftar kandidat keyword berikut, TENTUKAN 10 keyword UTAMA yang
paling worth ditarget untuk bisnis ini.
 
Nama Bisnis: {$project->name}
Tipe Bisnis: {$project->type}
 
Kandidat keyword: {$candidatesText}
{$volumeContext}
{$competitorSummary}{$locationSummary}
 
Pilih 10 keyword utama berdasarkan: relevansi dengan bisnis, volume
pencarian (kalau data tersedia), tingkat persaingan, dan peluang ranking
realistis untuk bisnis skala ini. Sertakan juga related keywords untuk
memperluas coverage konten.
 
Wajib kembalikan HANYA format JSON murni tanpa markdown:
{
  \"main_keywords\": [
    {\"keyword\": \"...\", \"avg_monthly_searches\": angka_atau_null, \"competition\": \"LOW/MEDIUM/HIGH_atau_null\", \"reasoning\": \"alasan singkat\"},
    ...10 item...
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