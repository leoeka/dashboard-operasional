<?php

use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * The workspace is one screen per workstream: a rail on the left carries the
 * project identity plus the stages of the selected track, the panel on the
 * right carries the stage. The proposal document itself still lives on its own
 * page, and these tests pin both halves: which stage opens by default, and what
 * the preview page does when there is no PDF.
 */
function workspaceProject(array $candidates): Project
{
    static $sequence = 0;

    $project = Project::create([
        'name' => 'Website Nusa Trails',
        'client_name' => 'Nusa Trails',
        'code' => 'NT-' . str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT),
        'type' => 'Travel',
        'status' => 'request',
    ]);

    Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => 'draft',
        'ai_reasoning' => json_encode([
            'mockup' => $candidates[0],
            'mockup_candidates' => $candidates,
            'selected_mockup_index' => 0,
        ]),
    ]);

    return $project;
}

function workspaceCandidate(): array
{
    return [
        'website_concept' => 'Kopi single origin.',
        'global_cta' => 'Pesan',
        'design' => ['primary_color' => '#1F3A5F', 'accent_color' => '#C87941', 'layout_variant' => 'split-left'],
        'pages' => [[
            'name' => 'Home',
            'sections' => [
                ['name' => 'Hero', 'headline' => 'Kopi Nusantara'],
                ['name' => 'Menu', 'headline' => 'Menu Unggulan', 'items' => [['title' => 'Gayo']]],
            ],
        ]],
    ];
}

beforeEach(function () {
    Storage::fake('public');
});

/** The workspace rendered to a string, entities decoded so the markup reads literally. */
function workspaceHtml(Project $project): string
{
    return html_entity_decode(
        test()->actingAs(User::factory()->create())
            ->get(route('pages.project-workspace', ['project' => $project->id]))
            ->assertOk()
            ->getContent(),
        ENT_QUOTES
    );
}

/**
 * The rail state the page is born with: which stages exist per track, which
 * track and stage Alpine opens. Read out of x-data, where Blade put the JSON in
 * with @js.
 */
function workspaceRailState(Project $project): array
{
    $html = workspaceHtml($project);
    $marker = 'x-data="workspaceRail(';
    $start = strpos($html, $marker) + strlen($marker);
    $call = substr($html, $start, strpos($html, '"', $start) - $start);

    preg_match_all('/JSON\.parse\(\'(.*?)\'\)/', $call, $matches);

    // @js escapes " ' & < > so the payload survives the attribute, which makes it
    // valid JS but not valid JSON until those five escapes are undone.
    $decode = fn(string $payload) => json_decode(
        strtr($payload, ['\u0022' => '"', '\u0027' => "'", '\u0026' => '&', '\u003c' => '<', '\u003e' => '>']),
        true
    );

    return [
        'stages' => $decode($matches[1][0]),
        'initial' => $decode($matches[1][1]),
    ];
}

it('puts the identity, the stage rail and the selected stage in one grid', function () {
    $project = workspaceProject([workspaceCandidate(), workspaceCandidate()]);

    $this->actingAs(User::factory()->create())
        ->get(route('pages.project-workspace', ['project' => $project->id]))
        ->assertOk()
        // rail holds 260px, panel takes the rest and may never push it
        ->assertSee('lg:grid-cols-[260px_minmax(0,1fr)]', false)
        ->assertSee('Pembuatan Web')
        ->assertSeeInOrder(['Website Type', 'Mockup &amp; build'], false)
        // the panel keeps the order the proposal card hands over to the mockup card
        ->assertSeeInOrder(['Project Proposal', 'Lihat Proposal', 'Website Mockup', 'id="mockup-candidate-grid"'], false)
        ->assertSee(route('pages.projects.proposal.preview', $project), false)
        ->assertSee('md:grid-cols-3', false)
        ->assertDontSee('Document Viewer')
        ->assertDontSee('h-[650px]', false);
});

it('marks each stage with the data that decides it', function () {
    $project = workspaceProject([workspaceCandidate()]);
    $project->update([
        'wants_seo' => true,
        'seo_requirements' => ['target_url' => 'https://nusa-trails.example'],
    ]);

    // Proposal and kebutuhan have their data. The mockup stage does not: its
    // job ends at the ZIP the client gets, so options alone are not enough.
    $html = workspaceHtml($project);
    expect(substr_count($html, 'bx-check-circle'))->toBe(2)
        ->and(substr_count($html, 'bx-circle'))->toBe(4);

    $project->bundles()->create([
        'bundle_path' => storage_path('app/bundles/'.$project->id),
        'zip_path' => storage_path('app/bundles/'.$project->id.'/theme-install.zip'),
        'status' => 'exported',
        'built_at' => now(),
        'exported_at' => now(),
    ]);

    $built = workspaceHtml($project);
    expect(substr_count($built, 'bx-check-circle'))->toBe(3)
        ->and(substr_count($built, 'bx-circle'))->toBe(3);

    $nothing = workspaceProject([workspaceCandidate()]);
    $nothing->update(['wants_seo' => true]);
    $bare = workspaceHtml($nothing);
    expect(substr_count($bare, 'bx-check-circle'))->toBe(1)
        ->and(substr_count($bare, 'bx-circle'))->toBe(5);
});

it('opens the first stage that still needs work, even across tracks', function () {
    $project = workspaceProject([workspaceCandidate()]);
    $project->update(['wants_seo' => true]);

    // The client's answer is recorded but no ZIP exists yet: the web track
    // still owns the screen, open on the stage that is waiting.
    $waiting = workspaceRailState($project);
    expect($waiting['initial']['track'])->toBe('web')
        ->and($waiting['initial']['step'])->toBe(['web' => 'mockup', 'seo' => 'kebutuhan']);

    $project->bundles()->create([
        'bundle_path' => storage_path('app/bundles/'.$project->id),
        'zip_path' => storage_path('app/bundles/'.$project->id.'/theme-install.zip'),
        'status' => 'exported',
        'built_at' => now(),
        'exported_at' => now(),
    ]);

    $state = workspaceRailState($project);

    // proposal and mockup are both finished, the seo track is not: open there
    expect($state['initial']['track'])->toBe('seo')
        ->and($state['initial']['step'])->toBe(['web' => 'proposal', 'seo' => 'kebutuhan'])
        ->and($state['stages']['seo'])->toBe(['kebutuhan', 'analisis', 'performa', 'traffic']);
});

it('keeps a web only project on the web track', function () {
    $project = workspaceProject([workspaceCandidate()]);
    Proposal::where('project_id', $project->id)->delete();

    $state = workspaceRailState($project);

    expect($state['stages'])->toHaveKey('web')
        ->and($state['stages'])->not->toHaveKey('seo')
        ->and($state['initial']['track'])->toBe('web')
        ->and($state['initial']['step']['web'])->toBe('proposal');
});


it('names the seo track after what the client actually ordered', function () {
    $seoOnly = workspaceProject([workspaceCandidate()]);
    $seoOnly->update(['wants_seo' => true]);
    expect(workspaceHtml($seoOnly))->toContain(">SEO<")
        ->and(workspaceHtml($seoOnly))->not->toContain('>Backlink<');

    $both = workspaceProject([workspaceCandidate()]);
    $both->update(['wants_seo' => true, 'wants_backlink' => true]);
    expect(workspaceHtml($both))->toContain('SEO & Backlink');

    // backlink only: the ordered scope shows, the publishing track does not
    $backlinkOnly = workspaceProject([workspaceCandidate()]);
    $backlinkOnly->update(['wants_backlink' => true, 'backlink_requirements' => ['quantity' => 10]]);
    $html = workspaceHtml($backlinkOnly);
    expect($html)->toContain('>Backlink<')
        ->and($html)->toContain('Backlink Requirements')
        ->and($html)->toContain('10 backlink');
});

it('does not ship controls that cannot do anything yet', function () {
    $project = workspaceProject([workspaceCandidate()]);
    $project->update(['wants_seo' => true, 'wants_backlink' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('pages.project-workspace', ['project' => $project->id]))
        ->assertOk()
        ->assertDontSee('Connect Website')
        ->assertDontSee('Generate Article')
        ->assertDontSee('Not connected')
        ->assertDontSee('ringkasan', false);
});

it('hands the finished ZIP to the same screen that recorded the choice', function () {
    $project = workspaceProject([workspaceCandidate(), workspaceCandidate()]);

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.proposal.approve', $project), ['mockup_index' => 1])
        ->assertRedirect();

    $bundle = $project->bundles()->create([
        'bundle_path' => storage_path('app/bundles/'.$project->id),
        'zip_path' => storage_path('app/bundles/'.$project->id.'/theme-install.zip'),
        'status' => 'exported',
        'built_at' => now(),
        'exported_at' => now(),
    ]);

    $html = workspaceHtml($project);
    expect($html)->toContain('Unduh WordPress')
        ->and($html)->toContain('Buat ulang WordPress')
        ->and($html)->not->toContain('Buat WordPress siap install');

    // A failed replacement build must not leave the older ZIP presented as current
    $bundle->update(['status' => 'failed', 'exported_at' => null]);
    $failed = workspaceHtml($project);
    expect($failed)->toContain('Build terakhir gagal.')
        ->and($failed)->toContain('Buat WordPress siap install')
        ->and($failed)->not->toContain('Unduh WordPress');
});

it('keeps #performa reachable for the screenshot redirect that lands on it', function () {
    $project = workspaceProject([workspaceCandidate()]);
    $project->update([
        'wants_seo' => true,
        'seo_requirements' => [
            'target_url' => 'https://nusa-trails.example',
            'pagespeed' => ['analyzed_at' => '2026-10-17 09:00'],
        ],
    ]);

    $html = workspaceHtml($project);

    // SeoBacklinkController::manualScreenshotRedirect() appends #performa, and the
    // forms in the performa panel still ask to come back there
    expect($html)->toContain("'performa'")
        ->and($html)->toContain('name="return_tab" value="performa"');
});


it('drops the inline viewer even when there is only one mockup option', function () {
    $project = workspaceProject([workspaceCandidate()]);

    $this->actingAs(User::factory()->create())
        ->get(route('pages.project-workspace', ['project' => $project->id]))
        ->assertOk()
        ->assertSee('Lihat Proposal')
        ->assertSee('Buat WordPress siap install')
        ->assertDontSee('<iframe', false)
        ->assertDontSee('Mockup not yet created');
});

it('still generates from the workspace while no proposal exists', function () {
    $project = workspaceProject([workspaceCandidate()]);
    Proposal::where('project_id', $project->id)->delete();

    $this->actingAs(User::factory()->create())
        ->get(route('pages.project-workspace', ['project' => $project->id]))
        ->assertOk()
        ->assertSee('id="generate-proposal-btn"', false)
        ->assertSee('Mockup not yet created')
        ->assertDontSee('Lihat Proposal');
});

it('renders the proposal on its own page and points back to the workspace', function () {
    $project = workspaceProject([workspaceCandidate()]);
    $project->latestProposal->update(['pdf_path' => 'proposals/nt-0001.pdf']);
    Storage::disk('public')->put('proposals/nt-0001.pdf', 'PDF');

    $this->actingAs(User::factory()->create())
        ->get(route('pages.projects.proposal.preview', $project))
        ->assertOk()
        ->assertSee('Proposal PDF Viewer', false)
        ->assertSee('Back to Workspace')
        ->assertSee(route('pages.project-workspace', ['project' => $project->id]), false)
        ->assertSee(Storage::url('proposals/nt-0001.pdf'), false);
});

it('explains a missing pdf on the preview page instead of embedding a broken frame', function () {
    $project = workspaceProject([workspaceCandidate()]);

    $this->actingAs(User::factory()->create())
        ->get(route('pages.projects.proposal.preview', $project))
        ->assertOk()
        ->assertSee('PDF File Not Available Yet')
        ->assertDontSee('<iframe', false);
});

it('sends a visitor back to the workspace when the proposal disappeared', function () {
    $project = workspaceProject([workspaceCandidate()]);
    Proposal::where('project_id', $project->id)->delete();

    $this->actingAs(User::factory()->create())
        ->get(route('pages.projects.proposal.preview', $project))
        ->assertRedirect(route('pages.project-workspace', ['project' => $project->id]));
});
