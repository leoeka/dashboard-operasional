<?php

namespace App\Support;

use App\Services\ElementorPageBuilderService;
use Illuminate\Support\Facades\Storage;

/**
 * The view-model behind every rendering of a design blueprint as a website:
 * the PNG the client approves (pdf/mockup-render) and the live demo
 * (projects/mockup-live) both render resources/views/mockup/site.blade.php from
 * what this returns.
 *
 * It decides nothing about layout itself. Which sections render and how comes
 * from ElementorPageBuilderService::describeSections() — the plan the
 * Gutenberg builder also renders from — and what each item says comes from
 * SectionContent. This class only gathers those decisions plus the page's
 * photographs into one structure for Blade.
 */
final class MockupSite
{
    /**
     * @param array $mockup  a candidate blueprint: pages, design, website_concept, global_cta
     * @param array $options brand:string, logo:?string, page:?string (slug), fixed:bool (1440px PNG canvas),
     *                       webfonts:bool, link:?callable(string $slug): string,
     *                       images: [slug => ['hero' => url, 'items' => [i => url], 'sections' => [s => [i => url]]]]
     */
    public static function build(array $mockup, array $options = []): array
    {
        $design = is_array($mockup['design'] ?? null) ? $mockup['design'] : [];
        $pages = SitemapPages::ordered(is_array($mockup['pages'] ?? null) ? $mockup['pages'] : []);
        $requested = (string) ($options['page'] ?? 'home');
        $current = collect($pages)->firstWhere('slug', $requested) ?? ($pages[0] ?? null);
        $link = $options['link'] ?? null;

        $primary = Palette::hex($design['primary_color'] ?? null, '#1F2937');
        $secondary = Palette::hex($design['secondary_color'] ?? null, '#F8FAFC');
        $accent = Palette::hex($design['accent_color'] ?? null, '#2563EB');
        $fontHeading = self::fontName($design['font_heading'] ?? null, 'Georgia');
        $fontBody = self::fontName($design['font_body'] ?? null, 'Arial');

        $nav = array_map(fn (array $page) => [
            'name' => $page['name'],
            'slug' => $page['slug'],
            'href' => is_callable($link) ? $link($page['slug']) : '#' . $page['slug'],
            'active' => $current && $page['slug'] === $current['slug'],
        ], $pages);

        $globalCta = SectionContent::text($mockup['global_cta'] ?? '');

        return [
            'brand' => (string) ($options['brand'] ?? 'Website'),
            'logo' => $options['logo'] ?? null,
            'fixed' => (bool) ($options['fixed'] ?? false),
            'fonts_url' => ($options['webfonts'] ?? false) ? self::fontsUrl([$fontHeading, $fontBody]) : null,
            'lang' => ($mockup['language'] ?? 'id') === 'en' ? 'en' : 'id',
            'full_page' => CompositionSpec::isFullPage($design),
            'layout_variant' => in_array($design['layout_variant'] ?? null, ['split-right', 'split-left', 'overlay-bg'], true) ? $design['layout_variant'] : 'split-right',
            'colors' => [
                'primary' => $primary,
                'secondary' => $secondary,
                'accent' => $accent,
                'on_primary' => Palette::textOn($primary),
                'on_accent' => Palette::textOn($accent),
                'ink' => Palette::INK,
            ],
            'fonts' => ['heading' => $fontHeading, 'body' => $fontBody],
            'global' => CompositionSpec::globalDesign($design),
            'tokens' => MockupDesignSpec::tokens(),
            'nav' => $nav,
            'nav_primary' => array_slice($nav, 0, 6),
            'cta' => $globalCta !== '' ? $globalCta : 'Hubungi Kami',
            'concept' => SectionContent::text($mockup['website_concept'] ?? ''),
            'page' => $current
                ? self::page($current, $design, $options['images'][$current['slug']] ?? [], $globalCta)
                : ['slug' => 'home', 'name' => 'Home', 'sections' => []],
        ];
    }

    /**
     * Section index => item index => photo, for one page. Shared with the
     * Gutenberg builder so a photograph lands on the same section in the demo
     * and in WordPress.
     *
     * Legacy image maps carry card photos under a flat `items` key with no
     * section index; those always belonged to the page's card grid.
     *
     * @return array<int, array<int, string>>
     */
    public static function photosBySection(array $pageImages, array $plan): array
    {
        $bySection = [];

        foreach (is_array($pageImages['sections'] ?? null) ? $pageImages['sections'] : [] as $sectionIndex => $items) {
            if (is_array($items)) {
                $bySection[(int) $sectionIndex] = $items;
            }
        }

        if (!empty($pageImages['items']) && is_array($pageImages['items'])) {
            foreach ($plan as $index => $sectionPlan) {
                if ($sectionPlan['rendered'] && $sectionPlan['role'] === 'card_grid') {
                    $bySection[$index] ??= $pageImages['items'];
                    break;
                }
            }
        }

        return $bySection;
    }

    /**
     * Public URLs for the photographs frozen onto a candidate, per page slug —
     * for the live demo, which the browser loads over HTTP. Storage-relative
     * paths go through Storage::url(); the absolute filesystem path never leaves
     * the server.
     */
    public static function imagesFromManifest(array $assets): array
    {
        $disk = Storage::disk('public');
        $images = [];

        foreach (is_array($assets['pages'] ?? null) ? $assets['pages'] : [] as $slug => $page) {
            if (!is_array($page)) {
                continue;
            }

            $url = function (mixed $path) use ($disk): ?string {
                $path = is_string($path) ? ltrim($path, '/') : '';

                return $path !== '' && !str_contains($path, '..') && $disk->exists($path) ? $disk->url($path) : null;
            };

            $pageImages = [];
            if ($hero = $url($page['hero']['path'] ?? null)) {
                $pageImages['hero'] = $hero;
            }

            foreach (is_array($page['sections'] ?? null) ? $page['sections'] : [] as $sectionIndex => $section) {
                foreach (is_array($section['items'] ?? null) ? $section['items'] : [] as $itemIndex => $item) {
                    if ($photo = $url($item['path'] ?? null)) {
                        $pageImages['sections'][(int) $sectionIndex][(int) $itemIndex] = $photo;
                    }
                }
            }

            $images[(string) $slug] = $pageImages;
        }

        return $images;
    }

    private static function page(array $entry, array $design, array $pageImages, string $globalCta): array
    {
        $sections = is_array($entry['page']['sections'] ?? null) ? array_values($entry['page']['sections']) : [];
        $plan = app(ElementorPageBuilderService::class)->describeSections($sections, $design);
        $photos = self::photosBySection($pageImages, $plan);
        $rendered = [];

        foreach ($plan as $index => $sectionPlan) {
            if (!$sectionPlan['rendered']) {
                continue;
            }

            $section = $sections[$index];
            $headline = SectionContent::text($section['headline'] ?? '') ?: SectionContent::text($section['name'] ?? '');
            $name = SectionContent::text($section['name'] ?? '');
            $sectionPhotos = $photos[$index] ?? [];

            $rendered[] = [
                'index' => $index,
                'role' => $sectionPlan['role'],
                'renderer' => $sectionPlan['renderer'],
                'plan' => $sectionPlan,
                'c' => $sectionPlan['composition'],
                'eyebrow' => self::eyebrow($name, $headline, $sectionPlan),
                'headline' => $headline,
                'description' => SectionContent::text($section['description'] ?? ''),
                'cta' => SectionContent::text($section['cta'] ?? '') ?: ($sectionPlan['renderer'] === 'cta' ? $globalCta : ''),
                'items' => SectionContent::items($section, $sectionPlan['item_limit']),
                'photos' => $sectionPhotos,
                'photo' => $sectionPlan['role'] === 'hero' ? ($pageImages['hero'] ?? null) : (reset($sectionPhotos) ?: null),
                'background' => CompositionSpec::RENDERER_BACKGROUNDS[$sectionPlan['renderer']] ?? 'none',
            ];
        }

        return ['slug' => $entry['slug'], 'name' => $entry['name'], 'sections' => $rendered];
    }

    /**
     * The small label above a section heading — the section's own name when it
     * says something the headline does not. Legacy blueprints never showed one.
     */
    public static function eyebrow(string $name, string $headline, array $plan): string
    {
        if (!in_array($plan['renderer'], ['editorial', 'alternating', 'testimonials', 'gallery', 'faq', 'stats', 'team', 'pricing'], true)) {
            return '';
        }

        if ($name === '' || mb_strlen($name) > 40 || mb_strtolower($name) === mb_strtolower($headline)) {
            return '';
        }

        return $name;
    }

    private static function fontName(mixed $value, string $default): string
    {
        $value = preg_replace('/[^A-Za-z0-9 ]/', '', is_string($value) ? $value : '') ?? '';

        return trim($value) !== '' ? trim($value) : $default;
    }

    private static function fontsUrl(array $families): ?string
    {
        $families = array_unique(array_filter($families, fn (string $f) => !in_array($f, ['Georgia', 'Arial', 'Helvetica'], true)));
        if (!$families) {
            return null;
        }

        $query = implode('&', array_map(
            fn (string $family) => 'family=' . str_replace(' ', '+', $family) . ':wght@400;500;600;700',
            $families
        ));

        return 'https://fonts.googleapis.com/css2?' . $query . '&display=swap';
    }
}
