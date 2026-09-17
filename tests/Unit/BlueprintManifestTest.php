<?php

use App\Models\Project;
use App\Services\BlueprintManifestService;
use App\Services\BundleBuilderService;
use App\Services\ClaudeWordPressBuilderService;
use App\Services\ElementorPageBuilderService;
use App\Support\MockupDesignSpec;
use App\Services\MockupAssetService;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Approval used to send the mockup PNG back to GPT vision and ask it to
 * describe the design it could see. These tests pin the replacement: the
 * manifest is derived from the approved blueprint itself, and describes the
 * same layout the page builder actually renders.
 */
function manifestMockup(): array
{
    return [
        'website_concept' => 'Kopi single origin untuk pecinta kopi rumahan.',
        'global_cta' => 'Pesan Sekarang',
        'seo' => ['meta_description' => 'Kopi Nusantara'],
        'design' => [
            'style' => 'Hangat, membumi, artisanal',
            'primary_color' => '#1F3A5F',
            'secondary_color' => '#F8FAFC',
            'accent_color' => '#C87941',
            'font_heading' => 'Playfair Display',
            'font_body' => 'Inter',
            'layout_variant' => 'split-right',
        ],
        'pages' => [
            [
                'name' => 'Home',
                'sections' => [
                    ['name' => 'Hero', 'type' => 'hero', 'headline' => 'Kopi Nusantara Pilihan', 'description' => 'Dari petani lokal.', 'cta' => 'Pesan Sekarang'],
                    ['name' => 'Kenapa Kami', 'headline' => 'Kenapa Memilih Kami', 'items' => [
                        ['title' => 'Segar', 'description' => 'Disangrai tiap minggu.'],
                        ['title' => 'Adil', 'description' => 'Harga adil untuk petani.'],
                    ]],
                    ['name' => 'Menu', 'headline' => 'Menu Unggulan', 'items' => [
                        ['title' => 'Kopi Gayo', 'description' => 'Aceh, medium roast.'],
                        ['title' => 'Kopi Toraja', 'description' => 'Sulawesi, dark roast.'],
                    ]],
                    ['name' => 'Testimoni', 'headline' => 'Kata Mereka', 'description' => 'Tidak pernah tampil di PNG.'],
                ],
            ],
            ['name' => 'About', 'sections' => [['name' => 'Tentang', 'headline' => 'Tentang Kami']]],
        ],
        'assets' => ['pages' => ['home' => [
            'hero' => ['slot' => 'home.hero', 'path' => 'mockup-assets/kn-0001/candidate-1/hero.jpg', 'required' => true, 'source' => 'generated'],
            'sections' => [2 => ['role' => 'card_grid', 'items' => [
                0 => ['slot' => 'home.section-2.item-0', 'path' => 'mockup-assets/kn-0001/candidate-1/section-2-item-0.jpg', 'required' => true, 'source' => 'generated'],
                1 => ['slot' => 'home.section-2.item-1', 'path' => 'mockup-assets/kn-0001/candidate-1/section-2-item-1.jpg', 'required' => false, 'source' => 'client_upload'],
            ]]],
        ]]],
    ];
}

function buildManifest(?array $mockup = null): array
{
    return (new BlueprintManifestService(new ElementorPageBuilderService()))
        ->build($mockup ?? manifestMockup());
}

it('marks the manifest as derived from the approved blueprint', function () {
    expect(buildManifest()['source'])->toBe('approved_blueprint');
});

it('carries the approved design tokens through without reinterpretation', function () {
    $design = buildManifest()['design_system'];

    expect($design['colors']['primary'])->toBe('#1F3A5F')
        ->and($design['colors']['accent'])->toBe('#C87941')
        ->and($design['typography']['heading_font'])->toBe('Playfair Display')
        ->and($design['typography']['body_font'])->toBe('Inter')
        ->and($design['layout']['variant'])->toBe('split-right')
        ->and($design['style'])->toBe('Hangat, membumi, artisanal');
});

it('records each section with the alignment the page builder will actually render', function () {
    $sections = collect(buildManifest()['sections'])->where('page', 'home')->values();

    $hero = $sections->firstWhere('role', 'hero');
    expect($hero['heading'])->toBe('Kopi Nusantara Pilihan')
        ->and($hero['cta'])->toBe('Pesan Sekarang')
        ->and($hero['layout']['body_align'])->toBe('left')
        ->and($hero['layout']['background'])->toBe('#1F3A5F');

    expect($sections->firstWhere('role', 'icon_band')['layout']['body_align'])->toBe('center');

    $cards = $sections->firstWhere('role', 'card_grid');
    expect($cards['layout']['body_align'])->toBe('left')
        ->and($cards['layout']['background'])->toBe(MockupDesignSpec::token('section_band_color'))
        ->and($cards['items'])->toHaveCount(2)
        ->and($cards['items'][0]['title'])->toBe('Kopi Gayo');
});

it('flips hero alignment for an overlay-bg mockup, exactly as the renderer does', function () {
    $mockup = manifestMockup();
    $mockup['design']['layout_variant'] = 'overlay-bg';

    $sections = collect(buildManifest($mockup)['sections'])->where('page', 'home');

    expect($sections->firstWhere('role', 'hero')['layout']['body_align'])->toBe('center')
        ->and($sections->firstWhere('role', 'icon_band')['layout']['body_align'])->toBe('left');
});

it('agrees with the page builder about which sections are dropped', function () {
    $manifest = buildManifest();
    $home = collect($manifest['pages'])->firstWhere('slug', 'home');

    // The blueprint has 4 Home sections, but the approved PNG only ever showed
    // hero + icon band + card grid.
    expect($home['blueprint_sections'])->toBe(4)
        ->and($home['rendered_sections'])->toBe(3);

    $headings = collect($manifest['sections'])->where('page', 'home')->pluck('heading');
    expect($headings)->not->toContain('Kata Mereka');
});

it('reports the frozen asset paths verbatim rather than guessing filenames', function () {
    $assets = collect(buildManifest()['assets']);

    $hero = $assets->firstWhere('slot', 'home.hero');
    expect($hero['path'])->toBe('mockup-assets/kn-0001/candidate-1/hero.jpg')
        ->and($hero['required'])->toBeTrue()
        ->and($hero['source'])->toBe('generated');

    $item = $assets->firstWhere('slot', 'home.section-2.item-1');
    expect($item['path'])->toBe('mockup-assets/kn-0001/candidate-1/section-2-item-1.jpg')
        ->and($item['required'])->toBeFalse()
        ->and($item['source'])->toBe('client_upload');
});

it('attaches card assets to the card_grid section, never the icon band', function () {
    $sections = collect(buildManifest()['sections'])->where('page', 'home');

    expect($sections->firstWhere('role', 'icon_band')['asset_slots'])->toBe([])
        ->and($sections->firstWhere('role', 'card_grid')['asset_slots'])
        ->toBe(['home.section-2.item-0', 'home.section-2.item-1']);
});

it('reports no assets at all for a blueprint that was approved before assets were persisted', function () {
    $mockup = manifestMockup();
    unset($mockup['assets']);

    expect(buildManifest($mockup)['assets'])->toBe([]);
});

it('builds a manifest without any API key configured', function () {
    config(['services.openai.key' => null, 'services.anthropic.key' => null]);

    expect(buildManifest()['sections'])->not->toBeEmpty();
});

it('survives a blueprint with no pages at all', function () {
    $manifest = buildManifest(['design' => []]);

    expect($manifest['pages'])->toBe([])
        ->and($manifest['sections'])->toBe([])
        ->and($manifest['design_system']['layout']['variant'])->toBe('split-right');
});

it('describes the project type instead of labelling every build a restaurant', function () {
    $builder = new class (
        app(ClaudeWordPressBuilderService::class),
        new ElementorPageBuilderService(),
        app(MockupAssetService::class),
    ) extends BundleBuilderService {
        public function templateFor(Project $project): array
        {
            return $this->resolveTemplate($project);
        }
    };

    $template = $builder->templateFor(new Project(['type' => 'Law Firm']));

    expect($template['name'])->toBe('Law Firm')
        ->and($template['category'])->toBe('law-firm')
        ->and($template['slug'])->toBe('law-firm');
});

it('claims no category at all when the project states no type', function () {
    $builder = new class (
        app(ClaudeWordPressBuilderService::class),
        new ElementorPageBuilderService(),
        app(MockupAssetService::class),
    ) extends BundleBuilderService {
        public function templateFor(Project $project): array
        {
            return $this->resolveTemplate($project);
        }
    };

    expect($builder->templateFor(new Project())['category'])->toBeNull();
});
