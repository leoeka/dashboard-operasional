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

        // FIX #1 (revisi): 'gemini-3.5-flash' tidak pernah ada di API Google.
        // 'gemini-2.5-flash' sempat dipakai tapi per Agustus 2026 sudah
        // "no longer available to new users" (lihat log error). Google sering
        // deprecate model Flash generation lama dengan cepat — per Juli 2026
        // model GA (stabil, production-ready) terbaru adalah gemini-3.6-flash.
        // WAJIB taruh di config/env, JANGAN hardcode di kode — supaya kalau
        // Google deprecate lagi, cukup ganti env var tanpa redeploy kode.
        $modelName = config('services.gemini.model', 'gemini-3.6-flash');

        try {
            $response = Gemini::generativeModel($modelName)
                ->generateContent($prompt);

            $responseText = $response->text();
            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($responseText));
            $result = json_decode($cleanJson, true);

            if (!$result || json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Gemini AI Analysis: response bukan JSON valid, pakai fallback.', [
                    'project_id' => $project->id,
                    'raw_response' => $responseText ?? null,
                ]);
                return $this->getFallbackAnalysis(false);
            }

            // FIX #4: tandai hasil sebagai AI-generated supaya layer di atas
            // (controller/PDF) tahu ini bukan data placeholder.
            $result['_ai_generated'] = true;

            return $result;
        } catch (\Exception $e) {
            // Deteksi khusus pesan deprecation Google ("no longer available")
            // supaya gampang di-grep di log/alert dan dibedakan dari error
            // jaringan/timeout biasa — model deprecation butuh fix kode
            // (ganti nama model), bukan sekadar retry.
            $isDeprecation = Str::contains(strtolower($e->getMessage()), ['no longer available', 'not found', 'deprecated']);

            Log::error('Gemini AI Analysis Error: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'model' => $modelName,
                'likely_model_deprecated' => $isDeprecation,
            ]);

            return $this->getFallbackAnalysis(false);
        }
    }

    private function getFallbackAnalysis(bool $aiGenerated = false): array
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
            // FIX #4: flag eksplisit — dipakai controller/PDF generator untuk
            // menandai proposal "perlu direview / regenerate", jangan
            // ditampilkan ke client tapi berguna untuk internal QA / retry job.
            '_ai_generated' => $aiGenerated,
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
     * Fallback kalau generator utama gagal/timeout — supaya alur PDF tetap jalan.
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
            return $this->fallbackTemplateByKeyword($project, $templates) ?? $templates[0];
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

            if (!$response->successful()) {
                throw new \Exception('OpenAI non-200 response: ' . $response->status() . ' — ' . $response->body());
            }

            $content = $response->json('choices.0.message.content');
            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content ?? ''));
            $result = json_decode($clean, true);
            $uuid = $result['uuid'] ?? null;

            $match = collect($templates)->firstWhere('uuid', $uuid);
            if ($match) {
                return $match;
            }

            // FIX #2: sebelumnya langsung asal ambil $templates[0] tanpa relevansi
            // sama sekali kalau GPT gagal/return uuid yang tidak match. Sekarang
            // coba cocokkan keyword lokal dulu sebagai fallback yang lebih masuk akal.
            Log::warning('Pick Best Template: GPT tidak mengembalikan uuid yang valid, coba fallback keyword lokal.', [
                'project_id' => $project->id,
                'gpt_uuid' => $uuid,
            ]);
            return $this->fallbackTemplateByKeyword($project, $templates) ?? $templates[0];

        } catch (\Exception $e) {
            Log::error('Pick Best Template Error: ' . $e->getMessage());
            return $this->fallbackTemplateByKeyword($project, $templates) ?? $templates[0];
        }
    }

    /**
     * FIX #2: fallback lokal berbasis pencocokan keyword sederhana antara
     * tipe/kebutuhan project dengan kategori & keyword template, dipakai
     * sebelum jatuh ke $templates[0] secara membabi buta.
     */
    private function fallbackTemplateByKeyword(Project $project, array $templates): ?array
    {
        $needleRaw = strtolower(trim(($project->type ?? '') . ' ' . ($project->requirement_notes ?? '')));
        if ($needleRaw === '') {
            return null;
        }

        $words = array_filter(explode(' ', $needleRaw), fn($w) => strlen($w) > 3);
        if (empty($words)) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;

        foreach ($templates as $template) {
            $haystack = strtolower(implode(' ', array_merge(
                $template['categories'] ?? [],
                $template['keywords'] ?? [],
                [$template['name'] ?? '']
            )));

            $score = 0;
            foreach ($words as $word) {
                if (Str::contains($haystack, $word)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $template;
            }
        }

        return $bestScore > 0 ? $bestMatch : null;
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
            $minValidBytes = 20000; // di bawah ini biasanya masih placeholder "please wait" milik thum.io

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $response = Http::timeout(30)->get($screenshotUrl);

                if ($response->successful()) {
                    $body = $response->body();

                    if (strlen($body) > $minValidBytes) {
                        $imageContent = $body;
                        break;
                    }

                    Log::info('Screenshot thum.io masih placeholder/belum siap, retry.', [
                        'project_id' => $project->id,
                        'attempt' => $attempt,
                        'bytes' => strlen($body),
                    ]);
                }

                if ($attempt < $maxAttempts) {
                    sleep(5);
                }
            }

            // FIX #3: sebelumnya di sini ada fallback yang menyimpan $body
            // terakhir (bisa jadi masih placeholder "please wait" thum.io)
            // sebagai hasil final. Sekarang kalau tidak ada gambar yang lolos
            // validasi ukuran, return null secara eksplisit — jangan simpan
            // gambar yang jelas-jelas gagal validasi.
            if (!$imageContent) {
                Log::warning('Screenshot thum.io belum siap setelah beberapa percobaan, tidak ada gambar valid untuk disimpan.', [
                    'project_id' => $project->id,
                    'preview_url' => $previewUrl,
                ]);
                return null;
            }

            $filename = 'mockups/screenshot_' . $project->id . '_' . Str::random(8) . '.png';
            Storage::disk('public')->put($filename, $imageContent);

            return 'storage/' . $filename;
        } catch (\Exception $e) {
            Log::error('Screenshot fetch error: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'preview_url' => $previewUrl,
            ]);
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

            if (!$response->successful()) {
                Log::error('Generate Logo gagal: ' . $response->body());
                return null;
            }

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
            Log::warning('Composite Logo: file screenshot atau logo tidak ditemukan di disk.', [
                'screenshot_full' => $screenshotFull,
                'screenshot_exists' => file_exists($screenshotFull),
                'logo_full' => $logoFull,
                'logo_exists' => file_exists($logoFull),
            ]);
            return $screenshotRelPath;
        }

        try {
            $base = $this->loadImageFromFile($screenshotFull);
            $logo = $this->loadImageFromFile($logoFull);

            if (!$base || !$logo) {
                Log::warning('Composite Logo: gagal load image resource via GD (format tidak didukung / file korup).', [
                    'screenshot_full' => $screenshotFull,
                    'screenshot_loaded' => (bool) $base,
                    'logo_full' => $logoFull,
                    'logo_loaded' => (bool) $logo,
                ]);
                return $screenshotRelPath;
            }

            $logoOriginalWidth = imagesx($logo);
            $logoOriginalHeight = imagesy($logo);

            // FIX: sebelumnya kalau imagesx($logo) === 0 (logo korup/format
            // aneh), pembagian ($logoWidth * (imagesy/imagesx)) bikin
            // DivisionByZeroError — ini bukan turunan \Exception di PHP 8,
            // jadi TIDAK ketangkep catch(\Exception $e) di bawah, errornya
            // silently crash bagian ini tanpa log sama sekali.
            if ($logoOriginalWidth <= 0 || $logoOriginalHeight <= 0) {
                Log::warning('Composite Logo: dimensi logo tidak valid (0 atau negatif), kemungkinan file korup.', [
                    'logo_full' => $logoFull,
                    'width' => $logoOriginalWidth,
                    'height' => $logoOriginalHeight,
                ]);
                imagedestroy($base);
                imagedestroy($logo);
                return $screenshotRelPath;
            }

            imagesavealpha($base, true);
            imagesavealpha($logo, true);

            $logoWidth = 120;
            $logoHeight = (int) ($logoWidth * ($logoOriginalHeight / $logoOriginalWidth));

            $resizedLogo = imagecreatetruecolor($logoWidth, $logoHeight);
            imagealphablending($resizedLogo, false);
            imagesavealpha($resizedLogo, true);
            imagecopyresampled($resizedLogo, $logo, 0, 0, 0, 0, $logoWidth, $logoHeight, $logoOriginalWidth, $logoOriginalHeight);

            $margin = 20;
            imagecopy($base, $resizedLogo, $margin, $margin, 0, 0, $logoWidth, $logoHeight);

            $filename = 'mockups/final_' . Str::random(10) . '.png';
            $targetPath = storage_path('app/public/' . $filename);
            $writeSuccess = imagepng($base, $targetPath);

            imagedestroy($base);
            imagedestroy($logo);
            imagedestroy($resizedLogo);

            // FIX: imagepng() return false kalau gagal nulis (folder tidak
            // writable, disk penuh, dll) — sebelumnya return value ini tidak
            // pernah dicek, jadi fungsi tetap "sukses" mengembalikan path ke
            // file yang sebenarnya TIDAK PERNAH ADA di disk. Akibatnya URL
            // gambar kelihatan valid tapi selalu 404 saat diakses browser.
            if (!$writeSuccess) {
                Log::error('Composite Logo: imagepng() gagal menulis file ke disk.', [
                    'target_path' => $targetPath,
                    'target_dir_exists' => is_dir(dirname($targetPath)),
                    'target_dir_writable' => is_writable(dirname($targetPath)),
                ]);
                return $screenshotRelPath;
            }

            // Verifikasi tambahan: pastikan file beneran ada & bukan 0 byte
            // setelah ditulis, sebelum diklaim sukses.
            if (!file_exists($targetPath) || filesize($targetPath) === 0) {
                Log::error('Composite Logo: imagepng() return true tapi file tidak valid setelah dicek ulang.', [
                    'target_path' => $targetPath,
                    'exists' => file_exists($targetPath),
                    'size' => file_exists($targetPath) ? filesize($targetPath) : null,
                ]);
                return $screenshotRelPath;
            }

            return 'storage/' . $filename;
        } catch (\Throwable $e) {
            // FIX: ganti dari catch(\Exception) ke catch(\Throwable) supaya
            // Error-turunan (DivisionByZeroError, TypeError, dll dari GD)
            // ikut ketangkep dan ke-log, bukan silently crash bagian ini.
            Log::error('Composite Logo Error: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'screenshot_full' => $screenshotFull,
                'logo_full' => $logoFull,
            ]);
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