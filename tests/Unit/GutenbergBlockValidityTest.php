<?php

use App\Services\BundleExporterService;
use App\Services\ElementorPageBuilderService;
use App\Support\MockupDesignSpec;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The Block Editor re-runs every block's save() and flags any difference from
 * the stored markup as invalid ("Attempt Block Recovery"). These are the four
 * differences a real WordPress 7.1 editor reported on a built V2 page — each
 * pinned to the exact markup core's save() produces, so they cannot return.
 */
function validityHtml(array $design = [], array $imageMap = []): string
{
    $fixture = require base_path('tests/Fixtures/v2-blueprint.php');

    return (new ElementorPageBuilderService())->buildPages(
        $fixture['pages'],
        array_merge($fixture['design'], $design),
        $imageMap ?: ['home' => ['hero' => 'home-hero.jpg', 'items' => [0 => 'home-item-0.jpg'], 'sections' => [2 => [0 => 'home-item-0.jpg'], 3 => [0 => 'home-section-3-item-0.jpg']]]],
        $fixture['global_cta']
    )['home']['html'];
}

it('writes button styles exactly as core/button saves them', function () {
    $html = validityHtml();

    expect($html)->toContain('style="color:#12343B;background-color:#ffffff"')
        ->not->toContain('text-decoration:none;display:inline-block');
});

it('writes card padding as the longhand core/group saves, with no extra declarations', function () {
    $t = MockupDesignSpec::tokens();
    $p = $t['card_padding'] . 'px';

    expect(validityHtml())
        ->toContain("style=\"border-color:{$t['card_border_color']};border-width:{$t['card_border_width']}px;border-radius:{$t['card_radius']}px;padding-top:{$p};padding-right:{$p};padding-bottom:{$p};padding-left:{$p}\"")
        ->not->toContain('overflow:hidden;padding:');
});

it('declares the image scale that produces the object-fit it writes', function () {
    expect(validityHtml())
        ->toContain('"aspectRatio":"3/4","scale":"cover"')
        ->toContain('style="aspect-ratio:3/4;object-fit:cover"');
});

it('puts the cover background image before the dim span, as core/cover saves it', function () {
    $html = validityHtml(['layout_variant' => 'overlay-bg']);
    $cover = substr($html, strpos($html, '<!-- wp:cover'));

    expect(strpos($cover, 'wp-block-cover__image-background'))
        ->toBeLessThan(strpos($cover, 'wp-block-cover__background'));
});

it('replaces image tokens outside the photo markers at import time', function () {
    $dir = sys_get_temp_dir() . '/exito-validity-' . uniqid();
    $theme = 'exito-client-theme';
    (new BundleExporterService())->export([
        'theme' => ['name' => $theme],
        'elementor_pages' => ['home' => ['title' => 'Home', 'html' => '<p>x</p>', 'elements' => []]],
        'wordpress' => ['files' => ["{$theme}/style.css" => "/*\nTheme Name: T\n*/"]],
    ], $dir);

    $zip = new ZipArchive();
    $zip->open($dir . '/theme-install.zip');
    $functions = $zip->getFromName("{$theme}/functions.php");
    $zip->close();

    // The marker pass must feed the second, marker-free pass — a cover's
    // `url` attribute otherwise keeps the raw token and the block is invalid.
    expect($functions)->toMatch('/\$html = preg_replace_callback\(\s+\'\/<!--EXITO_IMG_START:/')
        ->toContain("'/__EXITO_IMAGE:(.*?)__/'");
});
