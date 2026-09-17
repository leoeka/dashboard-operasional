<?php

namespace App\Support;

/**
 * The one place the approved mockup's visual measurements live.
 *
 * These numbers used to exist in three hand-synced copies: the CSS in
 * resources/views/pdf/mockup-render.blade.php (what the client actually sees
 * and approves), the Gutenberg block attributes in ElementorPageBuilderService
 * (what ships as the page body), and the prose measurements in
 * ClaudeWordPressBuilderService::chromeDesignSpec() (what Claude is told to
 * build header.php/footer.php/style.css from). Three copies of the same number
 * is three chances to drift, and the chrome copy in particular was already a
 * paraphrase ("~94px", "~32px gap") rather than the value itself.
 *
 * This is deliberately a flat bag of measurements, not a design system: it
 * holds only values that more than one of those three consumers needs. Brand
 * choices that vary per project — colors, fonts — are NOT here; they come from
 * the blueprint's own design tokens. The only colors here are the fixed chrome
 * neutrals that are the same on every project regardless of palette.
 *
 * Changing a value here changes the mockup, the built page, and Claude's brief
 * together, which is the entire point.
 */
final class MockupDesignSpec
{
    /**
     * @return array<string, int|string>
     */
    public static function tokens(): array
    {
        return [
            // ---- page shell ----
            'container_width' => 1440,
            'gutter' => 74,

            // ---- header / nav ----
            'nav_height' => 94,
            'nav_border' => 'rgba(0,0,0,.06)',
            'brand_font_size' => 22,
            'nav_link_gap' => 32,
            'nav_link_font_size' => 15,
            'button_radius' => 8,
            'nav_button_padding_y' => 13,
            'nav_button_padding_x' => 22,
            'nav_button_font_size' => 14,

            // ---- hero ----
            'hero_min_height' => 480,
            'hero_overlay_min_height' => 560,
            'hero_padding_y' => 80,
            'hero_gap' => 60,
            // The blade gives both hero columns `flex:1 1 45%` inside a 60px
            // gap, i.e. an even split. The Gutenberg hero used to hardcode
            // 55/45 instead, so an approved even split shipped lopsided.
            'hero_copy_width' => '50%',
            'hero_image_width' => '50%',
            'hero_copy_max_width' => 560,
            'hero_copy_font_size' => 18,
            'hero_image_height' => 380,
            'hero_image_radius' => 16,
            // .hero.split-left softens the photo corners.
            'hero_image_radius_soft' => 28,
            'hero_overlay_copy_max_width' => 760,
            'hero_overlay_padding_top' => 140,
            'hero_overlay_padding_bottom' => 90,

            // ---- sections ----
            'section_padding_y' => 60,
            'section_band_color' => '#F6F4F0',
            'section_head_max_width' => 680,
            'section_head_font_size' => 16,

            // ---- headings ----
            'h1_size' => 50,
            'h1_line_height' => '1.12',
            'h2_size' => 30,
            'icon_title_size' => 16,
            'card_title_size' => 15,
            'body_text_size' => 13,

            // ---- cards ----
            'grid_gap' => 20,
            'card_radius' => 14,
            'card_radius_soft' => 22,
            'card_padding' => 16,
            'card_border_color' => '#eae5dd',
            'card_border_width' => 1,
            'card_image_height' => 150,
            'card_image_height_soft' => 170,

            // ---- footer ----
            'footer_bg' => '#1c1a17',
            'footer_bottom_bg' => '#151310',
            'footer_text_color' => '#cfc8bd',
            'footer_heading_color' => '#c9c2b8',
            'footer_bottom_text_color' => '#8f887d',
            'footer_columns' => '2fr 1fr 1fr',
            'footer_gap' => 36,
            'footer_padding_top' => 44,
            'footer_padding_bottom' => 36,
            'footer_heading_size' => 14,
            'footer_text_size' => 14,
            'footer_bottom_padding_y' => 20,
            'footer_bottom_font_size' => 13,
        ];
    }

    /**
     * One measurement by name. Throws rather than silently returning null, so
     * a typo in a Blade template or a prompt surfaces immediately instead of
     * rendering a broken `min-height:px`.
     */
    public static function token(string $name): int|string
    {
        $tokens = self::tokens();

        if (!array_key_exists($name, $tokens)) {
            throw new \InvalidArgumentException("Unknown mockup design token [{$name}].");
        }

        return $tokens[$name];
    }
}
