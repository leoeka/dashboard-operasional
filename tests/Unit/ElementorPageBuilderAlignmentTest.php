<?php

use App\Services\ElementorPageBuilderService;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The approved PNG (resources/views/pdf/mockup-render.blade.php) and the
 * WordPress page built from the same blueprint must agree on alignment.
 * Every heading/paragraph/button used to be emitted with a hardcoded
 * `center`, so an approved split hero — whose copy sits left in the PNG —
 * arrived in WordPress centered. These tests pin the mapping per layout
 * variant so that can't regress.
 */
function alignmentPages(): array
{
    return [[
        'name' => 'Home',
        'sections' => [
            [
                'name' => 'Hero',
                'headline' => 'Kopi Nusantara Pilihan',
                'description' => 'Biji kopi single origin dari petani lokal.',
                'cta' => 'Pesan Sekarang',
            ],
            [
                'name' => 'Kenapa Kami',
                'headline' => 'Kenapa Memilih Kami',
                'description' => 'Tiga alasan utama.',
                'items' => [
                    ['title' => 'Segar', 'description' => 'Disangrai tiap minggu.'],
                    ['title' => 'Adil', 'description' => 'Harga adil untuk petani.'],
                ],
            ],
            [
                'name' => 'Menu',
                'headline' => 'Menu Unggulan',
                'items' => [
                    ['title' => 'Kopi Gayo', 'description' => 'Aceh, medium roast.'],
                    ['title' => 'Kopi Toraja', 'description' => 'Sulawesi, dark roast.'],
                ],
            ],
        ],
    ]];
}

function buildAlignmentHtml(string $layoutVariant, ?array $pages = null): string
{
    return (new ElementorPageBuilderService())->buildPages(
        $pages ?? alignmentPages(),
        ['primary_color' => '#1F3A5F', 'accent_color' => '#C87941', 'layout_variant' => $layoutVariant],
    )['home']['html'];
}

/**
 * Splits the page at each section-head heading, so a band can be asserted on
 * without matching a class name that happens to sit next to its text:
 * [0] hero, [1] the icon band, [2] the photo/card band.
 */
function alignmentBands(string $html): array
{
    return explode('<!-- wp:heading {"level":2', $html);
}

it('renders a split-right hero left-aligned, matching the approved PNG', function () {
    $hero = alignmentBands(buildAlignmentHtml('split-right'))[0];

    expect($hero)
        ->toContain('<!-- wp:heading {"level":1,"textAlign":"left"')
        ->toContain('has-text-align-left')
        ->not->toContain('has-text-align-center');
});

it('renders a split-left hero left-aligned too', function () {
    $hero = alignmentBands(buildAlignmentHtml('split-left'))[0];

    expect($hero)->toContain('"textAlign":"left"')->not->toContain('has-text-align-center');
});

it('keeps the overlay-bg hero centered, because the PNG centers it over the photo', function () {
    $hero = alignmentBands(buildAlignmentHtml('overlay-bg'))[0];

    expect($hero)
        ->toContain('<!-- wp:heading {"level":1,"textAlign":"center"')
        ->not->toContain('has-text-align-left');
});

it('aligns the hero CTA button with the hero copy instead of always centering it', function () {
    expect(buildAlignmentHtml('split-right'))->toContain('"justifyContent":"left"');
    expect(buildAlignmentHtml('overlay-bg'))->toContain('"justifyContent":"center"');
});

it('keeps section headings centered, matching .section-head in the PNG', function () {
    $html = buildAlignmentHtml('split-right');

    expect($html)->toContain('<!-- wp:heading {"level":2,"textAlign":"center","style"');
});

it('left-aligns card copy, because .card sets no text-align in any variant', function () {
    $cards = alignmentBands(buildAlignmentHtml('split-right'))[2];

    expect($cards)
        ->toContain('<!-- wp:heading {"level":3,"textAlign":"left"} -->')
        ->toContain('Kopi Gayo');
});

it('centers the icon band by default but left-aligns it under overlay-bg', function () {
    expect(alignmentBands(buildAlignmentHtml('split-right'))[1])
        ->toContain('<!-- wp:heading {"level":3,"textAlign":"center"} -->');

    expect(alignmentBands(buildAlignmentHtml('overlay-bg'))[1])
        ->toContain('<!-- wp:heading {"level":3,"textAlign":"left"} -->');
});

it('lets a blueprint text_align override the per-variant fallback', function () {
    $pages = alignmentPages();
    $pages[0]['sections'][0]['text_align'] = 'right';

    $hero = alignmentBands(buildAlignmentHtml('split-right', $pages))[0];

    expect($hero)->toContain('"textAlign":"right"')->toContain('has-text-align-right');
});

it('falls back safely when a blueprint declares an unsupported alignment', function () {
    $pages = alignmentPages();
    $pages[0]['sections'][0]['text_align'] = 'justify-all';

    $hero = alignmentBands(buildAlignmentHtml('split-right', $pages))[0];

    expect($hero)->toContain('"textAlign":"left"');
});
