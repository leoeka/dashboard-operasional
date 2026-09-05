<?php

namespace App\Services;

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
     * @param array $imageMap      SectionImageService's ['map'], keyed by the same page slugs this method
     *                             computes: page slug -> {hero?: filename, items?: {itemIndex: filename}}.
     * @return array<string, array{title:string, slug:string, html:string, elements:array}>
     *         Keyed by page slug ('home' for the first/"Home" page).
     */
    public function buildPages(array $mockupPages, array $design = [], array $imageMap = []): array
    {
        $pages = [];

        foreach ($mockupPages as $index => $page) {
            if (!is_array($page)) {
                continue;
            }

            $name = trim((string) ($page['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $slug = $index === 0 ? 'home' : (Str::slug($name) ?: 'page-' . ($index + 1));
            // array_values(): a page's `sections` list must be sequentially
            // indexed from 0 for the "index 0 = hero" convention below (and
            // in GenerateMockupGptService::pickMockupSections(), which the PNG mockup
            // renderer uses) to actually line up — GPT's JSON doesn't
            // guarantee that on decode.
            $sections = is_array($page['sections'] ?? null) ? array_values($page['sections']) : [];

            $pages[$slug] = [
                'title' => $name,
                'slug' => $slug,
                'html' => $this->renderGutenbergBlocks($sections, $imageMap[$slug] ?? [], $design),
                'elements' => $this->mapSectionsToElements($sections, $design),
            ];
        }

        return $pages;
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
     * a real URL, because the actual photo (from SectionImageService) is
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
        $altBandColor = '#F6F4F0';
        $layoutVariant = in_array($design['layout_variant'] ?? null, ['split-right', 'split-left', 'overlay-bg'], true)
            ? $design['layout_variant']
            : 'split-right';

        $heroFilename = $images['hero'] ?? null;
        $itemFilenames = $images['items'] ?? [];
        $picked = $this->pickIconPhotoIndexes($sections);

        $blocks = '';
        $itemImagesUsed = false;

        foreach ($sections as $sectionIndex => $section) {
            if (!is_array($section)) {
                continue;
            }

            $heading = $section['headline'] ?? $section['name'] ?? null;
            $description = $section['description'] ?? null;
            $cta = $section['cta'] ?? null;
            $items = is_array($section['items'] ?? null) ? array_values($section['items']) : [];

            if ($sectionIndex === 0) {
                $blocks .= $this->gbHero(
                    $heading ? (string) $heading : '',
                    $description ? (string) $description : '',
                    $cta ? (string) $cta : null,
                    $heroFilename,
                    $primary,
                    $accent,
                    $layoutVariant
                );
                continue;
            }

            $isIconSection = $sectionIndex === $picked['icon'];
            $isPhotoSection = $sectionIndex === $picked['photo'];

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

            $inner = '';
            if ($heading) {
                $inner .= $this->gbHeading((string) $heading, 2, $primary);
            }
            if ($description) {
                $inner .= $this->gbParagraph((string) $description);
            }

            if ($items) {
                if ($isIconSection) {
                    $inner .= $this->gbIconRow($items, $accent);
                } else {
                    // Only the designated photo section actually consumes the
                    // generated photo budget — matches SectionImageService.
                    $imagesForThisGrid = $itemImagesUsed ? [] : $itemFilenames;
                    $itemImagesUsed = $itemImagesUsed || (bool) $itemFilenames;
                    $inner .= $this->gbCardGrid($items, $imagesForThisGrid);
                }
            }

            if ($cta) {
                $inner .= $this->gbButton((string) $cta, $accent);
            }

            // Only the designated photo/showcase section gets the neutral
            // "alt" band, matching the PNG 1:1.
            $blocks .= $isPhotoSection ? $this->gbSection($inner, $altBandColor) : $this->gbSection($inner);
        }

        return trim($blocks);
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
    private function gbHero(string $heading, string $description, ?string $cta, ?string $heroImage, string $primary, string $accent, string $layoutVariant = 'split-right'): string
    {
        $textColor = $this->isLightColor($primary) ? '#1c1a17' : '#ffffff';

        $copy = '';
        if ($heading !== '') {
            $copy .= $this->gbHeading($heading, 1, $textColor);
        }
        if ($description !== '') {
            $copy .= $this->gbParagraph($description, $textColor);
        }
        if ($cta) {
            $copy .= $this->gbButton($cta, '#ffffff', $primary);
        }

        if ($copy === '') {
            return '';
        }

        if ($heroImage && $layoutVariant === 'overlay-bg') {
            return $this->gbCoverHero($copy, $heroImage, $primary);
        }

        if ($heroImage) {
            $copyWidth = $layoutVariant === 'split-left' ? '45%' : '55%';
            $imageWidth = $layoutVariant === 'split-left' ? '55%' : '45%';
            $imageBlock = $this->gbImage($heroImage, 'large');
            $colWidthsCopy = json_encode(['width' => $copyWidth], JSON_UNESCAPED_SLASHES);
            $colWidthsImg = json_encode(['width' => $imageWidth], JSON_UNESCAPED_SLASHES);
            $copyColumn = "<!-- wp:column {$colWidthsCopy} -->\n<div class=\"wp-block-column\" style=\"flex-basis:{$copyWidth}\">\n{$copy}</div>\n<!-- /wp:column -->\n\n";
            $imageColumn = "<!-- wp:column {$colWidthsImg} -->\n<div class=\"wp-block-column\" style=\"flex-basis:{$imageWidth}\">\n{$imageBlock}</div>\n<!-- /wp:column -->\n\n";
            $columns = $layoutVariant === 'split-left' ? ($imageColumn . $copyColumn) : ($copyColumn . $imageColumn);
            $inner = "<!-- wp:columns -->\n<div class=\"wp-block-columns\">\n{$columns}</div>\n<!-- /wp:columns -->\n\n";
        } else {
            $inner = $copy;
        }

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
            'minHeight' => 480,
            'contentPosition' => 'center center',
        ], JSON_UNESCAPED_SLASHES);

        // The marker span wraps ONLY the <img> tag (same convention as
        // gbImage()) — not the whole wp:cover block — so a failed/missing
        // photo just leaves a solid-color cover band (dim span still has
        // the brand color as its background) instead of losing the
        // headline/description/CTA that live inside the same block.
        return "<!-- wp:cover {$attrs} -->\n"
            . "<div class=\"wp-block-cover\" style=\"min-height:480px\">"
            . "<span aria-hidden=\"true\" class=\"wp-block-cover__background has-background-dim-60 has-background-dim\" style=\"background-color:{$primary}\"></span>"
            . "<!--EXITO_IMG_START:{$heroImage}-->"
            . "<img class=\"wp-block-cover__image-background\" alt=\"\" src=\"{$token}\" data-object-fit=\"cover\"/>"
            . "<!--EXITO_IMG_END:{$heroImage}-->"
            . "<div class=\"wp-block-cover__inner-container\">\n{$innerCopy}</div>"
            . "</div>\n<!-- /wp:cover -->\n\n";
    }

    private function gbHeading(string $text, int $level = 2, ?string $color = null): string
    {
        $escaped = e($text);
        $attrs = ['level' => $level, 'textAlign' => 'center'];
        $class = 'wp-block-heading has-text-align-center';
        $style = '';

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

    private function gbParagraph(string $text, ?string $color = null): string
    {
        $escaped = e($text);
        $attrs = ['align' => 'center'];
        $class = 'has-text-align-center';
        $style = '';

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

    private function gbButton(string $text, ?string $bgColor = null, ?string $textColor = null): string
    {
        $escaped = e($text);
        if ($bgColor && !$textColor) {
            $textColor = '#ffffff';
        }

        $style = [];
        $classes = ['wp-block-button__link', 'wp-element-button'];
        // Without this, the button renders as a plain underlined hyperlink
        // instead of a solid button — Gutenberg's own editor CSS strips the
        // underline via its stylesheet, but that stylesheet isn't loaded on
        // the live site unless a theme explicitly enqueues it, so a
        // generated theme's own CSS is the only thing that can do it. Set
        // inline rather than relying on that CSS existing/being correct.
        $inlineStyle = 'text-decoration:none;display:inline-block;';

        if ($bgColor) {
            $style['color']['background'] = $bgColor;
            $classes[] = 'has-background';
            $inlineStyle .= 'background-color:' . $bgColor . ';';
        }
        if ($textColor) {
            $style['color']['text'] = $textColor;
            $classes[] = 'has-text-color';
            $inlineStyle .= 'color:' . $textColor . ';';
        }

        $innerAttrs = $style ? json_encode(['style' => $style], JSON_UNESCAPED_SLASHES) : '';
        $styleAttr = $inlineStyle ? ' style="' . $inlineStyle . '"' : '';
        $classAttr = implode(' ', $classes);

        return "<!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"center\"}} -->\n"
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
    private function gbIconRow(array $items, string $accent): string
    {
        $columnsHtml = '';

        foreach (array_slice($items, 0, 4) as $index => $item) {
            $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? null) : (string) $item;
            $desc = is_array($item) ? ($item['description'] ?? null) : null;

            $inner = $this->gbHeading((string) ($index + 1), 4, $accent);
            if ($title) {
                $inner .= $this->gbHeading((string) $title, 3);
            }
            if ($desc) {
                $inner .= $this->gbParagraph((string) $desc);
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
     * @param array $itemImages original item index => generated photo filename (from SectionImageService).
     */
    private function gbCardGrid(array $items, array $itemImages = []): string
    {
        $columnsHtml = '';

        foreach (array_slice($items, 0, 4, true) as $itemIndex => $item) {
            $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? null) : (string) $item;
            $desc = is_array($item) ? ($item['description'] ?? null) : null;

            $inner = '';
            if (isset($itemImages[$itemIndex])) {
                $inner .= $this->gbImage($itemImages[$itemIndex], 'medium');
            }
            if ($title) {
                $inner .= $this->gbHeading((string) $title, 3);
            }
            if ($desc) {
                $inner .= $this->gbParagraph((string) $desc);
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
        $attrs = json_encode(['style' => ['border' => ['color' => '#eae5dd', 'width' => '1px', 'radius' => '14px'], 'spacing' => ['padding' => ['top' => '16px', 'bottom' => '16px', 'left' => '16px', 'right' => '16px']]]], JSON_UNESCAPED_SLASHES);

        return "<!-- wp:group {$attrs} -->\n"
            . "<div class=\"wp-block-group has-border-color\" style=\"border-color:#eae5dd;border-width:1px;border-radius:14px;overflow:hidden;padding:16px\">\n{$inner}</div>\n"
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
    private function gbImage(string $filename, string $sizeSlug = 'large'): string
    {
        $token = "__EXITO_IMAGE:{$filename}__";

        return "<!--EXITO_IMG_START:{$filename}-->"
            . "<!-- wp:image {\"sizeSlug\":\"{$sizeSlug}\",\"align\":\"center\"} -->\n"
            . "<figure class=\"wp-block-image aligncenter size-{$sizeSlug}\"><img src=\"{$token}\" alt=\"\"/></figure>\n"
            . "<!-- /wp:image -->"
            . "<!--EXITO_IMG_END:{$filename}-->\n\n";
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
        // Same restriction as renderGutenbergBlocks(): only the sections
        // actually shown in the approved PNG (hero + the picked icon/photo
        // sections), so switching a page to Elementor shows the same
        // approved-looking page instead of a much longer one with every
        // section from the mockup blueprint.
        $picked = $this->pickIconPhotoIndexes($sections);

        foreach ($sections as $index => $section) {
            if (!is_array($section)) {
                continue;
            }
            if ($index !== 0 && $index !== $picked['icon'] && $index !== $picked['photo']) {
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
