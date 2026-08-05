<?php
namespace App\Services;

use App\Models\Project;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiServices
{
    // =====================================================
    // ANALISIS TEKS PROJECT — tetap pakai Gemini (ini beda dari generate gambar)
    // =====================================================

    public function analyzeProject(Project $project): array
    {
        $prompt = "Kamu adalah seorang Web Strategy Consultant profesional.
Analisis request pembuatan website berikut dan berikan strategi yang terstruktur:
- Nama Klien: {$project->client_name}
- Nama Proyek: {$project->name}
- Tipe Website: {$project->type}
- Deskripsi / Kebutuhan: {$project->description}

Berikan respons dalam bahasa Indonesia yang ringkas, profesional, dan relevan dengan industri klien.
Wajib kembalikan HANYA format JSON murni tanpa markdown/text tambahan seperti ini:
{
  \"business_analysis\": \"...\",
  \"market_analysis\": \"...\",
  \"target_market\": \"...\",
  \"competitor_analysis\": \"...\",
  \"website_objective\": \"...\",
  \"sitemap\": \"...\",
  \"page_structure\": \"...\",
  \"content_strategy\": \"...\",
  \"cta_strategy\": \"...\",
  \"design_direction\": \"...\"
}";

        try {
            $response = Gemini::generativeModel('gemini-3.5-flash')
                ->generateContent($prompt);

            $responseText = $response->text();
            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($responseText));
            $result = json_decode($cleanJson, true);

            return $result ?: $this->getFallbackAnalysis();
        } catch (\Exception $e) {
            Log::error('Gemini AI Analysis Error: ' . $e->getMessage());
            return $this->getFallbackAnalysis();
        }
    }

    private function getFallbackAnalysis(): array
    {
        return [
            'business_analysis' => 'Analisis bisnis standar untuk pengembangan website profesional.',
            'market_analysis' => 'Pasar digital membutuhkan kehadiran platform yang terpercaya.',
            'target_market' => 'Pelanggan potensial dan mitra bisnis perusahaan.',
            'competitor_analysis' => 'Analisis dilakukan pada kompetitor sejenis di industri.',
            'website_objective' => 'Meningkatkan brand awareness dan konversi penjualan.',
            'sitemap' => 'Beranda, Tentang Kami, Layanan/Produk, Portofolio, Kontak.',
            'page_structure' => 'Header navigasi, Hero banner, Value proposition, Form kontak.',
            'content_strategy' => 'Konten informatif berbasis keunggulan layanan.',
            'cta_strategy' => 'Tombol Hubungi Kami via WhatsApp dan Formulir Penawaran.',
            'design_direction' => 'Desain modern, clean, dan responsif di semua perangkat.',
        ];
    }

    // =====================================================
    // GENERATE GAMBAR MOCKUP — PAKAI POLLINATIONS (gratis, tanpa API key)
    // JANGAN ganti balik ke Flux/Gemini manual — dua-duanya sudah terbukti
    // butuh billing berbayar (lihat riwayat log sebelumnya).
    // =====================================================

    public function generateMockup(Project $project, array $analysis): ?string
    {
        $prompt = $this->buildImagePrompt($project, $analysis);
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            Log::warning('OpenAI API Key tidak ditemukan.');
            return $this->generatePlaceholderMockup($project);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/images/generations', [
                        // gpt-image-1-mini jauh lebih murah dari gpt-image-1, cocok buat mockup preview
                        'model' => 'gpt-image-1-mini',
                        'prompt' => $prompt,
                        'n' => 1,
                        'size' => '1536x1024',
                        // quality low/medium menekan biaya lebih jauh (default gpt-image = 'auto'/high)
                        'quality' => 'medium',
                    ]);

            if (!$response->successful()) {
                Log::error('OpenAI image gen gagal (pakai placeholder): ' . $response->body());
                return $this->generatePlaceholderMockup($project);
            }

            // gpt-image-1 selalu mengembalikan base64 (b64_json), bukan url seperti dall-e-3
            $b64 = $response->json('data.0.b64_json');
            if (!$b64) {
                return $this->generatePlaceholderMockup($project);
            }

            $imageContents = base64_decode($b64);
            $filename = 'mockups/project_' . $project->id . '_' . Str::random(10) . '.png';
            Storage::disk('public')->put($filename, $imageContents);

            return 'storage/' . $filename;

        } catch (\Exception $e) {
            Log::error('OpenAI Mockup Generator Exception (pakai placeholder): ' . $e->getMessage());
            return $this->generatePlaceholderMockup($project);
        }
    }
    /**
     * Fallback kalau Pollinations gagal/timeout — supaya alur PDF tetap jalan.
     */
    private function generatePlaceholderMockup(Project $project): ?string
    {
        try {
            $response = Http::timeout(15)->get(
                'https://placehold.co/1440x896/2563eb/ffffff/png',
                ['text' => 'AI Mockup Preview (Placeholder)']
            );

            if (!$response->successful()) {
                return null;
            }

            $filename = 'mockups/project_' . $project->id . '_' . Str::random(10) . '.png';
            Storage::disk('public')->put($filename, $response->body());

            return 'storage/' . $filename;
        } catch (\Exception $e) {
            Log::error('Placeholder generator juga gagal: ' . $e->getMessage());
            return null;
        }
    }

    private function buildImagePrompt(Project $project, array $analysis): string
    {
        $designDirection = $analysis['design_direction'] ?? 'Modern, clean, responsive';
        $websiteType = $project->type ?? 'Landing Page';

        return "Dribbble style UI/UX design mockup for a {$websiteType} website. " .
            "Theme: {$designDirection}. " .
            "Clean layout, modern typography, hero section with call-to-action button, " .
            "high quality 8k desktop view, elegant color palette, Figma UI concept, smooth gradients, no blur, sharp resolution.";
    }
}
