<?php

namespace App\Support;

/**
 * Turns a blueprint section into concrete rendering decisions.
 *
 * The designer AI now chooses a composition by name rather than only colours
 * and fonts, but it is never allowed to emit HTML or CSS. Every name it can
 * pick is listed here, and every name resolves to a bounded set of decisions —
 * layout family, image position, widths, spacing, ratio, card treatment — that
 * the Blade mockup and the Gutenberg page body both read.
 *
 * This is the same principle as MockupDesignSpec (one copy of the numbers) and
 * describeSections() (one copy of the structural decisions): if the mockup and
 * WordPress resolved compositions separately, they would drift, which is the
 * whole class of bug this project has been unwinding.
 *
 * Blueprints written before compositions existed carry only a `layout_variant`
 * of split-right / split-left / overlay-bg. Those still resolve, to exactly the
 * arrangement they always rendered — see legacyHeroComposition().
 */
final class CompositionSpec
{
    public const HERO_COMPOSITIONS = [
        'split',
        'asymmetric_split',
        'fullscreen_image',
        'background_image',
        'editorial',
        'overlapping',
        'contained',
        'centered_minimal',
    ];

    public const SECTION_COMPOSITIONS = [
        'editorial_text_image',
        'alternating_media',
        'feature_grid',
        'asymmetric_cards',
        'standard_cards',
        'stats_band',
        'testimonial_grid',
        'gallery',
        'logo_showcase',
        'faq',
        'cta',
        'team',
        'pricing',
    ];

    public const CONTAINERS = ['narrow', 'standard', 'wide', 'full'];
    public const HEADING_SCALES = ['display-xl', 'display-lg', 'h1', 'h2', 'h3'];
    public const SPACING = ['compact', 'normal', 'generous', 'editorial'];
    public const RADIUS = ['none', 'small', 'medium', 'large', 'pill'];
    public const SHADOWS = ['none', 'soft', 'strong'];
    public const ALIGNMENTS = ['left', 'center', 'right'];

    /**
     * Fields that describe HOW a composition is drawn, as opposed to WHAT the
     * section says.
     *
     * A designer picks these to suit the composition it chose: centered_minimal
     * comes with text_align "center" and image_required false, asymmetric_split
     * with a 48% copy column and a 4:5 photograph. When distinctness swaps one
     * composition for another these become stale — an image_required of false
     * left over from a photo-free hero would tell the asset step that the new
     * photo-led hero needs no photograph, and the candidate would render with an
     * empty image slot the client never agreed to.
     *
     * So a SYSTEM-initiated composition change drops all of them and lets the
     * new composition's own defaults apply. Content — name, type, headline,
     * description, cta, items — is never touched.
     */
    public const COMPOSITION_DERIVED_KEYS = [
        'image_position',
        'image_ratio',
        'image_required',
        'content_width',
        'container',
        'heading_scale',
        'text_align',
        'spacing_top',
        'spacing_bottom',
        'card_treatment',
        'columns',
    ];

    /**
     * What a section's content actually IS, judged from the shape of its own
     * data — the keys its items carry, and what the section calls itself.
     *
     * Grouping compositions by "does it use photographs" alone was too coarse:
     * it made an FAQ list, a pricing table and a logo strip mutually
     * interchangeable just because none of them carry photos. Swapping a band of
     * {title, description} features into a pricing table produces a layout whose
     * semantics its content cannot fill.
     */
    public static function contentShape(array $section, string $role): string
    {
        $items = is_array($section['items'] ?? null) ? array_values($section['items']) : [];

        if (!$items) {
            return 'cta';
        }

        $label = strtolower(trim(
            (string) ($section['type'] ?? '') . ' ' .
            (string) ($section['name'] ?? '') . ' ' .
            (string) ($section['headline'] ?? '')
        ));

        $keys = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $keys = array_merge($keys, array_map('strtolower', array_keys($item)));
            }
        }

        $hasKey = fn (array $names) => (bool) array_intersect($names, $keys);
        $named = fn (array $words) => (bool) array_filter($words, fn ($word) => str_contains($label, $word));

        return match (true) {
            $hasKey(['question', 'answer', 'pertanyaan', 'jawaban']) || $named(['faq', 'tanya']) => 'faq',
            $hasKey(['price', 'harga', 'monthly', 'amount']) || $named(['pricing', 'harga', 'paket']) => 'pricing',
            $hasKey(['quote', 'testimonial', 'author', 'reviewer', 'ulasan']) || $named(['testimoni', 'ulasan']) => 'testimonials',
            $hasKey(['role', 'position', 'jabatan']) || $named(['team', 'tim kami', 'staff']) => 'team',
            $hasKey(['value', 'number', 'stat', 'angka', 'count']) || $named(['statistik', 'angka', 'pencapaian']) => 'stats',
            $named(['logo', 'klien kami', 'partner', 'mitra']) => 'logos',
            $named(['galeri', 'gallery', 'portfolio', 'portofolio']) => 'gallery',
            $role === 'icon_band' => 'feature_items',
            default => 'card_items',
        };
    }

    /**
     * The compositions a section could legitimately be re-rendered as.
     *
     * Two filters, both deliberately conservative:
     * 1. the content shape above — a feature band never becomes an FAQ;
     * 2. whether the section is photographed — a swap must not start or stop
     *    consuming photographs, because the asset manifest was built for the
     *    section as it stands.
     *
     * Returning just the current composition means "leave it alone". Two
     * candidates sharing a section is a far smaller problem than a section
     * whose layout its content cannot fill.
     *
     * @return array<int, string>
     */
    public static function compatibleSectionCompositions(string $role, array $section): array
    {
        $current = self::oneOf(
            $section['composition'] ?? null,
            self::SECTION_COMPOSITIONS,
            $role === 'icon_band' ? 'feature_grid' : 'standard_cards'
        );

        $allowed = match (self::contentShape($section, $role)) {
            'faq' => ['faq'],
            'pricing' => ['pricing'],
            'testimonials' => ['testimonial_grid'],
            'logos' => ['logo_showcase'],
            'cta' => ['cta'],
            'team' => ['team', 'standard_cards'],
            'gallery' => ['gallery', 'standard_cards'],
            'stats' => ['stats_band', 'feature_grid'],
            'feature_items' => ['feature_grid', 'stats_band'],
            default => ['standard_cards', 'asymmetric_cards', 'feature_grid'],
        };

        // Keep the section's photographic behaviour exactly as it is.
        $sameMedia = array_values(array_filter(
            $allowed,
            fn (string $composition) => self::usesPhotos($composition) === self::usesPhotos($current)
        ));

        if (!in_array($current, $sameMedia, true)) {
            array_unshift($sameMedia, $current);
        }

        return $sameMedia;
    }

    /** Container token -> max content width in px. `full` means edge to edge. */
    private const CONTAINER_WIDTHS = [
        'narrow' => 860,
        'standard' => 1140,
        'wide' => 1292,
        'full' => 0,
    ];

    private const SPACING_PX = [
        'compact' => 40,
        'normal' => 60,
        'generous' => 96,
        'editorial' => 128,
    ];

    private const HEADING_PX = [
        'display-xl' => 68,
        'display-lg' => 56,
        'h1' => 50,
        'h2' => 34,
        'h3' => 24,
    ];

    private const RADIUS_PX = [
        'none' => 0,
        'small' => 6,
        'medium' => 14,
        'large' => 24,
        'pill' => 999,
    ];

    private const RATIOS = ['1:1', '4:3', '3:4', '4:5', '5:4', '16:9', '3:2'];

    /**
     * How each hero composition behaves. `family` is what the renderers switch
     * on; everything else is the composition's own default, overridable by the
     * blueprint section.
     */
    private const HERO_RULES = [
        'split' => ['family' => 'split', 'image_position' => 'right', 'content_width' => 50, 'image_required' => true, 'container' => 'standard', 'heading_scale' => 'h1', 'image_ratio' => '4:3', 'spacing' => 80],
        'asymmetric_split' => ['family' => 'split', 'image_position' => 'right', 'content_width' => 48, 'image_required' => true, 'container' => 'wide', 'heading_scale' => 'display-xl', 'image_ratio' => '4:5', 'offset_image' => true, 'spacing' => 96],
        'fullscreen_image' => ['family' => 'stacked_media', 'image_position' => 'above', 'content_width' => 100, 'image_required' => true, 'container' => 'full', 'heading_scale' => 'display-lg', 'image_ratio' => '16:9', 'spacing' => 0],
        'background_image' => ['family' => 'overlay', 'image_position' => 'background', 'content_width' => 100, 'image_required' => true, 'container' => 'narrow', 'heading_scale' => 'display-lg', 'text_align' => 'center', 'image_ratio' => '16:9', 'spacing' => 0],
        'editorial' => ['family' => 'stacked_media', 'image_position' => 'below', 'content_width' => 100, 'image_required' => false, 'container' => 'narrow', 'heading_scale' => 'display-xl', 'image_ratio' => '3:2', 'spacing' => 128],
        'overlapping' => ['family' => 'split', 'image_position' => 'right', 'content_width' => 52, 'image_required' => true, 'container' => 'wide', 'heading_scale' => 'display-lg', 'image_ratio' => '3:4', 'overlap' => true, 'spacing' => 96],
        'contained' => ['family' => 'split', 'image_position' => 'right', 'content_width' => 50, 'image_required' => true, 'container' => 'standard', 'heading_scale' => 'h1', 'image_ratio' => '4:3', 'inset_card' => true, 'spacing' => 72],
        'centered_minimal' => ['family' => 'centered', 'image_position' => 'none', 'content_width' => 100, 'image_required' => false, 'container' => 'narrow', 'heading_scale' => 'display-lg', 'text_align' => 'center', 'spacing' => 120],
    ];

    /**
     * Section compositions. `photo_slots` is what decides whether this section
     * consumes item photographs at all.
     */
    private const SECTION_RULES = [
        'standard_cards' => ['family' => 'grid', 'columns' => 3, 'card_treatment' => 'bordered', 'photo_slots' => true, 'image_ratio' => '4:3'],
        'asymmetric_cards' => ['family' => 'grid', 'columns' => 3, 'card_treatment' => 'shadowed', 'photo_slots' => true, 'image_ratio' => '3:4', 'feature_first' => true],
        'feature_grid' => ['family' => 'grid', 'columns' => 3, 'card_treatment' => 'plain', 'photo_slots' => false],
        'gallery' => ['family' => 'grid', 'columns' => 4, 'card_treatment' => 'flush', 'photo_slots' => true, 'image_ratio' => '1:1'],
        'team' => ['family' => 'grid', 'columns' => 4, 'card_treatment' => 'plain', 'photo_slots' => true, 'image_ratio' => '1:1'],
        'pricing' => ['family' => 'grid', 'columns' => 3, 'card_treatment' => 'bordered', 'photo_slots' => false],
        'testimonial_grid' => ['family' => 'grid', 'columns' => 3, 'card_treatment' => 'shadowed', 'photo_slots' => false],
        'logo_showcase' => ['family' => 'grid', 'columns' => 5, 'card_treatment' => 'flush', 'photo_slots' => false],
        'stats_band' => ['family' => 'band', 'columns' => 4, 'card_treatment' => 'plain', 'photo_slots' => false, 'text_align' => 'center'],
        'faq' => ['family' => 'list', 'columns' => 1, 'card_treatment' => 'plain', 'photo_slots' => false, 'text_align' => 'left'],
        'editorial_text_image' => ['family' => 'media', 'columns' => 1, 'card_treatment' => 'plain', 'photo_slots' => true, 'image_position' => 'right', 'image_ratio' => '4:5', 'text_align' => 'left'],
        'alternating_media' => ['family' => 'media', 'columns' => 1, 'card_treatment' => 'plain', 'photo_slots' => true, 'image_position' => 'left', 'image_ratio' => '4:3', 'text_align' => 'left'],
        'cta' => ['family' => 'band', 'columns' => 1, 'card_treatment' => 'plain', 'photo_slots' => false, 'text_align' => 'center'],
    ];

    /** The layout family a hero composition belongs to, for picking a genuinely different alternative. */
    public static function heroFamily(string $composition): string
    {
        return self::HERO_RULES[$composition]['family'] ?? 'split';
    }

    /** Whether a section composition consumes item photographs. */
    public static function usesPhotos(string $composition): bool
    {
        return (bool) (self::SECTION_RULES[$composition]['photo_slots'] ?? false);
    }

    /**
     * Global design decisions, with every token validated and a legacy-safe
     * default so an old blueprint resolves exactly as it always did.
     */
    public static function globalDesign(array $design): array
    {
        $container = self::oneOf($design['container'] ?? null, self::CONTAINERS, 'standard');
        $spacing = self::oneOf($design['section_spacing'] ?? null, self::SPACING, 'normal');
        $radius = self::oneOf($design['radius'] ?? null, self::RADIUS, 'medium');

        return [
            'visual_direction' => is_string($design['visual_direction'] ?? null) ? $design['visual_direction'] : '',
            'density' => self::oneOf($design['density'] ?? null, self::SPACING, 'normal'),
            'container' => $container,
            'container_width' => self::CONTAINER_WIDTHS[$container],
            'section_spacing' => $spacing,
            'section_spacing_px' => self::SPACING_PX[$spacing],
            'radius' => $radius,
            'radius_px' => self::RADIUS_PX[$radius],
            'shadow' => self::oneOf($design['shadow'] ?? null, self::SHADOWS, 'soft'),
            'image_treatment' => self::oneOf($design['image_treatment'] ?? null, ['square', 'rounded', 'circle'], 'rounded'),
            'button_treatment' => self::oneOf($design['button_treatment'] ?? null, ['solid', 'outline', 'pill', 'link'], 'solid'),
            'typography_scale' => self::oneOf($design['typography_scale'] ?? null, ['compact', 'standard', 'expressive'], 'standard'),
        ];
    }

    /**
     * Resolves one section into rendering decisions.
     *
     * @param string $role 'hero', 'icon_band' or 'card_grid' — the structural
     *                     role describeSections() assigned to this section.
     */
    public static function resolve(array $section, array $design, string $role): array
    {
        return $role === 'hero'
            ? self::resolveHero($section, $design)
            : self::resolveSection($section, $design, $role);
    }

    private static function resolveHero(array $section, array $design): array
    {
        $composition = self::oneOf(
            $section['composition'] ?? null,
            self::HERO_COMPOSITIONS,
            self::legacyHeroComposition($design)
        );

        $rules = self::HERO_RULES[$composition];
        $global = self::globalDesign($design);

        $contentWidth = self::percent($section['content_width'] ?? null, $rules['content_width']);
        $container = self::oneOf($section['container'] ?? null, self::CONTAINERS, $rules['container']);

        return [
            'composition' => $composition,
            'family' => $rules['family'],
            'image_position' => self::oneOf(
                $section['image_position'] ?? null,
                ['left', 'right', 'background', 'above', 'below', 'none'],
                self::legacyImagePosition($design, $rules['image_position'], $section)
            ),
            'image_required' => self::bool($section['image_required'] ?? null, $rules['image_required']),
            'image_ratio' => self::oneOf($section['image_ratio'] ?? null, self::RATIOS, $rules['image_ratio'] ?? '4:3'),
            'content_width' => $contentWidth,
            'image_width' => 100 - $contentWidth,
            'text_align' => self::oneOf($section['text_align'] ?? null, self::ALIGNMENTS, $rules['text_align'] ?? 'left'),
            'heading_scale' => self::oneOf($section['heading_scale'] ?? null, self::HEADING_SCALES, $rules['heading_scale']),
            'heading_px' => self::HEADING_PX[self::oneOf($section['heading_scale'] ?? null, self::HEADING_SCALES, $rules['heading_scale'])],
            'container' => $container,
            'container_width' => self::CONTAINER_WIDTHS[$container],
            'spacing_top' => self::spacingPx($section['spacing_top'] ?? null, $global['section_spacing'], $rules['spacing']),
            'spacing_bottom' => self::spacingPx($section['spacing_bottom'] ?? null, $global['section_spacing'], $rules['spacing']),
            'radius_px' => $global['radius_px'],
            'shadow' => $global['shadow'],
            'offset_image' => (bool) ($rules['offset_image'] ?? false),
            'overlap' => (bool) ($rules['overlap'] ?? false),
            'inset_card' => (bool) ($rules['inset_card'] ?? false),
            'columns' => 1,
            'card_treatment' => 'plain',
            'photo_slots' => false,
        ];
    }

    private static function resolveSection(array $section, array $design, string $role): array
    {
        $composition = self::oneOf(
            $section['composition'] ?? null,
            self::SECTION_COMPOSITIONS,
            $role === 'icon_band' ? 'feature_grid' : 'standard_cards'
        );

        $rules = self::SECTION_RULES[$composition];
        $global = self::globalDesign($design);
        $container = self::oneOf($section['container'] ?? null, self::CONTAINERS, $global['container']);
        $itemCount = is_array($section['items'] ?? null) ? count($section['items']) : 0;

        return [
            'composition' => $composition,
            'family' => $rules['family'],
            'image_position' => self::oneOf($section['image_position'] ?? null, ['left', 'right', 'none'], $rules['image_position'] ?? 'none'),
            'image_required' => self::bool($section['image_required'] ?? null, false),
            'image_ratio' => self::oneOf($section['image_ratio'] ?? null, self::RATIOS, $rules['image_ratio'] ?? '4:3'),
            'content_width' => 100,
            'image_width' => 0,
            'text_align' => self::oneOf($section['text_align'] ?? null, self::ALIGNMENTS, $rules['text_align'] ?? 'left'),
            'heading_scale' => self::oneOf($section['heading_scale'] ?? null, self::HEADING_SCALES, 'h2'),
            'heading_px' => self::HEADING_PX[self::oneOf($section['heading_scale'] ?? null, self::HEADING_SCALES, 'h2')],
            'container' => $container,
            'container_width' => self::CONTAINER_WIDTHS[$container],
            'spacing_top' => self::spacingPx($section['spacing_top'] ?? null, $global['section_spacing']),
            'spacing_bottom' => self::spacingPx($section['spacing_bottom'] ?? null, $global['section_spacing']),
            'radius_px' => $global['radius_px'],
            'shadow' => $global['shadow'],
            'offset_image' => false,
            'overlap' => false,
            'inset_card' => false,
            // Never promise more columns than there are items to fill them.
            'columns' => max(1, min(self::columns($section['columns'] ?? null, $rules['columns']), $itemCount ?: $rules['columns'])),
            'card_treatment' => self::oneOf($section['card_treatment'] ?? null, ['plain', 'bordered', 'shadowed', 'flush'], $rules['card_treatment']),
            'photo_slots' => (bool) $rules['photo_slots'],
            'feature_first' => (bool) ($rules['feature_first'] ?? false),
        ];
    }

    /**
     * A blueprint from before compositions existed says only split-right,
     * split-left or overlay-bg. Those map to exactly the arrangement they have
     * always rendered, so an old approved project rebuilds unchanged.
     */
    private static function legacyHeroComposition(array $design): string
    {
        return ($design['layout_variant'] ?? null) === 'overlay-bg' ? 'background_image' : 'split';
    }

    /** split-left is the only legacy variant that mirrors the photo to the left. */
    private static function legacyImagePosition(array $design, string $default, array $section): string
    {
        if (isset($section['composition']) || !isset($design['layout_variant'])) {
            return $default;
        }

        return match ($design['layout_variant']) {
            'split-left' => 'left',
            'overlay-bg' => 'background',
            default => 'right',
        };
    }

    /**
     * A blueprint may state spacing as a token or as raw pixels. $compositionPx
     * lets a composition keep its own vertical rhythm (an editorial hero
     * breathes; a full-bleed one has no padding at all) instead of every hero
     * inheriting the generic section spacing.
     */
    private static function spacingPx(mixed $value, string $fallbackToken, ?int $compositionPx = null): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return max(0, min(320, (int) $value));
        }

        if (is_string($value) && in_array(strtolower(trim($value)), self::SPACING, true)) {
            return self::SPACING_PX[strtolower(trim($value))];
        }

        return $compositionPx ?? self::SPACING_PX[$fallbackToken];
    }

    private static function percent(mixed $value, int $default): int
    {
        if (is_string($value) && str_ends_with(trim($value), '%')) {
            $value = trim($value, " \t%");
        }

        if (!is_numeric($value)) {
            return $default;
        }

        return max(25, min(75, (int) round((float) $value)));
    }

    private static function columns(mixed $value, int $default): int
    {
        return is_numeric($value) ? max(1, min(6, (int) $value)) : $default;
    }

    private static function bool(mixed $value, bool $default): bool
    {
        return is_bool($value) ? $value : $default;
    }

    private static function oneOf(mixed $value, array $allowed, string $default): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($value, $allowed, true) ? $value : $default;
    }
}
