<?php

use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;

/**
 * Approving a mockup used to call GPT vision on the approved PNG to produce
 * the implementation manifest, so approval failed whenever OpenAI was
 * unavailable — the exact situation this project has been stuck in. The
 * manifest is now derived from the approved blueprint, so approval is a
 * local, deterministic operation.
 */
function approvableProject(array $overrides = []): Project
{
    $project = Project::create(array_merge([
        'name' => 'Website Kopi Nusantara',
        'client_name' => 'Kopi Nusantara',
        'type' => 'Coffee Shop',
        'status' => 'request',
    ], $overrides));

    Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => 'pending',
        'ai_reasoning' => json_encode([
            'selected_mockup_index' => 1,
            'mockup_candidates' => [
                ['design' => ['primary_color' => '#111111', 'layout_variant' => 'split-right'], 'pages' => []],
                [
                    'design' => [
                        'primary_color' => '#1F3A5F',
                        'accent_color' => '#C87941',
                        'font_heading' => 'Playfair Display',
                        'layout_variant' => 'overlay-bg',
                    ],
                    'global_cta' => 'Pesan Sekarang',
                    'pages' => [[
                        'name' => 'Home',
                        'sections' => [
                            ['name' => 'Hero', 'headline' => 'Kopi Nusantara Pilihan', 'description' => 'Dari petani lokal.'],
                            ['name' => 'Menu', 'headline' => 'Menu Unggulan', 'items' => [
                                ['title' => 'Kopi Gayo', 'description' => 'Aceh, medium roast.'],
                            ]],
                        ],
                    ]],
                ],
            ],
        ]),
    ]);

    return $project;
}

it('approves a mockup without calling any AI service', function () {
    config(['services.openai.key' => null]);

    $project = approvableProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.proposal.approve', $project))
        ->assertRedirect()
        ->assertSessionHas('success');

    $proposal = $project->fresh()->latestProposal;
    $data = json_decode((string) $proposal->ai_reasoning, true);

    expect($proposal->status)->toBe('approved')
        ->and($project->fresh()->status)->toBe('in_progress')
        ->and($data['implementation_manifest']['source'])->toBe('approved_blueprint');
});

it('freezes the candidate the client actually selected, not the first one', function () {
    config(['services.openai.key' => null]);

    $project = approvableProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.proposal.approve', $project));

    $data = json_decode((string) $project->fresh()->latestProposal->ai_reasoning, true);
    $manifest = $data['implementation_manifest'];

    expect($data['mockup']['design']['primary_color'])->toBe('#1F3A5F')
        ->and($manifest['design_system']['colors']['primary'])->toBe('#1F3A5F')
        ->and($manifest['design_system']['layout']['variant'])->toBe('overlay-bg');
});

it('records the approved alignment in the manifest so the build cannot recenter it', function () {
    config(['services.openai.key' => null]);

    $project = approvableProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.proposal.approve', $project));

    $manifest = json_decode((string) $project->fresh()->latestProposal->ai_reasoning, true)['implementation_manifest'];
    $hero = collect($manifest['sections'])->firstWhere('role', 'hero');

    // This blueprint is overlay-bg, which the approved PNG centers.
    expect($hero['layout']['body_align'])->toBe('center');
});

it('still refuses to approve a project that has no proposal yet', function () {
    $project = Project::create([
        'name' => 'Belum Ada Proposal',
        'client_name' => 'Klien Baru',
        'status' => 'request',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.proposal.approve', $project))
        ->assertSessionHas('error');
});
