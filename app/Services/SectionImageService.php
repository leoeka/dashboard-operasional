<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates a handful of real, on-topic photos (via OpenAI's image model)
 * for the pages ElementorPageBuilderService builds, so the generated
 * WordPress site isn't text-and-buttons only.
 *
 * Bounded by a project-wide budget (services.openai.section_image_count,
 * default 6) — each image is a paid API call, so this deliberately does
 * NOT try to illustrate every single card/item, just a page's hero and a
 * few items from its first "grid" section (e.g. a menu/products list).
 *
 * Failures (missing key, rate limit, network) degrade gracefully: that
 * image slot is simply skipped and the rest of the build still succeeds
 * (see BundleBuilderService, which also wraps this in a try/catch).
 */
class SectionImageService
{
    /**
     * @param array $mockupPages $mockup['pages'] from the approved proposal.
     * @return array{map: array<string, array{hero?: string, items?: array<int, string>}>, files: array<string, string>}
     *         map: page slug -> which generated filenames go where.
     *         files: filename -> raw PNG bytes.
     */
    public function generateForPages(Project $project, array $mockupPages): array
    {
        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            return ['map' => [], 'files' => []];
        }

        $budget = max(0, (int) config('services.openai.section_image_count', 6));
        $map = [];
        $files = [];

        foreach ($mockupPages as $index => $page) {
            if ($budget <= 0) {
                break;
            }
            if (!is_array($page)) {
                continue;
            }

            $name = trim((string) ($page['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $slug = $index === 0 ? 'home' : (Str::slug($name) ?: 'page-' . ($index + 1));
            $sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];
            if (!$sections) {
                continue;
            }

            $pageImages = [];

            $first = $sections[0];
            if (is_array($first) && $budget > 0) {
                $subject = $first['headline'] ?? $first['name'] ?? $name;
                $bytes = $this->requestImage($this->buildPrompt($project, (string) $subject, $first['description'] ?? null));
                if ($bytes) {
                    $filename = $slug . '-hero.jpg';
                    $files[$filename] = $bytes;
                    $pageImages['hero'] = $filename;
                    $budget--;
                }
            }

            if ($budget > 0) {
                foreach ($sections as $section) {
                    if (!is_array($section) || empty($section['items']) || !is_array($section['items'])) {
                        continue;
                    }

                    $itemFiles = [];
                    foreach (array_values($section['items']) as $itemIndex => $item) {
                        if ($budget <= 0) {
                            break;
                        }

                        $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? null) : (string) $item;
                        if (!$title) {
                            continue;
                        }

                        $desc = is_array($item) ? ($item['description'] ?? null) : null;
                        $bytes = $this->requestImage($this->buildPrompt($project, (string) $title, $desc));
                        if ($bytes) {
                            $filename = $slug . '-item-' . $itemIndex . '.jpg';
                            $files[$filename] = $bytes;
                            $itemFiles[$itemIndex] = $filename;
                            $budget--;
                        }
                    }

                    if ($itemFiles) {
                        $pageImages['items'] = $itemFiles;
                    }

                    // Only the first items-bearing section per page gets photos.
                    break;
                }
            }

            if ($pageImages) {
                $map[$slug] = $pageImages;
            }
        }

        return ['map' => $map, 'files' => $files];
    }

    private function buildPrompt(Project $project, string $subject, ?string $description): string
    {
        $businessType = $project->type ?: 'business';
        $context = $description ? " Context: {$description}." : '';

        return "A single professional, photorealistic marketing photo for a {$businessType} website. Subject: \"{$subject}\".{$context} "
            . 'Natural lighting, appetizing/inviting composition, no text, no watermark, no logo overlay, no visible human faces, square framing suitable for a website card.';
    }

    private function requestImage(string $prompt): ?string
    {
        $apiKey = config('services.openai.key');

        try {
            // JPEG (lossy) instead of PNG — these are photos, not graphics
            // with sharp edges/transparency, so PNG's lossless encoding was
            // producing 1-1.4MB files each for no visual benefit. A dozen
            // of those pushed the plugin ZIP past what some hosts' upload
            // limits allow, which WordPress then misreports as "The plugin
            // does not have a valid header" instead of an upload-size error.
            $response = Http::timeout(120)->withToken($apiKey)->asJson()->post('https://api.openai.com/v1/images/generations', [
                'model' => config('services.openai.section_image_model', 'gpt-image-1'),
                'prompt' => $prompt,
                'size' => '1024x1024',
                'quality' => config('services.openai.section_image_quality', 'low'),
                'output_format' => 'jpeg',
                'output_compression' => 70,
            ]);

            if (!$response->successful()) {
                Log::warning('SectionImageService: OpenAI image request gagal.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $base64 = $response->json('data.0.b64_json');
            if (!$base64) {
                return null;
            }

            $bytes = base64_decode($base64, true);
            return $bytes ?: null;
        } catch (\Throwable $e) {
            Log::warning('SectionImageService: gagal generate gambar section.', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
