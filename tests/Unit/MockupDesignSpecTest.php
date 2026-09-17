<?php

use App\Models\Project;
use App\Services\ClaudeWordPressBuilderService;
use App\Services\ElementorPageBuilderService;
use App\Support\MockupDesignSpec;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The mockup's visual measurements used to exist in three hand-synced copies:
 * the blade CSS the client's PNG is rendered from, the Gutenberg block
 * attributes that ship as the page body, and the prose brief Claude builds
 * header/footer/style.css from. These tests assert all three now read the same
 * numbers out of MockupDesignSpec, so changing one value moves all three.
 */
function specDesign(string $layoutVariant = 'split-right'): array
{
    return [
        'primary_color' => '#1F3A5F',
        'secondary_color' => '#F8FAFC',
        'accent_color' => '#C87941',
        'font_heading' => 'Playfair Display',
        'font_body' => 'Inter',
        'layout_variant' => $layoutVariant,
    ];
}

function specPages(): array
{
    return [[
        'name' => 'Home',
        'sections' => [
            ['name' => 'Hero', 'headline' => 'Kopi Nusantara', 'description' => 'Dari petani lokal.'],
            ['name' => 'Kenapa', 'headline' => 'Kenapa Kami', 'items' => [['title' => 'Segar'], ['title' => 'Adil']]],
            ['name' => 'Menu', 'headline' => 'Menu', 'items' => [['title' => 'Gayo'], ['title' => 'Toraja']]],
        ],
    ]];
}

function specImageMap(): array
{
    return ['home' => ['hero' => 'home-hero.jpg', 'items' => [0 => 'home-item-0.jpg']]];
}

function specChromeBrief(): string
{
    $method = new ReflectionMethod(ClaudeWordPressBuilderService::class, 'chromeDesignSpec');
    $method->setAccessible(true);

    return $method->invoke(new ClaudeWordPressBuilderService(), specDesign());
}

it('refuses to hand out a measurement that does not exist', function () {
    expect(fn () => MockupDesignSpec::token('nav_heigth'))
        ->toThrow(InvalidArgumentException::class);
});

it('renders the mockup PNG from the shared measurements', function () {
    $t = MockupDesignSpec::tokens();

    $html = view('pdf.mockup-render', [
        'project' => new Project(['name' => 'Kopi Nusantara', 'client_name' => 'Kopi Nusantara']),
        'mockup' => ['website_concept' => 'Kopi.', 'global_cta' => 'Pesan', 'design' => specDesign()],
        'design' => specDesign(),
        'pages' => [['name' => 'Home']],
        'homeSections' => [],
        'hero' => ['headline' => 'Kopi Nusantara', 'description' => 'Dari petani lokal.'],
        'iconSection' => null,
        'photoSection' => null,
        'heroPhoto' => null,
        'itemPhotos' => [],
        'logoDataUrl' => null,
    ])->render();

    expect($html)
        ->toContain(".site{width:{$t['container_width']}px")
        ->toContain(".nav{min-height:{$t['nav_height']}px;padding:0 {$t['gutter']}px")
        ->toContain(".section{padding:{$t['section_padding_y']}px {$t['gutter']}px}")
        ->toContain("border-radius:{$t['card_radius']}px")
        ->toContain("background:{$t['footer_bg']}");
});

it('keeps no second copy of the shared measurements in the mockup template', function () {
    $blade = file_get_contents(resource_path('views/pdf/mockup-render.blade.php'));

    // If any of these reappear as literals, a value has been duplicated back
    // out of the spec and the three consumers can drift again.
    expect($blade)
        ->not->toContain('1440px')
        ->not->toContain('74px')
        ->not->toContain('94px')
        ->not->toContain('#1c1a17')
        ->not->toContain('#eae5dd');
});

it('builds Gutenberg cards from the shared measurements', function () {
    $t = MockupDesignSpec::tokens();
    $html = (new ElementorPageBuilderService())->buildPages(specPages(), specDesign())['home']['html'];

    expect($html)
        ->toContain('"radius":"' . $t['card_radius'] . 'px"')
        ->toContain('"color":"' . $t['card_border_color'] . '"')
        ->toContain('"top":"' . $t['card_padding'] . 'px"')
        ->toContain('"background":"' . $t['section_band_color'] . '"');
});

it('gives the cover hero the mockup overlay height, not the plain hero height', function () {
    $t = MockupDesignSpec::tokens();
    $html = (new ElementorPageBuilderService())
        ->buildPages(specPages(), specDesign('overlay-bg'), specImageMap())['home']['html'];

    expect($html)
        ->toContain('"minHeight":' . $t['hero_overlay_min_height'])
        ->toContain('min-height:' . $t['hero_overlay_min_height'] . 'px')
        ->not->toContain('min-height:480px');
});

it('splits the hero columns evenly, the way the mockup lays them out', function () {
    $t = MockupDesignSpec::tokens();
    $html = (new ElementorPageBuilderService())
        ->buildPages(specPages(), specDesign(), specImageMap())['home']['html'];

    expect($html)
        ->toContain('flex-basis:' . $t['hero_copy_width'])
        ->toContain('flex-basis:' . $t['hero_image_width'])
        ->not->toContain('flex-basis:55%');
});

it('stops forcing content images to aligncenter, which the mockup never does', function () {
    $html = (new ElementorPageBuilderService())
        ->buildPages(specPages(), specDesign(), specImageMap())['home']['html'];

    expect($html)
        ->not->toContain('aligncenter')
        ->not->toContain('"align":"center"')
        ->toContain('<!-- wp:image {"sizeSlug":"medium"');
});

it('leaves the image token and its markers intact for the exporter to rewrite', function () {
    $html = (new ElementorPageBuilderService())
        ->buildPages(specPages(), specDesign(), specImageMap())['home']['html'];

    // The generated importer matches on exactly this pattern; the class-name
    // change above must not have disturbed it.
    expect(preg_match_all('/<!--EXITO_IMG_START:(.*?)-->(.*?)<!--EXITO_IMG_END:\1-->/s', $html, $matches))
        ->toBeGreaterThan(0)
        ->and($matches[2][0])->toContain('__EXITO_IMAGE:');
});

it('briefs Claude with the same measurements, not approximations of them', function () {
    $t = MockupDesignSpec::tokens();
    $brief = specChromeBrief();

    expect($brief)
        ->toContain("max-width {$t['container_width']}px")
        ->toContain("{$t['nav_height']}px min-height")
        ->toContain("horizontal padding {$t['gutter']}px")
        ->toContain("{$t['nav_link_gap']}px gap")
        ->toContain("border-radius {$t['button_radius']}px")
        ->toContain("grid-template-columns:{$t['footer_columns']};gap:{$t['footer_gap']}px")
        ->toContain($t['footer_bg'])
        ->toContain($t['footer_bottom_bg'])
        ->toContain($t['card_border_color']);
});

it('no longer briefs Claude with hand-typed approximations', function () {
    $brief = specChromeBrief();

    expect($brief)
        ->not->toContain('~94px')
        ->not->toContain('~74px')
        ->not->toContain('~32px');
});

it('tells Claude to let content images fill their container', function () {
    expect(specChromeBrief())->toContain('do NOT centre them at their natural size');
});
