<?php

namespace App\Services;

use App\Support\MockupDesignSpec;
use Illuminate\Support\Str;

/**
 * Builds Claude's implementation manifest deterministically from the mockup
 * blueprint the client approved.
 *
 * This replaces a GPT-vision round-trip: approval used to send the mockup PNG
 * back to GPT and ask it to describe the design it could see in the picture,
 * even though the blueprint that produced that PNG was already in hand. That
 * step could only lose or invent detail, made approval fail outright whenever
 * OpenAI was unavailable, and spent a paid vision call re-deriving data we
 * already had.
 *
 * Nothing here interprets anything. Every value is read straight out of the
 * approved blueprint, or out of ElementorPageBuilderService::describeSections()
 * — the same plan that renders the real WordPress page — so the manifest
 * cannot describe a layout different from the one that actually ships.
 */
class BlueprintManifestService
{
    public function __construct(private ElementorPageBuilderService $pageBuilder)
    {
    }

    public function build(array $mockup): array
    {
        $design = is_array($mockup['design'] ?? null) ? $mockup['design'] : [];
        $pages = is_array($mockup['pages'] ?? null) ? array_values($mockup['pages']) : [];

        $manifestPages = [];
        $sections = [];
        $assets = [];

        foreach ($pages as $pageIndex => $page) {
            if (!is_array($page)) {
                continue;
            }

            $name = trim((string) ($page['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            // Same slug rule as ElementorPageBuilderService::buildPages(), so
            // an asset slot below lines up with the page the build creates.
            $slug = $pageIndex === 0 ? 'home' : (Str::slug($name) ?: 'page-' . ($pageIndex + 1));
            $pageAssets = is_array($mockup['assets']['pages'][$slug] ?? null) ? $mockup['assets']['pages'][$slug] : [];
            $pageSections = is_array($page['sections'] ?? null) ? array_values($page['sections']) : [];
            $plan = $this->pageBuilder->describeSections($pageSections, $design);

            $renderedCount = 0;

            foreach ($plan as $index => $sectionPlan) {
                if (!$sectionPlan['rendered']) {
                    continue;
                }

                $section = is_array($pageSections[$index] ?? null) ? $pageSections[$index] : [];
                $renderedCount++;

                $slots = $this->assetSlots($pageAssets, $sectionPlan['role'], $index);
                foreach ($slots as $slot) {
                    $assets[] = $slot;
                }

                $sections[] = [
                    'page' => $slug,
                    'order' => $index,
                    'role' => $sectionPlan['role'],
                    'type' => $this->sectionType($section, $sectionPlan['role']),
                    'heading' => $this->text($section['headline'] ?? $section['name'] ?? ''),
                    'copy' => $this->text($section['description'] ?? ''),
                    'cta' => $this->text($section['cta'] ?? '') ?: null,
                    'layout' => [
                        'variant' => $sectionPlan['layout_variant'],
                        'heading_align' => $sectionPlan['heading_align'],
                        'body_align' => $sectionPlan['body_align'],
                        'background' => $this->sectionBackground($sectionPlan['role'], $design),
                    ],
                    'items' => array_values($this->items($section)),
                    'asset_slots' => array_column($slots, 'slot'),
                ];
            }

            $manifestPages[] = [
                'name' => $name,
                'slug' => $slug,
                'rendered_sections' => $renderedCount,
                // Sections the blueprint carries but the approved PNG never
                // showed; they stay out of the build on purpose.
                'blueprint_sections' => count($pageSections),
            ];
        }

        return [
            // Makes it unmistakable downstream that this is derived data, not
            // an AI's reading of a picture.
            'source' => 'approved_blueprint',
            'design_system' => [
                'colors' => array_filter([
                    'primary' => $this->text($design['primary_color'] ?? '') ?: null,
                    'secondary' => $this->text($design['secondary_color'] ?? '') ?: null,
                    'accent' => $this->text($design['accent_color'] ?? '') ?: null,
                    'section_band' => MockupDesignSpec::token('section_band_color'),
                ]),
                'typography' => array_filter([
                    'heading_font' => $this->text($design['font_heading'] ?? '') ?: null,
                    'body_font' => $this->text($design['font_body'] ?? '') ?: null,
                ]),
                'layout' => [
                    'variant' => $this->text($design['layout_variant'] ?? '') ?: 'split-right',
                ],
                'style' => $this->text($design['style'] ?? ''),
            ],
            'navigation' => [
                'items' => array_column($manifestPages, 'name'),
                'cta' => $this->text($mockup['global_cta'] ?? ''),
            ],
            'pages' => $manifestPages,
            'sections' => $sections,
            'assets' => $assets,
            'responsive_rules' => [
                // Not invented: this is the breakpoint WordPress core's own
                // wp-block-columns stylesheet uses, and every multi-column
                // part of the page is built from that block.
                'columns_stack_below' => '782px',
            ],
            'content' => [
                'website_concept' => $this->text($mockup['website_concept'] ?? ''),
                'global_cta' => $this->text($mockup['global_cta'] ?? ''),
                'seo' => is_array($mockup['seo'] ?? null) ? $mockup['seo'] : [],
            ],
        ];
    }

    /** Only the hero and the showcase band get a full-bleed background in the approved PNG. */
    private function sectionBackground(string $role, array $design): ?string
    {
        if ($role === 'card_grid') {
            return MockupDesignSpec::token('section_band_color');
        }

        return $role === 'hero' ? ($this->text($design['primary_color'] ?? '') ?: null) : null;
    }

    /**
     * The assets frozen onto this candidate at mockup time (see
     * MockupAssetService), reported verbatim — path, required flag and origin.
     *
     * Nothing is invented or re-derived here any more. The manifest used to
     * guess filenames from a naming convention and label every one of them
     * "generated at build", because the build really did regenerate them; the
     * build now ships these exact files.
     *
     * @return array<int, array{slot:string, path:string, required:bool, source:string}>
     */
    private function assetSlots(array $pageAssets, string $role, int $sectionIndex): array
    {
        if ($role === 'hero') {
            $hero = $pageAssets['hero'] ?? null;

            return is_array($hero) ? [$this->assetEntry($hero)] : [];
        }

        if ($role !== 'card_grid') {
            return [];
        }

        $items = $pageAssets['sections'][$sectionIndex]['items'] ?? [];

        return array_values(array_map(
            fn (array $item) => $this->assetEntry($item),
            array_filter(is_array($items) ? $items : [], 'is_array')
        ));
    }

    /** @return array{slot:string, path:string, required:bool, source:string} */
    private function assetEntry(array $asset): array
    {
        return [
            'slot' => $this->text($asset['slot'] ?? ''),
            'path' => $this->text($asset['path'] ?? ''),
            'required' => (bool) ($asset['required'] ?? false),
            'source' => $this->text($asset['source'] ?? '') ?: 'unknown',
        ];
    }

    private function sectionType(array $section, string $role): string
    {
        $declared = strtolower($this->text($section['type'] ?? ''));

        return $declared !== '' ? $declared : $role;
    }

    /** @return array<int, array{title:string, description:string}> keyed by the item's original index. */
    private function items(array $section): array
    {
        $items = is_array($section['items'] ?? null) ? array_values($section['items']) : [];
        $normalized = [];

        foreach ($items as $index => $item) {
            $title = $this->text(is_array($item) ? ($item['title'] ?? $item['name'] ?? '') : $item);
            if ($title === '') {
                continue;
            }

            $normalized[$index] = [
                'title' => $title,
                'description' => $this->text(is_array($item) ? ($item['description'] ?? '') : ''),
            ];
        }

        return $normalized;
    }

    /** Blueprint text fields are normalized upstream; this just guards the odd non-string. */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
