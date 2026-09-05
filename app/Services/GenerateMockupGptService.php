<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * All GPT/OpenAI calls: the mockup design pass (colors/typography per
 * candidate, layered onto AnalisisGeminiService's already-final content), the
 * mockup PNG rendering pipeline (HTML/CSS render + individually-generated
 * photos), and decomposing an approved mockup into a build manifest for
 * Claude. See AnalisisGeminiService for the Gemini side (business analysis, the
 * sitemap/copy that analysis produces, keyword research) — these two used
 * to be one class.
 */
class GenerateMockupGptService
{
    public function __construct(private ScreenshotService $screenshotService)
    {
    }
    /**
     * AI 2 — DESIGN ONLY. Gemini (analyzeBusinessWithGemini(), stored at
     * $analysis['sitemap']) already wrote the actual website content —
     * sitemap, headlines, copy, CTAs, language. GPT here does not write,
     * rewrite, or touch that content at all: it only picks the visual
     * language (colors, typography, style mood) for ONE design direction,
     * which is then merged with Gemini's untouched content in PHP. This is
     * why the 3 mockup candidates the client sees always show identical
     * content and differ ONLY in design — previously each candidate ran an
     * independent content-writing pass too, so options could show different
     * headlines/copy alongside different designs, conflating two separate
     * decisions the client has to make.
     */
    public function generateMockup(Project $project, array $analysis, string $variantInstruction = '', array $competitorContents = [], ?array $precomputedReference = null): array
    {
        $sitemap = is_array($analysis['sitemap'] ?? null) ? $analysis['sitemap'] : null;
        if (!$sitemap || empty($sitemap['pages'])) {
            // Gemini's sitemap is missing (old cached analysis, or Gemini
            // failed before this content stage existed) — there's no
            // content to attach a design to, so fall back to a fully local
            // mockup rather than asking GPT to invent content again.
            Log::warning('AI 1 (Gemini) tidak menyertakan sitemap/konten; memakai mockup fallback lokal.', ['project_id' => $project->id]);
            return $this->fallbackMockup($project, $analysis);
        }

        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            Log::warning('OpenAI API Key tidak ditemukan; memakai desain fallback di atas konten Gemini.', ['project_id' => $project->id]);
            return $this->mergeDesignIntoSitemap($sitemap, $this->fallbackDesign());
        }

        $sitemapJson = json_encode($sitemap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $brandContextJson = json_encode([
            'business_analysis' => $analysis['business_analysis'] ?? [],
            'target_market' => $analysis['target_market'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $referenceType = $project->design_reference_type ?: 'none';
        $referenceUrl = $project->design_reference_url ?: 'not provided';
        $referenceFile = $project->design_reference_path ? basename($project->design_reference_path) : 'not provided';
        $variantSection = trim($variantInstruction) !== ''
            ? "\nVISUAL VARIANT DIRECTION for THIS design option:\n{$variantInstruction}\n"
            : '';

        // Resolve once per call, unless the caller already resolved it
        // (generateMockupCandidates() does this ONCE and reuses it across
        // all 3 independent generateMockup() calls, so we don't screenshot
        // the same client/competitor URLs three times over).
        $reference = $precomputedReference ?? $this->resolveDesignReference($project, $competitorContents);
        $referenceImages = $reference['images'];
        $designSourceLine = $reference['line'];

        $prompt = <<<PROMPT
You are a senior website designer. The website's content is already final (written by a separate content stage) — your ONLY job is to choose the visual design for it: colors, typography, and style mood for one design option.

Client: {$project->client_name}
Project: {$project->name}
Website category: {$project->type}

BRAND CONTEXT (for grounding color/style choices only — do not rewrite any of this):
{$brandContextJson}

THE WEBSITE'S FINAL CONTENT (read-only — for context on tone/mood only, you are not authoring or editing any of it):
{$sitemapJson}

CLIENT DESIGN REFERENCE (use it only as inspiration; never copy branding, text, assets, or source code):
Type: {$referenceType}
Website URL: {$referenceUrl}
Uploaded file: {$referenceFile}
{$designSourceLine}{$variantSection}
COLOR GROUNDING — avoid the single most common mockup mistake: defaulting to a "safe" warm beige/tan/brown/cream palette no matter what the business is. Derive `primary_color`/`secondary_color`/`accent_color` specifically from THIS business's brand identity/positioning and target market psychographics above — a different brand identity or positioning should produce a genuinely different palette, not a variation on the same warm neutrals. A warm/earthy palette is only correct here if the brand itself is specifically about warmth, nature, or craft (e.g. artisanal food, leather goods) — for anything else (tech, healthcare, fashion, finance, sports, beauty, etc.) actively choose a palette that fits THAT brand instead (which could be cool, bold, monochrome, vibrant, dark, or anything else the brand identity actually calls for).

Return ONLY valid JSON with this exact shape — design tokens only, no content fields of any kind:
{
  "style": "1 sentence describing this design option's overall mood",
  "primary_color": "#...", "secondary_color": "#...", "accent_color": "#...",
  "font_heading": "a real Google Font name", "font_body": "a real Google Font name"
}
PROMPT;

        try {
            $messageContent = [['type' => 'text', 'text' => $prompt]];
            foreach ($referenceImages as $referenceImage) {
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

            $design = json_decode((string) $response->json('choices.0.message.content'), true);
            if (!$response->successful() || !is_array($design) || empty($design['primary_color'])) {
                throw new \RuntimeException('Respons desain AI tidak valid.');
            }

            return $this->mergeDesignIntoSitemap($sitemap, $design);
        } catch (\Throwable $e) {
            Log::warning('AI desain gagal; memakai desain fallback di atas konten Gemini.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            return $this->mergeDesignIntoSitemap($sitemap, $this->fallbackDesign());
        }
    }

    /**
     * Combines Gemini's already-final content (sitemap/copy) with GPT's
     * chosen design tokens for one candidate, into the same mockup shape
     * every downstream consumer (PDF proposal, PNG render, WordPress
     * builder) already expects — so nothing downstream needs to know that
     * content and design now come from two different AI calls.
     */
    private function mergeDesignIntoSitemap(array $sitemap, array $design): array
    {
        return [
            'website_concept' => $sitemap['website_concept'] ?? '',
            'design' => $design,
            'pages' => $sitemap['pages'] ?? [],
            'global_cta' => $sitemap['global_cta'] ?? '',
            'seo' => $sitemap['seo'] ?? [],
        ];
    }

    private function fallbackDesign(): array
    {
        return [
            'style' => 'Modern, clean, professional, and conversion-focused',
            'primary_color' => '#1E3A5F', 'secondary_color' => '#F8FAFC', 'accent_color' => '#2563EB',
            'font_heading' => 'Poppins', 'font_body' => 'Inter',
        ];
    }

    /**
     * Produces 3 design options for the SAME content. Gemini
     * (analyzeBusinessWithGemini(), $analysis['sitemap']) already wrote the
     * site's actual content once — sitemap, copy, CTAs. Each candidate here
     * calls generateMockup() independently, but generateMockup() no longer
     * touches content at all, it only picks design tokens for that one
     * option (see its docblock) — so all 3 candidates the client compares
     * show identical copy and differ ONLY in visual design, never in what
     * the page actually says.
     */
    public function generateMockupCandidates(Project $project, array $analysis, array $competitorContents = []): array
    {
        // Resolve the visual reference (client's own, or real competitor
        // screenshots) ONCE — screenshotting is comparatively slow, no
        // need to repeat it per candidate — then reuse it across 3
        // INDEPENDENT generateMockup() calls. Each candidate gets its own
        // full GPT generation (not just a restyled copy of one shared
        // generation) so the client sees genuinely different content
        // takes, not just different product photos on identical copy;
        // content_benchmark grounding (see generateMockup()) is what keeps
        // each of the three from drifting into something generic or
        // inconsistent with AI 1's analysis.
        $reference = $this->resolveDesignReference($project, $competitorContents);

        // Composition/typography/photography axes only — deliberately
        // generic across any business category, and deliberately WITHOUT
        // any color-temperature words ("warm", "earthy", etc). A previous
        // version said "warm neutral surfaces" here — that single fixed
        // phrase, reused verbatim for every project's Option 1 regardless
        // of business, is why a shoe brand and a coffee shop both ended up
        // with the same brown/tan palette: we were telling GPT to pick a
        // warm palette every single time. Color now comes ONLY from the
        // brand-specific instruction below (see colorGroundingLine).
        $visualDirections = [
            'Option 1 - Editorial: elegant editorial composition, expressive serif headings, generous whitespace, premium photography-led hero.',
            'Option 2 - Modern & confident: clean conversion-focused composition, strong grid, crisp sans-serif typography, bold decisive layout choices.',
            'Option 3 - Calm & approachable: soft rounded cards, friendly approachable hierarchy, understated photography, airy layout.',
        ];

        // A real STRUCTURAL layout per option, not just a color/font
        // difference — assigned deterministically (not left to GPT) so
        // each render is guaranteed one of these three known-good,
        // fully-tested arrangements instead of an unpredictable one. This
        // is what was actually making every option feel "kaku" (rigid):
        // the design tokens varied, but mockup-render.blade.php's actual
        // hero/section markup never did. See layout_variant handling in
        // mockup-render.blade.php and ElementorPageBuilderService.
        $layoutVariants = ['split-right', 'overlay-bg', 'split-left'];

        $count = max(2, min(3, (int) config('services.openai.mockup_candidate_count', 3)));
        $candidates = [];

        for ($index = 0; $index < $count; $index++) {
            $candidate = $this->generateMockup($project, $analysis, $visualDirections[$index], $competitorContents, $reference);
            // Normalized HERE (not just before rendering the PNG) so every
            // consumer of the stored candidate — the PDF proposal template,
            // the approved-mockup decomposition step, the WordPress
            // builder — gets guaranteed strings too, not just the mockup
            // PNG render. See normalizeMockupForRender()'s docblock.
            $candidate = $this->normalizeMockupForRender($candidate);
            $candidate['design']['layout_variant'] = $layoutVariants[$index % count($layoutVariants)];
            $candidate['candidate_number'] = $index + 1;
            $candidate['candidate_label'] = str_replace('Option ' . ($index + 1) . ' - ', '', $visualDirections[$index]);
            $candidate['client_logo_path'] = $project->client?->logo_path
                ? Storage::disk('public')->path($project->client->logo_path)
                : null;
            $candidate['design_reference_type'] = $project->design_reference_type;
            $candidate['design_reference_url'] = $project->design_reference_url;
            $candidate['screenshot_path'] = $this->generateMockupImage($project, $analysis, $candidate, $index + 1, $visualDirections[$index]);
            $candidates[] = $candidate;
        }

        return $candidates;
    }

    /**
     * Renders the mockup as a real HTML/CSS page (design tokens, actual
     * copy, a guaranteed-present footer) and screenshots it — instead of
     * asking gpt-image-1 to draw an entire multi-section webpage as one
     * image. That approach was repeatedly cropping the footer mid-section
     * and inventing content/imagery that wasn't in the blueprint (an
     * inherent limitation of single-shot image generation for a complex,
     * text-heavy, precisely-laid-out composition — no amount of prompt
     * wording fixed it reliably). HTML capture cannot crop: Browsershot
     * screenshots the full scrollable page height. AI is only asked to
     * draw individual product/hero PHOTOS (generateMockupPhotos()), which
     * is a task it's actually reliable at.
     */
    public function generateMockupImage(Project $project, array $analysis, array $mockup, int $candidateNumber = 1, string $visualDirection = ''): ?string
    {
        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY belum tersedia untuk membuat PNG mockup.');
        }

        // GPT's JSON doesn't always match the requested schema exactly —
        // a "headline"/"description"/"cta"/"global_cta" field sometimes
        // comes back as an array instead of a string. Normalize every
        // text-bearing field to a real string ONCE, up front, so nothing
        // downstream (section picking, photo prompts, and Blade's
        // {{ }} which calls htmlspecialchars() and fatals on a non-string)
        // has to guess or re-check.
        $mockup = $this->normalizeMockupForRender($mockup);

        $pages = is_array($mockup['pages'] ?? null) ? $mockup['pages'] : [];
        $home = collect($pages)->first(fn ($page) => is_array($page) && strtolower((string) ($page['name'] ?? '')) === 'home') ?? ($pages[0] ?? []);
        $homeSections = is_array($home['sections'] ?? null) ? array_values($home['sections']) : [];
        $hero = $homeSections[0] ?? [];
        $picked = $this->pickMockupSections($homeSections);
        $photos = $this->generateMockupPhotos($project, $hero, $picked['photo'], $visualDirection);

        $html = view('pdf.mockup-render', [
            'project' => $project,
            'mockup' => $mockup,
            'design' => is_array($mockup['design'] ?? null) ? $mockup['design'] : [],
            'pages' => $pages,
            'homeSections' => $homeSections,
            'hero' => $hero,
            'iconSection' => $picked['icon'],
            'photoSection' => $picked['photo'],
            'heroPhoto' => $photos['hero'],
            'itemPhotos' => $photos['items'],
            'logoDataUrl' => $this->clientLogoDataUrl($project),
        ])->render();

        $path = 'mockups/' . $project->code . '-gpt-option-' . $candidateNumber . '.png';
        $saved = $this->screenshotService->captureHtml($html, $path);

        if (!$saved) {
            throw new \RuntimeException('Gagal merender PNG mockup (Browsershot).');
        }

        return $saved;
    }

    /**
     * Picks at most two of the Home page's non-hero sections to actually
     * render, so the mockup stays a compact, complete single page instead
     * of stacking every section in the blueprint (which is what made
     * earlier PNGs "too long" — see mockup-render.blade.php's comment).
     * - `icon`: a compact "why choose us"-style band, rendered with plain
     *   icon badges — no AI photo needed.
     * - `photo`: the one section that gets real AI-generated photos, i.e.
     *   the part of the page actually worth illustrating (products/menu/
     *   services). If only one items-bearing section exists at all, it
     *   becomes the photo section (a single showcase is more compelling
     *   illustrated than reduced to icons).
     *
     * @return array{icon: ?array, photo: ?array}
     */
    /**
     * Coerces every text-bearing field in a mockup blueprint (page names,
     * section headline/description/cta/name, item title/description,
     * global_cta, website_concept, design tokens) to a real string. GPT's
     * JSON output doesn't always match the requested schema — a field
     * documented as a string sometimes comes back as an array (e.g. a list
     * of alternatives) — and Blade's `{{ }}` calls htmlspecialchars()
     * internally, which fatals on anything but a string. Leaves the
     * overall array structure (pages/sections/items as arrays) untouched.
     */
    private function normalizeMockupForRender(array $mockup): array
    {
        $mockup['website_concept'] = $this->flattenToString($mockup['website_concept'] ?? '');
        $mockup['global_cta'] = $this->flattenToString($mockup['global_cta'] ?? '');

        if (is_array($mockup['design'] ?? null)) {
            foreach (['style', 'primary_color', 'secondary_color', 'accent_color', 'font_heading', 'font_body'] as $key) {
                if (isset($mockup['design'][$key])) {
                    $mockup['design'][$key] = $this->flattenToString($mockup['design'][$key]);
                }
            }
        }

        if (is_array($mockup['pages'] ?? null)) {
            $mockup['pages'] = array_map(function ($page) {
                if (!is_array($page)) {
                    return $page;
                }

                $page['name'] = $this->flattenToString($page['name'] ?? '');

                if (is_array($page['sections'] ?? null)) {
                    $page['sections'] = array_map(
                        fn ($section) => is_array($section) ? $this->normalizeSectionText($section) : $section,
                        $page['sections']
                    );
                }

                return $page;
            }, $mockup['pages']);
        }

        return $mockup;
    }

    private function normalizeSectionText(array $section): array
    {
        foreach (['headline', 'description', 'cta', 'name', 'type', 'layout'] as $key) {
            if (isset($section[$key])) {
                $section[$key] = $this->flattenToString($section[$key]);
            }
        }

        if (is_array($section['items'] ?? null)) {
            $section['items'] = array_map(function ($item) {
                if (!is_array($item)) {
                    return $this->flattenToString($item);
                }
                foreach (['title', 'name', 'description', 'price'] as $key) {
                    if (isset($item[$key])) {
                        $item[$key] = $this->flattenToString($item[$key]);
                    }
                }
                return $item;
            }, $section['items']);
        }

        return $section;
    }

    /** Best-effort coercion of an unexpected type (typically an array where a string was expected) into a string. */
    private function flattenToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_numeric($value) || is_bool($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $parts = array_filter(array_map(
                fn ($v) => is_string($v) || is_numeric($v) ? (string) $v : null,
                $value
            ));
            return implode(', ', $parts);
        }

        return '';
    }

    private function pickMockupSections(array $homeSections): array
    {
        $itemSections = [];
        foreach ($homeSections as $index => $section) {
            if ($index === 0 || !is_array($section)) {
                continue; // index 0 is the hero, handled separately
            }
            if (!empty($section['items']) && is_array($section['items'])) {
                $itemSections[] = $section;
            }
        }

        if (count($itemSections) >= 2) {
            return ['icon' => $itemSections[0], 'photo' => $itemSections[1]];
        }

        if (count($itemSections) === 1) {
            return ['icon' => null, 'photo' => $itemSections[0]];
        }

        return ['icon' => null, 'photo' => null];
    }

    /**
     * Generates the hero photo plus up to 4 photos for the chosen "photo"
     * section, all CONCURRENTLY (see generateMockupPhotoDataUrls()) — one
     * clean photo per prompt is a task gpt-image-1 handles reliably,
     * unlike composing an entire webpage. Best-effort: a failed photo just
     * leaves that slot empty in the HTML template, never blocks the
     * mockup.
     *
     * @return array{hero: ?string, items: array<int, string>}
     */
    private function generateMockupPhotos(Project $project, array $hero, ?array $photoSection, string $visualDirection = ''): array
    {
        $jobs = [];

        if ($hero) {
            $jobs['hero'] = [
                'subject' => (string) ($hero['headline'] ?? $hero['name'] ?? $project->name),
                'context' => $hero['description'] ?? null,
            ];
        }

        if ($photoSection) {
            foreach (array_slice($photoSection['items'] ?? [], 0, 4) as $index => $item) {
                $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? null) : $item;
                if (!$title) {
                    continue;
                }
                $jobs['item_' . $index] = [
                    'subject' => (string) $title,
                    'context' => is_array($item) ? ($item['description'] ?? null) : null,
                ];
            }
        }

        if (!$jobs) {
            return ['hero' => null, 'items' => []];
        }

        $photos = $this->generateMockupPhotoDataUrls($project, $jobs, $visualDirection);

        $result = ['hero' => $photos['hero'] ?? null, 'items' => []];
        foreach ($photos as $key => $photo) {
            if ($key !== 'hero' && $photo && preg_match('/^item_(\d+)$/', $key, $m)) {
                $result['items'][(int) $m[1]] = $photo;
            }
        }

        return $result;
    }

    /**
     * Fires every requested photo prompt CONCURRENTLY (Http::pool())
     * instead of one after another. With up to 5 photos per mockup
     * candidate — hero + 4 items — times 3 candidates, doing this
     * sequentially could take several minutes and was pushing whole
     * proposal generation past GenerateProposalJob's timeout. On Windows
     * (no pcntl extension) Laravel's own graceful job-timeout mechanism
     * can't fire, so the queue LISTENER's own process-level --timeout is
     * what actually kills it — and because the dev script runs
     * `concurrently ... --kill-others`, that listener crash was taking the
     * whole dev server down with it (surfacing to the browser as "Failed
     * to fetch"). Concurrent requests are bounded by the slowest single
     * response instead of the sum of all of them.
     *
     * @param array<string, array{subject:string, context:?string}> $jobs
     * @return array<string, ?string>
     */
    private function generateMockupPhotoDataUrls(Project $project, array $jobs, string $visualDirection = ''): array
    {
        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            return [];
        }

        $businessType = $project->type ?: 'business';
        $styleLine = trim($visualDirection) !== '' ? " Visual treatment: {$visualDirection}." : '';

        try {
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($jobs, $apiKey, $businessType, $styleLine) {
                $requests = [];
                foreach ($jobs as $key => $job) {
                    $contextLine = $job['context'] ? " Context: {$job['context']}." : '';
                    $prompt = $this->toSafeAscii(
                        "A single professional, photorealistic marketing photo for a {$businessType} website. Subject: \"{$job['subject']}\".{$contextLine}{$styleLine} "
                        . 'Natural lighting, clean uncluttered composition, no text, no watermark, no logo, no UI elements or browser chrome, square framing suitable for a website card.'
                    );

                    $requests[] = $pool->as($key)->timeout(60)->withToken($apiKey)->asJson()->post('https://api.openai.com/v1/images/generations', [
                        'model' => config('services.openai.image_model', 'gpt-image-1'),
                        'prompt' => $prompt,
                        'size' => '1024x1024',
                        'quality' => 'low',
                        'output_format' => 'jpeg',
                        'output_compression' => 70,
                    ]);
                }

                return $requests;
            });
        } catch (\Throwable $e) {
            Log::warning('GenerateMockupGptService: pool generate foto mockup gagal total.', ['error' => $e->getMessage()]);
            return [];
        }

        $results = [];
        foreach ($jobs as $key => $job) {
            $response = $responses[$key] ?? null;

            if ($response instanceof \Throwable) {
                Log::warning('GenerateMockupGptService: gagal generate foto mockup (pool).', ['key' => $key, 'error' => $response->getMessage()]);
                $results[$key] = null;
                continue;
            }

            if (!$response instanceof \Illuminate\Http\Client\Response || !$response->successful()) {
                Log::warning('GenerateMockupGptService: gagal generate foto mockup (pool).', [
                    'key' => $key,
                    'status' => $response instanceof \Illuminate\Http\Client\Response ? $response->status() : null,
                    'body' => $response instanceof \Illuminate\Http\Client\Response ? $response->body() : null,
                ]);
                $results[$key] = null;
                continue;
            }

            $base64 = $response->json('data.0.b64_json');
            $results[$key] = $base64 ? 'data:image/jpeg;base64,' . $base64 : null;
        }

        return $results;
    }

    /** Client's real logo as a data URI for the mockup nav, or null if none/unreadable. */
    private function clientLogoDataUrl(Project $project): ?string
    {
        $logoPath = $project->client?->logo_path;
        if (!$logoPath) {
            return null;
        }

        try {
            $fullPath = Storage::disk('public')->path($logoPath);
            if (!is_file($fullPath) || filesize($fullPath) > 5 * 1024 * 1024) {
                return null;
            }

            $mime = mime_content_type($fullPath) ?: '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'], true)) {
                return null;
            }

            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($fullPath));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function decomposeApprovedMockup(Project $project, array $mockup): array
    {
        $apiKey = config('services.openai.key');
        $path = $mockup['screenshot_path'] ?? null;
        if (!$apiKey || !$path) {
            throw new \RuntimeException('PNG mockup approved atau OPENAI_API_KEY belum tersedia.');
        }

        $fullPath = Storage::disk('public')->path($path);
        if (!is_file($fullPath)) {
            throw new \RuntimeException('File PNG mockup approved tidak ditemukan.');
        }

        $contentJson = json_encode($this->normalizeUtf8($mockup), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $prompt = $this->toSafeAscii("Read this approved website mockup image and turn it into an implementation manifest for a WordPress developer. Preserve the exact visual intent and use the approved copy from the JSON. Do not invent facts. Return only valid JSON with these keys: design_system (colors, typography, spacing, layout), navigation, sections (ordered list with type, heading, copy, CTA, layout, asset_slots, items), assets (list with slot, purpose, required, source), pages, responsive_rules, content. The manifest must be detailed enough for Claude to rebuild the same website, not a generic theme. APPROVED MOCKUP JSON:\n{$contentJson}");

        $mime = mime_content_type($fullPath) ?: 'image/png';
        $response = Http::timeout(180)->withToken($apiKey)->asJson()->post('https://api.openai.com/v1/chat/completions', [
            'model' => config('services.openai.mockup_model', 'gpt-5-mini'),
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($fullPath)), 'detail' => 'high']],
                ],
            ]],
            'response_format' => ['type' => 'json_object'],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('GPT mockup decomposition gagal: ' . $response->body());
        }

        $result = json_decode((string) $response->json('choices.0.message.content'), true);
        if (!is_array($result) || empty($result['sections']) || empty($result['design_system'])) {
            throw new \RuntimeException('GPT tidak mengembalikan manifest desain yang lengkap.');
        }

        return $result;
    }

    private function normalizeUtf8(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$this->normalizeUtf8($key)] = $this->normalizeUtf8($item);
            }
            return $normalized;
        }

        if (!is_string($value)) {
            return $value;
        }

        $cleaned = iconv('UTF-8', 'UTF-8//IGNORE', $value);
        return $cleaned === false ? '' : $cleaned;
    }

    private function toSafeAscii(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            return $ascii;
        }

        return preg_replace('/[^\x00-\x7F]/', '', $value) ?? '';
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

    /**
     * Screenshots a real URL (client's own reference URL, or a real
     * competitor site) and returns it as a vision-compatible data URL, so
     * generateMockup() can show GPT what a referenced site actually looks
     * like instead of just its URL as text. Best-effort: any failure
     * (unreachable site, headless browser issue, timeout) just means that
     * particular reference is skipped, never fails the whole mockup.
     */
    /**
     * DESIGN SOURCE resolution — the client's OWN reference (uploaded image
     * or a URL we screenshot) always wins when present. Otherwise, when we
     * have real competitor URLs (from Gemini's competitor discovery),
     * screenshot a couple of them so GPT designs from real visual
     * references instead of inventing colors/layout from nothing — same
     * principle as content_benchmark grounding content, applied to the
     * visual side. Called ONCE by generateMockupCandidates() and reused
     * across all 3 independent generateMockup() calls (screenshotting is
     * comparatively slow; no need to repeat it per candidate).
     *
     * @return array{images: array<int, string>, mode: string, line: string}
     */
    private function resolveDesignReference(Project $project, array $competitorContents): array
    {
        $referenceType = $project->design_reference_type ?: 'none';
        $referenceImages = [];
        $designSourceMode = 'none';

        if ($referenceType === 'image' && $project->design_reference_path) {
            $dataUrl = $this->referenceImageDataUrl($project);
            if ($dataUrl) {
                $referenceImages[] = $dataUrl;
                $designSourceMode = 'client';
            }
        } elseif ($referenceType === 'url' && trim((string) $project->design_reference_url) !== '') {
            $dataUrl = $this->urlToImageDataUrl($project->design_reference_url, 'design-refs/' . $project->id . '-client-ref.png');
            if ($dataUrl) {
                $referenceImages[] = $dataUrl;
                $designSourceMode = 'client';
            }
        }

        if ($designSourceMode !== 'client' && !empty($competitorContents)) {
            foreach (array_slice($competitorContents, 0, 2) as $competitor) {
                $competitorUrl = $competitor['url'] ?? null;
                if (!$competitorUrl) {
                    continue;
                }
                $dataUrl = $this->urlToImageDataUrl($competitorUrl, 'design-refs/' . $project->id . '-competitor-' . md5($competitorUrl) . '.png');
                if ($dataUrl) {
                    $referenceImages[] = $dataUrl;
                }
            }
            if ($referenceImages) {
                $designSourceMode = 'competitor';
            }
        }

        $designSourceLine = match ($designSourceMode) {
            'client' => "\nDESIGN SOURCE: the client provided their own reference — attached below as an image. Treat it as the PRIMARY and DOMINANT visual direction: extract its layout hierarchy, spacing, section order, typography mood, colour treatment, card composition, navigation treatment, and CTA placement, and reinterpret those for this client. Never copy its literal text, branding, or photos.\n",
            'competitor' => "\nDESIGN SOURCE: the client did not provide their own reference, so " . count($referenceImages) . " real competitor website(s) in this exact space are attached below as images instead. Study their overall visual language — layout patterns, typography mood, colour palette conventions, card/grid composition, spacing rhythm — and BLEND that into a design that fits this client, differentiated per content_benchmark.must_exceed above. Do not copy any single one of them directly; synthesize something that would feel at home next to them while clearly being its own brand.\n",
            default => "\nDESIGN SOURCE: no client reference or competitor screenshots were available — design from AI 1's analysis and content_benchmark above: infer a palette, typography mood, and layout style that specifically fits this business, target market, and positioning, not a generic default.\n",
        };

        return ['images' => $referenceImages, 'mode' => $designSourceMode, 'line' => $designSourceLine];
    }

    private function urlToImageDataUrl(string $url, string $relativePath): ?string
    {
        try {
            $saved = $this->screenshotService->capture($url, $relativePath);
            if (!$saved) {
                return null;
            }

            $fullPath = Storage::disk('public')->path($saved);
            if (!is_file($fullPath)) {
                return null;
            }

            $mime = mime_content_type($fullPath) ?: 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($fullPath));
        } catch (\Throwable $e) {
            Log::warning('GenerateMockupGptService: gagal mengambil screenshot referensi desain.', [
                'url' => $url,
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
}
