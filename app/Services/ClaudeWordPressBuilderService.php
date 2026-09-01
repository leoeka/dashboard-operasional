<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClaudeWordPressBuilderService
{
    public function build(Project $project, array $bundle): array
    {
        $apiKey = config('services.anthropic.key');

        if (!$apiKey) {
            throw new \RuntimeException('ANTHROPIC_API_KEY belum tersedia. Claude wajib aktif untuk membangun WordPress dari analisis GPT.');
        }

        $prompt = $this->buildPrompt($project, $bundle);

        try {
            $response = Http::timeout(180)->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.builder_model', 'claude-sonnet-4-5'),
                'max_tokens' => 50000,
                'system' => 'You are a senior WordPress engineer. Return only valid JSON.',
                'messages' => [['role' => 'user', 'content' => $this->messageContent($prompt, $bundle)]],
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('Anthropic API error: ' . $response->body());
            }

            $text = (string) $response->json('content.0.text');
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

    private function messageContent(string $prompt, array $bundle): array
    {
        $content = [['type' => 'text', 'text' => $prompt]];
        $path = data_get($bundle, 'mockup.screenshot_path');

        if ($path) {
            $fullPath = Storage::disk('public')->path($path);
            if (is_file($fullPath)) {
                $mime = mime_content_type($fullPath) ?: 'image/png';
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

        return $content;
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

        return 'Claude gagal membangun WordPress dari analisis GPT. Periksa konfigurasi Claude lalu coba lagi.';
    }

    private function buildPrompt(Project $project, array $bundle): string
    {
        $bundleJson = json_encode([
            'analysis' => $bundle['analysis'] ?? [],
            'template' => $bundle['template'] ?? [],
            'mockup_png' => ['path' => data_get($bundle, 'mockup.screenshot_path')],
            'brand' => $bundle['brand'] ?? [],
            'content' => $bundle['content'] ?? [],
            'elementor' => $bundle['elementor'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Build an install-ready WordPress package for this approved client project.

Project: {$project->name}
Client: {$project->client_name}

The business analysis, approved GPT website blueprint, brand values, and final content are below:
{$bundleJson}

Return ONLY this JSON shape:
{"files":{"exito-client-theme/style.css":"...","exito-client-theme/functions.php":"...","exito-client-theme/index.php":"...","exito-client-theme/front-page.php":"...","exito-client-theme/page.php":"...","exito-client-theme/header.php":"...","exito-client-theme/footer.php":"...","exito-client-theme/assets/theme.json":"...","exito-core/exito-core.php":"...","elementor/home.json":"...","README.md":"..."}}

Rules:
- Generate valid WordPress PHP files with a safe unique prefix: exito_client_.
- The theme must be installable as a normal WordPress theme and render the approved content without external build tools.
- Recreate the approved mockup faithfully, not a generic landing page: preserve its section order, navigation, spacing rhythm, visual hierarchy, card/grid composition, typography mood, color palette, CTA placement, and footer structure.
- The front page must render every meaningful Home section in the supplied blueprint: hero, value propositions, services/products, testimonials, newsletter/CTA, and footer. Do not stop after the hero.
- Use the supplied pages blueprint to create usable About, Services, and Contact page templates or sections. The generated result must look like the mockup in a browser at desktop and mobile widths.
- If this is an ecommerce project, include WooCommerce-friendly product grids and purchase CTAs, but do not invent products beyond the supplied content.
- The plugin must have a valid WordPress plugin header and expose a small setup/admin notice explaining the generated bundle.
- Use escaped output, wp_enqueue_style, wp_head, wp_footer, and standard WordPress APIs.
- Use semantic HTML, responsive CSS, CSS variables for the supplied colors, real cards/grids, polished buttons, and accessible navigation. Do not use placeholder text or a bare unstyled page.
- Keep all text and colors grounded in the supplied approved content. Do not invent client facts.
- Include a complete README with installation order: theme, plugin, Elementor templates.
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

            $safeFiles[$normalized] = $contents;
        }

        return $safeFiles;
    }
}
