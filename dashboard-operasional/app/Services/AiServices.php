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
        $businessAnalysis = $analysis['business_analysis'] ?? '';
        $websiteType = $project->type ?? 'Landing Page';
        $clientName = $project->client_name;

        return "Dribbble style UI/UX design mockup for \"{$clientName}\", a {$websiteType} website. " .
            "Business context: {$businessAnalysis}. " .
            "Design direction: {$designDirection}. " .
            "Clean layout, modern typography, hero section with call-to-action button, " .
            "high quality 8k desktop view, elegant color palette, Figma UI concept, smooth gradients, no blur, sharp resolution.";
    }
    // =====================================================
// PILIH TEMPLATE ZIPWP YANG PALING SESUAI (GPT)
// =====================================================

    public function pickBestTemplate(Project $project, array $templates): ?array
    {
        if (empty($templates)) {
            Log::warning('pickBestTemplate: daftar template kosong, tidak ada kandidat untuk dipilih.');
            return null;
        }

        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            return $templates[0];
        }

        $candidateList = collect($templates)->map(function ($t, $i) {
            $categories = implode(', ', $t['categories'] ?? []);
            $keywords = implode(', ', array_slice($t['keywords'] ?? [], 0, 8));
            return "{$i}. UUID: {$t['uuid']} | Nama: {$t['name']} | Kategori: {$categories} | Keywords: {$keywords}";
        })->implode("\n");

        $prompt = "Kamu adalah asisten yang memilih template website paling relevan untuk sebuah bisnis.

Data bisnis client:
- Tipe Bisnis: {$project->type}
- Detail: {$project->requirement_notes}

Daftar template tersedia:
{$candidateList}

Pilih SATU template paling sesuai. Jawab HANYA JSON murni tanpa markdown:
{\"uuid\": \"...\"}";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                        'model' => 'gpt-4o-mini',
                        'messages' => [['role' => 'user', 'content' => $prompt]],
                        'temperature' => 0,
                    ]);

            $content = $response->json('choices.0.message.content');
            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content ?? ''));
            $result = json_decode($clean, true);
            $uuid = $result['uuid'] ?? null;

            return collect($templates)->firstWhere('uuid', $uuid) ?? $templates[0];
        } catch (\Exception $e) {
            Log::error('Pick Best Template Error: ' . $e->getMessage());
            return $templates[0];
        }
    }

    // =====================================================
// SCREENSHOT TEMPLATE ZIPWP ASLI
// =====================================================

    public function fetchTemplateScreenshot(string $previewUrl, Project $project): ?string
    {
        try {
            $screenshotUrl = 'https://image.thum.io/get/width/1200/crop/1600/' . $previewUrl;

            $imageContent = null;
            $maxAttempts = 4;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $response = Http::timeout(30)->get($screenshotUrl);

                if ($response->successful()) {
                    $body = $response->body();

                    // Placeholder thum.io ("please wait") biasanya jauh lebih kecil
                    // dari screenshot asli. Kalau masih kecil, berarti belum siap.
                    if (strlen($body) > 20000) {
                        $imageContent = $body;
                        break;
                    }
                }

                // Belum siap, tunggu sebelum coba lagi
                if ($attempt < $maxAttempts) {
                    sleep(5);
                }
            }

            if (!$imageContent) {
                Log::warning('Screenshot thum.io belum siap setelah beberapa percobaan, pakai hasil terakhir.');
                $imageContent = $body ?? null;
            }

            if (!$imageContent) {
                return null;
            }

            $filename = 'mockups/screenshot_' . $project->id . '_' . Str::random(8) . '.png';
            Storage::disk('public')->put($filename, $imageContent);

            return 'storage/' . $filename;
        } catch (\Exception $e) {
            Log::error('Screenshot fetch error: ' . $e->getMessage());
            return null;
        }
    }

    // =====================================================
// GENERATE LOGO AI (FALLBACK KALAU KLIEN TIDAK PUNYA LOGO)
// =====================================================

    public function generateLogo(Project $project, array $analysis): ?string
    {
        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            return null;
        }

        $businessType = $project->type ?? 'business';
        $prompt = "Minimalist modern logo mark for \"{$project->client_name}\", a {$businessType} business. " .
            "Simple flat icon style, clean lines, single or two-color palette, transparent background, no text, centered, professional branding logo.";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/images/generations', [
                        'model' => 'gpt-image-1-mini',
                        'prompt' => $prompt,
                        'n' => 1,
                        'size' => '1024x1024',
                        'quality' => 'medium',
                        'background' => 'transparent',
                    ]);

            $b64 = $response->json('data.0.b64_json');
            if (!$b64) {
                return null;
            }

            $filename = 'logos/ai_logo_' . $project->id . '_' . Str::random(8) . '.png';
            Storage::disk('public')->put($filename, base64_decode($b64));

            return 'storage/' . $filename;
        } catch (\Exception $e) {
            Log::error('Generate Logo Error: ' . $e->getMessage());
            return null;
        }
    }

    // =====================================================
// TEMPEL LOGO KE SCREENSHOT MOCKUP
// =====================================================

    public function compositeLogoOntoMockup(?string $screenshotRelPath, ?string $logoRelPath): ?string
    {
        if (!$screenshotRelPath) {
            return null;
        }

        $screenshotFull = storage_path('app/public/' . Str::after($screenshotRelPath, 'storage/'));

        if (!$logoRelPath) {
            return $screenshotRelPath;
        }

        $logoFull = storage_path('app/public/' . Str::after($logoRelPath, 'storage/'));

        if (!file_exists($screenshotFull) || !file_exists($logoFull)) {
            return $screenshotRelPath;
        }

        try {
            $base = $this->loadImageFromFile($screenshotFull);
            $logo = $this->loadImageFromFile($logoFull);

            if (!$base || !$logo) {
                return $screenshotRelPath;
            }

            imagesavealpha($base, true);
            imagesavealpha($logo, true);

            $logoWidth = 120;
            $logoHeight = (int) ($logoWidth * (imagesy($logo) / imagesx($logo)));

            $resizedLogo = imagecreatetruecolor($logoWidth, $logoHeight);
            imagealphablending($resizedLogo, false);
            imagesavealpha($resizedLogo, true);
            imagecopyresampled($resizedLogo, $logo, 0, 0, 0, 0, $logoWidth, $logoHeight, imagesx($logo), imagesy($logo));

            $margin = 20;
            imagecopy($base, $resizedLogo, $margin, $margin, 0, 0, $logoWidth, $logoHeight);

            $filename = 'mockups/final_' . Str::random(10) . '.png';
            imagepng($base, storage_path('app/public/' . $filename));

            imagedestroy($base);
            imagedestroy($logo);
            imagedestroy($resizedLogo);

            return 'storage/' . $filename;
        } catch (\Exception $e) {
            Log::error('Composite Logo Error: ' . $e->getMessage());
            return $screenshotRelPath;
        }
    }

    private function loadImageFromFile(string $path)
    {
        $type = @exif_imagetype($path);
        return match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => null,
        };
    }
}
