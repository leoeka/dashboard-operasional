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
    public function analyzeProject(Project $project, Client $client): array
    {
        // =====================================================
        // TAHAP 1: GEMINI -> Analisis Bisnis & Target Pasar
        // =====================================================
        $businessAnalysis = $this->analyzeBusinessWithGemini($project, $client);

        // =====================================================
        // TAHAP 2: GPT -> Sitemap, Struktur, Desain Web
        // =====================================================
        $designAnalysis = $this->analyzeDesignWithGpt($project, $businessAnalysis);

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

        $maxRetries = 2;
        $attempt = 0;

        while ($attempt <= $maxRetries) {
            try {
                $response = Gemini::generativeModel('gemini-3.5-flash')
                    ->withGenerationConfig(
                        generationConfig: new GenerationConfig(
                            responseMimeType: ResponseMimeType::APPLICATION_JSON,
                        )
                    )
                    ->generateContent($prompt);

                $responseText = $response->text();

                if (empty($responseText)) {
                    Log::warning('Gemini Business Analysis: Empty response', ['project_id' => $project->id]);
                    throw new \RuntimeException('AI (Gemini) tidak memberikan respons analisis bisnis.');
                }

                $result = json_decode($responseText, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('Gemini Business Analysis: Invalid JSON', [
                        'project_id' => $project->id,
                        'raw' => $responseText,
                        'json_error' => json_last_error_msg(),
                    ]);
                    throw new \RuntimeException('Format respons analisis bisnis (Gemini) tidak valid.');
                }

                return $result;

            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Exception $e) {
                $isQuotaError = str_contains($e->getMessage(), 'quota') || str_contains($e->getMessage(), 'rate-limit');

                if ($isQuotaError && $attempt < $maxRetries) {
                    $attempt++;
                    sleep(60);
                    continue;
                }

                Log::error('Gemini Business Analysis Error: ' . $e->getMessage(), ['project_id' => $project->id]);

                throw new \RuntimeException(
                    $isQuotaError
                    ? 'Kuota AI (Gemini) sudah habis. Silakan coba lagi dalam beberapa saat.'
                    : 'Gagal menghubungi layanan AI (Gemini) untuk analisis bisnis.'
                );
            }
        }

        throw new \RuntimeException('Gagal mendapatkan analisis bisnis dari Gemini.');
    }

    /**
     * TAHAP 2: GPT fokus ke sitemap, struktur halaman, content strategy,
     * CTA strategy, dan design direction. Menerima hasil analisis Gemini
     * sebagai konteks supaya keputusan desainnya nyambung dengan bisnisnya.
     */
    private function analyzeDesignWithGpt(Project $project, array $businessAnalysis): array
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            Log::warning('OpenAI API Key tidak ditemukan untuk analisis desain.');
            throw new \RuntimeException('Konfigurasi AI (GPT) untuk analisis desain belum lengkap.');
        }

        $businessContext = json_encode($businessAnalysis, JSON_UNESCAPED_UNICODE);

        $prompt = "
You are a Senior UI/UX Designer and Information Architect.
Based on the business and market analysis below, design the WEBSITE STRUCTURE and VISUAL DIRECTION.
DO NOT repeat or re-analyze the business/market — just use it as context.
 
Business & Market Context:
{$businessContext}
 
Return a JSON object with EXACTLY these keys:
{
  \"sitemap\": { \"primary_navigation\": [...], \"secondary_navigation\": [...], \"footer_navigation\": [...] },
  \"page_structure\": { \"homepage\": [...], \"product_detail_page\": [...], \"about_story_page\": [...] },
  \"content_strategy\": { \"tone_of_voice\": \"...\", \"key_messaging_pillars\": {...}, \"media_assets_requirements\": \"...\" },
  \"cta_strategy\": { \"primary_ctas\": {...}, \"secondary_ctas\": {...}, \"micro_conversions\": [...] },
  \"design_direction\": { \"color_palette\": {...}, \"typography\": {...}, \"layout_style\": {...} }
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

            return $result;

        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GPT Design Analysis Exception: ' . $e->getMessage(), ['project_id' => $project->id]);
            throw new \RuntimeException('Gagal menghubungi layanan AI (GPT) untuk analisis desain web.');
        }
    }
    public function pickBestTemplate(Project $project, array $candidates, array $analysis = []): ?array
    {
        if (empty($candidates)) {
            return null;
        }

        // Kumpulkan kata kunci dari project + hasil analisis bisnis
        $keywords = array_filter([
            $project->type,
            $analysis['business_analysis']['brand_identity'] ?? null,
            $analysis['target_market']['demographics'] ?? null,
        ]);

        $keywordText = strtolower(implode(' ', array_map(
            fn($v) => is_array($v) ? json_encode($v) : (string) $v,
            $keywords
        )));

        $bestScore = -1;
        $bestTemplate = null;

        foreach ($candidates as $template) {
            $templateText = strtolower(
                ($template['name'] ?? '') . ' ' . ($template['category'] ?? '')
            );

            $score = 0;
            foreach (preg_split('/\s+/', $templateText) as $word) {
                $word = trim($word);
                if (strlen($word) >= 4 && str_contains($keywordText, $word)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTemplate = $template;
            }
        }

        // Kalau tidak ada yang cocok sama sekali (score 0 semua),
        // tetap ambil kandidat pertama daripada gagal total.
        return $bestTemplate ?? $candidates[0] ?? null;
    }

}