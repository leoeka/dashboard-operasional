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
    Http::fake(['api.openai.com/*' => function ($request) {
        preg_match('/Subject: "(.*?)"/', (string) ($request->data()['prompt'] ?? ''), $m);

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

    Http::fake(['api.openai.com/*' => Http::response(['data' => [['b64_json' => base64_encode('REGENERATED')]]])]);
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

    config(['services.anthropic.key' => 'test-anthropic-key']);
    Http::fake([
        'api.anthropic.com/*' => Http::response(
            'data: ' . json_encode([
                'type' => 'content_block_delta',
                'delta' => ['type' => 'text_delta', 'text' => json_encode(['files' => ['exito-client-theme/style.css' => '/* theme */']])],
            ]) . "\n"
        ),
        'api.openai.com/*' => Http::response(['data' => [['b64_json' => base64_encode('REGENERATED')]]]),
    ]);

    $bundle = app(BundleBuilderService::class)->build($project->fresh());

    // No new photo was drawn anywhere in the build.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai.com'));

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
