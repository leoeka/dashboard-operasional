<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeWordPressBuilderService
{
    public function build(Project $project, array $bundle): array
    {
        $apiKey = config('services.anthropic.key');

        if (!$apiKey) {
            Log::warning('Anthropic API Key tidak ditemukan; memakai package WordPress lokal.', ['project_id' => $project->id]);
            return $this->fallbackFiles($bundle);
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
                'messages' => [['role' => 'user', 'content' => $prompt]],
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
            Log::warning('Claude WordPress build gagal; memakai package lokal.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            return $this->fallbackFiles($bundle);
        }
    }

    private function buildPrompt(Project $project, array $bundle): string
    {
        $bundleJson = json_encode([
            'analysis' => $bundle['analysis'] ?? [],
            'template' => $bundle['template'] ?? [],
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
{"files":{"exito-client-theme/style.css":"...","exito-client-theme/functions.php":"...","exito-client-theme/index.php":"...","exito-client-theme/front-page.php":"...","exito-client-theme/header.php":"...","exito-client-theme/footer.php":"...","exito-client-theme/assets/theme.json":"...","exito-core/exito-core.php":"...","elementor/home.json":"...","README.md":"..."}}

Rules:
- Generate valid WordPress PHP files with a safe unique prefix: exito_client_.
- The theme must be installable as a normal WordPress theme and render the approved content without external build tools.
- The plugin must have a valid WordPress plugin header and expose a small setup/admin notice explaining the generated bundle.
- Use escaped output, wp_enqueue_style, wp_head, wp_footer, and standard WordPress APIs.
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

    private function fallbackFiles(array $bundle): array
    {
        $themeName = $bundle['theme']['name'] ?? 'exito-client-theme';
        $pluginName = $bundle['plugin']['name'] ?? 'exito-core';
        $content = $bundle['content'] ?? [];
        $brand = $bundle['brand'] ?? [];
        $title = addslashes((string) data_get($content, 'hero.title', $brand['company_name'] ?? 'Client Website'));
        $description = addslashes((string) data_get($content, 'hero.subtitle', 'Welcome to our website.'));
        $primaryColor = $brand['primary_color'] ?? '#1F2937';

        return ['files' => [
            "{$themeName}/style.css" => "/*\nTheme Name: {$brand['company_name']}\nVersion: 1.0.0\n*/\n:root{--primary-color:{$primaryColor}}body{font-family:Arial,sans-serif;margin:0;color:#1f2937}main{max-width:1100px;margin:auto;padding:64px 24px}.hero{padding:80px 0}.button{background:var(--primary-color);color:#fff;padding:12px 20px;text-decoration:none}",
            "{$themeName}/functions.php" => "<?php\nadd_action('wp_enqueue_scripts', function () { wp_enqueue_style('exito-client-style', get_stylesheet_uri(), [], '1.0.0'); });\n",
            "{$themeName}/index.php" => "<?php get_header(); ?><main><?php if (have_posts()) : while (have_posts()) : the_post(); the_title('<h1>','</h1>'); the_content(); endwhile; endif; ?></main><?php get_footer(); ?>",
            "{$themeName}/front-page.php" => "<?php get_header(); ?><main><section class=\"hero\"><h1>{$title}</h1><p>{$description}</p><a class=\"button\" href=\"#contact\">" . addslashes((string) data_get($content, 'hero.cta_primary', 'Get Started')) . "</a></section></main><?php get_footer(); ?>",
            "{$themeName}/header.php" => "<!doctype html><html <?php language_attributes(); ?>><head><meta charset=\"<?php bloginfo('charset'); ?>\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><?php wp_head(); ?></head><body <?php body_class(); ?>>",
            "{$themeName}/footer.php" => "<?php wp_footer(); ?></body></html>",
            "{$pluginName}/{$pluginName}.php" => "<?php\n/** Plugin Name: Exito Client Core\n * Version: 1.0.0\n */\nif (!defined('ABSPATH')) exit;\n",
            'elementor/home.json' => json_encode($bundle['elementor']['home'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'README.md' => "# WordPress Bundle\n\nInstall the theme ZIP, then the plugin ZIP. Import files in `elementor/` with Elementor if used.\n",
        ]];
    }
}
