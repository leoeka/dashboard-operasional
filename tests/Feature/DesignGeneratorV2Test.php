<?php

use App\Models\Project;
use App\Services\ElementorPageBuilderService;
use App\Services\GenerateMockupGptService;
use App\Services\ScreenshotService;
use App\Support\CompositionSpec;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Design Engine V2: the designer art-directs EVERY section, from a closed list
 * its content can fill, and the result is stamped to render every section.
 */
class FakeReferenceScreenshots extends ScreenshotService
{
    public function capture(string $url, string $relativePath): ?string
    {
        Storage::disk('public')->put($relativePath, 'PNG-REFERENCE');

        return $relativePath;
    }

    public function captureHtml(string $html, string $relativePath): ?string
    {
        return $relativePath;
    }
}

function v2Analysis(): array
{
    $fixture = require base_path('tests/Fixtures/v2-blueprint.php');

    return [
        'business_analysis' => ['value_proposition' => 'Tur kecil dengan pemandu lokal.'],
        'target_market' => ['segment' => 'Pelancong urban'],
        'sitemap' => [
            'website_concept' => $fixture['website_concept'],
            'global_cta' => $fixture['global_cta'],
            'seo' => [],
            // content only — the designer adds every design decision
            'pages' => array_map(fn (array $page) => [
                'name' => $page['name'],
                'sections' => array_map(fn (array $s) => array_diff_key($s, ['composition' => true]), $page['sections']),
            ], $fixture['pages']),
        ],
    ];
}

/** @param array<int, array> $sectionsPerCall one designer `sections` list per candidate call */
function fakeV2Designer(array $sectionsPerCall, array &$prompts): void
{
    $call = 0;
    Http::fake(function (Request $request) use (&$call, $sectionsPerCall, &$prompts) {
        if (str_contains($request->url(), 'images/generations')) {
            return Http::response(['data' => [['b64_json' => base64_encode('PHOTO')]]]);
        }

        $prompts[] = $request->data()['messages'][0]['content'];
        $sections = $sectionsPerCall[$call++ % count($sectionsPerCall)];

        return Http::response(['choices' => [['message' => ['content' => json_encode([
            'style' => 'Editorial',
            'primary_color' => '#12343B',
            'secondary_color' => '#F4F1EA',
            'accent_color' => '#E07A3F',
            'font_heading' => 'Playfair Display',
            'font_body' => 'Inter',
            'sections' => $sections,
        ])]]]]);
    });
}

function v2Project(array $attributes = []): Project
{
    return Project::create(array_merge([
        'name' => 'Website Nusa Trails',
        'client_name' => 'Nusa Trails',
        'code' => 'NT-2001',
        'type' => 'Travel',
        'status' => 'request',
    ], $attributes));
}

beforeEach(function () {
    Storage::fake('public');
    config(['services.openai.key' => 'test-key', 'services.openai.mockup_candidate_count' => 3]);
    app()->instance(ScreenshotService::class, new FakeReferenceScreenshots());
});

it('briefs the designer with every section and only the compositions its content can fill', function () {
    $prompts = [];
    fakeV2Designer([[]], $prompts);

    app(GenerateMockupGptService::class)->generateMockup(v2Project(), v2Analysis());
    $prompt = $prompts[0][0]['text'];

    expect($prompt)
        ->toContain('Do not design a generic WordPress page')
        ->toContain('repeated 3-column card rows')
        ->toContain('Would a professional designer intentionally make this layout decision?')
        ->toContain('[7] faq "Pertanyaan umum" — faq, 2 items → faq')
        ->toContain('[5] testimonial "Kata para tamu" — testimonials, 2 items → testimonial_grid')
        ->toContain('Page 2 "Paket":')
        // site chrome is never offered for design
        ->not->toContain('[9] footer');
});

it('applies addressed compositions to every page and stamps the blueprint as full-page', function () {
    $prompts = [];
    fakeV2Designer([[
        ['page' => 0, 'index' => 0, 'composition' => 'fullscreen_image'],
        ['page' => 0, 'index' => 3, 'composition' => 'editorial_text_image', 'image_position' => 'left'],
        ['page' => 0, 'index' => 6, 'composition' => 'gallery'],
        ['page' => 1, 'index' => 1, 'composition' => 'team'],
    ]], $prompts);

    $mockup = app(GenerateMockupGptService::class)->generateMockup(v2Project(), v2Analysis());

    expect($mockup['design']['renderer_version'])->toBe(CompositionSpec::RENDERER_VERSION)
        ->and($mockup['pages'][0]['sections'][0]['composition'])->toBe('fullscreen_image')
        ->and($mockup['pages'][0]['sections'][3]['image_position'])->toBe('left')
        ->and($mockup['pages'][1]['sections'][1]['composition'])->toBe('team');
});

it('refuses a composition the section content cannot fill, and never lets the designer rewrite content', function () {
    $prompts = [];
    fakeV2Designer([[
        // an FAQ cannot become a card grid
        ['page' => 0, 'index' => 7, 'composition' => 'standard_cards', 'columns' => 3],
        // design fields only — the headline echoed back must not overwrite copy
        ['page' => 0, 'index' => 1, 'composition' => 'feature_grid', 'headline' => 'HIJACKED', 'items' => []],
    ]], $prompts);

    $mockup = app(GenerateMockupGptService::class)->generateMockup(v2Project(), v2Analysis());
    $faq = $mockup['pages'][0]['sections'][7];
    $features = $mockup['pages'][0]['sections'][1];

    expect($faq)->not->toHaveKey('composition')->not->toHaveKey('columns')
        ->and($features['headline'])->toBe('Perjalanan yang dirancang, bukan dijual')
        ->and($features['items'])->toHaveCount(4);

    $plan = (new ElementorPageBuilderService())->describeSections($mockup['pages'][0]['sections'], $mockup['design']);
    expect($plan[7]['renderer'])->toBe('faq');
});

it('produces three candidates with genuinely different layouts', function () {
    $prompts = [];
    fakeV2Designer([
        [['page' => 0, 'index' => 0, 'composition' => 'background_image'], ['page' => 0, 'index' => 2, 'composition' => 'asymmetric_cards'], ['page' => 0, 'index' => 3, 'composition' => 'editorial_text_image']],
        [['page' => 0, 'index' => 0, 'composition' => 'asymmetric_split'], ['page' => 0, 'index' => 2, 'composition' => 'standard_cards'], ['page' => 0, 'index' => 3, 'composition' => 'cta']],
        [['page' => 0, 'index' => 0, 'composition' => 'editorial', 'image_required' => false], ['page' => 0, 'index' => 1, 'composition' => 'alternating_media'], ['page' => 0, 'index' => 2, 'composition' => 'feature_grid']],
    ], $prompts);

    $service = app(GenerateMockupGptService::class);
    $candidates = $service->generateMockupCandidates(v2Project(), v2Analysis());
    $fingerprints = array_map(fn (array $c) => $service->designFingerprint($c), $candidates);

    expect($candidates)->toHaveCount(3)
        ->and(array_unique($fingerprints))->toHaveCount(3)
        // every content section renders, not just three of them
        ->and(count(explode('|', $fingerprints[0])))->toBe(9);
});

it('designs from scratch when the project has no reference', function () {
    $prompts = [];
    fakeV2Designer([[]], $prompts);

    app(GenerateMockupGptService::class)->generateMockup(v2Project(), v2Analysis());

    expect($prompts[0][0]['text'])->toContain('no client reference or competitor screenshots')
        ->and($prompts[0])->toHaveCount(1); // text only, no image attached
});

it('attaches a screenshot of the client reference URL as the dominant direction', function () {
    $prompts = [];
    fakeV2Designer([[]], $prompts);
    $project = v2Project(['design_reference_type' => 'url', 'design_reference_url' => 'https://example.com']);

    app(GenerateMockupGptService::class)->generateMockup($project, v2Analysis());

    expect($prompts[0][0]['text'])->toContain('the client provided their own reference')
        ->and($prompts[0][1]['type'])->toBe('image_url');
});

it('attaches an uploaded design reference image', function () {
    $prompts = [];
    fakeV2Designer([[]], $prompts);
    // a real 1x1 PNG, so mime detection accepts it
    Storage::disk('public')->put('design-refs/upload.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    $project = v2Project(['design_reference_type' => 'image', 'design_reference_path' => 'design-refs/upload.png']);

    app(GenerateMockupGptService::class)->generateMockup($project, v2Analysis());

    expect($prompts[0][0]['text'])->toContain('the client provided their own reference')
        ->and($prompts[0][1]['image_url']['url'])->toStartWith('data:image/png;base64,');
});
