<?php

use App\Models\Project;
use App\Models\Proposal;
use App\Services\BlueprintManifestService;
use App\Services\BundleBuilderService;
use App\Services\ElementorPageBuilderService;
use App\Services\MockupAssetService;
use App\Support\MockupSite;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * LIVE PREVIEW = APPROVED BLUEPRINT = WORDPRESS RESULT.
 *
 * One V2 blueprint, three consumers: the site renderer (demo + PNG), the
 * Gutenberg builder and the implementation manifest. These tests hold that
 * they render the same sections, in the same order, in the same form.
 */
function parityBlueprint(): array
{
    return require base_path('tests/Fixtures/v2-blueprint.php');
}

function wpPages(?array $mockup = null, array $imageMap = []): array
{
    $mockup ??= parityBlueprint();

    return (new ElementorPageBuilderService())->buildPages($mockup['pages'], $mockup['design'], $imageMap, $mockup['global_cta'] ?? '');
}

/** Every `<!-- wp:name` must be closed by `<!-- /wp:name`, or the Block Editor reports invalid content. */
function assertBalancedBlocks(string $html): void
{
    preg_match_all('/<!-- wp:([a-z\-\/]+)[ \{]/', $html, $open);
    preg_match_all('/<!-- \/wp:([a-z\-\/]+) -->/', $html, $close);

    expect(array_count_values($open[1]))->toEqual(array_count_values($close[1]));
}

it('renders every section the live demo shows, in the same order, in WordPress', function () {
    $mockup = parityBlueprint();
    $html = wpPages($mockup)['home']['html'];
    $site = MockupSite::build($mockup, ['page' => 'home']);

    $previewSections = array_values(array_filter($site['page']['sections'], fn ($s) => $s['renderer'] !== 'hero'));
    preg_match_all('/"className":"exito-section exito-([a-z]+)"/', $html, $wpRenderers);

    expect($wpRenderers[1])->toBe(array_column($previewSections, 'renderer'));

    $position = 0;
    foreach ($previewSections as $section) {
        $found = strpos($html, e($section['headline']), $position);
        expect($found)->not->toBeFalse("\"{$section['headline']}\" is in the demo but missing from WordPress");
        $position = $found;
    }
});

it('builds FAQ, testimonials, stats and gallery from core blocks, not generic cards', function () {
    $html = wpPages(null, ['home' => ['sections' => [6 => [0 => 'home-section-6-item-0.jpg', 1 => 'home-section-6-item-1.jpg']]]])['home']['html'];

    expect($html)
        ->toContain('<!-- wp:details {"showContent":true} -->')
        ->toContain('<summary>Apakah termasuk tiket pesawat?</summary>')
        ->toContain('<!-- wp:quote {"className":"exito-quote-lead"} -->')
        ->toContain('<cite>Rina, Jakarta</cite>')
        ->toContain('exito-stat-value')
        ->toContain('12.000+')
        ->toContain('<!-- wp:gallery')
        ->toContain('__EXITO_IMAGE:home-section-6-item-0.jpg__')
        // the unphotographed gallery items become colour tiles, as in the demo
        ->toContain('exito-tile-caption');

    assertBalancedBlocks($html);
});

it('produces balanced, editable block markup on every page', function () {
    foreach (wpPages() as $slug => $page) {
        expect($page['html'])->not->toBe('', "page {$slug} is empty");
        assertBalancedBlocks($page['html']);
    }
});

it('builds the whole sitemap with Home first and no slug collisions', function () {
    $pages = wpPages();

    expect(array_keys($pages))->toBe(['home', 'tentang', 'paket', 'home-2'])
        ->and($pages['paket']['html'])->toContain('Pilih paket')->toContain('exito-plans')
        ->and($pages['tentang']['html'])->toContain('exito-team')->toContain('exito-monogram');
});

it('describes exactly the sections that ship in the implementation manifest', function () {
    $mockup = parityBlueprint();
    $manifest = (new BlueprintManifestService(new ElementorPageBuilderService()))->build($mockup);
    $home = collect($manifest['sections'])->where('page', 'home');

    // hero + 8 content sections; the content stage's "Footer" is theme chrome
    expect($home)->toHaveCount(9)
        ->and($home->pluck('renderer')->all())->toBe(['hero', 'features', 'cards', 'editorial', 'stats', 'testimonials', 'gallery', 'faq', 'cta'])
        ->and($home->firstWhere('renderer', 'stats')['layout']['background'])->toBe('#12343B')
        ->and(collect($manifest['pages'])->pluck('slug')->all())->toBe(['home', 'tentang', 'paket', 'home-2']);
});

it('keeps a legacy blueprint to the three sections its PNG showed', function () {
    $legacy = [
        'design' => ['primary_color' => '#1F3A5F', 'layout_variant' => 'split-right'],
        'pages' => [['name' => 'Home', 'sections' => [
            ['name' => 'Hero', 'headline' => 'A'],
            ['name' => 'Kenapa', 'headline' => 'B', 'items' => [['title' => 'x']]],
            ['name' => 'Menu', 'headline' => 'C', 'items' => [['title' => 'y']]],
            ['name' => 'FAQ', 'headline' => 'Tanya', 'items' => [['question' => 'q', 'answer' => 'a']]],
        ]]],
    ];

    $html = wpPages($legacy)['home']['html'];

    expect($html)->not->toContain('Tanya')->not->toContain('exito-section');
});

it('photographs every photo-led V2 section and ships each photo under its own name', function () {
    Storage::fake('public');
    config(['services.openai.key' => 'test-key']);
    Http::fake(['api.openai.com/v1/images/generations' => function ($request) {
        preg_match('/Scene: (.+?)(?: - |\. The scene)/', (string) ($request->data()['prompt'] ?? ''), $m);

        return Http::response(['data' => [['b64_json' => base64_encode('PHOTO:' . ($m[1] ?? '?'))]]]);
    }]);

    $project = Project::create(['name' => 'Nusa', 'client_name' => 'Nusa', 'code' => 'NT-3001', 'type' => 'Travel', 'status' => 'request']);
    $mockup = parityBlueprint();
    $assets = (new MockupAssetService(new ElementorPageBuilderService()))->generateForCandidate($project, $mockup, 1);
    $mockup['assets'] = $assets['manifest'];

    $sections = $assets['manifest']['pages']['home']['sections'];
    expect(array_keys($sections))->toBe([2, 3, 6])
        ->and($sections[2]['role'])->toBe('card_grid')
        ->and($sections[3]['role'])->toBe('editorial_media')
        ->and($sections[6]['role'])->toBe('gallery');

    // The demo and the PNG get the same photos, per section.
    expect($assets['images']['sections'][6])->toHaveCount(4)
        ->and($assets['images']['items'])->toHaveCount(3);

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
    ]);

    $bundle = app(BundleBuilderService::class)->build($project->fresh());

    expect(array_keys($bundle['elementor_pages']))->toBe(['home', 'tentang', 'paket', 'home-2'])
        ->and($bundle['section_images'])->toHaveKeys(['home-hero.jpg', 'home-item-0.jpg', 'home-section-3-item-0.jpg', 'home-section-6-item-0.jpg'])
        ->and($bundle['section_images']['home-section-6-item-0.jpg'])->toBe('PHOTO:Padar')
        ->and($bundle['elementor_pages']['home']['html'])
            ->toContain('__EXITO_IMAGE:home-item-0.jpg__')
            ->toContain('__EXITO_IMAGE:home-section-3-item-0.jpg__')
            ->toContain('__EXITO_IMAGE:home-section-6-item-3.jpg__')
        ->and($bundle['mockup_rendering']['pages']['home']['html'])
            ->toContain('<!-- wp:html -->')
            ->toContain('class="site full-page"')
            ->toContain('__EXITO_IMAGE:home-hero.jpg__')
            ->toContain('__EXITO_IMAGE:home-section-6-item-3.jpg__')
        ->and($bundle['mockup_rendering']['css'])
            ->toContain('.hero--split')
            ->not->toContain('<style');
});
