<?php

namespace App\Services;

use App\Models\Project;

class BundleBuilderService
{
    public function __construct(private ClaudeWordPressBuilderService $wordpressBuilder)
    {
    }

    public function build(Project $project): array
    {
        $proposal = $project->latestProposal;
        if (!$proposal || $proposal->status !== 'approved') {
            throw new \RuntimeException('Mockup belum disetujui client. Setujui proposal terlebih dahulu sebelum meminta Claude membangun WordPress.');
        }
        $proposalData = json_decode((string) ($proposal?->ai_reasoning ?? ''), true) ?: [];
        $analysis = $this->resolveAnalysis($project, $proposalData['analysis'] ?? []);
        $template = $this->resolveTemplate($project);
        $brand = $this->resolveBrand($project, $proposalData['mockup'] ?? []);
        $content = $this->resolveContent($project, $analysis, $proposalData['mockup'] ?? []);

        $bundle = [
            'analysis' => $analysis,
            'mockup' => $proposalData['mockup'] ?? [],
            'template' => $template,
            'brand' => $brand,
            'content' => $content,
            'theme' => $this->buildThemePackage($template, $brand),
            'plugin' => $this->buildPluginPackage(),
            'elementor' => $this->buildElementorTemplates($template, $content),
            'assets' => $this->collectAssets($project),
        ];

        $bundle['wordpress'] = $this->wordpressBuilder->build($project, $bundle);

        return $bundle;
    }

    protected function resolveAnalysis(Project $project, array $proposalAnalysis = []): array
    {
        return array_replace_recursive([
            'business_summary' => $project->description ?? 'Business summary not provided',
            'target_market' => $project->target_market ?? 'General market',
            'type' => $project->type ?? 'company',
        ], $proposalAnalysis);
    }

    protected function resolveTemplate(Project $project): array
    {
        return [
            'slug' => 'restaurant-modern',
            'name' => 'Restaurant Modern',
            'category' => 'restaurant',
            'preview_url' => null,
        ];
    }

    protected function resolveBrand(Project $project, array $mockup = []): array
    {
        $design = $mockup['design'] ?? [];

        return [
            'company_name' => $project->client_name ?? 'Client Company',
            'primary_color' => $design['primary_color'] ?? '#1F2937',
            'secondary_color' => $design['secondary_color'] ?? '#D97706',
            'accent_color' => $design['accent_color'] ?? '#2563EB',
            'font_primary' => $design['font_body'] ?? 'Inter',
            'font_secondary' => $design['font_heading'] ?? 'Playfair Display',
            'logo_path' => $project->client?->logo_path,
            'phone' => null,
            'email' => null,
            'address' => null,
        ];
    }

    protected function resolveContent(Project $project, array $analysis, array $mockup = []): array
    {
        $sections = collect($mockup['pages'] ?? [])
            ->flatMap(fn (array $page) => $page['sections'] ?? [])
            ->values();
        $hero = $sections->first(fn (array $section) => strtolower((string) ($section['type'] ?? $section['name'] ?? '')) === 'hero') ?? [];
        $about = $sections->first(fn (array $section) => strtolower((string) ($section['type'] ?? $section['name'] ?? '')) === 'about') ?? [];
        $services = $sections->first(fn (array $section) => strtolower((string) ($section['type'] ?? $section['name'] ?? '')) === 'services') ?? [];
        $faq = $sections->first(fn (array $section) => strtolower((string) ($section['type'] ?? $section['name'] ?? '')) === 'faq') ?? [];
        $cta = $sections->first(fn (array $section) => strtolower((string) ($section['type'] ?? $section['name'] ?? '')) === 'cta') ?? [];

        return [
            'hero' => [
                'title' => $hero['headline'] ?? $project->name,
                'subtitle' => $hero['description'] ?? data_get($analysis, 'business_analysis.value_proposition', $project->description ?? ''),
                'cta_primary' => $hero['cta'] ?? $mockup['global_cta'] ?? 'Get Started',
                'cta_secondary' => $hero['cta_secondary'] ?? 'Learn More',
            ],
            'about' => [
                'title' => $about['headline'] ?? 'Tentang ' . $project->name,
                'content' => $about['description'] ?? data_get($analysis, 'business_analysis.value_proposition', $analysis['business_summary']),
            ],
            'services' => [
                'title' => $services['headline'] ?? 'Layanan Kami',
                'description' => $services['description'] ?? '',
                'items' => $services['items'] ?? [],
            ],
            'footer' => [
                'text' => data_get($mockup, 'footer.text', 'Hubungi ' . $project->name . ' untuk informasi lebih lanjut.'),
            ],
            'faq' => $faq['items'] ?? [],
            'cta' => ['title' => $cta['headline'] ?? $mockup['global_cta'] ?? 'Mulai Sekarang', 'description' => $cta['description'] ?? ''],
            'seo' => [
                'title' => $project->website_name ?? 'Business Website',
                'description' => data_get($mockup, 'seo.meta_description', $analysis['business_summary']),
            ],
        ];
    }

    protected function buildThemePackage(array $template, array $brand): array
    {
        return [
            'name' => 'exito-child',
            'template_slug' => $template['slug'],
            'brand_name' => $brand['company_name'],
            'style_tokens' => [
                'primary_color' => $brand['primary_color'],
                'secondary_color' => $brand['secondary_color'],
            ],
        ];
    }

    protected function buildPluginPackage(): array
    {
        return [
            'name' => 'exito-core',
            'features' => ['custom-post-type', 'demo-importer', 'shortcodes'],
        ];
    }

    protected function buildElementorTemplates(array $template, array $content): array
    {
        return [
            'home' => [
                'template_name' => 'Home',
                'hero_title' => $content['hero']['title'],
                'cta_primary' => $content['hero']['cta_primary'],
            ],
            'about' => [
                'template_name' => 'About',
                'content' => $content['about']['content'],
            ],
            'contact' => [
                'template_name' => 'Contact',
                'cta' => 'Contact Us',
            ],
        ];
    }

    protected function collectAssets(Project $project): array
    {
        return [
            'logo' => null,
            'images' => [],
            'files' => [],
        ];
    }
}
