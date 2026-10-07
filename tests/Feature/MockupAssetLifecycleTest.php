<?php

use App\Models\Project;
use App\Models\Proposal;
use App\Services\BundleBuilderService;
use App\Services\ElementorPageBuilderService;
use App\Services\MockupAssetService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The asset lifecycle, end to end.
 *
 * Photos used to be generated as base64 data URLs, screenshotted, and thrown
 * away; the WordPress build then generated a fresh set from different prompts,
 * so the delivered site showed different pictures from the approved mockup —
 * or none at all, silently, when generation failed. These tests pin the
 * replacement: generate or pick, persist, reference, render from the file,
 * freeze on approval, and ship those exact bytes.
 */
function lifecycleMockup(string $layoutVariant = 'split-right'): array
{
    return [
        'website_concept' => 'Kopi single origin.',
        'global_cta' => 'Pesan Sekarang',
        'design' => [
            'primary_color' => '#1F3A5F',
            'accent_color' => '#C87941',
            'layout_variant' => $layoutVariant,
        ],
        'pages' => [[
            'name' => 'Home',
            'sections' => [
                ['name' => 'Hero', 'headline' => 'Kopi Nusantara Pilihan', 'description' => 'Dari petani lokal.'],
                // section 1 -> icon_band: titles here must NEVER become photos.
                ['name' => 'Kenapa', 'headline' => 'Kenapa Kami', 'items' => [
                    ['title' => 'Segar', 'description' => 'Disangrai tiap minggu.'],
                    ['title' => 'Adil', 'description' => 'Harga adil.'],
                ]],
                // section 2 -> card_grid: these are the ones that get photos.
                ['name' => 'Menu', 'headline' => 'Menu Unggulan', 'items' => [
                    ['title' => 'Kopi Gayo', 'description' => 'Aceh.'],
                    ['title' => 'Kopi Toraja', 'description' => 'Sulawesi.'],
                ]],
            ],
        ]],
    ];
}

/** Each fake photo's bytes name their own subject, so a mis-mapped photo is visible. */
function fakePhotoApi(): void
{
    Http::fake(['api.openai.com/v1/images/generations' => function ($request) {
        preg_match('/Scene: (.+?)(?: - |\. The scene)/', (string) ($request->data()['prompt'] ?? ''), $m);

        return Http::response(['data' => [['b64_json' => base64_encode('PHOTO:' . ($m[1] ?? 'unknown'))]]]);
    }]);
}

function assetService(): MockupAssetService
{
    return new MockupAssetService(new ElementorPageBuilderService());
}

function lifecycleProject(): Project
{
    return Project::create([
        'name' => 'Website Kopi Nusantara',
        'client_name' => 'Kopi Nusantara',
        'code' => 'KN-0001',
        'type' => 'Coffee Shop',
        'status' => 'request',
    ]);
}

beforeEach(function () {
    Storage::fake('public');
    config(['services.openai.key' => 'test-key']);
});

it('writes every generated mockup photo to storage as a real file', function () {
    fakePhotoApi();

    assetService()->generateForCandidate(lifecycleProject(), lifecycleMockup(), 1);

    $disk = Storage::disk('public');
    expect($disk->exists('mockup-assets/kn-0001/candidate-1/hero.jpg'))->toBeTrue()
        ->and($disk->exists('mockup-assets/kn-0001/candidate-1/section-2-item-0.jpg'))->toBeTrue()
        ->and($disk->exists('mockup-assets/kn-0001/candidate-1/section-2-item-1.jpg'))->toBeTrue();
});

it('regenerates a photo when the visual review rejects text or collage artifacts', function () {
    config(['services.openai.review_generated_images' => true]);
    config(['services.openai.images_per_minute' => 0]);
    $imageNumber = 0;
    $reviewNumber = 0;
    Http::fake([
        'api.openai.com/v1/images/generations' => function () use (&$imageNumber) {
            $imageNumber++;
            return Http::response(['data' => [['b64_json' => base64_encode('IMAGE-' . $imageNumber)]]]);
        },
        'api.openai.com/v1/responses' => function () use (&$reviewNumber) {
            $reviewNumber++;
            $accepted = $reviewNumber !== 1;
            return Http::response(['output_text' => json_encode(['accepted' => $accepted, 'reason' => $accepted ? 'clean photo' : 'text overlay'])]);
        },
    ]);

    $result = assetService()->generateForCandidate(lifecycleProject(), lifecycleMockup(), 1);

    expect($imageNumber)->toBe(4)
        ->and($reviewNumber)->toBe(4)
        ->and(Storage::disk('public')->get('mockup-assets/kn-0001/candidate-1/hero.jpg'))->toBe('IMAGE-4')
        ->and($result['degraded'])->toBeFalse();
});

it('does not approve an image after two visual review rejections', function () {
    config(['services.openai.review_generated_images' => true]);
    config(['services.openai.images_per_minute' => 0]);
    $reviewNumber = 0;
    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('BAD-PHOTO')]]]),
        'api.openai.com/v1/responses' => function () use (&$reviewNumber) {
            $reviewNumber++;
            return Http::response(['output_text' => '{"accepted":false,"reason":"poster text"}']);
        },
    ]);

    $result = assetService()->generateForCandidate(lifecycleProject(), lifecycleMockup(), 1);

    expect($reviewNumber)->toBe(6)
        ->and($result['degraded'])->toBeTrue()
        ->and($result['manifest']['pages']['home']['hero']['path'])->toBeNull()
        ->and(Storage::disk('public')->exists('mockup-assets/kn-0001/candidate-1/hero.jpg'))->toBeFalse();
});

it('records storage-relative references in the candidate manifest, never absolute paths', function () {
    fakePhotoApi();

    $manifest = assetService()->generateForCandidate(lifecycleProject(), lifecycleMockup(), 1)['manifest'];
    $home = $manifest['pages']['home'];

    expect($home['hero']['slot'])->toBe('home.hero')
        ->and($home['hero']['path'])->toBe('mockup-assets/kn-0001/candidate-1/hero.jpg')
        ->and($home['hero']['required'])->toBeTrue()
        ->and($home['hero']['source'])->toBe('generated')
        ->and($home['sections'][2]['items'][0]['slot'])->toBe('home.section-2.item-0')
        ->and($home['sections'][2]['items'][0]['path'])->not->toContain(base_path())
        ->and($home['sections'][2]['items'][0]['path'])->not->toStartWith('/');
});

it('renders the mockup from the persisted file, not from discarded bytes', function () {
    fakePhotoApi();

    $result = assetService()->generateForCandidate(lifecycleProject(), lifecycleMockup(), 1);
    $stored = Storage::disk('public')->get('mockup-assets/kn-0001/candidate-1/hero.jpg');

    expect($result['images']['hero'])->toContain(base64_encode($stored));
});

it('photographs the card_grid section, never the icon band', function () {
    fakePhotoApi();

    assetService()->generateForCandidate(lifecycleProject(), lifecycleMockup(), 1);
    $disk = Storage::disk('public');

    // Before this change the photos came from the FIRST items-bearing section
    // (the icon band) but were displayed against the card grid's items.
    expect($disk->get('mockup-assets/kn-0001/candidate-1/section-2-item-0.jpg'))->toBe('PHOTO:Kopi Gayo')
        ->and($disk->get('mockup-assets/kn-0001/candidate-1/section-2-item-1.jpg'))->toBe('PHOTO:Kopi Toraja');

    expect($disk->allFiles('mockup-assets/kn-0001/candidate-1'))
        ->not->toContain('mockup-assets/kn-0001/candidate-1/section-1-item-0.jpg');
});

it('gives each candidate its own assets so approval cannot pick up another candidate', function () {
    fakePhotoApi();
    $project = lifecycleProject();

    $first = assetService()->generateForCandidate($project, lifecycleMockup(), 1)['manifest'];
    $second = assetService()->generateForCandidate($project, lifecycleMockup('overlay-bg'), 2)['manifest'];

    expect($first['pages']['home']['hero']['path'])->toContain('candidate-1/')
        ->and($second['pages']['home']['hero']['path'])->toContain('candidate-2/')
        ->and($first['pages']['home']['hero']['path'])->not->toBe($second['pages']['home']['hero']['path']);

    // The approved candidate's own manifest is what loads, byte for byte.
    Storage::disk('public')->put($second['pages']['home']['hero']['path'], 'CANDIDATE-2-HERO');
    $loaded = assetService()->loadApproved(['assets' => $second]);

    expect($loaded['files']['home-hero.jpg'])->toBe('CANDIDATE-2-HERO');
});

it('prefers a real client photo over an invented one', function () {
    fakePhotoApi();
    $project = lifecycleProject();
    Storage::disk('public')->put('project-files/foto-asli.jpg', 'REAL-CLIENT-PHOTO');
    $project->files()->create(['category' => 'foto', 'file_path' => 'project-files/foto-asli.jpg', 'original_name' => 'foto-asli.jpg']);

    $result = assetService()->generateForCandidate($project->fresh(), lifecycleMockup(), 1);
    $home = $result['manifest']['pages']['home'];

    expect($home['hero']['source'])->toBe('client_upload')
        ->and(Storage::disk('public')->get($home['hero']['path']))->toBe('REAL-CLIENT-PHOTO');
});

it('loads approved assets without issuing a single image generation call', function () {
    fakePhotoApi();
    $manifest = assetService()->generateForCandidate(lifecycleProject(), lifecycleMockup(), 1)['manifest'];

    Http::fake(['api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('REGENERATED')]]])]);
    $loaded = assetService()->loadApproved(['assets' => $manifest]);

    Http::assertNothingSent();
    expect($loaded['files'])->toHaveCount(3);
});

it('fails the build loudly when an approved required asset is gone', function () {
    fakePhotoApi();
    $manifest = assetService()->generateForCandidate(lifecycleProject(), lifecycleMockup(), 1)['manifest'];

    Storage::disk('public')->delete('mockup-assets/kn-0001/candidate-1/hero.jpg');

    expect(fn () => assetService()->loadApproved(['assets' => $manifest]))
        ->toThrow(RuntimeException::class, 'Approved asset missing');
});

it('skips a missing optional asset without failing', function () {
    fakePhotoApi();
    $manifest = assetService()->generateForCandidate(lifecycleProject(), lifecycleMockup(), 1)['manifest'];
    $manifest['pages']['home']['sections'][2]['items'][1]['required'] = false;

    Storage::disk('public')->delete('mockup-assets/kn-0001/candidate-1/section-2-item-1.jpg');
    $loaded = assetService()->loadApproved(['assets' => $manifest]);

    expect($loaded['files'])->toHaveCount(2)
        ->and($loaded['map']['home']['items'])->not->toHaveKey(1);
});

it('ships the approved bytes into the WordPress bundle unchanged', function () {
    fakePhotoApi();
    $project = lifecycleProject();
    $mockup = lifecycleMockup();
    $mockup['assets'] = assetService()->generateForCandidate($project, $mockup, 1)['manifest'];

    Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => 'approved',
        'ai_reasoning' => json_encode(['mockup' => $mockup, 'analysis' => []]),
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response(
            "event: response.output_text.delta\ndata: " . json_encode([
                'type' => 'response.output_text.delta',
                'delta' => json_encode(['files' => [
                    'exito-client-theme/style.css' => '/* theme */',
                    'exito-client-theme/index.php' => '<?php get_header(); the_content(); get_footer();',
                ]]),
            ]) . "\n\nevent: response.completed\ndata: {\"type\":\"response.completed\"}\n\n",
            200,
            ['Content-Type' => 'text/event-stream']
        ),
        'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('REGENERATED')]]]),
    ]);

    $bundle = app(BundleBuilderService::class)->build($project->fresh());

    // No new photo was drawn anywhere in the build.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v1/images/generations'));

    $disk = Storage::disk('public');
    foreach ([
        'home-hero.jpg' => 'mockup-assets/kn-0001/candidate-1/hero.jpg',
        'home-item-0.jpg' => 'mockup-assets/kn-0001/candidate-1/section-2-item-0.jpg',
        'home-item-1.jpg' => 'mockup-assets/kn-0001/candidate-1/section-2-item-1.jpg',
    ] as $bundled => $approved) {
        expect(hash('sha256', $bundle['section_images'][$bundled]))
            ->toBe(hash('sha256', $disk->get($approved)));
    }
});

it('keeps the image token and its markers working on top of approved assets', function () {
    fakePhotoApi();
    $mockup = lifecycleMockup();
    $mockup['assets'] = assetService()->generateForCandidate(lifecycleProject(), $mockup, 1)['manifest'];

    $loaded = assetService()->loadApproved($mockup);
    $html = (new ElementorPageBuilderService())
        ->buildPages($mockup['pages'], $mockup['design'], $loaded['map'])['home']['html'];

    expect(preg_match_all('/<!--EXITO_IMG_START:(.*?)-->(.*?)<!--EXITO_IMG_END:\1-->/s', $html, $matches))
        ->toBe(3);

    foreach ($matches[1] as $index => $filename) {
        expect($loaded['files'])->toHaveKey($filename)
            ->and($matches[2][$index])->toContain("__EXITO_IMAGE:{$filename}__");
    }
});

it('sends hero ratio focal point and no-text constraints to the image generator', function () {
    $prompts = [];

    Http::fake([
        'api.openai.com/*' => function ($request) use (&$prompts) {
            $prompt = (string) ($request->data()['prompt'] ?? '');
            $prompts[] = $prompt;

            preg_match('/Scene: (.+?)(?: - |\. The scene)/', $prompt, $matches);

            return Http::response([
                'data' => [[
                    'b64_json' => base64_encode(
                        'PHOTO:' . ($matches[1] ?? 'unknown')
                    ),
                ]],
            ]);
        },
    ]);

    $mockup = lifecycleMockup();

    $mockup['design']['renderer_version'] = 2;
    $mockup['pages'][0]['sections'][0]['composition'] = 'split';
    $mockup['pages'][0]['sections'][0]['focal_point'] = 'right';

    assetService()->generateForCandidate(
        lifecycleProject(),
        $mockup,
        1
    );

    $heroPrompt = collect($prompts)->first(
        fn (string $prompt) =>
            str_contains($prompt, 'Scene: Kopi Nusantara Pilihan')
    );

    expect($heroPrompt)
        ->not->toBeNull()
        ->and($heroPrompt)->toContain('landscape orientation')
        ->and($heroPrompt)->toContain('subject toward the right third')
        ->and($heroPrompt)->toContain('Main subject in the right third')
        ->and($heroPrompt)->toContain('No text anywhere in the image')
        ->and($heroPrompt)->toContain('not a graphic design, poster')
        // A quoted headline is what made the model print it across the photo.
        ->and($heroPrompt)->not->toContain('"Kopi Nusantara Pilihan"');
});

it('never sends the candidate layout brief to the image generator', function () {
    $prompts = [];

    Http::fake([
        'api.openai.com/*' => function ($request) use (&$prompts) {
            $prompts[] = (string) ($request->data()['prompt'] ?? '');

            return Http::response(['data' => [['b64_json' => base64_encode('FAKE-IMAGE')]]]);
        },
    ]);

    $mockup = lifecycleMockup();
    $mockup['design']['renderer_version'] = 2;

    assetService()->generateForCandidate(
        lifecycleProject(),
        $mockup,
        1,
        'Option 1 - Editorial: elegant editorial composition, expressive serif headings, a composed gallery; almost no boxed cards.'
    );

    expect($prompts)->not->toBeEmpty();

    foreach ($prompts as $prompt) {
        // Layout and typography words get drawn INTO the photo as type and collages.
        expect($prompt)->not->toContain('serif headings')
            ->and($prompt)->not->toContain('composed gallery')
            ->and($prompt)->toContain('cinematic natural light');
    }
});

it('requests a landscape image size for a landscape hero ratio', function () {
    $requestedSizes = [];

    Http::fake([
        'api.openai.com/*' => function ($request) use (&$requestedSizes) {
            $data = $request->data();

            $requestedSizes[] = [
                'prompt' => (string) ($data['prompt'] ?? ''),
                'size' => (string) ($data['size'] ?? ''),
            ];

            return Http::response([
                'data' => [[
                    'b64_json' => base64_encode('FAKE-IMAGE'),
                ]],
            ]);
        },
    ]);

    $mockup = lifecycleMockup();

    $mockup['design']['renderer_version'] = 2;
    $mockup['pages'][0]['sections'][0]['composition'] = 'split';
    $mockup['pages'][0]['sections'][0]['image_ratio'] = '4:3';

    assetService()->generateForCandidate(
        lifecycleProject(),
        $mockup,
        1
    );

    $heroRequest = collect($requestedSizes)->first(
        fn (array $request) =>
            str_contains(
                $request['prompt'],
                'Scene: Kopi Nusantara Pilihan'
            )
    );

    expect($heroRequest)
        ->not->toBeNull()
        ->and($heroRequest['size'])->toBe('1536x1024');
});

it('requests a portrait image size for a portrait hero ratio', function () {
    $requestedSizes = [];

    Http::fake([
        'api.openai.com/*' => function ($request) use (&$requestedSizes) {
            $data = $request->data();

            $requestedSizes[] = [
                'prompt' => (string) ($data['prompt'] ?? ''),
                'size' => (string) ($data['size'] ?? ''),
            ];

            return Http::response([
                'data' => [[
                    'b64_json' => base64_encode('FAKE-IMAGE'),
                ]],
            ]);
        },
    ]);

    $mockup = lifecycleMockup();

    $mockup['design']['renderer_version'] = 2;
    $mockup['pages'][0]['sections'][0]['composition'] = 'asymmetric_split';
    $mockup['pages'][0]['sections'][0]['image_ratio'] = '4:5';

    assetService()->generateForCandidate(
        lifecycleProject(),
        $mockup,
        1
    );

    $heroRequest = collect($requestedSizes)->first(
        fn (array $request) =>
            str_contains(
                $request['prompt'],
                'Scene: Kopi Nusantara Pilihan'
            )
    );

    expect($heroRequest)
        ->not->toBeNull()
        ->and($heroRequest['size'])->toBe('1024x1536');
});

it('requests a square image size for a square ratio', function () {
    $requestedSizes = [];

    Http::fake([
        'api.openai.com/*' => function ($request) use (&$requestedSizes) {
            $data = $request->data();

            $requestedSizes[] = [
                'prompt' => (string) ($data['prompt'] ?? ''),
                'size' => (string) ($data['size'] ?? ''),
            ];

            return Http::response([
                'data' => [[
                    'b64_json' => base64_encode('FAKE-IMAGE'),
                ]],
            ]);
        },
    ]);

    $mockup = lifecycleMockup();

    $mockup['design']['renderer_version'] = 2;
    $mockup['pages'][0]['sections'][0]['composition'] = 'split';
    $mockup['pages'][0]['sections'][0]['image_ratio'] = '1:1';

    assetService()->generateForCandidate(
        lifecycleProject(),
        $mockup,
        1
    );

    $heroRequest = collect($requestedSizes)->first(
        fn (array $request) =>
            str_contains(
                $request['prompt'],
                'Scene: Kopi Nusantara Pilihan'
            )
    );

    expect($heroRequest)
        ->not->toBeNull()
        ->and($heroRequest['size'])->toBe('1024x1024');
});

it('requests a portrait image size for section item slots with a portrait ratio', function () {
    $requestedSizes = [];

    Http::fake([
        'api.openai.com/*' => function ($request) use (&$requestedSizes) {
            $data = $request->data();

            $requestedSizes[] = [
                'prompt' => (string) ($data['prompt'] ?? ''),
                'size' => (string) ($data['size'] ?? ''),
            ];

            return Http::response([
                'data' => [[
                    'b64_json' => base64_encode('FAKE-IMAGE'),
                ]],
            ]);
        },
    ]);

    $mockup = lifecycleMockup();

    $mockup['design']['renderer_version'] = 2;

    // Hero valid tanpa gambar.
    $mockup['pages'][0]['sections'][0]['composition'] = 'centered_minimal';

    // Section Menu adalah card section yang memang memakai foto.
    $mockup['pages'][0]['sections'][2]['composition'] = 'asymmetric_cards';
    $mockup['pages'][0]['sections'][2]['image_ratio'] = '4:5';

    assetService()->generateForCandidate(
        lifecycleProject(),
        $mockup,
        1
    );

    $itemRequest = collect($requestedSizes)->first(
        fn (array $request) =>
            str_contains(
                $request['prompt'],
                'Scene: Kopi Gayo'
            )
    );

    expect($itemRequest)
        ->not->toBeNull()
        ->and($itemRequest['size'])->toBe('1024x1536');
});
