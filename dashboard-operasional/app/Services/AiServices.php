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
    // =====================================================
    // ANALISIS TEKS PROJECT — tetap pakai Gemini (ini beda dari generate gambar)
    // =====================================================

    public function analyzeProject(Project $project, Client $client): array
    {
        $prompt = "
Act as a Senior Web Strategy Consultant.
Based on the client request below, analyze the business and recommend the best website strategy.
company name:
{$client->company_name}
Type Website:
{$project->type}
website description:
{$project->description}
target market:
{$project->target_market}
Return ONLY JSON containing:
- business_analysis
- market_analysis
- target_market
- competitor_analysis
- website_objective
- sitemap
- page_structure
- content_strategy
- cta_strategy
- design_direction

The design_direction must be detailed enough for another AI to generate a professional website mockup.
";

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
                Log::warning('Gemini AI Analysis: Empty response', ['project_id' => $project->id]);
                throw new \RuntimeException('AI tidak memberikan respons. Silakan coba lagi.');
            }

            $result = json_decode($responseText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Gemini AI Analysis: Invalid JSON', [
                    'project_id' => $project->id,
                    'raw' => $responseText,
                    'json_error' => json_last_error_msg(),
                ]);
                throw new \RuntimeException('Format respons AI tidak valid. Silakan coba lagi.');
            }

            return $result;

        } catch (\RuntimeException $e) {
            // lempar ulang pesan yang sudah jelas
            throw $e;
        } catch (\Exception $e) {
            Log::error('Gemini AI Analysis Error: ' . $e->getMessage(), [
                'project_id' => $project->id,
            ]);
            throw new \RuntimeException('Gagal menghubungi layanan AI. Silakan coba beberapa saat lagi.');
        }
    }




    // =====================================================
    // GENERATE GAMBAR MOCKUP — PAKAI POLLINATIONS (gratis, tanpa API key)
    // JANGAN ganti balik ke Flux/Gemini manual — dua-duanya sudah terbukti
    // butuh billing berbayar (lihat riwayat log sebelumnya).
    // =====================================================

    public function generateMockup(Project $project, array $analysis): array
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            Log::warning('OpenAI API Key tidak ditemukan.');
            $placeholder = $this->generatePlaceholderMockup($project);
            return ['merged' => $placeholder, 'sections' => []];
        }

        // Definisikan 3 bagian yang akan di-generate terpisah
        $sections = [
            'top' => $this->buildImagePromptSection($project, $analysis, 'navbar_hero'),
            'middle' => $this->buildImagePromptSection($project, $analysis, 'content'),
            'bottom' => $this->buildImagePromptSection($project, $analysis, 'footer'),
        ];

        $generatedImages = [];

        foreach ($sections as $key => $prompt) {
            $imagePath = $this->generateSingleImage($prompt, $key);
            if ($imagePath) {
                $generatedImages[$key] = $imagePath;
            }
        }

        // Kalau semua gagal, fallback ke placeholder
        if (empty($generatedImages)) {
            Log::error('Semua section mockup gagal digenerate, pakai placeholder.', ['project_id' => $project->id]);
            $placeholder = $this->generatePlaceholderMockup($project);
            return ['merged' => $placeholder, 'sections' => []];
        }

        // Gabung semua gambar yang berhasil jadi 1 PNG panjang
        // (dipakai untuk thumbnail/preview di halaman project, BUKAN untuk PDF)
        $finalPath = $this->stitchImagesVertically($generatedImages, $project);

        // TIDAK dihapus lagi — dipakai terpisah di PDF supaya potongan halaman
        // jatuh di antara section (bukan di tengah section) seperti kejadian sebelumnya.
        // Ubah path jadi bentuk 'storage/...' supaya konsisten dipakai di blade.
        $sectionsForPdf = [];
        foreach (['top', 'middle', 'bottom'] as $key) {
            if (isset($generatedImages[$key])) {
                $sectionsForPdf[$key] = 'storage/' . $generatedImages[$key];
            }
        }

        return [
            'merged' => $finalPath ?? $this->generatePlaceholderMockup($project),
            'sections' => $sectionsForPdf,
        ];
    }

    /**
     * Generate satu gambar dari satu prompt, simpan sebagai file sementara,
     * dan return path relatif-nya (dalam disk 'public').
     * Return null kalau gagal (supaya section lain tetap bisa lanjut).
     */
    private function generateSingleImage(string $prompt, string $sectionKey): ?string
    {
        $apiKey = config('services.openai.key');

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/images/generations', [
                        'model' => 'gpt-image-1-mini',
                        'prompt' => $prompt,
                        'n' => 1,
                        'size' => '1024x1536',
                        'quality' => 'medium',
                    ]);

            if (!$response->successful()) {
                Log::error("OpenAI image gen gagal untuk section [{$sectionKey}]: " . $response->body());
                return null;
            }

            $b64 = $response->json('data.0.b64_json');
            if (!$b64) {
                Log::warning("OpenAI tidak mengembalikan b64_json untuk section [{$sectionKey}]");
                return null;
            }

            $imageContents = base64_decode($b64);
            $filename = 'mockups/temp_' . $sectionKey . '_' . Str::random(10) . '.png';

            // Simpan dulu, lalu potong ruang kosong di bagian bawah (kalau ada)
            // supaya tidak ada gap besar saat ditaruh di PDF.
            Storage::disk('public')->put($filename, $imageContents);
            $this->trimBottomWhitespace(Storage::disk('public')->path($filename));

            return $filename;

        } catch (\Exception $e) {
            Log::error("Exception generate section [{$sectionKey}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Potong ruang kosong (putih/transparan) di bagian BAWAH sebuah gambar PNG.
     * Dipakai supaya gambar hasil AI yang kontennya cuma mengisi sebagian atas
     * kanvas tidak menyisakan gap kosong besar saat ditaruh di PDF.
     * Scan baris demi baris dari bawah, cari baris terakhir yang punya konten
     * (bukan putih/transparan polos), lalu crop di situ (+ sedikit padding).
     */
    private function trimBottomWhitespace(string $fullPath, int $tolerance = 250, int $paddingBottom = 20): void
    {
        $image = @imagecreatefrompng($fullPath);
        if (!$image) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $lastContentRow = $height - 1;

        // Scan dari baris paling bawah ke atas, sample beberapa titik per baris
        // (tidak semua pixel, biar cepat) untuk cari baris terakhir yang ada kontennya.
        for ($y = $height - 1; $y >= 0; $y--) {
            $hasContent = false;

            for ($x = 0; $x < $width; $x += max(1, intdiv($width, 40))) {
                $rgb = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $rgb);

                $isNearWhite = $colors['red'] >= (255 - $tolerance) / 255 * 255
                    && $colors['green'] >= (255 - $tolerance) / 255 * 255
                    && $colors['blue'] >= (255 - $tolerance) / 255 * 255;
                $isTransparent = $colors['alpha'] >= 120;

                if (!$isNearWhite && !$isTransparent) {
                    $hasContent = true;
                    break;
                }
            }

            if ($hasContent) {
                $lastContentRow = $y;
                break;
            }
        }

        $newHeight = min($height, $lastContentRow + $paddingBottom);

        // Kalau hasil crop tidak signifikan (kontennya memang hampir penuh),
        // tidak usah crop supaya tidak buang-buang proses.
        if ($newHeight >= $height * 0.95) {
            imagedestroy($image);
            return;
        }

        $cropped = imagecreatetruecolor($width, $newHeight);
        imagesavealpha($cropped, true);
        $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
        imagefill($cropped, 0, 0, $transparent);
        imagecopy($cropped, $image, 0, 0, 0, 0, $width, $newHeight);

        imagepng($cropped, $fullPath);

        imagedestroy($image);
        imagedestroy($cropped);
    }

    /**
     * Gabung beberapa gambar (urutan: top, middle, bottom) jadi 1 PNG
     * panjang secara vertikal menggunakan GD Library.
     * Semua gambar di-resize dulu ke lebar yang sama sebelum digabung,
     * supaya hasil akhirnya rapi (tidak ada bagian yang lebih sempit/lebar).
     */
    private function stitchImagesVertically(array $imagePaths, Project $project): ?string
    {
        try {
            // Urutan digabung: top -> middle -> bottom
            $orderedKeys = ['top', 'middle', 'bottom'];
            $images = [];
            $targetWidth = null;
            $totalHeight = 0;

            foreach ($orderedKeys as $key) {
                if (!isset($imagePaths[$key])) {
                    continue; // skip section yang gagal generate
                }

                $fullPath = Storage::disk('public')->path($imagePaths[$key]);
                $imageResource = imagecreatefrompng($fullPath);

                if (!$imageResource) {
                    Log::warning("Gagal load image untuk stitching: {$key}");
                    continue;
                }

                $width = imagesx($imageResource);
                $height = imagesy($imageResource);

                // Tentukan lebar target dari gambar pertama yang berhasil di-load
                if ($targetWidth === null) {
                    $targetWidth = $width;
                }

                // Resize proporsional kalau lebar gambar ini beda dari target
                if ($width !== $targetWidth) {
                    $newHeight = (int) round($height * ($targetWidth / $width));
                    $resized = imagecreatetruecolor($targetWidth, $newHeight);
                    imagecopyresampled($resized, $imageResource, 0, 0, 0, 0, $targetWidth, $newHeight, $width, $height);
                    imagedestroy($imageResource);
                    $imageResource = $resized;
                    $height = $newHeight;
                }

                $images[] = [
                    'resource' => $imageResource,
                    'height' => $height,
                ];
                $totalHeight += $height;
            }

            if (empty($images) || $targetWidth === null) {
                return null;
            }

            // Buat canvas gabungan
            $canvas = imagecreatetruecolor($targetWidth, $totalHeight);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);

            $currentY = 0;
            foreach ($images as $img) {
                imagecopy($canvas, $img['resource'], 0, $currentY, 0, 0, $targetWidth, $img['height']);
                $currentY += $img['height'];
                imagedestroy($img['resource']);
            }

            // Perkecil ukuran akhir supaya tidak "kebesaran" saat ditaruh di PDF.
            // Target lebar 800px (proporsional) — cukup tajam untuk preview/PDF,
            // tapi ukuran filenya jauh lebih kecil dan tidak overflow halaman.
            $maxFinalWidth = 800;
            if ($targetWidth > $maxFinalWidth) {
                $scaledHeight = (int) round($totalHeight * ($maxFinalWidth / $targetWidth));
                $scaledCanvas = imagecreatetruecolor($maxFinalWidth, $scaledHeight);
                imagesavealpha($scaledCanvas, true);
                $scaledTransparent = imagecolorallocatealpha($scaledCanvas, 0, 0, 0, 127);
                imagefill($scaledCanvas, 0, 0, $scaledTransparent);
                imagecopyresampled($scaledCanvas, $canvas, 0, 0, 0, 0, $maxFinalWidth, $scaledHeight, $targetWidth, $totalHeight);
                imagedestroy($canvas);
                $canvas = $scaledCanvas;
            }

            // Simpan hasil gabungan
            $finalFilename = 'mockups/project_' . $project->id . '_' . Str::random(10) . '.png';
            $finalFullPath = Storage::disk('public')->path($finalFilename);

            // Pastikan folder tujuan ada
            if (!file_exists(dirname($finalFullPath))) {
                mkdir(dirname($finalFullPath), 0755, true);
            }

            imagepng($canvas, $finalFullPath);
            imagedestroy($canvas);

            return 'storage/' . $finalFilename;

        } catch (\Exception $e) {
            Log::error('Gagal menggabungkan gambar mockup: ' . $e->getMessage(), ['project_id' => $project->id]);
            return null;
        }
    }

    /**
     * Bangun prompt untuk satu section spesifik (navbar_hero / content / footer).
     * Setiap section tetap dikasih context design_direction yang sama supaya
     * hasilnya konsisten (warna, font, style tidak berubah antar section).
     */
    private function buildImagePromptSection(Project $project, array $analysis, string $sectionType): string
    {
        $toText = fn($value) => is_array($value)
            ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : ($value ?? '');

        $sitemap = $toText($analysis['sitemap'] ?? '');
        $pageStructure = $toText($analysis['page_structure'] ?? '');
        $contentStrategy = $toText($analysis['content_strategy'] ?? '');
        $ctaStrategy = $toText($analysis['cta_strategy'] ?? '');
        $designDirection = $toText($analysis['design_direction'] ?? '');

        $sharedContext = "
Business Description:
{$project->description}
 
Design Direction (colors, typography, layout style — follow exactly, keep CONSISTENT across all sections):
{$designDirection}
 
=== TEXT RENDERING RULES ===
Keep ALL text SHORT, BOLD, and SIMPLE:
- Headlines: max 4-6 words.
- Sub-headlines: max 8-10 words.
- Button labels: 1-3 words only.
- Avoid long paragraphs or full sentences.
 
=== CANVAS FILL RULE (IMPORTANT) ===
Fill the ENTIRE canvas edge-to-edge with content — do NOT leave large empty white space at the bottom or between elements.
If the described content doesn't naturally fill the full canvas height, add more relevant supporting elements (extra feature cards, additional product items, more testimonials, etc.) to fill the space instead of leaving it blank.
The design should look dense and complete, like a real, fully-designed webpage — never like a half-empty draft.
";

        switch ($sectionType) {
            case 'navbar_hero':
                return "
You are an award-winning Senior UI/UX Designer.
Create ONLY the TOP part of a website desktop mockup: Navigation Bar + Hero Section.
 
Navigation Bar: logo on the left, menu items in center/right (use this sitemap for menu items: {$sitemap}), a clear CTA button on the right.
Hero Section: large headline, short sub-headline, one primary CTA button, and a relevant hero image/background.
 
{$sharedContext}
 
This image will be stacked on TOP of other section images to form a full page, so:
- The very top edge of the image MUST be the navbar (no cropping above it).
- The bottom edge should end cleanly at the bottom of the hero section.
- Do not include any footer or additional sections.
Present this as a clean Figma-style component crop, top edge = navbar, full width.
";

            case 'content':
                return "
You are an award-winning Senior UI/UX Designer.
Create ONLY the MIDDLE content sections of a website desktop mockup (NO navbar, NO hero, NO footer).
 
Follow this page structure for the sections to include: {$pageStructure}
Follow this content strategy: {$contentStrategy}
Follow this CTA strategy for buttons within sections: {$ctaStrategy}
 
{$sharedContext}
 
This image will be stacked BETWEEN a navbar/hero image (above) and a footer image (below), so:
- Do NOT include a navigation bar.
- Do NOT include a hero section.
- Do NOT include a footer.
- Start directly with the first content section and end directly after the last content section.
Present this as a clean Figma-style component crop of just the middle body sections, full width.
";

            case 'footer':
            default:
                return "
You are an award-winning Senior UI/UX Designer.
Create ONLY the FOOTER section of a website desktop mockup.
 
The footer MUST include: multi-column layout with quick links, contact information, social media icons, and a copyright line at the very bottom.
 
{$sharedContext}
 
This image will be stacked at the BOTTOM of other section images to form a full page, so:
- The top edge of the image should start right at the beginning of the footer (no content sections above it).
- The very bottom edge of the image MUST be the last visible part of the footer (copyright line fully visible, not cropped).
- Do not include any navbar, hero, or content sections.
Present this as a clean Figma-style component crop, bottom edge = end of footer, full width.
";
        }
    }

    private function generatePlaceholderMockup(Project $project): ?string
    {
        // Fallback sederhana: pakai gambar placeholder statis
        $placeholderPath = 'mockups/placeholder-default.png';

        if (!Storage::disk('public')->exists($placeholderPath)) {
            Log::warning('Placeholder mockup default tidak ditemukan di storage.');
            return null;
        }

        return 'storage/' . $placeholderPath;
    }
}
