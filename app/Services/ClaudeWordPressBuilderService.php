<?php

namespace App\Services;

use App\Models\Project;
use App\Services\Concerns\LintsGeneratedPhp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClaudeWordPressBuilderService
{
    use LintsGeneratedPhp;

    public function build(Project $project, array $bundle): array
    {
        $apiKey = config('services.anthropic.key');

        if (!$apiKey) {
            throw new \RuntimeException('ANTHROPIC_API_KEY belum tersedia. Claude wajib aktif untuk membangun WordPress dari analisis GPT.');
        }

        $prompt = $this->buildPrompt($project, $bundle);

        $headers = [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        // Required by the Anthropic API when ANTHROPIC_API_KEY is an
        // "identity-linked" key (tied to a personal Console login rather
        // than scoped to one workspace) — omitted entirely for a normal
        // workspace-scoped API key, which doesn't need or want this header.
        $workspaceId = config('services.anthropic.workspace_id');
        if ($workspaceId) {
            $headers['anthropic-workspace-id'] = $workspaceId;
        }

        try {
            // Generating a full WordPress theme+plugin (up to 50k output
            // tokens) while also reading the mockup PNG plus the client's
            // logo/photos genuinely takes a while. A plain (non-streaming)
            // request gets zero bytes back until the ENTIRE response is
            // ready, and infrastructure in front of the Anthropic API
            // (Cloudflare, etc.) silently kills long-idle connections like
            // that well before generation finishes — raising our own
            // timeout doesn't fix it ("cURL error 28: ... 0 bytes
            // received" even at 480s). Streaming avoids this entirely:
            // Anthropic itself recommends it for large/long-running
            // requests, since data starts flowing back within seconds.
            $text = $this->streamCompletion($headers, $prompt, $bundle);
            $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
            $text = preg_replace('/\s*```$/', '', $text);
            $files = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($files['files'] ?? null)) {
                throw new \RuntimeException('Respons Claude bukan manifest file WordPress yang valid.');
            }

            return ['files' => $this->sanitizeFiles($files['files'])];
        } catch (\Throwable $e) {
            Log::error('Claude WordPress build gagal.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException($this->friendlyError($e->getMessage()), 0, $e);
        }
    }

    /**
     * Calls the Anthropic Messages API with `stream: true` and accumulates
     * the streamed text deltas into the final response text. See the
     * comment in build() for why this is required rather than a plain
     * request — a plain request for an output this large gets zero bytes
     * back until it's entirely done, and gets silently killed by
     * infrastructure in front of the API well before that.
     */
    private function streamCompletion(array $headers, string $prompt, array $bundle): string
    {
        $response = Http::withOptions(['stream' => true])
            ->timeout(config('services.anthropic.build_timeout', 480))
            ->withHeaders($headers)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.builder_model', 'claude-sonnet-4-5'),
                'max_tokens' => 50000,
                'stream' => true,
                'system' => 'You are a senior WordPress engineer. Return only valid JSON.',
                'messages' => [['role' => 'user', 'content' => $this->messageContent($prompt, $bundle)]],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Anthropic API error: ' . $response->body());
        }

        $body = $response->toPsrResponse()->getBody();
        $text = '';
        $buffer = '';

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '' || $chunk === false) {
                continue;
            }
            $buffer .= $chunk;

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $newlinePos), "\r");
                $buffer = substr($buffer, $newlinePos + 1);

                if (!str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = trim(substr($line, 5));
                if ($payload === '' || $payload === '[DONE]') {
                    continue;
                }

                $event = json_decode($payload, true);
                if (!is_array($event)) {
                    continue;
                }

                $eventType = $event['type'] ?? '';

                if ($eventType === 'content_block_delta' && isset($event['delta']['text'])) {
                    $text .= $event['delta']['text'];
                } elseif ($eventType === 'error') {
                    $message = $event['error']['message'] ?? json_encode($event);
                    throw new \RuntimeException('Anthropic stream error: ' . $message);
                }
            }
        }

        if ($text === '') {
            throw new \RuntimeException('Claude tidak mengembalikan konten apa pun (stream kosong).');
        }

        return $text;
    }

    private function messageContent(string $prompt, array $bundle): array
    {
        $content = [['type' => 'text', 'text' => $prompt]];
        $path = data_get($bundle, 'mockup.screenshot_path');

        if ($path) {
            $fullPath = Storage::disk('public')->path($path);
            if (is_file($fullPath)) {
                $mime = mime_content_type($fullPath) ?: 'image/png';
                $content[] = ['type' => 'text', 'text' => 'Approved mockup design (visual reference for the whole build):'];
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mime,
                        'data' => base64_encode((string) file_get_contents($fullPath)),
                    ],
                ];
            }
        }

        $logo = $bundle['assets']['logo'] ?? null;
        if (is_array($logo) && !empty($logo['bytes'])) {
            $content[] = [
                'type' => 'text',
                'text' => "The client's real logo — already embedded for you at assets/{$logo['filename']} in the theme package. Use it as-is for the site logo (e.g. in header.php); do not invent or describe a different logo.",
            ];
            $content[] = [
                'type' => 'image',
                'source' => ['type' => 'base64', 'media_type' => $logo['mime'], 'data' => base64_encode($logo['bytes'])],
            ];
        }

        foreach ($bundle['assets']['images'] ?? [] as $image) {
            if (empty($image['bytes'])) {
                continue;
            }
            $content[] = [
                'type' => 'text',
                'text' => "A real client photo — already embedded for you at assets/{$image['filename']} in the theme package. Use it where a relevant section exists (e.g. About/gallery) instead of describing generic imagery.",
            ];
            $content[] = [
                'type' => 'image',
                'source' => ['type' => 'base64', 'media_type' => $image['mime'], 'data' => base64_encode($image['bytes'])],
            ];
        }

        return $content;
    }

    /**
     * Tells the AI exactly which real client asset files exist and the
     * fixed path each will be embedded at (BundleExporterService writes the
     * actual bytes to that same path regardless of what the AI does), so it
     * references real files by a known-good path instead of inventing a
     * placeholder logo or guessing at a filename that won't exist.
     */
    private function describeAssets(array $assets): string
    {
        $lines = [];

        $logo = $assets['logo'] ?? null;
        if (is_array($logo) && !empty($logo['filename'])) {
            $lines[] = "- assets/{$logo['filename']} — the client's real logo (attached as an image above). Reference this exact path for the site logo.";
        }

        foreach ($assets['images'] ?? [] as $image) {
            if (empty($image['filename'])) {
                continue;
            }
            $lines[] = "- assets/{$image['filename']} — a real client photo (attached as an image above).";
        }

        if (!$lines) {
            return "\nNo real client logo/photos were supplied for this project — do not fabricate a specific brand logo; use a simple text/wordmark treatment for the site name instead.\n";
        }

        $exampleFilename = is_array($logo) ? ($logo['filename'] ?? 'client-logo.png') : 'client-logo.png';

        return "\nCLIENT ASSETS — these real files are already embedded in the theme package at the exact paths below (e.g. `<?php echo get_stylesheet_directory_uri(); ?>/assets/{$exampleFilename}`). Reference them by these exact paths; do not create your own placeholder logo/photo files:\n"
            . implode("\n", $lines) . "\n";
    }

    /**
     * Exact measurements pulled from resources/views/pdf/mockup-render.blade.php
     * — the same template used to render the PNG the client approved — so
     * Claude reproduces the chrome (nav bar, footer) with the identical
     * proportions instead of a generic/looser interpretation of "the mood".
     * The page body itself doesn't need this (it's built deterministically
     * by ElementorPageBuilderService using these same numbers), but header.php
     * and footer.php are entirely Claude's own code, so this is the only way
     * they end up actually matching instead of merely being on-brand.
     */
    private function chromeDesignSpec(array $design): string
    {
        $primary = $design['primary_color'] ?? '#1F2937';
        $secondary = $design['secondary_color'] ?? '#F8FAFC';
        $accent = $design['accent_color'] ?? '#2563EB';
        $fontHeading = $design['font_heading'] ?? 'Georgia';
        $fontBody = $design['font_body'] ?? 'Arial';

        return <<<SPEC

CHROME DESIGN SPEC — the exact layout the approved PNG mockup uses for its nav bar and footer (built from the same design tokens: primary {$primary}, secondary {$secondary}, accent {$accent}, heading font {$fontHeading}, body font {$fontBody}). Match these measurements, not just the colors:

Nav bar (header.php):
- Sits in the page's normal document flow at the very top (do NOT use `position: fixed` or `position: sticky` — it must scroll away with the page, not overlay the hero section below it). White background, ~94px min-height, horizontal padding ~74px (scale down responsively).
- Left: logo image (if supplied) + site name, bold, heading font, ~22px.
- Right: page links (from wp_list_pages as instructed above) with ~32px gap between them, then a pill-shaped CTA button — background {$accent}, white text, ~13px 22px padding, border-radius 8px, bold, no underline.
- A subtle 1px bottom border (very light, e.g. rgba(0,0,0,.06)) — no heavy box-shadow.

Footer (footer.php):
- Full-width band, background #1c1a17 (dark, near-black — not pure black, not the brand's primary color), light gray/cream text (~#cfc8bd).
- 3-column grid (`display:grid;grid-template-columns:2fr 1fr 1fr;gap:36px`, stacking to 1 column on mobile): column 1 = brand name + short description; column 2 = "Navigasi" heading (uppercase, small, letter-spaced) + the same page list as the nav; column 3 = "Kontak" heading + contact info.
- Below the 3-column grid, a full-width thin band, background slightly darker (#151310), centered small copyright line: "© {current year} {site name}. All rights reserved."
- Headings inside the footer columns: uppercase, ~14px, letter-spacing 1px, muted color (~#c9c2b8) — not the same size/weight as body headings.

Overall page chrome:
- Body font: '{$fontBody}'. Heading font: '{$fontHeading}' for site name and footer/nav headings.
- No visible gap/margin between header, the page content, and footer — they should sit flush against each other, matching a single continuous page the way the approved mockup does.
SPEC;
    }

    private function friendlyError(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (str_contains($lowerMessage, 'credit balance') || str_contains($lowerMessage, 'billing')) {
            return 'Claude belum dapat membangun WordPress karena saldo Anthropic habis. Isi kredit Anthropic lalu jalankan build ulang.';
        }

        if (str_contains($lowerMessage, 'credential validation')) {
            return 'Credential Claude tidak valid. Periksa ANTHROPIC_API_KEY lalu jalankan build ulang.';
        }

        if (str_contains($lowerMessage, 'anthropic-workspace-id')) {
            return 'ANTHROPIC_API_KEY yang dipakai adalah identity-linked key dan butuh ANTHROPIC_WORKSPACE_ID. Ambil workspace ID di console.anthropic.com > Settings > Workspaces, isi ke .env, lalu jalankan build ulang.';
        }

        return 'Claude gagal membangun WordPress dari analisis GPT. Periksa konfigurasi Claude lalu coba lagi.';
    }

    private function buildPrompt(Project $project, array $bundle): string
    {
        $bundleJson = json_encode([
            'analysis' => $bundle['analysis'] ?? [],
            'template' => $bundle['template'] ?? [],
            'mockup_png' => ['path' => data_get($bundle, 'mockup.screenshot_path')],
            'implementation_manifest' => $bundle['implementation_manifest'] ?? [],
            'brand' => $bundle['brand'] ?? [],
            'content' => $bundle['content'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $assetsSection = $this->describeAssets($bundle['assets'] ?? []);
        $chromeSpec = $this->chromeDesignSpec($bundle['mockup']['design'] ?? []);

        return <<<PROMPT
Build an install-ready WordPress package for this approved client project.

Project: {$project->name}
Client: {$project->client_name}

The business analysis, approved GPT website blueprint, brand values, and final content are below:
{$bundleJson}

The `implementation_manifest` is the handoff produced by GPT after visually reading the approved PNG. Treat it as the source of truth for the build: reproduce its ordered sections and design system, map every declared asset slot, and use the supplied approved copy. The PNG is a visual reference for the same approved design, not optional inspiration.
{$assetsSection}
IMPORTANT — a separate, deterministic step (not you) already appends page-creation code straight into functions.php, and that code creates every WordPress Page in the blueprint (Home, About, Services, Contact, etc.) with its real content written as native Gutenberg blocks — so the client can visually edit it in WordPress's built-in Block Editor immediately after installing this ONE theme. There is no separate plugin; installing and activating this theme is the client's only step. Because of that:
- `front-page.php` and `page.php` MUST render the actual page content via the standard WordPress Loop and `the_content()` — do NOT hardcode the homepage's sections as static markup in `front-page.php`. If you hardcode the content there instead of calling `the_content()`, the client's block-editor edits will never show up on the live site, which defeats the whole point.
- Still call `get_header()` and `get_footer()` around the Loop so your header/nav/branding and footer render normally.
- Nothing in this build creates or assigns a WordPress navigation menu (Appearance > Menus) — do NOT call `wp_nav_menu()` in header.php or footer.php, it can silently render an unrelated leftover menu from a previous site setup instead of this project's own pages. Build the nav from the real pages directly instead, e.g. `wp_list_pages(['title_li' => '', 'sort_column' => 'menu_order'])` inside a `<ul>`, so it always reflects exactly the pages this build actually created.
- `style.css` must still style the site chrome (header/nav/footer, colors, typography, buttons) AND give sensible default styling to plain content HTML rendered inside `the_content()` — real `<h1>`-`<h6>`, `<p>`, `<ul>`/`<li>`, `<a>` elements, plus these utility classes used by the plain-content fallback: `.exito-section`, `.exito-grid` (a responsive card grid), `.exito-card`, `.exito-button`. Do not assume there's no content inside `the_content()` — style it properly, matching the approved mockup's spacing/typography/color mood.
- Do NOT write any page-creation, `wp_insert_post`, or activation-import logic yourself in functions.php — that is appended separately and automatically after your functions.php, and would only risk duplicating or conflicting with it.
{$chromeSpec}

Return ONLY this JSON shape (theme files only — there is no plugin):
{"files":{"exito-client-theme/style.css":"...","exito-client-theme/functions.php":"...","exito-client-theme/index.php":"...","exito-client-theme/front-page.php":"...","exito-client-theme/page.php":"...","exito-client-theme/header.php":"...","exito-client-theme/footer.php":"...","exito-client-theme/assets/theme.json":"...","README.md":"..."}}

Rules:
- Generate valid WordPress PHP files with a safe unique prefix: exito_client_.
- The theme must be installable as a normal WordPress theme and render the approved content without external build tools.
- header.php/footer.php/style.css MUST follow the exact measurements given in "CHROME DESIGN SPEC" below — not a loose interpretation of the mockup's "mood". The Home/About/Services/Contact section content itself comes from the_content() as explained above — don't duplicate it as static markup.
- If this is an ecommerce project, include WooCommerce-friendly styling hooks, but do not invent products beyond the supplied content.
- Use escaped output, wp_enqueue_style, wp_head, wp_footer, and standard WordPress APIs.
- Use semantic HTML, responsive CSS, CSS variables for the supplied colors, polished buttons, and accessible navigation. Do not use placeholder text or a bare unstyled page.
- Keep all text and colors grounded in the supplied approved content. Do not invent client facts.
- Include a complete README explaining: install & activate this one theme — pages, content, and photos appear automatically, no other plugin needed.
- Every value in files must be a string. Do not include binary assets; reference them by filename in README.
PROMPT;
    }

    private function sanitizeFiles(array $files): array
    {
        $safeFiles = [];

        foreach ($files as $path => $contents) {
            if (!is_string($path) || !is_string($contents)) {
                continue;
            }

            $normalized = str_replace('\\', '/', ltrim($path, '/'));
            if ($normalized === '' || str_contains($normalized, '..') || str_starts_with($normalized, '.')) {
                continue;
            }

            if (str_ends_with($normalized, '.php') && !$this->isValidPhpSyntax($contents)) {
                Log::warning('Claude WordPress build: PHP tidak valid dari AI, file dilewati.', ['path' => $normalized]);
                continue;
            }

            $safeFiles[$normalized] = $contents;
        }

        return $safeFiles;
    }
}
