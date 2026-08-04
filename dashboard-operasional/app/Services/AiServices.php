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
    public function analyzeProject(Project $project): array
    {
        // Prompt yang memaksa keluaran JSON terstruktur
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
            // Panggil model gemini-2.5-flash langsung
            $response = Gemini::generativeModel('gemini-2.5-flash')
                ->generateContent($prompt);

            $responseText = $response->text();

            // Saring markdown fence jika AI tidak sengaja menyertakan ```json ... ```
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

    public function generateMockup(Project $project, array $analysis): ?string
    {
        // 1. Susun Prompt Gambar yang Sangat Spesifik untuk UI/UX
        $prompt = $this->buildImagePrompt($project, $analysis);

        try {
            // 2. Opsi A: Menggunakan API Black Forest Labs / Flux (Rekomendasi untuk UI Design)
            // Jika Anda menggunakan provider seperti Replicate / Together AI / Fal.ai yang menyediakan model Flux:
            $apiKey = config('services.flux.api_key', env('FLUX_API_KEY'));

            if (!$apiKey) {
                Log::warning('Flux/Blackbox API Key tidak ditemukan. Menggunakan placeholder mockup.');
                return null;
            }

            // Contoh HTTP Request ke Provider Flux / Image AI API
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->post('https://api.bfl.ml/v1/flux-pro-1.1', [
                        'prompt' => $prompt,
                        'width' => 1440,
                        'height' => 900,
                        'aspect_ratio' => '16:9',
                        'output_format' => 'png',
                    ]);

            if ($response->successful()) {
                $imageUrl = $response->json('result.sample'); // Sesuaikan dengan key JSON response API Anda

                // Download gambar dan simpan ke local storage Laravel
                return $this->downloadAndSaveImage($imageUrl, $project->id);
            }

            Log::error('Image Generation Error: ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('Mockup Generator Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Formula Prompt UI/UX Design agar hasil estetik dan profesional.
     */
    private function buildImagePrompt(Project $project, array $analysis): string
    {
        $designDirection = $analysis['design_direction'] ?? 'Modern, clean, responsive';
        $websiteType = $project->type ?? 'Landing Page';

        return "Dribbble style UI/UX design mockup for a {$websiteType} website. " .
            "Theme: {$designDirection}. " .
            "Clean layout, modern typography, hero section with call-to-action button, " .
            "high quality 8k desktop view, elegant color palette, Figma UI concept, smooth gradients, no blur, sharp resolution.";
    }

    /**
     * Simpan gambar dari URL API ke folder storage/app/public/mockups
     */
    private function downloadAndSaveImage(string $url, int $projectId): ?string
    {
        $contents = file_get_contents($url);
        if (!$contents)
            return null;

        $filename = 'mockups/project_' . $projectId . '_' . Str::random(10) . '.png';
        Storage::disk('public')->put($filename, $contents);

        return 'storage/' . $filename;
    }
}