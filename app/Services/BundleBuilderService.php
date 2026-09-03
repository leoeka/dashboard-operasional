<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BundleBuilderService
{
    public function __construct(
        private ClaudeWordPressBuilderService $claudeBuilder,
        private ElementorPageBuilderService $elementorPageBuilder,
        private SectionImageService $sectionImageService,
    ) {
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

        $mockup = $proposalData['mockup'] ?? [];
        $mockupPages = $mockup['pages'] ?? [];

        // TEMPORARY, per explicit request: build only the Home page for now
        // so the layout can be verified/matched against the approved
        // mockup one page at a time, instead of every page (About/Services/
        // Contact/...) at once. Remove this slice to build the full
        // sitemap again once Home looks right.
        $mockupPages = array_slice($mockupPages, 0, 1);

        // A few real, on-topic photos (hero + some items) generated via
        // OpenAI, bounded by services.openai.section_image_count. Wrapped in
        // try/catch — this is an enhancement, not a blocker: if it fails
        // (no key, rate limit, network), the build still succeeds with a
        // text-and-buttons-only page instead of an error. See
        // SectionImageService.
        try {
            $sectionImages = $this->sectionImageService->generateForPages($project, $mockupPages);
        } catch (\Throwable $e) {
            Log::warning('SectionImageService gagal, lanjut build tanpa gambar section.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            $sectionImages = ['map' => [], 'files' => []];
        }

        $bundle = [
            'analysis' => $analysis,
            'mockup' => $mockup,
            'implementation_manifest' => $proposalData['implementation_manifest'] ?? [],
            'template' => $template,
            'brand' => $brand,
            'content' => $content,
            'theme' => $this->buildThemePackage($template, $brand),
            'elementor' => $this->buildElementorTemplates($template, $content),
            // Real WordPress page content (Gutenberg blocks) built
            // deterministically from the approved mockup, not by the AI
            // builder, so every page is actually populated & editable in
            // the built-in Block Editor after install — see
            // ElementorPageBuilderService and BundleExporterService.
            'elementor_pages' => $this->elementorPageBuilder->buildPages($mockupPages, $mockup['design'] ?? [], $sectionImages['map']),
            // filename => raw JPEG bytes, embedded into the theme and
            // uploaded to the Media Library the first time the theme is
            // activated.
            'section_images' => $sectionImages['files'],
            'assets' => $this->collectAssets($project),
        ];

        $bundle['wordpress'] = $this->claudeBuilder->build($project, $bundle);
        $bundle['built_with'] = 'claude';

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
            // Must match the folder prefix the AI builder prompt is told to
            // use for theme files (see ClaudeWordPressBuilderService) —
            // BundleExporterService filters wordpress.files by this name,
            // so a mismatch here means it always finds zero theme files.
            'name' => 'exito-client-theme',
            'template_slug' => $template['slug'],
            'brand_name' => $brand['company_name'],
            'style_tokens' => [
                'primary_color' => $brand['primary_color'],
                'secondary_color' => $brand['secondary_color'],
            ],
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

    /**
     * Gathers the client's real logo and any photos uploaded for this
     * project (see ProjectFile::categoryLabels()), as actual binary image
     * data — not just a path string. These get:
     * - shown to Claude as vision input, so the builder knows what the
     *   real logo/photos look like (see ClaudeWordPressBuilderService),
     * - embedded verbatim into the generated theme's assets/ folder at a
     *   fixed filename the AI is told to reference (see
     *   BundleExporterService::embedThemeAssets()), so the shipped site
     *   uses the client's actual files rather than an AI-invented
     *   placeholder logo/photo.
     *
     * Bounded to a handful of images (1 logo + up to 4 photos) to keep the
     * build prompt and ZIP a reasonable size. Reads that fail (missing
     * file, non-image mime type) are just skipped rather than failing the
     * whole build.
     *
     * @return array{logo: ?array{filename:string,mime:string,bytes:string}, images: array<int, array{filename:string,mime:string,bytes:string}>, files: array}
     */
    protected function collectAssets(Project $project): array
    {
        $disk = Storage::disk('public');
        $logo = null;
        $images = [];

        $logoPath = $project->client?->logo_path;
        if ($logoPath) {
            $logo = $this->readImageAsset($disk, $logoPath, 'client-logo');
        }

        $photoIndex = 0;
        foreach ($project->files as $file) {
            if (!in_array($file->category, ['logo', 'foto'], true)) {
                continue; // skip documents/company profile PDFs — not usable as visual site assets.
            }

            if ($file->category === 'logo' && !$logo) {
                $logo = $this->readImageAsset($disk, $file->file_path, 'client-logo');
                continue;
            }

            if (count($images) >= 4) {
                continue;
            }

            $asset = $this->readImageAsset($disk, $file->file_path, 'client-photo-' . (++$photoIndex));
            if ($asset) {
                $images[] = $asset;
            }
        }

        return [
            'logo' => $logo,
            'images' => $images,
            'files' => [],
        ];
    }

    private function readImageAsset($disk, ?string $path, string $slot): ?array
    {
        if (!$path || !$disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: '';
        if (!str_starts_with($mime, 'image/')) {
            return null;
        }

        $bytes = $disk->get($path);
        if (!is_string($bytes) || $bytes === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'png');
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            $extension = 'png';
        }

        return [
            'filename' => $slot . '.' . $extension,
            'mime' => $mime,
            'bytes' => $bytes,
        ];
    }
}
