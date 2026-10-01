<?php

namespace App\Services;

use App\Support\CompositionSpec;
use App\Support\MockupDesignSpec;
use App\Support\MockupSite;
use App\Support\Palette;
use App\Support\SectionContent;
use App\Support\SitemapPages;
use Illuminate\Support\Str;

/**
 * Builds each approved-mockup page's real WordPress content, deterministically
 * in PHP from the same `mockup.pages` data that drives the PDF/PNG mockup —
 * instead of asking Claude/GPT to hand-write it (this project has repeatedly
 * hit bugs from AI output not matching the exact shape a consumer expected).
 *
 * Two things are produced per page:
 * - `html`: valid Gutenberg block markup (`<!-- wp:heading -->...`), used as
 *   the page's real `post_content`. This is editable out of the box in
 *   WordPress's built-in Block Editor — no extra plugin required.
 * - `elements`: the same content as an Elementor "classic" section/column/
 *   widget tree (real `_elementor_data` shape), kept available in case a
 *   project wants Elementor editing too — it's simply unused if the Elementor
 *   plugin was never installed.
 *
 * Both now apply the approved mockup's design tokens (colors) and mirror the
 * same Hero / icon-band / photo-card structure the client actually saw and
 * approved in the PNG mockup (see resources/views/pdf/mockup-render.blade.php
 * and GenerateMockupGptService::pickMockupSections()) — a plain, uncolored heading/paragraph
 * loop was producing a WordPress page that looked nothing like what was
 * approved, even though Claude's header/footer/style.css were on-brand.
 */
class ElementorPageBuilderService
{

    /**
     * @param array $mockupPages   $mockup['pages'] from the approved proposal (list of {name, sections}).
     * @param array $design        $mockup['design'] (primary/secondary/accent colors, fonts).
     * @param array $imageMap      MockupAssetService::loadApproved()'s ['map'], keyed by the same page slugs this method
     *                             computes: page slug -> {hero?: filename, items?: {itemIndex: filename}}.
     * @param string $globalCta    $mockup['global_cta'] — the label a CTA band or pricing plan falls back to, as in the live demo.
     * @return array<string, array{title:string, slug:string, html:string, elements:array}>
     *         Keyed by page slug. Home is always first and always `home`;
     *         every other slug is unique (see SitemapPages), so two pages whose
     *         names slug alike can no longer overwrite each other.
     */
    public function buildPages(array $mockupPages, array $design = [], array $imageMap = [], string $globalCta = '', string $language = 'id'): array
    {
        $this->globalCta = trim($globalCta);
        $this->language = $language === 'en' ? 'en' : 'id';
        $pages = [];

        foreach (SitemapPages::ordered($mockupPages) as $entry) {
            $slug = $entry['slug'];
            // array_values(): a page's `sections` list must be sequentially
            // indexed from 0 for the "index 0 = hero" convention below to line
            // up — GPT's JSON doesn't guarantee that on decode.
            $sections = is_array($entry['page']['sections'] ?? null) ? array_values($entry['page']['sections']) : [];

            $pages[$slug] = [
                'title' => $entry['name'],
                'slug' => $slug,
                'html' => $this->renderGutenbergBlocks($sections, $imageMap[$slug] ?? [], $design),
                'elements' => $this->mapSectionsToElements($sections, $design),
            ];
        }

        return $pages;
    }

    /** Site-wide CTA label for the page currently being built. */
    private string $globalCta = '';

    /** The site's language, for the few labels the builder writes itself — MockupSite's `lang` rule. */
    private string $language = 'id';

    /**
     * The structural decisions this builder actually applies to one page's
     * sections: which section becomes the hero / icon band / card grid,
     * which are dropped, and the alignment each part renders with.
     *
     * Public because the approval step builds Claude's implementation
     * manifest from exactly these decisions (see BlueprintManifestService)
     * instead of asking GPT to read them back out of the mockup PNG.
     * renderGutenbergBlocks() consumes the same method, so the manifest and
     * the shipped markup cannot describe different layouts.
     *
     * Two regimes, chosen by the blueprint itself (CompositionSpec::isFullPage()):
     * - full page (renderer_version 2): every section with content renders,
     *   through the composition the designer chose for it — or, when it chose
     *   none that the content can fill, the default for that content shape.
     * - legacy: hero + first items-bearing section (icon band) + second (card
     *   grid), everything else skipped — exactly what the PNGs approved before
     *   V2 showed, so an old approved project still builds what it approved.
     *
     * `renderer` is the one key the Blade site renderer and the Gutenberg
     * builder both switch on, so a section cannot be drawn as one thing in the
     * live preview and another in WordPress.
     *
     * @return array<int, array{index:int, role:string, renderer:?string, heading_align:string, body_align:string, layout_variant:string, composition:?array, item_limit:int, rendered:bool}>
     */
    public function describeSections(array $sections, array $design = []): array
    {
        $sections = array_values($sections);

        return CompositionSpec::isFullPage($design)
            ? $this->describeFullPage($sections, $design)
            : $this->describeLegacy($sections, $design);
    }

    /** Item caps per renderer — shared by the live/PNG renderer and Gutenberg so both show the same items. */
    private const ITEM_LIMITS = [
        'features' => 6,
        'cards' => 6,
        'editorial' => 5,
        'alternating' => 4,
        'stats' => 4,
        'testimonials' => 3,
        'gallery' => 6,
        'logos' => 8,
        'faq' => 8,
        'team' => 8,
        'pricing' => 4,
        'cta' => 0,
    ];

    /** Sections that describe site chrome the theme already draws, never page content. */
    private const CHROME_TYPES = ['footer', 'header', 'navigation', 'nav', 'navbar', 'menu'];

    private function describeFullPage(array $sections, array $design): array
    {
        $layoutVariant = $this->layoutVariant($design);
        $plan = [];
        $genericSeen = 0;

        foreach ($sections as $index => $section) {
            if (!is_array($section)) {
                continue;
            }

            if ($index === 0) {
                $composition = CompositionSpec::resolve($section, $design, 'hero');
                $plan[$index] = [
                    'index' => $index,
                    'role' => 'hero',
                    'renderer' => 'hero',
                    'shape' => 'hero',
                    'heading_align' => $composition['text_align'],
                    'body_align' => $composition['text_align'],
                    'layout_variant' => $layoutVariant,
                    'composition' => $composition,
                    'item_limit' => 0,
                    'rendered' => true,
                ];
                continue;
            }

            if (!$this->hasContent($section)) {
                $plan[$index] = $this->skippedPlan($index, $layoutVariant, $section);
                continue;
            }

            $shape = CompositionSpec::sectionShape($section);
            $declared = strtolower(trim((string) ($section['composition'] ?? '')));
            $generic = in_array($shape, ['feature_items', 'card_items', 'listing'], true);

            // A declared composition is honoured only when the content can
            // actually fill it — a band of plain features never becomes an FAQ
            // just because a designer said so.
            $name = in_array($declared, CompositionSpec::compositionsForShape($shape), true)
                ? $declared
                : CompositionSpec::defaultCompositionForShape($shape, $genericSeen);

            if ($generic) {
                $genericSeen++;
            }

            $role = CompositionSpec::roleForComposition($name);
            $renderer = CompositionSpec::rendererForComposition($name);
            $composition = CompositionSpec::resolve(array_merge($section, ['composition' => $name]), $design, $role);

            $plan[$index] = [
                'index' => $index,
                'role' => $role,
                'renderer' => $renderer,
                'shape' => $shape,
                'heading_align' => $composition['text_align'],
                'body_align' => $composition['text_align'],
                'layout_variant' => $layoutVariant,
                'composition' => $composition,
                'item_limit' => self::ITEM_LIMITS[$renderer] ?? 6,
                'rendered' => true,
            ];
        }

        return $plan;
    }

    private function hasContent(array $section): bool
    {
        $type = strtolower(trim((string) ($section['type'] ?? '')));
        if (in_array($type, self::CHROME_TYPES, true)) {
            return false;
        }

        foreach (['headline', 'name', 'description'] as $key) {
            if (is_string($section[$key] ?? null) && trim($section[$key]) !== '') {
                return true;
            }
        }

        return !empty($section['items']) && is_array($section['items']);
    }

    private function skippedPlan(int $index, string $layoutVariant, array $section): array
    {
        $align = $this->resolveAlign($section, 'center');

        return [
            'index' => $index,
            'role' => 'skipped',
            'renderer' => null,
            'shape' => null,
            'heading_align' => $align,
            'body_align' => $align,
            'layout_variant' => $layoutVariant,
            'composition' => null,
            'item_limit' => 0,
            'rendered' => false,
        ];
    }

    private function describeLegacy(array $sections, array $design): array
    {
        $layoutVariant = $this->layoutVariant($design);
        $picked = $this->pickIconPhotoIndexes($sections);
        $plan = [];

        foreach ($sections as $index => $section) {
            if (!is_array($section)) {
                continue;
            }

            if ($index === 0) {
                // .hero lays its copy out in a left column, except
                // .hero.overlay-bg which centers it over the full-bleed photo.
                $role = 'hero';
                $align = $this->resolveAlign($section, $layoutVariant === 'overlay-bg' ? 'center' : 'left');
                $headingAlign = $align;
                $bodyAlign = $align;
            } elseif ($index === $picked['icon']) {
                // .icon-row centers its badges; .icon-row.minimal (the
                // overlay-bg pairing) makes the band a left-aligned list.
                $role = 'icon_band';
                $headingAlign = $this->resolveAlign($section, 'center');
                $bodyAlign = $this->resolveAlign($section, $layoutVariant === 'overlay-bg' ? 'left' : 'center');
            } elseif ($index === $picked['photo']) {
                // .card sets no text-align in any variant, so card copy reads left.
                $role = 'card_grid';
                $headingAlign = $this->resolveAlign($section, 'center');
                $bodyAlign = $this->resolveAlign($section, 'left');
            } else {
                // Real content GPT wrote, but never part of the PNG the
                // client approved - see the note in renderGutenbergBlocks().
                $role = 'skipped';
                $headingAlign = $this->resolveAlign($section, 'center');
                $bodyAlign = $headingAlign;
            }

            // The composition the blueprint asked for, resolved into concrete
            // rendering decisions. Both the mockup Blade and the Gutenberg body
            // read these same values, so a composition cannot mean one thing in
            // the PNG and another in WordPress.
            $composition = $role === 'skipped' ? null : CompositionSpec::resolve($section, $design, $role);

            // A blueprint that states its own text_align wins over the legacy
            // per-variant mapping; CompositionSpec applies that same rule, so
            // take the alignment from there whenever a composition resolved.
            if ($composition && isset($section['text_align'])) {
                $bodyAlign = $composition['text_align'];
                if ($role === 'hero') {
                    $headingAlign = $composition['text_align'];
                }
            }

            $plan[$index] = [
                'index' => $index,
                'role' => $role,
                // Legacy rendering never looked at a band's composition to decide
                // what to draw: the icon band was always the numbered feature row
                // and the photo section always the card grid.
                'renderer' => match ($role) {
                    'hero' => 'hero',
                    'icon_band' => 'features',
                    'card_grid' => 'cards',
                    default => null,
                },
                'shape' => null,
                'heading_align' => $headingAlign,
                'body_align' => $bodyAlign,
                'layout_variant' => $layoutVariant,
                'composition' => $composition,
                // What the approved legacy PNG actually showed: three feature
                // badges and up to four photographed cards.
                'item_limit' => match ($role) {
                    'icon_band' => 3,
                    'card_grid' => 4,
                    default => 0,
                },
                'rendered' => $role !== 'skipped',
            ];
        }

        return $plan;
    }

    /** The approved PNG's structural arrangement, guarded against an unknown value. */
    private function layoutVariant(array $design): string
    {
        return in_array($design['layout_variant'] ?? null, ['split-right', 'split-left', 'overlay-bg'], true)
            ? $design['layout_variant']
            : 'split-right';
    }

    /**
     * Native WordPress Block Editor content — real `<!-- wp:type -->` block
     * comments around standard core-block markup, so opening the page in
     * wp-admin shows genuine, individually-editable heading/paragraph/
     * columns/button blocks (not one big "Custom HTML" blob).
     *
     * Mirrors the approved PNG mockup's structure section-by-section:
     * - section 0 is always rendered as a colored Hero band (design's
     *   primary color), copy + optional side photo.
     * - the first items-bearing section after that becomes a compact
     *   "icon row" (numbered badges, no photos) — matches the mockup's
     *   "why choose us" band.
     * - the next items-bearing section becomes a bordered photo/card grid
     *   — the one part of the page that actually gets AI photos.
     * - every other section falls back to a plain heading/paragraph/grid,
     *   alternating a light background band for visual rhythm.
     *
     * Image blocks reference a `__EXITO_IMAGE:<filename>__` token instead of
     * a real URL, because the actual photo (from the approved mockup's assets) is
     * only uploaded to the Media Library at plugin-activation time inside
     * WordPress — see BundleExporterService, which replaces these tokens
     * with the real attachment URL (or strips the block entirely if that
     * particular photo failed to generate/upload).
     */
    private function renderGutenbergBlocks(array $sections, array $images, array $design): string
    {
        $primary = $this->colorOrDefault($design['primary_color'] ?? null, '#1F2937');
        $accent = $this->colorOrDefault($design['accent_color'] ?? null, '#2563EB');
        // A fixed soft neutral, NOT the mockup's own secondary_color — in the
        // approved PNG (mockup-render.blade.php's `.section.alt`), the
        // showcase band's background is always this same neutral regardless
        // of brand palette. secondary_color there is only ever used for the
        // page's overall body background, not as a full-bleed section band;
        // reusing it here for a section background produced loud, ungrounded
        // colors (e.g. a bright pink) that never appeared in what the client
        // actually approved.
        $altBandColor = (string) MockupDesignSpec::token('section_band_color');
        $layoutVariant = $this->layoutVariant($design);

        $heroFilename = $images['hero'] ?? null;
        $plan = $this->describeSections($sections, $design);
        // Section index => item index => photo filename; the same assignment the
        // live demo uses (MockupSite::photosBySection()).
        $photos = MockupSite::photosBySection($images, $plan);
        $fullPage = CompositionSpec::isFullPage($design);

        $blocks = '';

        foreach ($sections as $sectionIndex => $section) {
            if (!is_array($section)) {
                continue;
            }

            $heading = $section['headline'] ?? $section['name'] ?? null;
            $description = $section['description'] ?? null;
            $cta = $section['cta'] ?? null;
            $items = is_array($section['items'] ?? null) ? array_values($section['items']) : [];

            $sectionPlan = $plan[$sectionIndex] ?? null;
            if (!$sectionPlan || !$sectionPlan['rendered']) {
                continue;
            }

            if ($fullPage && $sectionPlan['role'] !== 'hero') {
                $blocks .= $this->gbFullPageSection($section, $sectionPlan, $photos[$sectionIndex] ?? [], $design, $primary, $accent);
                continue;
            }

            if ($sectionPlan['role'] === 'hero') {
                $blocks .= $this->gbHero(
                    $heading ? (string) $heading : '',
                    $description ? (string) $description : '',
                    $cta ? (string) $cta : null,
                    $heroFilename,
                    $primary,
                    $accent,
                    $sectionPlan['body_align'],
                    $sectionPlan['composition']
                );
                continue;
            }

            $isIconSection = $sectionPlan['role'] === 'icon_band';
            $isPhotoSection = $sectionPlan['role'] === 'card_grid';

            // Every other section in the mockup blueprint (pricing,
            // testimonials, instructor bios, FAQ, ...) is real content GPT
            // wrote, but it was never part of what the client actually saw
            // and approved — the PNG only ever showed Hero + this one icon
            // section + this one photo section (see pickMockupSections() in
            // GenerateMockupGptService, which built that same PNG). Rendering everything
            // here made the live page several screens longer than, and
            // structurally unrecognizable from, the approved design.
            // Skipping anything that isn't one of those three keeps the
            // real page an exact match for the approved PNG.
            if (!$isIconSection && !$isPhotoSection) {
                continue;
            }

            // .section-head is text-align:center in every layout variant,
            // so the band's own heading/intro stay centered even when the
            // items below them are left-aligned.
            $sectionHeadAlign = $sectionPlan['heading_align'];

            $inner = '';
            if ($heading) {
                $inner .= $this->gbHeading((string) $heading, 2, $primary, $sectionHeadAlign);
            }
            if ($description) {
                $inner .= $this->gbParagraph((string) $description, null, $sectionHeadAlign);
            }

            if ($items) {
                if ($isIconSection) {
                    $inner .= $this->gbIconRow($items, $accent, $sectionPlan['body_align']);
                } else {
                    // Only the designated photo section actually consumes the
                    // approved photo budget — matches MockupAssetService.
                    $inner .= $this->gbCardGrid($items, $photos[$sectionIndex] ?? [], $sectionPlan['body_align'], $sectionPlan['composition']);
                }
            }

            if ($cta) {
                $inner .= $this->gbButton((string) $cta, $accent, null, $sectionHeadAlign);
            }

            // Only the designated photo/showcase section gets the neutral
            // "alt" band, matching the PNG 1:1.
            $blocks .= $isPhotoSection ? $this->gbSection($inner, $altBandColor) : $this->gbSection($inner);
        }

        return trim($blocks);
    }

    /**
     * One V2 section as core blocks, mirroring resources/views/mockup/sections/
     * {renderer}.blade.php: same plan, same items (SectionContent with the
     * plan's item_limit), same photos, same band colour, same eyebrow. Every
     * block is a stock core block (group, columns, heading, paragraph, list,
     * quote, details, gallery, image, buttons), so the page stays editable in
     * the Block Editor; `exito-*` classes let the theme stylesheet add the
     * finishing the core blocks do not carry.
     */
    private function gbFullPageSection(array $section, array $plan, array $photos, array $design, string $primary, string $accent): string
    {
        $c = $plan['composition'];
        $renderer = $plan['renderer'];
        $align = $plan['heading_align'];
        $items = SectionContent::items($section, $plan['item_limit']);
        $headline = SectionContent::text($section['headline'] ?? '') ?: SectionContent::text($section['name'] ?? '');
        $data = [
            'eyebrow' => MockupSite::eyebrow(SectionContent::text($section['name'] ?? ''), $headline, $plan),
            'headline' => $headline,
            'description' => SectionContent::text($section['description'] ?? ''),
            'cta' => SectionContent::text($section['cta'] ?? ''),
        ];

        $band = CompositionSpec::RENDERER_BACKGROUNDS[$renderer] ?? 'none';
        $bg = match ($band) {
            'band' => (string) MockupDesignSpec::token('section_band_color'),
            'primary' => $primary,
            'accent' => $accent,
            default => null,
        };
        $onBand = $bg ? Palette::textOn($bg) : null;
        $headingColor = in_array($band, ['primary', 'accent'], true) ? $onBand : $primary;

        $inner = match ($renderer) {
            'features' => $this->gbFeatures($data, $items, $c, $align, $headingColor, $accent),
            'cards' => $this->gbHead($data, $align, $headingColor, $accent)
                . $this->gbCardRows($items, $photos, $c, $plan['body_align'])
                . ($data['cta'] ? $this->gbButton($data['cta'], $accent, null, $align) : ''),
            'editorial' => $this->gbEditorial($data, $items, $photos, $c, $headingColor, $accent),
            'alternating' => $this->gbAlternating($data, $items, $photos, $c, $align, $headingColor, $primary, $accent),
            'stats' => $this->gbStats($data, $items, $align, $onBand ?? $primary, $accent),
            'testimonials' => $this->gbTestimonials($data, $items, $align, $headingColor, $accent),
            'gallery' => $this->gbGalleryMosaic($data, $items, $photos, $align, $headingColor, $accent, $primary),
            'logos' => $this->gbHead($data, 'center', $headingColor, $accent) . $this->gbLogos($items),
            'faq' => $this->gbFaq($data, $items, $align, $headingColor, $accent),
            'cta' => $this->gbCta($data, $align, $onBand ?? Palette::INK, $accent, $design),
            'team' => $this->gbTeam($data, $items, $photos, $c, $align, $headingColor, $accent),
            'pricing' => $this->gbPricing($data, $items, $align, $headingColor, $primary, $accent, $design),
            default => '',
        };

        return $this->gbBand($inner, $renderer, $bg, $c);
    }

    /** The section's full-bleed band: its colour, its vertical rhythm, its content width. */
    private function gbBand(string $inner, string $renderer, ?string $bg, array $c): string
    {
        if (trim($inner) === '') {
            return '';
        }

        $top = $c['spacing_top'] . 'px';
        $bottom = $c['spacing_bottom'] . 'px';
        $attrs = [
            'className' => "exito-section exito-{$renderer}",
            'style' => ['spacing' => ['padding' => ['top' => $top, 'bottom' => $bottom]]],
            'layout' => $c['container_width']
                ? ['type' => 'constrained', 'contentSize' => $c['container_width'] . 'px']
                : ['type' => 'default'],
        ];
        $classes = "wp-block-group exito-section exito-{$renderer}";
        $style = "padding-top:{$top};padding-bottom:{$bottom}";

        if ($bg) {
            $text = Palette::textOn($bg);
            $attrs['style']['color'] = ['background' => $bg, 'text' => $text];
            $classes .= ' has-text-color has-background';
            $style = "color:{$text};background-color:{$bg};" . $style;
        }

        return '<!-- wp:group ' . json_encode($attrs, JSON_UNESCAPED_SLASHES) . " -->\n"
            . "<div class=\"{$classes}\" style=\"{$style}\">\n{$inner}</div>\n"
            . "<!-- /wp:group -->\n\n";
    }

    private function gbHead(array $data, string $align, ?string $headingColor, string $accent): string
    {
        $out = '';
        if ($data['eyebrow'] !== '') {
            $out .= $this->gbParagraph($data['eyebrow'], $accent, $align, 'exito-eyebrow');
        }
        if ($data['headline'] !== '') {
            $out .= $this->gbHeading($data['headline'], 2, $headingColor, $align);
        }
        if ($data['description'] !== '') {
            $out .= $this->gbParagraph($data['description'], null, $align);
        }

        return $out;
    }

    /**
     * @param array<int, array{0:string, 1?:?string}> $columns inner markup and optional flex-basis width
     */
    private function gbColumnsRow(array $columns, ?string $className = null): string
    {
        $html = '';
        foreach ($columns as $column) {
            [$inner, $width] = [$column[0], $column[1] ?? null];
            $attrs = $width ? ' ' . json_encode(['width' => $width], JSON_UNESCAPED_SLASHES) : '';
            $style = $width ? " style=\"flex-basis:{$width}\"" : '';
            $html .= "<!-- wp:column{$attrs} -->\n<div class=\"wp-block-column\"{$style}>\n{$inner}</div>\n<!-- /wp:column -->\n\n";
        }

        if ($html === '') {
            return '';
        }

        $attrs = $className ? ' ' . json_encode(['className' => $className], JSON_UNESCAPED_SLASHES) : '';
        $class = 'wp-block-columns' . ($className ? ' ' . $className : '');

        return "<!-- wp:columns{$attrs} -->\n<div class=\"{$class}\">\n{$html}</div>\n<!-- /wp:columns -->\n\n";
    }

    /** Several rows of at most $perRow columns — never one row squeezed six wide. */
    private function gbGrid(array $cells, int $perRow, ?string $className = null): string
    {
        $out = '';
        foreach (array_chunk($cells, max(1, $perRow)) as $row) {
            $out .= $this->gbColumnsRow(array_map(fn (string $cell) => [$cell], $row), $className);
        }

        return $out;
    }

    private function gbList(array $lines, bool $ordered = false, ?string $className = null): string
    {
        $lines = array_values(array_filter($lines, fn ($line) => trim((string) $line) !== ''));
        if (!$lines) {
            return '';
        }

        $attrs = array_filter(['ordered' => $ordered ?: null, 'className' => $className]);
        $attrsJson = $attrs ? ' ' . json_encode($attrs, JSON_UNESCAPED_SLASHES) : '';
        $tag = $ordered ? 'ol' : 'ul';
        $class = 'wp-block-list' . ($className ? ' ' . $className : '');
        $inner = '';
        foreach ($lines as $line) {
            $inner .= "<!-- wp:list-item -->\n<li>" . e($line) . "</li>\n<!-- /wp:list-item -->\n";
        }

        return "<!-- wp:list{$attrsJson} -->\n<{$tag} class=\"{$class}\">{$inner}</{$tag}>\n<!-- /wp:list -->\n\n";
    }

    private function gbFeatures(array $data, array $items, array $c, string $align, ?string $headingColor, string $accent): string
    {
        $cells = [];
        foreach (array_values($items) as $position => $item) {
            $cells[] = $this->gbParagraph(sprintf('%02d', $position + 1), $accent, $align, 'exito-num')
                . $this->gbHeading($item['title'], 3, null, $align)
                . ($item['text'] !== '' ? $this->gbParagraph($item['text'], null, $align) : '');
        }

        $cta = $data['cta'] !== '' ? $this->gbButton($data['cta'], $accent, null, $align) : '';
        $count = count($cells);

        // Same rule as the site renderer: more than three features sit beside
        // the heading, two to a row, unless the band is centred.
        if ($count > 3 && $align !== 'center') {
            return $this->gbColumnsRow([
                [$this->gbHead($data, $align, $headingColor, $accent), '40%'],
                [$this->gbGrid($cells, 2, 'exito-feature-grid'), '60%'],
            ]) . $cta;
        }

        return $this->gbHead($data, $align, $headingColor, $accent)
            . $this->gbGrid($cells, max(1, min($c['columns'], $count ?: 1)), 'exito-feature-grid')
            . $cta;
    }

    private function gbCardRows(array $items, array $photos, array $c, string $align): string
    {
        if (!empty($c['listing'])) {
            $cards = [];
            foreach ($items as $itemIndex => $item) {
                $cards[] = $this->gbListingCard($item, $photos[$itemIndex] ?? null, $c);
            }

            return $cards ? $this->gbGrid($cards, max(1, min($c['columns'], count($cards))), 'exito-cards exito-listing-grid') : '';
        }

        $cards = [];
        foreach ($items as $itemIndex => $item) {
            $inner = isset($photos[$itemIndex]) ? $this->gbImage($photos[$itemIndex], 'medium', $c['image_ratio']) : '';
            $inner .= $this->gbHeading($item['title'], 3, null, $align);
            if ($item['text'] !== '') {
                $inner .= $this->gbParagraph($item['text'], null, $align);
            }
            if ($item['price'] !== '') {
                $inner .= $this->gbParagraph($item['price'], null, $align, 'exito-price');
            }
            $cards[] = $c['card_treatment'] === 'plain' || $c['card_treatment'] === 'flush' ? $inner : $this->gbCard($inner);
        }

        if (!$cards) {
            return '';
        }

        // Feature-first: the lead card takes a wide column, the rest stack
        // beside it — the asymmetric arrangement the site renderer draws.
        if (!empty($c['feature_first']) && count($cards) >= 3) {
            $lead = array_shift($cards);

            return $this->gbColumnsRow([
                [$lead, '58%'],
                [$this->gbGrid($cards, count($cards) >= 4 ? 2 : 1), '42%'],
            ], 'exito-cards exito-cards--feature-first');
        }

        return $this->gbGrid($cards, max(1, min($c['columns'], count($cards))), 'exito-cards');
    }

    /**
     * A tour, room or product card — the same facts, in the same order, as
     * resources/views/mockup/partials/listing-card.blade.php. Each fact is a
     * plain core block, so the client edits a price or a duration in the
     * Block Editor like any other text.
     */
    private function gbListingCard(array $item, ?string $photo, array $c): string
    {
        $labels = SectionContent::listingLabels($this->language);
        $inner = $photo ? $this->gbImage($photo, 'medium', $c['image_ratio']) : '';

        $facts = array_values(array_filter([$item['location'], $item['duration']]));
        $rating = $item['rating'] !== ''
            ? '★ ' . $item['rating'] . ($item['reviews'] !== '' ? ' (' . $item['reviews'] . ')' : '')
            : '';
        $meta = implode('  ·  ', array_filter([implode('  ·  ', $facts), $rating]));
        if ($meta !== '') {
            $inner .= $this->gbParagraph($meta, null, 'left', 'exito-listing-meta');
        }

        $inner .= $this->gbHeading($item['title'], 3, null, 'left');
        if ($item['text'] !== '') {
            $inner .= $this->gbParagraph($item['text'], null, 'left', 'exito-listing-text');
        }
        if ($item['price'] !== '') {
            $inner .= $this->gbParagraph(trim($item['price'] . ' ' . $item['price_unit']), null, 'left', 'exito-price');
        }
        $inner .= $this->gbButton($labels['details'], null, null, 'left');

        return $this->gbCard($inner);
    }

    private function gbEditorial(array $data, array $items, array $photos, array $c, ?string $headingColor, string $accent): string
    {
        $list = $this->gbList(array_map(
            fn (array $item) => trim($item['title'] . ($item['text'] !== '' ? ' — ' . $item['text'] : '')),
            $items
        ), true, 'exito-editorial-list');
        $cta = $data['cta'] !== '' ? $this->gbButton($data['cta'], $accent, null, 'left') : '';
        $photo = reset($photos) ?: null;

        if ($photo) {
            $copy = [$this->gbHead($data, 'left', $headingColor, $accent) . $list . $cta, '50%'];
            $media = [$this->gbImage($photo, 'large', $c['image_ratio']), '50%'];

            return $this->gbColumnsRow($c['image_position'] === 'left' ? [$media, $copy] : [$copy, $media], 'exito-editorial');
        }

        // No photograph: heading in a narrow column, the story in a wide one.
        $head = ($data['eyebrow'] !== '' ? $this->gbParagraph($data['eyebrow'], $accent, 'left', 'exito-eyebrow') : '')
            . $this->gbHeading($data['headline'], 2, $headingColor, 'left');
        $body = ($data['description'] !== '' ? $this->gbParagraph($data['description'], null, 'left', 'exito-lead') : '') . $list . $cta;

        return $this->gbColumnsRow([[$head, '42%'], [$body, '58%']], 'exito-editorial exito-editorial--text');
    }

    private function gbAlternating(array $data, array $items, array $photos, array $c, string $align, ?string $headingColor, string $primary, string $accent): string
    {
        $rows = '';
        foreach (array_values(array_keys($items)) as $position => $itemIndex) {
            $item = $items[$itemIndex];
            $number = sprintf('%02d', $position + 1);
            $media = isset($photos[$itemIndex])
                ? $this->gbImage($photos[$itemIndex], 'large', $c['image_ratio'])
                : $this->gbSection($this->gbHeading($number, 3, Palette::textOn($position % 2 ? $accent : $primary), 'left', 'exito-panel-number'), $position % 2 ? $accent : $primary);
            $copy = $this->gbParagraph($number, $accent, 'left', 'exito-num')
                . $this->gbHeading($item['title'], 3, null, 'left')
                . ($item['text'] !== '' ? $this->gbParagraph($item['text'], null, 'left') : '');

            $columns = [[$media, '55%'], [$copy, '45%']];
            $rows .= $this->gbColumnsRow($position % 2 ? array_reverse($columns) : $columns, 'exito-alt-row');
        }

        return $this->gbHead($data, $align, $headingColor, $accent)
            . $rows
            . ($data['cta'] !== '' ? $this->gbButton($data['cta'], $accent, null, $align) : '');
    }

    private function gbStats(array $data, array $items, string $align, string $textColor, string $accent): string
    {
        $cells = [];
        foreach ($items as $item) {
            $cells[] = $this->gbHeading($item['value'], 3, $textColor, 'left', 'exito-stat-value')
                . ($item['label'] !== '' && $item['label'] !== $item['value'] ? $this->gbParagraph($item['label'], $textColor, 'left', 'exito-stat-label') : '');
        }

        $grid = $this->gbGrid($cells, max(1, min(4, count($cells) ?: 1)), 'exito-stats-grid');
        $hasHead = $data['headline'] !== '' || $data['description'] !== '';

        if ($hasHead && $align !== 'center') {
            return $this->gbColumnsRow([[$this->gbHead($data, $align, $textColor, $textColor), '34%'], [$grid, '66%']]);
        }

        return ($hasHead ? $this->gbHead($data, $align, $textColor, $textColor) : '') . $grid;
    }

    private function gbTestimonials(array $data, array $items, string $align, ?string $headingColor, string $accent): string
    {
        $quotes = SectionContent::quotes($items);
        if (!$quotes) {
            return $this->gbHead($data, $align, $headingColor, $accent);
        }

        $lead = array_shift($quotes);
        $leadBlock = $this->gbQuote($lead, 'exito-quote-lead');

        if (!$quotes) {
            return $this->gbHead($data, $align, $headingColor, $accent) . $leadBlock;
        }

        $side = implode('', array_map(fn (array $quote) => $this->gbQuote($quote, 'exito-quote-small'), $quotes));

        return $this->gbHead($data, $align, $headingColor, $accent)
            . $this->gbColumnsRow([[$leadBlock, '58%'], [$side, '42%']], 'exito-quotes');
    }

    /** @param array{quote:string, author:string, role:string} $quote */
    private function gbQuote(array $quote, string $className): string
    {
        $cite = trim($quote['author'] . ($quote['role'] !== '' ? ', ' . $quote['role'] : ''), ', ');
        $attrs = json_encode(['className' => $className], JSON_UNESCAPED_SLASHES);

        return "<!-- wp:quote {$attrs} -->\n"
            . "<blockquote class=\"wp-block-quote {$className}\"><!-- wp:paragraph -->\n<p>" . e($quote['quote']) . "</p>\n<!-- /wp:paragraph -->"
            . ($cite !== '' ? '<cite>' . e($cite) . '</cite>' : '')
            . "</blockquote>\n<!-- /wp:quote -->\n\n";
    }

    private function gbGalleryMosaic(array $data, array $items, array $photos, string $align, ?string $headingColor, string $accent, string $primary): string
    {
        $images = '';
        $tiles = [];

        foreach (array_values(array_keys($items)) as $position => $itemIndex) {
            $caption = $items[$itemIndex]['title'];

            if (isset($photos[$itemIndex])) {
                $filename = $photos[$itemIndex];
                $token = "__EXITO_IMAGE:{$filename}__";
                $images .= "<!--EXITO_IMG_START:{$filename}-->"
                    . "<!-- wp:image {\"sizeSlug\":\"large\"} -->\n"
                    . "<figure class=\"wp-block-image size-large\"><img src=\"{$token}\" alt=\"" . e($caption) . '"/>'
                    . ($caption !== '' ? '<figcaption class="wp-element-caption">' . e($caption) . '</figcaption>' : '')
                    . "</figure>\n<!-- /wp:image -->"
                    . "<!--EXITO_IMG_END:{$filename}-->\n";
                continue;
            }

            // No photograph: the same colour tile the site renderer draws.
            $tileColor = [$primary, $accent, (string) MockupDesignSpec::token('section_band_color')][$position % 3];
            $tiles[] = $this->gbSection($this->gbParagraph($caption, Palette::textOn($tileColor), 'left', 'exito-tile-caption'), $tileColor);
        }

        $gallery = $images !== ''
            ? "<!-- wp:gallery {\"columns\":3,\"linkTo\":\"none\",\"className\":\"exito-gallery\"} -->\n"
                . "<figure class=\"wp-block-gallery has-nested-images columns-3 is-cropped exito-gallery\">{$images}</figure>\n<!-- /wp:gallery -->\n\n"
            : '';

        return $this->gbHead($data, $align, $headingColor, $accent)
            . $gallery
            . ($tiles ? $this->gbGrid($tiles, 3, 'exito-tiles') : '');
    }

    private function gbLogos(array $items): string
    {
        $inner = '';
        foreach ($items as $item) {
            $inner .= $this->gbParagraph($item['title'], null, 'center', 'exito-logo');
        }

        if ($inner === '') {
            return '';
        }

        return "<!-- wp:group {\"className\":\"exito-logos\",\"layout\":{\"type\":\"flex\",\"flexWrap\":\"wrap\",\"justifyContent\":\"center\"}} -->\n"
            . "<div class=\"wp-block-group exito-logos\">\n{$inner}</div>\n<!-- /wp:group -->\n\n";
    }

    private function gbFaq(array $data, array $items, string $align, ?string $headingColor, string $accent): string
    {
        $list = '';
        $first = true;
        foreach ($items as $item) {
            // Only the first answer starts open, exactly as in the live demo.
            $attrs = $first ? ' {"showContent":true}' : '';
            $open = $first ? ' open' : '';
            $list .= "<!-- wp:details{$attrs} -->\n"
                . "<details class=\"wp-block-details\"{$open}><summary>" . e($item['title']) . '</summary>'
                . ($item['text'] !== '' ? "<!-- wp:paragraph -->\n<p>" . e($item['text']) . "</p>\n<!-- /wp:paragraph -->" : '')
                . "</details>\n<!-- /wp:details -->\n\n";
            $first = false;
        }

        $head = $this->gbHead($data, $align, $headingColor, $accent);

        return $align === 'center'
            ? $head . $list
            : $this->gbColumnsRow([[$head, '36%'], [$list, '64%']], 'exito-faq');
    }

    private function gbCta(array $data, string $align, string $textColor, string $accent, array $design): string
    {
        $cta = $data['cta'] !== '' ? $data['cta'] : $this->globalCta;
        $copy = ($data['eyebrow'] !== '' ? $this->gbParagraph($data['eyebrow'], $textColor, $align, 'exito-eyebrow') : '')
            . $this->gbHeading($data['headline'], 2, $textColor, $align)
            . ($data['description'] !== '' ? $this->gbParagraph($data['description'], $textColor, $align) : '');
        // The button inverts the band: band colour on the text colour.
        $button = $cta !== '' ? $this->gbButton($cta, $textColor, $accent, $align) : '';

        return $align === 'center' || $button === ''
            ? $copy . $button
            : $this->gbColumnsRow([[$copy, '66%'], [$button, '34%']], 'exito-cta');
    }

    private function gbTeam(array $data, array $items, array $photos, array $c, string $align, ?string $headingColor, string $accent): string
    {
        $cells = [];
        foreach ($items as $itemIndex => $item) {
            $cells[] = (isset($photos[$itemIndex])
                    ? $this->gbImage($photos[$itemIndex], 'medium', $c['image_ratio'])
                    : $this->gbParagraph(SectionContent::initials($item['title']), $headingColor, 'left', 'exito-monogram'))
                . $this->gbHeading($item['title'], 3, null, 'left')
                . ($item['role'] !== '' ? $this->gbParagraph($item['role'], $accent, 'left', 'exito-role') : '')
                . ($item['text'] !== '' ? $this->gbParagraph($item['text'], null, 'left') : '');
        }

        return $this->gbHead($data, $align, $headingColor, $accent)
            . $this->gbGrid($cells, max(1, min($c['columns'], count($cells) ?: 1)), 'exito-team');
    }

    private function gbPricing(array $data, array $items, string $align, ?string $headingColor, string $primary, string $accent, array $design): string
    {
        $featured = SectionContent::featuredIndex($items);
        $button = $data['cta'] !== '' ? $data['cta'] : ($this->globalCta ?: 'Hubungi Kami');
        $cells = [];

        foreach (array_values($items) as $position => $item) {
            $isFeatured = $position === $featured;
            $inner = $this->gbHeading($item['title'], 3, $isFeatured ? Palette::textOn($primary) : null, 'left')
                . ($item['price'] !== '' ? $this->gbParagraph($item['price'], $isFeatured ? Palette::textOn($primary) : null, 'left', 'exito-price') : '')
                . ($item['text'] !== '' ? $this->gbParagraph($item['text'], $isFeatured ? Palette::textOn($primary) : null, 'left') : '')
                . $this->gbList($item['features'])
                . $this->gbButton($button, $accent, null, 'left');
            $cells[] = $isFeatured ? $this->gbSection($inner, $primary) : $this->gbCard($inner);
        }

        return $this->gbHead($data, $align, $headingColor, $accent)
            . $this->gbGrid($cells, max(1, min(4, count($cells) ?: 1)), 'exito-plans');
    }

    /**
     * Same "index 0 is the hero, first items-bearing section after that is
     * the icon row, the next one is the photo/card grid" heuristic as
     * GenerateMockupGptService::pickMockupSections() — kept in sync so the real WordPress
     * page matches the structure of the PNG the client actually approved.
     *
     * @return array{icon: ?int, photo: ?int}
     */
    private function pickIconPhotoIndexes(array $sections): array
    {
        $itemSectionIndexes = [];
        foreach ($sections as $index => $section) {
            if ($index === 0 || !is_array($section)) {
                continue;
            }
            if (!empty($section['items']) && is_array($section['items'])) {
                $itemSectionIndexes[] = $index;
            }
        }

        if (count($itemSectionIndexes) >= 2) {
            return ['icon' => $itemSectionIndexes[0], 'photo' => $itemSectionIndexes[1]];
        }

        if (count($itemSectionIndexes) === 1) {
            return ['icon' => null, 'photo' => $itemSectionIndexes[0]];
        }

        return ['icon' => null, 'photo' => null];
    }

    /**
     * The page's opening band: colored background (design's primary color),
     * headline/description/CTA, with the hero photo laid out beside the copy
     * (two columns) when one was generated — mirrors .hero in
     * mockup-render.blade.php instead of just stacking plain text.
     */
    /**
     * Mirrors mockup-render.blade.php's 3 layout variants (see
     * layout_variant in GenerateMockupGptService::generateMockupCandidates())
     * so the built WordPress page structurally matches whichever option the
     * client actually approved, not just its colors:
     * - split-right (default): copy left / photo right, two columns.
     * - split-left: mirrored — photo left / copy right.
     * - overlay-bg: photo as a full-bleed wp:cover background with a dim
     *   overlay, copy centered on top of it.
     */
    private function gbHero(string $heading, string $description, ?string $cta, ?string $heroImage, string $primary, string $accent, string $align = 'left', ?array $composition = null): string
    {
        $composition ??= CompositionSpec::resolve([], [], 'hero');
        $textColor = $this->isLightColor($primary) ? '#1c1a17' : '#ffffff';

        $copy = '';
        if ($heading !== '') {
            $copy .= $this->gbHeading($heading, 1, $textColor, $align);
        }
        if ($description !== '') {
            $copy .= $this->gbParagraph($description, $textColor, $align);
        }
        if ($cta) {
            $copy .= $this->gbButton($cta, '#ffffff', $primary, $align);
        }

        if ($copy === '') {
            return '';
        }

        // The same decisions the mockup PNG was rendered from: a composition
        // that shows no photograph is copy alone, an overlay hero is a cover
        // block, and a split hero uses the blueprint's own column widths rather
        // than one fixed pair.
        if ($composition['image_position'] === 'none' || !$heroImage) {
            return $this->gbSection($copy, $primary);
        }

        if ($composition['family'] === 'overlay') {
            return $this->gbCoverHero($copy, $heroImage, $primary);
        }

        if ($composition['family'] === 'split') {
            $copyWidth = $composition['content_width'] . '%';
            $imageWidth = $composition['image_width'] . '%';
            $imageBlock = $this->gbImage($heroImage, 'large', $composition['image_ratio']);
            $colWidthsCopy = json_encode(['width' => $copyWidth], JSON_UNESCAPED_SLASHES);
            $colWidthsImg = json_encode(['width' => $imageWidth], JSON_UNESCAPED_SLASHES);
            $copyColumn = "<!-- wp:column {$colWidthsCopy} -->\n<div class=\"wp-block-column\" style=\"flex-basis:{$copyWidth}\">\n{$copy}</div>\n<!-- /wp:column -->\n\n";
            $imageColumn = "<!-- wp:column {$colWidthsImg} -->\n<div class=\"wp-block-column\" style=\"flex-basis:{$imageWidth}\">\n{$imageBlock}</div>\n<!-- /wp:column -->\n\n";
            $columns = $composition['image_position'] === 'left' ? ($imageColumn . $copyColumn) : ($copyColumn . $imageColumn);
            $inner = "<!-- wp:columns -->\n<div class=\"wp-block-columns\">\n{$columns}</div>\n<!-- /wp:columns -->\n\n";

            return $this->gbSection($inner, $primary);
        }

        // stacked_media and centered: the photo sits above or below the copy.
        $imageBlock = $this->gbImage($heroImage, 'large', $composition['image_ratio']);
        $inner = $composition['image_position'] === 'above' ? ($imageBlock . $copy) : ($copy . $imageBlock);

        return $this->gbSection($inner, $primary);
    }

    /**
     * The "overlay-bg" hero variant: a native wp:cover block with the hero
     * photo as its background image, a dim overlay in the brand's primary
     * color, and the heading/description/CTA centered on top — the same
     * effect as mockup-render.blade.php's `.hero.overlay-bg`. Uses wp:cover
     * specifically (rather than a styled wp:group) because it's the block
     * WordPress itself ships for exactly this "background image + dim +
     * centered content" pattern, so it edits normally in the Block Editor.
     */
    private function gbCoverHero(string $innerCopy, string $heroImage, string $primary): string
    {
        $token = "__EXITO_IMAGE:{$heroImage}__";
        $attrs = json_encode([
            'url' => $token,
            'dimRatio' => 60,
            'overlayColor' => null,
            'customOverlayColor' => $primary,
            'minHeight' => MockupDesignSpec::token('hero_overlay_min_height'),
            'contentPosition' => 'center center',
        ], JSON_UNESCAPED_SLASHES);

        $coverHeight = MockupDesignSpec::token('hero_overlay_min_height');

        // The marker span wraps ONLY the <img> tag (same convention as
        // gbImage()) — not the whole wp:cover block — so a failed/missing
        // photo just leaves a solid-color cover band (dim span still has
        // the brand color as its background) instead of losing the
        // headline/description/CTA that live inside the same block.
        // core/cover's save() puts the background <img> BEFORE the dim span;
        // the other order is flagged invalid in the Block Editor. The `url`
        // attribute above carries the same token, and the importer replaces
        // tokens outside the markers too (exito_client_apply_images()), so the
        // attribute and the <img> agree after import.
        return "<!-- wp:cover {$attrs} -->\n"
            . "<div class=\"wp-block-cover\" style=\"min-height:{$coverHeight}px\">"
            . "<!--EXITO_IMG_START:{$heroImage}-->"
            . "<img class=\"wp-block-cover__image-background\" alt=\"\" src=\"{$token}\" data-object-fit=\"cover\"/>"
            . "<!--EXITO_IMG_END:{$heroImage}-->"
            . "<span aria-hidden=\"true\" class=\"wp-block-cover__background has-background-dim-60 has-background-dim\" style=\"background-color:{$primary}\"></span>"
            . "<div class=\"wp-block-cover__inner-container\">\n{$innerCopy}</div>"
            . "</div>\n<!-- /wp:cover -->\n\n";
    }

    private function gbHeading(string $text, int $level = 2, ?string $color = null, string $align = 'center', ?string $className = null): string
    {
        $escaped = e($text);
        $align = $this->safeAlign($align);
        $attrs = ['level' => $level, 'textAlign' => $align];
        $class = 'wp-block-heading has-text-align-' . $align;
        $style = '';

        if ($className) {
            $attrs['className'] = $className;
            $class .= ' ' . $className;
        }

        if ($color) {
            $attrs['style'] = ['color' => ['text' => $color]];
            $class .= ' has-text-color';
            $style = ' style="color:' . $color . '"';
        }

        $attrsJson = json_encode($attrs, JSON_UNESCAPED_SLASHES);

        return "<!-- wp:heading {$attrsJson} -->\n"
            . "<h{$level} class=\"{$class}\"{$style}>{$escaped}</h{$level}>\n"
            . "<!-- /wp:heading -->\n\n";
    }

    private function gbParagraph(string $text, ?string $color = null, string $align = 'center', ?string $className = null): string
    {
        $escaped = e($text);
        $align = $this->safeAlign($align);
        $attrs = ['align' => $align];
        $class = 'has-text-align-' . $align;
        $style = '';

        if ($className) {
            $attrs['className'] = $className;
            $class .= ' ' . $className;
        }

        if ($color) {
            $attrs['style'] = ['color' => ['text' => $color]];
            $class .= ' has-text-color';
            $style = ' style="color:' . $color . '"';
        }

        $attrsJson = json_encode($attrs, JSON_UNESCAPED_SLASHES);

        return "<!-- wp:paragraph {$attrsJson} -->\n"
            . "<p class=\"{$class}\"{$style}>{$escaped}</p>\n"
            . "<!-- /wp:paragraph -->\n\n";
    }

    private function gbButton(string $text, ?string $bgColor = null, ?string $textColor = null, string $align = 'center'): string
    {
        $escaped = e($text);
        $align = $this->safeAlign($align);
        if ($bgColor && !$textColor) {
            $textColor = '#ffffff';
        }

        $style = [];
        $classes = ['wp-block-button__link', 'wp-element-button'];
        // The inline style must be EXACTLY what core/button's save() emits for
        // these attributes (text colour, then background), or the Block Editor
        // marks the block invalid ("Attempt Block Recovery") — verified in a real
        // WordPress 7.1 editor. The underline removal and inline-block display
        // this used to inline live in the theme's block-content.css instead
        // (BundleExporterService::blockContentCss()), which is enqueued on the
        // live site and in the editor.
        $inlineStyle = '';

        if ($textColor) {
            $style['color']['text'] = $textColor;
            $classes[] = 'has-text-color';
            $inlineStyle .= 'color:' . $textColor . ';';
        }
        if ($bgColor) {
            $style['color']['background'] = $bgColor;
            $classes[] = 'has-background';
            $inlineStyle .= 'background-color:' . $bgColor . ';';
        }

        $innerAttrs = $style ? json_encode(['style' => $style], JSON_UNESCAPED_SLASHES) : '';
        $styleAttr = $inlineStyle ? ' style="' . rtrim($inlineStyle, ';') . '"' : '';
        $classAttr = implode(' ', $classes);

        return "<!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"{$align}\"}} -->\n"
            . "<div class=\"wp-block-buttons\"><!-- wp:button" . ($innerAttrs ? " {$innerAttrs}" : '') . " -->\n"
            . "<div class=\"wp-block-button\"><a class=\"{$classAttr}\"{$styleAttr} href=\"#\">{$escaped}</a></div>\n"
            . "<!-- /wp:button --></div>\n"
            . "<!-- /wp:buttons -->\n\n";
    }

    private function gbSeparator(): string
    {
        return "<!-- wp:separator {\"opacity\":\"css\"} -->\n<hr class=\"wp-block-separator has-css-opacity\"/>\n<!-- /wp:separator -->\n\n";
    }

    /**
     * Wraps a block of inner content in a `wp:group`, optionally with a solid
     * background band (and auto-picked readable text color) — the mechanism
     * behind the hero band and the alternating light section backgrounds,
     * using officially-supported group color attributes so the Block Editor
     * doesn't flag it as "unexpected content" when the client opens it.
     */
    private function gbSection(string $inner, ?string $bgColor = null): string
    {
        if (trim($inner) === '') {
            return '';
        }

        if (!$bgColor) {
            return "<!-- wp:group -->\n<div class=\"wp-block-group\">\n{$inner}</div>\n<!-- /wp:group -->\n\n";
        }

        $textColor = $this->isLightColor($bgColor) ? '#1c1a17' : '#ffffff';
        $attrs = ['style' => ['color' => ['background' => $bgColor, 'text' => $textColor]]];
        $attrsJson = json_encode($attrs, JSON_UNESCAPED_SLASHES);

        return "<!-- wp:group {$attrsJson} -->\n"
            . "<div class=\"wp-block-group has-text-color has-background\" style=\"color:{$textColor};background-color:{$bgColor}\">\n{$inner}</div>\n"
            . "<!-- /wp:group -->\n\n";
    }

    /**
     * Compact "why choose us"-style row — a colored number "badge" (a small
     * colored heading, not a hand-styled span, so it stays within the
     * heading block's own supported color attribute) above a title/description,
     * no photo needed. Mirrors .icon-row in mockup-render.blade.php.
     */
    private function gbIconRow(array $items, string $accent, string $align = 'center'): string
    {
        $columnsHtml = '';

        foreach (array_slice($items, 0, 4) as $index => $item) {
            $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? null) : (string) $item;
            $desc = is_array($item) ? ($item['description'] ?? null) : null;

            $inner = $this->gbHeading((string) ($index + 1), 4, $accent, $align);
            if ($title) {
                $inner .= $this->gbHeading((string) $title, 3, null, $align);
            }
            if ($desc) {
                $inner .= $this->gbParagraph((string) $desc, null, $align);
            }

            $columnsHtml .= "<!-- wp:column -->\n<div class=\"wp-block-column\">\n{$inner}</div>\n<!-- /wp:column -->\n\n";
        }

        if ($columnsHtml === '') {
            return '';
        }

        return "<!-- wp:columns -->\n<div class=\"wp-block-columns\">\n{$columnsHtml}</div>\n<!-- /wp:columns -->\n\n";
    }

    /**
     * @param array $items      up to 4 mockup section items (original item index as key), rendered as a bordered card grid.
     * @param array $itemImages original item index => approved photo filename (from MockupAssetService).
     */
    private function gbCardGrid(array $items, array $itemImages = [], string $align = 'left', ?array $composition = null): string
    {
        $columnsHtml = '';
        $ratio = $composition['image_ratio'] ?? null;
        $limit = max(1, min(6, $composition['columns'] ?? 4));

        foreach (array_slice($items, 0, $limit, true) as $itemIndex => $item) {
            $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? null) : (string) $item;
            $desc = is_array($item) ? ($item['description'] ?? null) : null;

            $inner = '';
            if (isset($itemImages[$itemIndex])) {
                $inner .= $this->gbImage($itemImages[$itemIndex], 'medium', $ratio);
            }
            if ($title) {
                $inner .= $this->gbHeading((string) $title, 3, null, $align);
            }
            if ($desc) {
                $inner .= $this->gbParagraph((string) $desc, null, $align);
            }
            if ($inner === '') {
                continue;
            }

            $columnsHtml .= "<!-- wp:column -->\n<div class=\"wp-block-column\">\n{$this->gbCard($inner)}</div>\n<!-- /wp:column -->\n\n";
        }

        if ($columnsHtml === '') {
            return '';
        }

        return "<!-- wp:columns -->\n<div class=\"wp-block-columns\">\n{$columnsHtml}</div>\n<!-- /wp:columns -->\n\n";
    }

    /**
     * A bordered/rounded card wrapper (`wp:group` with the border support
     * WordPress core has shipped since 6.1) — mirrors .card in
     * mockup-render.blade.php.
     */
    private function gbCard(string $inner): string
    {
        $border = MockupDesignSpec::token('card_border_width') . 'px';
        $radius = MockupDesignSpec::token('card_radius') . 'px';
        $padding = MockupDesignSpec::token('card_padding') . 'px';
        $attrs = json_encode(['style' => [
            'border' => ['color' => MockupDesignSpec::token('card_border_color'), 'width' => $border, 'radius' => $radius],
            'spacing' => ['padding' => ['top' => $padding, 'bottom' => $padding, 'left' => $padding, 'right' => $padding]],
        ]], JSON_UNESCAPED_SLASHES);

        // Exactly the declarations core/group's save() derives from the attrs
        // above, in its order — anything else (the old `overflow:hidden;
        // padding:16px` shorthand) is flagged invalid in the Block Editor. The
        // photo-clipping overflow lives in block-content.css instead.
        $borderColor = MockupDesignSpec::token('card_border_color');
        $style = "border-color:{$borderColor};border-width:{$border};border-radius:{$radius};"
            . "padding-top:{$padding};padding-right:{$padding};padding-bottom:{$padding};padding-left:{$padding}";

        return "<!-- wp:group {$attrs} -->\n"
            . "<div class=\"wp-block-group has-border-color\" style=\"{$style}\">\n{$inner}</div>\n"
            . "<!-- /wp:group -->\n\n";
    }

    /**
     * A `wp:image` block pointing at an `__EXITO_IMAGE:<filename>__` token,
     * wrapped in `<!--EXITO_IMG_START:filename-->...<!--EXITO_IMG_END:filename-->`
     * markers. BundleExporterService's generated importer replaces the token
     * with the real Media Library URL after uploading the photo, or deletes
     * everything between the markers if that photo never made it (generation
     * or upload failed) — so a missing photo just means one less image
     * block, never a broken `<img>`.
     */
    private function gbImage(string $filename, string $sizeSlug = 'large', ?string $imageRatio = null): string
    {
        $token = "__EXITO_IMAGE:{$filename}__";
        // The blueprint's aspect ratio, applied as the crop the approved mockup
        // used. wp:image supports aspectRatio natively, so the block still edits
        // normally in the Block Editor. The object-fit in the style must come
        // from the `scale` attribute, or core/image's save() omits it and the
        // block is flagged invalid.
        $ratioAttr = $imageRatio ? ',"aspectRatio":"' . str_replace(':', '/', $imageRatio) . '","scale":"cover"' : '';
        $ratioStyle = $imageRatio ? ' style="aspect-ratio:' . str_replace(':', '/', $imageRatio) . ';object-fit:cover"' : '';

        return "<!--EXITO_IMG_START:{$filename}-->"
            . "<!-- wp:image {\"sizeSlug\":\"{$sizeSlug}\"{$ratioAttr}} -->\n"
            . "<figure class=\"wp-block-image size-{$sizeSlug}\"><img src=\"{$token}\"{$ratioStyle} alt=\"\"/></figure>\n"
            . "<!-- /wp:image -->"
            . "<!--EXITO_IMG_END:{$filename}-->\n\n";
    }

    /** Guards against an unsupported alignment reaching Gutenberg's block attributes. */
    private function safeAlign(string $align): string
    {
        return in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';
    }

    /**
     * TEMPORARY bridge for the current blueprint schema, which carries no
     * alignment field at all: the alignment the client actually approved
     * lives only in mockup-render.blade.php's CSS, so callers pass the
     * fallback that matches what that CSS renders for the section in
     * question (see alignment notes on gbHero/gbIconRow/gbCardGrid).
     *
     * A blueprint section that already declares `text_align` wins outright,
     * so once the design schema is widened to emit it per section, every
     * fallback here becomes dead and this whole mapping can be deleted
     * without touching a single caller.
     */
    private function resolveAlign(array $section, string $fallback): string
    {
        $declared = strtolower(trim((string) ($section['text_align'] ?? '')));

        return in_array($declared, ['left', 'center', 'right'], true) ? $declared : $fallback;
    }

    /** Validates a hex color string, falling back to a safe default if GPT sent something unusable. */
    private function colorOrDefault(?string $value, string $default): string
    {
        $value = trim((string) $value);

        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) ? $value : $default;
    }

    /** Simple relative-luminance check so text placed on a colored band stays readable. */
    private function isLightColor(string $hex): bool
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return false;
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.6;
    }

    /**
     * Optional: Elementor's "classic" section > column > widget tree (real
     * `_elementor_data` shape) — not used unless the Elementor plugin is
     * later installed and a page is switched to it. See class docblock.
     */
    private function mapSectionsToElements(array $sections, array $design): array
    {
        $elements = [];
        // Same sections as renderGutenbergBlocks(): exactly those the section
        // plan renders, so switching a page to Elementor shows the approved
        // page rather than a longer or shorter one.
        $plan = $this->describeSections($sections, $design);

        foreach ($sections as $index => $section) {
            if (!is_array($section) || !($plan[$index]['rendered'] ?? false)) {
                continue;
            }

            $heading = $section['headline'] ?? $section['name'] ?? null;
            $description = $section['description'] ?? null;
            $cta = $section['cta'] ?? null;
            $items = is_array($section['items'] ?? null) ? array_values($section['items']) : [];

            $introWidgets = array_values(array_filter([
                $heading ? $this->headingWidget((string) $heading, $design) : null,
                $description ? $this->textWidget((string) $description) : null,
                $cta ? $this->buttonWidget((string) $cta, $design) : null,
            ]));

            if ($introWidgets) {
                $elements[] = $this->section([$this->column($introWidgets, 100)]);
            }

            foreach (array_chunk(array_slice($items, 0, 12), 3) as $chunk) {
                $columnSize = (int) floor(100 / max(1, count($chunk)));
                $columns = [];

                foreach ($chunk as $item) {
                    $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? null) : (string) $item;
                    $desc = is_array($item) ? ($item['description'] ?? null) : null;

                    $itemWidgets = array_values(array_filter([
                        $title ? $this->headingWidget((string) $title, $design, 'h4') : null,
                        $desc ? $this->textWidget((string) $desc) : null,
                    ]));

                    if ($itemWidgets) {
                        $columns[] = $this->column($itemWidgets, $columnSize);
                    }
                }

                if ($columns) {
                    $elements[] = $this->section($columns);
                }
            }
        }

        return $elements;
    }

    private function headingWidget(string $text, array $design, string $size = 'h2'): array
    {
        return $this->widget('heading', array_filter([
            'title' => $text,
            'header_size' => $size,
            'align' => 'center',
            'title_color' => $design['primary_color'] ?? null,
        ]));
    }

    private function textWidget(string $text): array
    {
        return $this->widget('text-editor', [
            'editor' => '<p>' . e($text) . '</p>',
            'align' => 'center',
        ]);
    }

    private function buttonWidget(string $text, array $design): array
    {
        return $this->widget('button', array_filter([
            'text' => $text,
            'align' => 'center',
            'background_color' => $design['accent_color'] ?? null,
        ]));
    }

    private function widget(string $widgetType, array $settings): array
    {
        return [
            'id' => $this->newId(),
            'elType' => 'widget',
            'widgetType' => $widgetType,
            'settings' => $settings,
            'elements' => [],
        ];
    }

    private function column(array $widgets, int $size = 100): array
    {
        return [
            'id' => $this->newId(),
            'elType' => 'column',
            'settings' => ['_column_size' => $size],
            'elements' => $widgets,
        ];
    }

    private function section(array $columns): array
    {
        return [
            'id' => $this->newId(),
            'elType' => 'section',
            'settings' => [],
            'elements' => $columns,
        ];
    }

    private function newId(): string
    {
        return substr(bin2hex(random_bytes(4)), 0, 7);
    }
}
