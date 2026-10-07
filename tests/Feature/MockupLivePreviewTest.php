<?php

use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * The live demo must be the approved blueprint, rendered — not a fourth design.
 * These tests pin the routes, their guards, and that each section renders in
 * the form its content calls for.
 */
function v2Blueprint(): array
{
    return require base_path('tests/Fixtures/v2-blueprint.php');
}

function previewProject(array $candidates, string $status = 'draft'): Project
{
    $project = Project::create([
        'name' => 'Website Nusa Trails',
        'client_name' => 'Nusa Trails',
        'code' => 'NT-0001',
        'type' => 'Travel',
        'status' => 'request',
    ]);

    Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => $status,
        'ai_reasoning' => json_encode(['mockup' => $candidates[0], 'mockup_candidates' => $candidates, 'selected_mockup_index' => 0]),
    ]);

    return $project;
}

function legacyCandidate(): array
{
    return [
        'website_concept' => 'Kopi single origin.',
        'global_cta' => 'Pesan',
        'design' => ['primary_color' => '#1F3A5F', 'accent_color' => '#C87941', 'layout_variant' => 'split-left'],
        'pages' => [[
            'name' => 'Home',
            'sections' => [
                ['name' => 'Hero', 'headline' => 'Kopi Nusantara'],
                ['name' => 'Kenapa', 'headline' => 'Kenapa Kami', 'items' => [['title' => 'Segar'], ['title' => 'Adil']]],
                ['name' => 'Menu', 'headline' => 'Menu Unggulan', 'items' => [['title' => 'Gayo'], ['title' => 'Toraja']]],
                ['name' => 'Testimoni', 'headline' => 'Kata Mereka', 'description' => 'Tidak pernah tampil di PNG.'],
            ],
        ]],
    ];
}

beforeEach(function () {
    Storage::fake('public');
});

it('renders the live website for a candidate', function () {
    $project = previewProject([v2Blueprint()]);

    $this->actingAs(User::factory()->create())
        ->get(route('pages.projects.mockup.preview', [$project, 0]))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Jelajahi Nusantara Tanpa Ribet')
        ->assertSee('name="viewport"', false)
        // a real website: no dashboard sidebar or admin controls
        ->assertDontSee('Workspace')
        ->assertDontSee('csrf', false);
});

it('404s for a candidate that does not exist', function () {
    $project = previewProject([v2Blueprint()]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('pages.projects.mockup.preview', [$project, 5]))->assertNotFound();
    $this->actingAs($user)->get(route('pages.projects.mockup.demo', [$project, 5]))->assertNotFound();
    $this->actingAs($user)->get('/projects/' . $project->id . '/mockup/abc/preview')->assertNotFound();
});

it('404s for a project without a proposal', function () {
    $project = Project::create(['name' => 'Kosong', 'client_name' => 'Kosong', 'status' => 'request']);

    $this->actingAs(User::factory()->create())
        ->get(route('pages.projects.mockup.preview', [$project, 0]))
        ->assertNotFound();
});

it('keeps the preview behind authentication', function () {
    $project = previewProject([v2Blueprint()]);

    $this->get(route('pages.projects.mockup.preview', [$project, 0]))->assertRedirect(route('login'));
    $this->get(route('pages.projects.mockup.demo', [$project, 0]))->assertRedirect(route('login'));
});

it('wraps the demo in desktop, tablet, mobile and fullscreen controls around an iframe', function () {
    $project = previewProject([v2Blueprint(), v2Blueprint()]);

    $this->actingAs(User::factory()->create())
        ->get(route('pages.projects.mockup.demo', [$project, 1]))
        ->assertOk()
        ->assertSeeInOrder(['Desktop', 'Tablet', 'Mobile', 'Fullscreen'])
        ->assertSee('<iframe', false)
        ->assertSee(route('pages.projects.mockup.preview', [$project, 1]), false)
        ->assertSee('Opsi 2');
});

it('navigates every page of the sitemap, with unique slugs', function () {
    $project = previewProject([v2Blueprint()]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pages.projects.mockup.preview', [$project, 0, 'page' => 'paket']))
        ->assertOk()
        ->assertSee('Pilih paket')
        ->assertSee('plan--featured', false)
        ->assertSee('page=tentang', false)
        // the second page called "Home" cannot take over the front page slug
        ->assertSee('page=home-2', false);

    // An unknown page falls back to Home instead of erroring.
    $this->actingAs($user)
        ->get(route('pages.projects.mockup.preview', [$project, 0, 'page' => '../../etc']))
        ->assertOk()
        ->assertSee('Jelajahi Nusantara Tanpa Ribet');
});

it('renders each section in the form its content calls for', function () {
    $project = previewProject([v2Blueprint()]);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('pages.projects.mockup.preview', [$project, 0]))
        ->getContent();

    // FAQ is an FAQ: expandable question/answer pairs.
    expect($html)->toContain('<details class="faq-item"')
        ->toContain('<summary>Apakah termasuk tiket pesawat?</summary>')
        // Testimonials lead with a quote and an attribution — not a card grid.
        ->toContain('class="quote-lead"')
        ->toContain('Tur terbaik yang pernah saya ikuti.')
        // The gallery is a composed mosaic.
        ->toContain('class="mosaic mosaic--')
        ->toContain('tile--0')
        ->toContain('class="stats-grid"')
        ->toContain('12.000+');

    expect(substr_count($html, 'section--testimonials'))->toBe(1);
    expect($html)->not->toMatch('/section--testimonials[^>]*>\s*<div class="wrap"[^>]*>\s*<div class="grid/');
});

it('does not render site chrome sections from the content stage as page content', function () {
    $project = previewProject([v2Blueprint()]);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('pages.projects.mockup.preview', [$project, 0]))
        ->getContent();

    expect(substr_count($html, '<footer class="footer">'))->toBe(1)
        ->and($html)->not->toContain('<h2>Footer</h2>');
});

it('still renders a blueprint approved before V2, exactly as its PNG showed it', function () {
    $project = previewProject([legacyCandidate()], 'approved');

    $html = $this->actingAs(User::factory()->create())
        ->get(route('pages.projects.mockup.preview', [$project, 0]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('hero--c-split')
        ->toContain('icon-row chips')
        ->toContain('Menu Unggulan')
        // the legacy PNG never showed this section, so neither does the demo
        ->not->toContain('Kata Mereka');
});

it('renders the preview and demo for a legacy proposal with only one mockup field', function () {
    $project = Project::create([
        'name' => 'Website Kopi Nusantara',
        'client_name' => 'Kopi Nusantara',
        'code' => 'KN-LEGACY',
        'status' => 'request',
    ]);
    Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => 'pending',
        'ai_reasoning' => json_encode(['mockup' => legacyCandidate(), 'selected_mockup_index' => 0]),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pages.projects.mockup.preview', [$project, 0]))
        ->assertOk()
        ->assertSee('Kopi Nusantara');

    $this->actingAs($user)
        ->get(route('pages.projects.mockup.demo', [$project, 0]))
        ->assertOk()
        ->assertSee('Demo Opsi 1')
        ->assertSee('Kopi Nusantara')
        ->assertDontSee('aria-label="Opsi desain"', false);
});

it('shows a legacy mockup in the workspace when the candidate list is empty', function () {
    $project = Project::create([
        'name' => 'Website Kopi Nusantara',
        'client_name' => 'Kopi Nusantara',
        'code' => 'KN-LEGACY-WORKSPACE',
        'status' => 'request',
    ]);
    Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => 'pending',
        'ai_reasoning' => json_encode([
            'mockup' => legacyCandidate(),
            'mockup_candidates' => [],
            'selected_mockup_index' => 0,
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('pages.project-workspace', ['project' => $project->id]))
        ->assertOk()
        ->assertSee('Buat WordPress siap install');
});

it('serves photographs by public URL, never an absolute storage path', function () {
    $candidate = v2Blueprint();
    Storage::disk('public')->put('mockup-assets/nt-0001/candidate-1/hero.jpg', 'JPEG');
    $candidate['assets'] = ['pages' => ['home' => [
        'hero' => ['slot' => 'home.hero', 'path' => 'mockup-assets/nt-0001/candidate-1/hero.jpg', 'required' => true],
        // a path that tries to escape the disk is ignored
        'sections' => [2 => ['role' => 'card_grid', 'items' => [0 => ['path' => '../../.env', 'required' => false]]]],
    ]]];
    $project = previewProject([$candidate]);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('pages.projects.mockup.preview', [$project, 0]))
        ->getContent();

    expect($html)->toContain(Storage::disk('public')->url('mockup-assets/nt-0001/candidate-1/hero.jpg'))
        ->not->toContain(storage_path())
        ->not->toContain('.env');
});

it('makes three candidates look like three layouts', function () {
    $second = v2Blueprint();
    $second['pages'][0]['sections'][0]['composition'] = 'split';
    $second['pages'][0]['sections'][2]['composition'] = 'standard_cards';
    $second['pages'][0]['sections'][3]['composition'] = 'cta';

    $project = previewProject([v2Blueprint(), $second]);
    $user = User::factory()->create();

    $first = $this->actingAs($user)->get(route('pages.projects.mockup.preview', [$project, 0]))->getContent();
    $other = $this->actingAs($user)->get(route('pages.projects.mockup.preview', [$project, 1]))->getContent();

    expect($first)->toContain('hero--c-background_image')->toContain('class="grid grid--feature-first"')
        ->and($other)->toContain('hero--c-split')->not->toContain('class="grid grid--feature-first"');
});
