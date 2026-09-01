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
        $analysis = $this->resolveAnalysis($project);
        $template = $this->resolveTemplate($project);
        $brand = $this->resolveBrand($project);
        $content = $this->resolveContent($project, $analysis);

        $bundle = [
            'analysis' => $analysis,
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

    protected function resolveAnalysis(Project $project): array
    {
        return [
            'business_summary' => $project->description ?? 'Business summary not provided',
            'target_market' => $project->target_market ?? 'General market',
            'type' => $project->type ?? 'company',
        ];
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

    protected function resolveBrand(Project $project): array
    {
        return [
            'company_name' => $project->client_name ?? 'Client Company',
            'primary_color' => '#1F2937',
            'secondary_color' => '#D97706',
            'font_primary' => 'Inter',
            'font_secondary' => 'Playfair Display',
            'logo_path' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
        ];
    }

    protected function resolveContent(Project $project, array $analysis): array
    {
        return [
            'hero' => [
                'title' => 'Modern brand experience for your business',
                'subtitle' => 'We help ambitious brands grow with sharper positioning and stronger digital presence.',
                'cta_primary' => 'Get Started',
                'cta_secondary' => 'View Portfolio',
            ],
            'about' => [
                'title' => 'About Us',
                'content' => $analysis['business_summary'],
            ],
            'footer' => [
                'text' => 'Built for modern business growth.',
            ],
            'faq' => [
                ['question' => 'What do we offer?', 'answer' => 'We create digital experiences that convert.'],
            ],
            'seo' => [
                'title' => $project->website_name ?? 'Business Website',
                'description' => $analysis['business_summary'],
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
