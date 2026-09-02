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
            $sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];

            $pages[$slug] = [
                'title' => $name,
                'slug' => $slug,
                'html' => $this->renderGutenbergBlocks($sections, $imageMap[$slug] ?? []),
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
     * Image blocks reference a `__EXITO_IMAGE:<filename>__` token instead of
     * a real URL, because the actual photo (from SectionImageService) is
     * only uploaded to the Media Library at plugin-activation time inside
     * WordPress — see BundleExporterService, which replaces these tokens
     * with the real attachment URL (or strips the block entirely if that
     * particular photo failed to generate/upload).
     */
    private function renderGutenbergBlocks(array $sections, array $images = []): string
    {
        $blocks = '';
        $heroFilename = $images['hero'] ?? null;
        $itemFilenames = $images['items'] ?? [];
        $itemImagesUsed = false;

        foreach ($sections as $sectionIndex => $section) {
            if (!is_array($section)) {
                continue;
            }

            $heading = $section['headline'] ?? $section['name'] ?? null;
            $description = $section['description'] ?? null;
            $cta = $section['cta'] ?? null;
            $items = is_array($section['items'] ?? null) ? array_values($section['items']) : [];

            if ($heading) {
                $blocks .= $this->gbHeading((string) $heading, 2);
            }
            if ($sectionIndex === 0 && $heroFilename) {
                $blocks .= $this->gbImage($heroFilename, 'large');
            }
            if ($description) {
                $blocks .= $this->gbParagraph((string) $description);
            }

            if ($items) {
                // Only the first items-bearing section on the page gets photos
                // (matches the budget SectionImageService generated against).
                $imagesForThisGrid = $itemImagesUsed ? [] : $itemFilenames;
                $itemImagesUsed = $itemImagesUsed || (bool) $itemFilenames;

                foreach (array_chunk(array_slice($items, 0, 12), 3, true) as $chunk) {
                    $blocks .= $this->gbColumns($chunk, $imagesForThisGrid);
                }
            }

            if ($cta) {
                $blocks .= $this->gbButton((string) $cta);
            }

            $blocks .= $this->gbSeparator();
        }

        return trim($blocks);
    }

    private function gbHeading(string $text, int $level = 2): string
    {
        $text = e($text);

        return "<!-- wp:heading {\"level\":{$level},\"textAlign\":\"center\"} -->\n"
            . "<h{$level} class=\"wp-block-heading has-text-align-center\">{$text}</h{$level}>\n"
            . "<!-- /wp:heading -->\n\n";
    }

    private function gbParagraph(string $text): string
    {
        $text = e($text);

        return "<!-- wp:paragraph {\"align\":\"center\"} -->\n"
            . "<p class=\"has-text-align-center\">{$text}</p>\n"
            . "<!-- /wp:paragraph -->\n\n";
    }

    private function gbButton(string $text): string
    {
        $text = e($text);

        return "<!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"center\"}} -->\n"
            . "<div class=\"wp-block-buttons\"><!-- wp:button -->\n"
            . "<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"#\">{$text}</a></div>\n"
            . "<!-- /wp:button --></div>\n"
            . "<!-- /wp:buttons -->\n\n";
    }

    private function gbSeparator(): string
    {
        return "<!-- wp:separator {\"opacity\":\"css\"} -->\n<hr class=\"wp-block-separator has-css-opacity\"/>\n<!-- /wp:separator -->\n\n";
    }

    /**
     * @param array $items      up to 3 mockup section items (original item index as key), rendered as one Gutenberg columns row.
     * @param array $itemImages original item index => generated photo filename (from SectionImageService).
     */
    private function gbColumns(array $items, array $itemImages = []): string
    {
        $columnsHtml = '';

        foreach ($items as $itemIndex => $item) {
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

            $columnsHtml .= "<!-- wp:column -->\n<div class=\"wp-block-column\">\n{$inner}</div>\n<!-- /wp:column -->\n\n";
        }

        if ($columnsHtml === '') {
            return '';
        }

        return "<!-- wp:columns -->\n<div class=\"wp-block-columns\">\n{$columnsHtml}</div>\n<!-- /wp:columns -->\n\n";
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

    /**
     * Optional: Elementor's "classic" section > column > widget tree (real
     * `_elementor_data` shape) — not used unless the Elementor plugin is
     * later installed and a page is switched to it. See class docblock.
     */
    private function mapSectionsToElements(array $sections, array $design): array
    {
        $elements = [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
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
