<?php

use App\Models\Project;
use App\Services\ElementorPageBuilderService;
use App\Services\GenerateMockupGptService;
use App\Services\MockupAssetService;
use App\Support\CompositionSpec;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The designer used to choose only colours, fonts and a mood, while the actual
 * arrangement came from a hardcoded rotation of three layouts — which is why
 * every project looked like the same template in different paint. It now picks
 * a composition from a closed vocabulary, and CompositionSpec resolves that one
 * choice for both renderers so the mockup and WordPress cannot disagree.
 */
function blueprint(array $heroOverrides = [], array $cardOverrides = [], array $design = []): array
{
    return [
        'design' => array_merge(['primary_color' => '#1F3A5F', 'accent_color' => '#C87941'], $design),
        'pages' => [[
            'name' => 'Home',
            'sections' => [
                array_merge(['name' => 'Hero', 'headline' => 'Kopi Nusantara', 'description' => 'Dari petani lokal.'], $heroOverrides),
                ['name' => 'Kenapa', 'headline' => 'Kenapa Kami', 'items' => [['title' => 'Segar'], ['title' => 'Adil']]],
                array_merge(['name' => 'Menu', 'headline' => 'Menu', 'items' => [['title' => 'Gayo'], ['title' => 'Toraja']]], $cardOverrides),
            ],
        ]],
    ];
}

function renderBlade(array $mockup, array $images = []): string
{
    $sections = $mockup['pages'][0]['sections'];
    $plan = (new ElementorPageBuilderService())->describeSections($sections, $mockup['design']);

    $by = fn (string $role) => collect($plan)->firstWhere('role', $role);

    return view('pdf.mockup-render', [
        'project' => new Project(['name' => 'Kopi Nusantara', 'client_name' => 'Kopi Nusantara']),
        'mockup' => $mockup + ['website_concept' => '', 'global_cta' => 'Pesan'],
        'design' => $mockup['design'],
        'pages' => [['name' => 'Home']],
        'homeSections' => $sections,
        'hero' => $sections[0],
        'iconSection' => $sections[1],
        'photoSection' => $sections[2],
        'heroComposition' => $by('hero')['composition'],
        'iconComposition' => $by('icon_band')['composition'],
        'photoComposition' => $by('card_grid')['composition'],
        'heroPhoto' => $images['hero'] ?? null,
        'itemPhotos' => $images['items'] ?? [],
        'logoDataUrl' => null,
    ])->render();
}

function renderGutenberg(array $mockup, array $imageMap = []): string
{
    return (new ElementorPageBuilderService())
        ->buildPages($mockup['pages'], $mockup['design'], $imageMap)['home']['html'];
}

function reflect(object $object, string $method, array $args): mixed
{
    $ref = new ReflectionMethod($object, $method);
    $ref->setAccessible(true);

    return $ref->invoke($object, ...$args);
}

it('still resolves a blueprint written before compositions existed', function () {
    foreach ([
        'split-right' => ['split', 'right'],
        'split-left' => ['split', 'left'],
        'overlay-bg' => ['background_image', 'background'],
    ] as $variant => [$composition, $position]) {
        $resolved = CompositionSpec::resolve([], ['layout_variant' => $variant], 'hero');

        expect($resolved['composition'])->toBe($composition)
            ->and($resolved['image_position'])->toBe($position);
    }

    // And the legacy split keeps the exact geometry it always rendered.
    $legacy = CompositionSpec::resolve([], ['layout_variant' => 'split-right'], 'hero');
    expect($legacy['content_width'])->toBe(50)
        ->and($legacy['heading_px'])->toBe(50)
        ->and($legacy['spacing_top'])->toBe(80);
});

it('builds a legacy approved blueprint without any new fields present', function () {
    $mockup = blueprint([], [], ['layout_variant' => 'split-left']);

    expect(renderGutenberg($mockup, ['home' => ['hero' => 'home-hero.jpg']]))
        ->toContain('wp-block-columns')
        ->and(renderBlade($mockup))->toContain('hero--c-split');
});

it('applies a blueprint text_align identically in the mockup and in WordPress', function () {
    $mockup = blueprint(['composition' => 'split', 'text_align' => 'right']);

    expect(renderBlade($mockup))->toContain('text-align:right')
        ->and(renderGutenberg($mockup))->toContain('"textAlign":"right"');
});

it('uses one resolver for both renderers, so a composition cannot mean two things', function () {
    $mockup = blueprint(['composition' => 'asymmetric_split', 'content_width' => '48%', 'image_position' => 'left']);
    $resolved = CompositionSpec::resolve($mockup['pages'][0]['sections'][0], $mockup['design'], 'hero');

    $blade = renderBlade($mockup, ['hero' => 'data:image/jpeg;base64,AAAA']);
    $gutenberg = renderGutenberg($mockup, ['home' => ['hero' => 'home-hero.jpg']]);

    // Same column split, same direction, in both.
    expect($blade)->toContain("flex:0 0 {$resolved['content_width']}%")
        ->and($gutenberg)->toContain("flex-basis:{$resolved['content_width']}%")
        ->and($blade)->toContain('hero--img-left')
        ->and($resolved['image_position'])->toBe('left');

    // The image column precedes the copy column when the photo is on the left.
    expect(strpos($gutenberg, 'wp:image'))->toBeLessThan(strpos($gutenberg, 'wp:heading'));
});

it('crops to the same image ratio in the mockup and in WordPress', function () {
    $mockup = blueprint(['composition' => 'split', 'image_ratio' => '3:4'], ['image_ratio' => '1:1']);

    $blade = renderBlade($mockup, ['hero' => 'data:image/jpeg;base64,AAAA', 'items' => [0 => 'data:image/jpeg;base64,BBBB']]);
    $gutenberg = renderGutenberg($mockup, ['home' => ['hero' => 'home-hero.jpg', 'items' => [0 => 'home-item-0.jpg']]]);

    expect($blade)->toContain('aspect-ratio:3/4')->toContain('--card-ratio:1/1')
        ->and($gutenberg)->toContain('"aspectRatio":"3/4"')->toContain('"aspectRatio":"1/1"');
});

it('lets the blueprint decide which photos are required, not the API', function () {
    $needsPhoto = CompositionSpec::resolve(['composition' => 'asymmetric_split'], [], 'hero');
    $needsNone = CompositionSpec::resolve(['composition' => 'centered_minimal'], [], 'hero');

    expect($needsPhoto['image_required'])->toBeTrue()
        ->and($needsNone['image_required'])->toBeFalse()
        ->and($needsNone['image_position'])->toBe('none');

    // An explicit blueprint value overrides the composition's own default.
    expect(CompositionSpec::resolve(['composition' => 'split', 'image_required' => false], [], 'hero')['image_required'])
        ->toBeFalse();
});

it('marks a candidate degraded when its composition needs a photo it cannot get', function () {
    Storage::fake('public');
    config(['services.openai.key' => 'test-key']);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'no credit']], 429)]);

    $result = (new MockupAssetService(new ElementorPageBuilderService()))
        ->generateForCandidate(
            Project::create(['name' => 'Kopi', 'client_name' => 'Kopi', 'code' => 'KN-0002', 'status' => 'request']),
            blueprint(['composition' => 'asymmetric_split']),
            1
        );

    expect($result['degraded'])->toBeTrue()
        ->and($result['missing'])->toContain('home.hero')
        // The gap is on record rather than silently becoming a text-only design.
        ->and($result['manifest']['pages']['home']['hero']['path'])->toBeNull()
        ->and($result['manifest']['pages']['home']['hero']['required'])->toBeTrue();
});

it('leaves a photo-free composition perfectly valid without any image', function () {
    Storage::fake('public');
    config(['services.openai.key' => 'test-key']);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'no credit']], 429)]);

    $result = (new MockupAssetService(new ElementorPageBuilderService()))
        ->generateForCandidate(
            Project::create(['name' => 'Kopi', 'client_name' => 'Kopi', 'code' => 'KN-0003', 'status' => 'request']),
            blueprint(['composition' => 'centered_minimal'], ['composition' => 'feature_grid']),
            1
        );

    expect($result['degraded'])->toBeFalse()
        ->and($result['missing'])->toBe([]);
});

it('never lets two candidates ship the same hero composition', function () {
    $service = app(GenerateMockupGptService::class);

    $candidates = array_map(
        fn (string $composition) => blueprint(['composition' => $composition]),
        ['asymmetric_split', 'asymmetric_split', 'asymmetric_split']
    );

    $distinct = reflect($service, 'enforceDistinctDesigns', [$candidates]);

    $used = array_map(fn (array $c) => $c['pages'][0]['sections'][0]['composition'], $distinct);

    expect($used)->toHaveCount(3)
        ->and(array_unique($used))->toHaveCount(3)
        ->and($used[0])->toBe('asymmetric_split');
});

it('refuses a partial set of options rather than quietly delivering fewer', function () {
    $service = app(GenerateMockupGptService::class);
    $project = Project::create(['name' => 'Kopi', 'client_name' => 'Kopi', 'status' => 'request']);

    // Two of three complete is not a smaller success — the product promises
    // three options, so this is a failed stage.
    try {
        reflect($service, 'presentableCandidates', [[
            ['candidate_number' => 1, 'degraded' => false, 'missing_assets' => []],
            ['candidate_number' => 2, 'degraded' => false, 'missing_assets' => []],
            ['candidate_number' => 3, 'degraded' => true, 'missing_assets' => ['home.hero']],
        ], $project]);
        test()->fail('Expected a ProviderException.');
    } catch (\App\Exceptions\ProviderException $e) {
        expect($e->getMessage())->toContain('Baru 2 dari 3')
            ->toContain('opsi 3')
            ->toContain('home.hero')
            // and it says the finished work is not wasted
            ->toContain('tetap tersimpan');
    }
});

it('passes a complete set straight through', function () {
    $service = app(GenerateMockupGptService::class);
    $project = Project::create(['name' => 'Kopi', 'client_name' => 'Kopi', 'status' => 'request']);

    $kept = reflect($service, 'presentableCandidates', [[
        ['candidate_number' => 1, 'degraded' => false, 'missing_assets' => []],
        ['candidate_number' => 2, 'degraded' => false, 'missing_assets' => []],
    ], $project]);

    expect($kept)->toHaveCount(2);
});

it('refuses to present a proposal when no candidate is complete', function () {
    $service = app(GenerateMockupGptService::class);
    $project = Project::create(['name' => 'Kopi', 'client_name' => 'Kopi', 'status' => 'request']);

    expect(fn () => reflect($service, 'presentableCandidates', [[
        ['candidate_number' => 1, 'degraded' => true, 'missing_assets' => ['home.hero']],
    ], $project]))->toThrow(RuntimeException::class, 'home.hero');
});

it('extracts a structured design profile from the reference and feeds it to the designer', function () {
    config(['services.openai.key' => 'test-key']);
    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'hero_composition' => 'full-bleed photography',
        'whitespace_level' => 'very generous',
        'card_style' => 'borderless',
        'unrelated_key' => 'should be dropped',
    ])]]]])]);

    $service = app(GenerateMockupGptService::class);
    $project = Project::create(['name' => 'Kopi', 'client_name' => 'Kopi', 'status' => 'request']);

    $profile = $service->extractDesignProfile($project, ['images' => ['data:image/png;base64,AAAA']]);

    expect($profile)->toHaveKey('hero_composition')
        ->and($profile)->not->toHaveKey('unrelated_key');

    // And it reaches the designer's brief as character, not as content.
    $section = reflect($service, 'designProfileSection', [$profile]);
    expect($section)->toContain('REFERENCE DESIGN PROFILE')
        ->toContain('whitespace level: very generous')
        ->toContain('never copy the reference itself');
});

it('works without a reference at all', function () {
    $service = app(GenerateMockupGptService::class);
    $project = Project::create(['name' => 'Kopi', 'client_name' => 'Kopi', 'status' => 'request']);

    expect($service->extractDesignProfile($project, ['images' => []]))->toBeNull()
        ->and(reflect($service, 'designProfileSection', [null]))->toBe('');
});

it('offers the designer only compositions the renderers actually implement', function () {
    foreach (CompositionSpec::HERO_COMPOSITIONS as $composition) {
        $resolved = CompositionSpec::resolve(['composition' => $composition], [], 'hero');
        expect($resolved['family'])->toBeIn(['split', 'overlay', 'stacked_media', 'centered']);
    }

    foreach (CompositionSpec::SECTION_COMPOSITIONS as $composition) {
        $resolved = CompositionSpec::resolve(['composition' => $composition, 'items' => [1, 2, 3]], [], 'card_grid');
        expect($resolved['family'])->toBeIn(['grid', 'band', 'list', 'media']);
    }
});

it('ignores a composition or token the designer invented', function () {
    $resolved = CompositionSpec::resolve(
        ['composition' => 'parallax_explosion', 'text_align' => 'justify', 'container' => 'gigantic'],
        ['layout_variant' => 'split-right'],
        'hero'
    );

    expect($resolved['composition'])->toBe('split')
        ->and($resolved['text_align'])->toBe('left')
        ->and($resolved['container'])->toBe('standard');
});
