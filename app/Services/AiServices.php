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

            $businessAnalysis = [
                'business_analysis' => [
                    'brand_identity' => $project->name,
                    'value_proposition' => $project->description ?: 'Professional business website',
                ],
                'market_analysis' => [],
                'target_market' => [
                    'demographics' => $project->target_market ?: null,
                ],
                'competitor_analysis' => [],
                'website_objective' => [
                    'primary_goal' => 'Generate qualified enquiries',
                ],
            ];
        }

        return $businessAnalysis;
    }

    private function fallbackProjectAnalysis(Project $project): array
    {
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
            'website_objective' => [
                'primary_goal' => 'Generate qualified enquiries',
            ],
        ];
    }

    /**
     * TAHAP 1: Gemini fokus ke analisis bisnis, pasar, kompetitor.
     * Hasilnya JANGAN termasuk sitemap/design_direction — itu tugas GPT.
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
You are a Senior Business & Market Analyst preparing input for a real client proposal.
Analyze the following business and return ONLY business/market-related insights.
DO NOT include website sitemap, page structure, or visual design direction — that will be handled separately.

STRICT GROUNDING RULES:
- The User Story below is the main source of truth — it's written by our team based on what the client actually requested. Base every field on it, plus the Target Market and Website Category given below. Do not invent specific facts (e.g. real competitor names, made-up statistics) that aren't implied by the input" . (!empty($competitorContents) ? ' or the real competitor data below' : '') . ".
- The Website Name is just a branding/domain label — do NOT treat it as a reliable signal of industry or positioning; rely on the User Story for that instead.
- Do NOT write generic filler that could apply to any business (e.g. \"young professionals aged 25-40 who value quality\"). Every sentence must clearly connect back to THIS business's User Story and Target Market.
- target_market and website_objective are the most important sections. target_market must build directly on the Target Market input above (don't contradict or ignore it). website_objective.primary_goal must be INFERRED from what the User Story says the client wants the website to achieve — state it as a concrete, operational goal (e.g. \"drive online orders from the given target market\"), not a copy of the User Story text.
" . (!empty($competitorContents) ? "- You were given REAL competitor websites below (not hypothetical). competitor_analysis.likely_competitor_types and differentiation_strategy MUST be grounded in what they actually show (their headings/content), not generic assumptions.\n" : '') . "- If some information is genuinely insufficient to be specific, make a reasonable, clearly-labeled assumption based on the User Story — never leave a field as vague boilerplate.

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
  \"website_objective\": { \"primary_goal\": \"concrete goal inferred from the User Story\", \"kpis\": [...], \"conversion_goals\": [...], \"ux_goals\": [...] }
}

If no real competitor websites were given above, return an empty array for real_competitors_found — do not invent entries.

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
     * AI 2 — turn the completed business analysis into a website blueprint.
     * This intentionally has no ZipWP/WordPress template input.
     */
    public function generateMockup(Project $project, array $analysis): array
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            Log::warning('OpenAI API Key tidak ditemukan; memakai mockup fallback lokal.', ['project_id' => $project->id]);
            return $this->fallbackMockup($project, $analysis);
        }

        $analysisJson = json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $referenceType = $project->design_reference_type ?: 'none';
        $referenceUrl = $project->design_reference_url ?: 'not provided';
        $referenceFile = $project->design_reference_path ? basename($project->design_reference_path) : 'not provided';
        $prompt = <<<PROMPT
You are a senior website designer. Create a complete website mockup blueprint from the client data and the AI 1 business analysis below.

Client: {$project->client_name}
Project: {$project->name}
Website category: {$project->type}
Client brief: {$project->description}

AI 1 ANALYSIS:
{$analysisJson}

CLIENT DESIGN REFERENCE (use it only as inspiration; never copy branding, text, assets, or source code):
Type: {$referenceType}
Website URL: {$referenceUrl}
Uploaded file: {$referenceFile}

Return ONLY valid JSON with this exact shape:
{
  "website_concept": "...",
  "design": {
    "style": "...", "primary_color": "#...", "secondary_color": "#...", "accent_color": "#...",
    "font_heading": "...", "font_body": "..."
  },
  "pages": [
    {"name": "Home", "sections": [
      {"type": "hero", "name": "Hero", "headline": "...", "description": "...", "cta": "...", "layout": "...", "items": []}
    ]}
  ],
  "global_cta": "...",
  "seo": {"primary_keyword": "...", "meta_title": "...", "meta_description": "..."}
}

Include Home, About, Services, and Contact where relevant. Give each page meaningful sections and realistic content tied to this client. Use section types such as hero, about, services, portfolio, features, testimonial, cta, contact, and footer. For card/grid sections, provide 3-6 concise items with title and description.

If an image reference is attached to this message, it is the PRIMARY visual direction. Extract its layout hierarchy, spacing, section order, typography mood, colour treatment, card composition, navigation treatment, and CTA placement. Reinterpret those characteristics for this client; do not copy names, text, logos, photographs, or protected artwork from it. The result must be a content-rich full website mockup, never an empty basic theme or wireframe. Do not select or mention any WordPress, ZipWP, or BeTheme template.
PROMPT;

        try {
            $messageContent = [['type' => 'text', 'text' => $prompt]];
            $referenceImage = $this->referenceImageDataUrl($project);
            if ($referenceImage) {
                $messageContent[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $referenceImage, 'detail' => 'high'],
                ];
            }

            $response = Http::timeout(90)->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.mockup_model', 'gpt-5-mini'),
                'messages' => [['role' => 'user', 'content' => $messageContent]],
                'response_format' => ['type' => 'json_object'],
            ]);

            $result = json_decode((string) $response->json('choices.0.message.content'), true);
            if (!$response->successful() || !is_array($result) || empty($result['pages'])) {
                throw new \RuntimeException('Respons mockup AI tidak valid.');
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('AI mockup gagal; memakai mockup fallback lokal.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            return $this->fallbackMockup($project, $analysis);
        }
    }

    /** Return a local design-reference image as a vision-compatible data URL. */
    private function referenceImageDataUrl(Project $project): ?string
    {
        if ($project->design_reference_type !== 'image' || !$project->design_reference_path) {
            return null;
        }

        try {
            $path = Storage::disk('public')->path($project->design_reference_path);
            if (!is_file($path) || filesize($path) > 5 * 1024 * 1024) {
                return null;
            }

            $mime = mime_content_type($path) ?: '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return null;
            }

            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
        } catch (\Throwable $e) {
            Log::warning('Design reference image could not be attached to AI mockup request.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fallbackMockup(Project $project, array $analysis): array
    {
        $overview = data_get($analysis, 'business_analysis.value_proposition', $project->description ?: 'Layanan profesional untuk kebutuhan Anda.');
        $cta = 'Konsultasikan Kebutuhan Anda';

        return [
            'website_concept' => 'Website profesional yang membangun kredibilitas dan mengarahkan calon pelanggan untuk menghubungi bisnis.',
            'design' => [
                'style' => 'Modern, clean, professional, and conversion-focused',
                'primary_color' => '#1E3A5F', 'secondary_color' => '#F8FAFC', 'accent_color' => '#2563EB',
                'font_heading' => 'Poppins', 'font_body' => 'Inter',
            ],
            'pages' => [
                ['name' => 'Home', 'sections' => [
                    ['type' => 'hero', 'name' => 'Hero', 'headline' => $project->name, 'description' => $overview, 'cta' => $cta],
                    ['type' => 'about', 'name' => 'About', 'headline' => 'Tentang Kami', 'description' => 'Kenali nilai, pengalaman, dan komitmen kami kepada setiap pelanggan.'],
                    ['type' => 'services', 'name' => 'Services', 'headline' => 'Layanan Kami', 'description' => 'Solusi yang disusun untuk menjawab kebutuhan bisnis dan pelanggan Anda.', 'items' => [
                        ['title' => 'Konsultasi', 'description' => 'Diskusi kebutuhan bersama tim kami.'],
                        ['title' => 'Solusi Utama', 'description' => 'Layanan yang tepat untuk target bisnis Anda.'],
                        ['title' => 'Dukungan', 'description' => 'Pendampingan yang jelas dari awal hingga selesai.'],
                    ]],
                    ['type' => 'cta', 'name' => 'CTA', 'headline' => $cta, 'description' => 'Hubungi tim kami untuk memulai percakapan.', 'cta' => $cta],
                ]],
                ['name' => 'About', 'sections' => [
                    ['name' => 'Company Profile', 'headline' => 'Mengenal ' . $project->name, 'description' => $overview],
                    ['name' => 'Why Choose Us', 'headline' => 'Mengapa Memilih Kami', 'description' => 'Kualitas layanan, komunikasi yang jelas, dan fokus pada hasil.'],
                ]],
                ['name' => 'Services', 'sections' => [
                    ['name' => 'Service List', 'headline' => 'Layanan Profesional', 'description' => 'Jelajahi layanan yang paling relevan untuk kebutuhan Anda.'],
                ]],
                ['name' => 'Contact', 'sections' => [
                    ['name' => 'Contact Form', 'headline' => 'Mari Berdiskusi', 'description' => 'Kirimkan kebutuhan Anda dan tim kami akan menghubungi Anda.', 'cta' => $cta],
                ]],
            ],
            'global_cta' => $cta,
            'seo' => [
                'primary_keyword' => strtolower((string) ($project->type ?: $project->name)),
                'meta_title' => $project->name . ' | ' . ($project->type ?: 'Website'),
                'meta_description' => $project->description ?: 'Informasi layanan dan kontak ' . $project->name,
            ],
        ];
    }

    /**
     * Legacy template-selection implementation. It is retained only for
     * backwards compatibility with non-proposal callers.
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

ALIGNMENT RULES:
- website_objective.primary_goal in the context below is the client's actual stated goal. Every decision here must serve it directly:
  - cta_strategy.primary_ctas must be the literal action that achieves that goal (e.g. goal = \"increase online sales\" -> primary CTA is \"Shop Now\" / \"Order Now\", NOT a generic \"Contact Us\").
  - content_strategy must speak to target_market's demographics/psychographics/pain_points from the context, not a generic audience.
  - sitemap/page_structure must include whatever pages that goal actually requires (e.g. a sales goal needs product/pricing/checkout-adjacent pages; a leads goal needs a strong contact/quote-request page).
- template_selection.reason must explain the match in terms of THIS business's industry and target_market — not just \"it looks modern/professional\".
- Avoid generic placeholder language everywhere (\"engaging content\", \"user-friendly design\") — be specific to this business.
- If competitor_analysis.real_competitors_found is present in the context (real websites already serving this target market), use it: content_strategy and page_structure should let this site credibly compete with what those real competitors emphasize, not just follow a generic template.

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
  \"template_selection\": { \"uuid\": \"...\", \"name\": \"...\", \"reason\": \"tie explicitly to this business's industry and target market\" }
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
