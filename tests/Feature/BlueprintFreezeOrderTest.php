<?php

use App\Models\Project;
use App\Services\GenerateMockupGptService;
use App\Services\ScreenshotService;
use App\Support\CompositionSpec;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Ordering, which is the whole point of this file.
 *
 * Distinctness used to run AFTER each candidate's photos had been generated and
 * its screenshot rendered, then rewrite the blueprint. A client could therefore
 * approve a picture of an asymmetric_split hero while the stored blueprint said
 * background_image — and WordPress would compile the blueprint, not the picture.
 * That breaks design once -> approve -> freeze -> compile at its first step.
 *
 * The pipeline is now: generate blueprints -> normalise -> finalise (including
 * distinctness) -> FREEZE -> assets -> screenshot. These tests hold that order
 * by capturing the exact HTML the screenshot renderer was handed and comparing
 * it against the blueprint that was ultimately stored.
 */
class RecordingScreenshotService extends ScreenshotService
{
    /** @var array<int, array{html: string, path: string}> */
    public array $captures = [];

    public function captureHtml(string $html, string $relativePath): ?string
    {
        $this->captures[] = ['html' => $html, 'path' => $relativePath];

        return $relativePath;
    }
}

function freezeAnalysis(): array
{
    return [
        'business_analysis' => ['value_proposition' => 'Kopi single origin.'],
        'target_market' => ['segment' => 'Pecinta kopi rumahan'],
        'sitemap' => [
            'website_concept' => 'Kopi single origin untuk rumah.',
            'global_cta' => 'Pesan Sekarang',
            'seo' => [],
            'pages' => [[
                'name' => 'Home',
                'sections' => [
                    ['name' => 'Hero', 'headline' => 'Kopi Nusantara', 'description' => 'Dari petani lokal.', 'cta' => 'Pesan'],
                    ['name' => 'Kenapa', 'headline' => 'Kenapa Kami', 'items' => [['title' => 'Segar'], ['title' => 'Adil']]],
                    ['name' => 'Menu', 'headline' => 'Menu', 'items' => [['title' => 'Gayo'], ['title' => 'Toraja']]],
                ],
            ]],
        ],
    ];
}

/** Every design call answers with the SAME compositions, forcing the distinctness pass to act. */
function fakeDesignerReturning(string $heroComposition, string $cardComposition = 'standard_cards', array $heroExtras = []): void
{
    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('PHOTO')]]]),
        'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'style' => 'Hangat dan membumi',
            'visual_direction' => 'editorial warmth',
            'primary_color' => '#1F3A5F',
            'secondary_color' => '#F8FAFC',
            'accent_color' => '#C87941',
            'font_heading' => 'Playfair Display',
            'font_body' => 'Inter',
            'sections' => [
                array_merge(['role' => 'hero', 'composition' => $heroComposition, 'text_align' => 'left'], $heroExtras),
                ['role' => 'icon_band', 'composition' => 'feature_grid'],
                ['role' => 'card_grid', 'composition' => $cardComposition],
            ],
        ])]]]]),
    ]);
}

function runCandidates(string $hero = 'asymmetric_split', string $cards = 'standard_cards', array $heroExtras = []): array
{
    Storage::fake('public');
    config(['services.openai.key' => 'test-key', 'services.openai.mockup_candidate_count' => 3]);
    fakeDesignerReturning($hero, $cards, $heroExtras);

    $recorder = new RecordingScreenshotService();
    app()->instance(ScreenshotService::class, $recorder);

    $project = Project::create([
        'name' => 'Website Kopi Nusantara',
        'client_name' => 'Kopi Nusantara',
        'code' => 'KN-9001',
        'type' => 'Coffee Shop',
        'status' => 'request',
    ]);

    $candidates = app(GenerateMockupGptService::class)->generateMockupCandidates($project, freezeAnalysis());

    return ['candidates' => $candidates, 'recorder' => $recorder, 'project' => $project];
}

/** The hero composition a stored candidate blueprint actually carries. */
function storedHero(array $candidate): string
{
    return $candidate['pages'][0]['sections'][0]['composition'];
}

it('produces three candidates whose heroes are all different, even when the designer repeats itself', function () {
    $run = runCandidates('asymmetric_split');
    $heroes = array_map('storedHero', $run['candidates']);

    expect($run['candidates'])->toHaveCount(3)
        ->and(array_unique($heroes))->toHaveCount(3)
        // The designer's own first choice is kept; only the repeats move.
        ->and($heroes[0])->toBe('asymmetric_split');
});

it('renders each screenshot from the FINAL composition, not the one the designer first returned', function () {
    $run = runCandidates('asymmetric_split');

    expect($run['recorder']->captures)->toHaveCount(3);

    foreach ($run['candidates'] as $index => $candidate) {
        $finalHero = storedHero($candidate);
        $html = $run['recorder']->captures[$index]['html'];

        // The rendered PNG carries the composition's own class, so this is the
        // arrangement the client actually looked at.
        expect($html)->toContain("hero--c-{$finalHero}");
    }
});

it('stores exactly the blueprint that was screenshotted', function () {
    $run = runCandidates('asymmetric_split');

    foreach ($run['candidates'] as $index => $candidate) {
        $html = $run['recorder']->captures[$index]['html'];
        $resolved = CompositionSpec::resolve($candidate['pages'][0]['sections'][0], $candidate['design'], 'hero');

        // Composition, family and image position all agree between the stored
        // blueprint and the picture. A post-render mutation would break all three.
        expect($html)->toContain("hero--c-{$resolved['composition']}")
            ->toContain("hero--{$resolved['family']}")
            ->toContain("hero--img-{$resolved['image_position']}")
            ->and($candidate['screenshot_path'])->toBe($run['recorder']->captures[$index]['path']);
    }
});

it('derives required assets from the final composition, not the designer first draft', function () {
    // Every candidate is asked for a photo-free hero. Distinctness must replace
    // the repeats, and the replacements DO need photographs — so asset
    // generation has to follow the new composition, not the original one.
    $run = runCandidates('centered_minimal');

    foreach ($run['candidates'] as $candidate) {
        $resolved = CompositionSpec::resolve($candidate['pages'][0]['sections'][0], $candidate['design'], 'hero');
        $heroAsset = $candidate['assets']['pages']['home']['hero'] ?? null;

        if ($resolved['image_position'] === 'none') {
            expect($heroAsset)->toBeNull();
            continue;
        }

        expect($heroAsset)->not->toBeNull()
            ->and($heroAsset['required'])->toBe($resolved['image_required'])
            ->and($heroAsset['path'])->toContain('mockup-assets/kn-9001/');
    }
});

it('persists each candidate assets under its own candidate folder', function () {
    $run = runCandidates('asymmetric_split');

    foreach ($run['candidates'] as $index => $candidate) {
        $hero = $candidate['assets']['pages']['home']['hero'] ?? null;

        if ($hero && $hero['path']) {
            expect($hero['path'])->toContain('candidate-' . ($index + 1) . '/')
                ->and(Storage::disk('public')->exists($hero['path']))->toBeTrue();
        }
    }
});

it('leaves no candidate whose design differs from what its own assets were built for', function () {
    $run = runCandidates('centered_minimal');

    foreach ($run['candidates'] as $candidate) {
        $resolved = CompositionSpec::resolve($candidate['pages'][0]['sections'][0], $candidate['design'], 'hero');
        $hasHeroAsset = isset($candidate['assets']['pages']['home']['hero']);

        // A photo-free hero must not have paid for a hero photograph, and a
        // photo-led hero must not be missing one.
        expect($hasHeroAsset)->toBe($resolved['image_position'] !== 'none');
    }
});

it('separates three candidates that share a full design fingerprint, not just a hero', function () {
    $service = app(GenerateMockupGptService::class);

    $identical = array_fill(0, 3, [
        'design' => ['primary_color' => '#111111'],
        'pages' => [[
            'name' => 'Home',
            'sections' => [
                ['name' => 'Hero', 'headline' => 'A', 'composition' => 'split'],
                ['name' => 'Kenapa', 'headline' => 'B', 'items' => [['title' => 'x'], ['title' => 'y']], 'composition' => 'feature_grid'],
                ['name' => 'Menu', 'headline' => 'C', 'items' => [['title' => 'p'], ['title' => 'q']], 'composition' => 'standard_cards'],
            ],
        ]],
    ]);

    $method = new ReflectionMethod($service, 'enforceDistinctDesigns');
    $method->setAccessible(true);
    $distinct = $method->invoke($service, $identical);

    $fingerprints = array_map(fn (array $c) => $service->designFingerprint($c), $distinct);

    expect(array_unique($fingerprints))->toHaveCount(3)
        ->and($fingerprints[0])->toBe('split|feature_grid|standard_cards');
});

it('keeps a varied section composition compatible with what the section actually holds', function () {
    $service = app(GenerateMockupGptService::class);

    $identical = array_fill(0, 2, [
        'design' => [],
        'pages' => [[
            'name' => 'Home',
            'sections' => [
                ['name' => 'Hero', 'headline' => 'A', 'composition' => 'split'],
                ['name' => 'Kenapa', 'headline' => 'B', 'items' => [['title' => 'x']], 'composition' => 'feature_grid'],
                ['name' => 'Menu', 'headline' => 'C', 'items' => [['title' => 'p']], 'composition' => 'standard_cards'],
            ],
        ]],
    ]);

    $method = new ReflectionMethod($service, 'enforceDistinctDesigns');
    $method->setAccessible(true);
    $distinct = $method->invoke($service, $identical);

    $sections = $distinct[1]['pages'][0]['sections'];

    // Whichever section moved, a photographed grid stayed photographed and a
    // photo-free band stayed photo-free — the content shape is unchanged.
    expect(CompositionSpec::usesPhotos($sections[1]['composition']))->toBeFalse()
        ->and(CompositionSpec::usesPhotos($sections[2]['composition']))->toBeTrue();
});

it('drops composition-specific overrides when the system swaps a composition', function () {
    // The production schema DOES emit these, so the fixture emits them too: a
    // photo-free hero with its own alignment, copy width and heading scale.
    $run = runCandidates('centered_minimal', 'standard_cards', [
        'image_required' => false,
        'text_align' => 'center',
        'content_width' => '70%',
        'heading_scale' => 'display-lg',
        'image_position' => 'none',
    ]);

    foreach ($run['candidates'] as $candidate) {
        $section = $candidate['pages'][0]['sections'][0];

        if ($section['composition'] === 'centered_minimal') {
            // Untouched by distinctness, so the designer's own choices stand.
            expect($section['image_required'])->toBeFalse()
                ->and($section['text_align'])->toBe('center');
            continue;
        }

        // Replaced by the system: every composition-derived field went with the
        // old composition, so nothing stale can contradict the new one.
        foreach (CompositionSpec::COMPOSITION_DERIVED_KEYS as $key) {
            expect($section)->not->toHaveKey($key);
        }

        // Content survived the swap untouched.
        expect($section['headline'])->toBe('Kopi Nusantara')
            ->and($section['description'])->toBe('Dari petani lokal.');
    }
});

it('takes image_required from the final composition, not the designer first draft', function () {
    $run = runCandidates('centered_minimal', 'standard_cards', [
        'image_required' => false,
        'text_align' => 'center',
        'image_position' => 'none',
    ]);

    $photoLed = 0;

    foreach ($run['candidates'] as $candidate) {
        $section = $candidate['pages'][0]['sections'][0];
        $resolved = CompositionSpec::resolve($section, $candidate['design'], 'hero');
        $heroAsset = $candidate['assets']['pages']['home']['hero'] ?? null;

        if ($section['composition'] === 'centered_minimal') {
            expect($resolved['image_required'])->toBeFalse()
                ->and($heroAsset)->toBeNull();
            continue;
        }

        $photoLed++;

        // The stale image_required=false is gone, so the new composition's own
        // requirement applies and the photograph was actually produced.
        expect($resolved['image_required'])->toBeTrue()
            ->and($heroAsset)->not->toBeNull()
            ->and($heroAsset['required'])->toBeTrue()
            ->and($heroAsset['path'])->not->toBeNull()
            ->and(Storage::disk('public')->exists($heroAsset['path']))->toBeTrue();
    }

    // The swap really did happen, otherwise this test proves nothing.
    expect($photoLed)->toBeGreaterThan(0);
});

it('never rewrites a band of generic features as an FAQ, pricing table or team grid', function () {
    $features = [
        'name' => 'Kenapa',
        'headline' => 'Kenapa Kami',
        'composition' => 'feature_grid',
        'items' => [['title' => 'Cepat', 'description' => '...'], ['title' => 'Aman', 'description' => '...']],
    ];

    $allowed = CompositionSpec::compatibleSectionCompositions('icon_band', $features);

    expect($allowed)->not->toContain('faq')
        ->not->toContain('pricing')
        ->not->toContain('team')
        ->not->toContain('logo_showcase')
        ->not->toContain('gallery')
        ->and($allowed)->toContain('feature_grid');
});

it('reads the content shape from the items themselves', function () {
    $shape = fn (array $items, string $name = 'Bagian', string $role = 'card_grid') => CompositionSpec::contentShape(
        ['name' => $name, 'headline' => $name, 'items' => $items],
        $role
    );

    expect($shape([['question' => 'Berapa lama?', 'answer' => '3 minggu']]))->toBe('faq')
        ->and($shape([['title' => 'Basic', 'price' => 'Rp 5jt']]))->toBe('pricing')
        ->and($shape([['quote' => 'Bagus', 'author' => 'Dimas']]))->toBe('testimonials')
        ->and($shape([['title' => 'Budi', 'role' => 'Barista']]))->toBe('team')
        ->and($shape([['title' => 'Klien', 'value' => '120']]))->toBe('stats')
        ->and($shape([['title' => 'Cepat', 'description' => '...']], 'Kenapa', 'icon_band'))->toBe('feature_items')
        ->and($shape([['title' => 'Kopi Gayo', 'description' => 'Aceh']]))->toBe('card_items');
});

it('keeps a section as it is when nothing compatible exists to swap it for', function () {
    $faq = [
        'name' => 'FAQ',
        'headline' => 'Pertanyaan Umum',
        'composition' => 'faq',
        'items' => [['question' => 'Berapa lama?', 'answer' => '3 minggu']],
    ];

    // Only one composition suits question/answer content, so distinctness has
    // nothing legitimate to offer and must leave the section alone.
    expect(CompositionSpec::compatibleSectionCompositions('card_grid', $faq))->toBe(['faq']);

    $service = app(GenerateMockupGptService::class);
    $identical = array_fill(0, 2, [
        'design' => [],
        'pages' => [['name' => 'Home', 'sections' => [
            ['name' => 'Hero', 'headline' => 'A', 'composition' => 'split'],
            $faq,
        ]]],
    ]);

    $method = new ReflectionMethod($service, 'enforceDistinctDesigns');
    $method->setAccessible(true);
    $distinct = $method->invoke($service, $identical);

    expect($distinct[1]['pages'][0]['sections'][1]['composition'])->toBe('faq')
        ->and($distinct[1]['pages'][0]['sections'][1]['items'][0]['question'])->toBe('Berapa lama?');
});

it('does not start or stop using photographs when it varies a section', function () {
    $photographed = [
        'name' => 'Menu',
        'headline' => 'Menu',
        'composition' => 'standard_cards',
        'items' => [['title' => 'Gayo'], ['title' => 'Toraja']],
    ];
    $photoFree = [
        'name' => 'Kenapa',
        'headline' => 'Kenapa Kami',
        'composition' => 'feature_grid',
        'items' => [['title' => 'Cepat'], ['title' => 'Aman']],
    ];

    foreach (CompositionSpec::compatibleSectionCompositions('card_grid', $photographed) as $composition) {
        expect(CompositionSpec::usesPhotos($composition))->toBeTrue();
    }

    foreach (CompositionSpec::compatibleSectionCompositions('icon_band', $photoFree) as $composition) {
        expect(CompositionSpec::usesPhotos($composition))->toBeFalse();
    }
});
