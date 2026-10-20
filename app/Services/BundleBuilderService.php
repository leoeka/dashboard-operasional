<?php

namespace App\Services;

use App\Models\Project;
use App\Support\MockupSite;
use App\Support\SitemapPages;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BundleBuilderService
{
    public function __construct(
        private ElementorPageBuilderService $elementorPageBuilder,
        private MockupAssetService $mockupAssets,
    ) {}

    public function build(Project $project): array
    {
        $proposal = $project->latestProposal;
        if (! $proposal) {
            throw new \RuntimeException('Generate proposal dan mockup terlebih dahulu sebelum membangun WordPress.');
        }
        $proposalData = json_decode((string) $proposal->ai_reasoning, true) ?: [];
        $analysis = $this->resolveAnalysis($project, $proposalData['analysis'] ?? []);
        $template = $this->resolveTemplate($project);
        $brand = $this->resolveBrand($project, $proposalData['mockup'] ?? []);
        $content = $this->resolveContent($project, $analysis, $proposalData['mockup'] ?? []);

        $mockup = $proposalData['mockup'] ?? [];
        $mockupPages = $mockup['pages'] ?? [];

        // The whole approved sitemap is built. Home is always first and the
        // front page, and every slug is unique — see SitemapPages, which the
        // page builder, the manifest and the live demo all share.

        // The exact photo files the client approved, read back off disk — no
        // image generation happens after approval at all. Deliberately NOT
        // wrapped in a try/catch: a photo the approved design actually shows
        // going missing is a real failure, and swallowing it is what used to
        // ship a text-only page while reporting success. Optional/absent slots
        // are skipped quietly; required ones throw. See MockupAssetService.
        $sectionImages = $this->mockupAssets->loadApproved($mockup);

        $bundle = [
            'project_id' => $project->id,
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
            'elementor_pages' => $this->elementorPageBuilder->buildPages(
                is_array($mockupPages) ? $mockupPages : [],
                $mockup['design'] ?? [],
                $sectionImages['map'],
                is_string($mockup['global_cta'] ?? null) ? $mockup['global_cta'] : '',
                ($mockup['language'] ?? 'id') === 'en' ? 'en' : 'id'
            ),
            // filename => raw JPEG bytes, embedded into the theme and
            // uploaded to the Media Library the first time the theme is
            // activated.
            'section_images' => $sectionImages['files'],
            'assets' => $this->collectAssets($project),
        ];

        $bundle['mockup_rendering'] = $this->buildMockupRendering($project, $mockup, $sectionImages['map'], $bundle['assets']);

        return $bundle;
    }

    /** Render the same approved Blade page and CSS into the installable theme. */
    private function buildMockupRendering(Project $project, array $mockup, array $imageMap, array $assets): array
    {
        $brand = $project->client?->company_name ?? $project->name;
        $images = [];
        foreach ($imageMap as $slug => $pageImages) {
            if (is_string($pageImages['hero'] ?? null)) {
                $images[$slug]['hero'] = '__EXITO_IMAGE:'.$pageImages['hero'].'__';
            }
            foreach (($pageImages['items'] ?? []) as $index => $filename) {
                $images[$slug]['items'][$index] = '__EXITO_IMAGE:'.$filename.'__';
            }
            foreach (($pageImages['sections'] ?? []) as $section => $items) {
                foreach ($items as $index => $filename) {
                    $images[$slug]['sections'][$section][$index] = '__EXITO_IMAGE:'.$filename.'__';
                }
            }
        }

        $logo = isset($assets['logo']['filename']) ? '__EXITO_IMAGE:'.$assets['logo']['filename'].'__' : null;
        $pages = [];
        foreach (SitemapPages::ordered(is_array($mockup['pages'] ?? null) ? $mockup['pages'] : []) as $page) {
            $site = MockupSite::build($mockup, [
                'brand' => $brand,
                'logo' => $logo,
                'page' => $page['slug'],
                'images' => $images,
                'webfonts' => true,
                'link' => static fn (string $slug): string => '__EXITO_PAGE_URL:'.$slug.'__',
                // Only the page itself is stored as content. The navigation bar
                // and footer belong to the theme (see the chrome render below),
                // so a page the client adds later in WordPress arrives with the
                // same navigation and footer as the approved pages.
                'chrome' => ['render' => false],
            ]);
            $body = view('mockup.body', ['site' => $site])->render();
            $pages[$page['slug']] = ['title' => $page['name'], 'html' => "<!-- wp:html -->\n{$body}\n<!-- /wp:html -->"];
        }

        $homeSite = MockupSite::build($mockup, ['brand' => $brand, 'logo' => $logo, 'page' => 'home', 'images' => $images, 'webfonts' => true]);
        $css = view('mockup.styles', ['site' => $homeSite])->render();
        $css = preg_replace('/^<style>|<\/style>$/', '', trim($css)) ?? $css;

        // The same two partials, rendered once more for the theme's header.php
        // and footer.php. Only the link list, the brand link, the logo and the
        // copyright year change: instead of a frozen list of pages they become
        // the WordPress calls that read the site's own menu, so editing the
        // navigation in wp-admin changes every page at once. The logo slot
        // carries a token the exporter fills with the path of the logo file
        // that ships inside the theme (BundleExporterService::embedThemeAssets).
        $chromeSite = MockupSite::build($mockup, [
            'brand' => $brand,
            'logo' => $logo ? '__EXITO_THEME_ASSET__' : null,
            'page' => 'home',
            'images' => $images,
            'chrome' => [
                'menu' => "<?php exito_client_nav_menu('links'); ?>",
                'footer_menu' => $homeSite['global']['footer_layout'] === 'minimal'
                    ? "<?php exito_client_nav_menu('footer-links'); ?>"
                    : "<?php exito_client_nav_menu(''); ?>",
                'home' => "<?php echo esc_url(home_url('/')); ?>",
                'cta_href' => "<?php echo exito_client_cta_href(); ?>",
                'year' => "<?php echo (int) date('Y'); ?>",
            ],
        ]);

        return [
            'pages' => $pages,
            'css' => $css,
            'fonts_url' => $homeSite['fonts_url'],
            'lang' => $homeSite['lang'],
            'chrome' => [
                'wrapper' => '<div class="site '.($chromeSite['full_page'] ? 'full-page' : 'legacy-page').'">',
                'header' => view('mockup.header', ['site' => $chromeSite])->render(),
                'footer' => view('mockup.footer', ['site' => $chromeSite])->render(),
            ],
            // The approved navigation, as data: the importer turns it into the
            // WordPress menu items, and the theme keeps the label for the CTA.
            'nav' => array_map(
                static fn (array $item): array => ['label' => $item['name'], 'slug' => $item['slug']],
                $chromeSite['nav_primary']
            ),
            'cta' => $chromeSite['cta'],
        ];
    }

    protected function resolveAnalysis(Project $project, array $proposalAnalysis = []): array
    {
        return array_replace_recursive([
            'business_summary' => $project->description ?? 'Business summary not provided',
            'target_market' => $project->target_market ?? 'General market',
            'type' => $project->type ?? 'company',
        ], $proposalAnalysis);
    }

    /**
     * Describes the project's own website category. This used to return a
     * fixed "Restaurant Modern" / category "restaurant" for every project,
     * and that claim travelled into the bundle and the proposal, so a law
     * firm or a coffee roaster was described as a restaurant build. Where the
     * project states no type, nothing is claimed rather than a category being
     * invented.
     */
    protected function resolveTemplate(Project $project): array
    {
        $type = trim((string) ($project->type ?? ''));

        if ($type === '') {
            return ['slug' => null, 'name' => null, 'category' => null, 'preview_url' => null];
        }

        return [
            'slug' => Str::slug($type),
            'name' => $type,
            'category' => Str::slug($type),
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
                'title' => $about['headline'] ?? 'Tentang '.$project->name,
                'content' => $about['description'] ?? data_get($analysis, 'business_analysis.value_proposition', $analysis['business_summary']),
            ],
            'services' => [
                'title' => $services['headline'] ?? 'Layanan Kami',
                'description' => $services['description'] ?? '',
                'items' => $services['items'] ?? [],
            ],
            'footer' => [
                'text' => data_get($mockup, 'footer.text', 'Hubungi '.$project->name.' untuk informasi lebih lanjut.'),
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
            // Every file in the shipped theme is written under this folder
            // name (see BundleExporterService::themeShell()), and WordPress
            // installs the theme by that folder, so it is the one identity
            // the ZIP and the README have to agree on.
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
     * data — not just a path string. These get embedded verbatim into the
     * generated theme's assets/ folder at a fixed filename
     * (BundleExporterService::embedThemeAssets()), so the shipped site uses
     * the client's actual files rather than a placeholder, and the mockup
     * shell references them by those same paths.
     *
     * Bounded to a handful of images (1 logo + up to 4 photos) to keep the
     * ZIP a reasonable size. Reads that fail (missing file, non-image mime
     * type) are just skipped rather than failing the whole build.
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
            if (! in_array($file->category, ['logo', 'foto'], true)) {
                continue; // skip documents/company profile PDFs — not usable as visual site assets.
            }

            if ($file->category === 'logo' && ! $logo) {
                $logo = $this->readImageAsset($disk, $file->file_path, 'client-logo');

                continue;
            }

            if (count($images) >= 4) {
                continue;
            }

            $asset = $this->readImageAsset($disk, $file->file_path, 'client-photo-'.(++$photoIndex));
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
        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: '';
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        $bytes = $disk->get($path);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'png');
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            $extension = 'png';
        }

        return [
            'filename' => $slot.'.'.$extension,
            'mime' => $mime,
            'bytes' => $bytes,
        ];
    }
}
