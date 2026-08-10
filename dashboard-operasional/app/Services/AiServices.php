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
        // =====================================================
        // TAHAP 1: GEMINI -> Analisis Bisnis & Target Pasar
        // =====================================================
        $businessAnalysis = $this->analyzeBusinessWithGemini($project, $client);

        // =====================================================
        // TAHAP 2: GPT -> Sitemap, Struktur, Desain Web, + PILIH TEMPLATE
        // =====================================================
        $designAnalysis = $this->analyzeDesignWithGpt($project, $businessAnalysis, $zipWpTemplates);

        // =====================================================
        // GABUNGKAN HASIL KEDUANYA
        // =====================================================
        return array_merge($businessAnalysis, $designAnalysis);
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

        $maxRetries = 3;
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
                if (empty($keyword)) continue;
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
}