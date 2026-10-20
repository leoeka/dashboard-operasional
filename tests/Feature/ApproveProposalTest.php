<?php

use App\Models\PipelineCheckpoint;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Services\PipelineCheckpointService;

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

it('records the option the button was pressed on in the same action as the approval', function () {
    config(['services.openai.key' => null]);

    $project = approvableProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.proposal.approve', $project), ['mockup_index' => 0])
        ->assertRedirect()
        ->assertSessionHas('success', 'Pilihan klien tercatat pada Opsi 1. WordPress siap dibuat.');

    $proposal = $project->fresh()->latestProposal;
    $data = json_decode((string) $proposal->ai_reasoning, true);

    expect($proposal->status)->toBe('approved')
        ->and($data['selected_mockup_index'])->toBe(0)
        ->and($data['mockup']['design']['primary_color'])->toBe('#111111')
        ->and($data['implementation_manifest']['design_system']['colors']['primary'])->toBe('#111111');
});

it('refuses to record an option this proposal does not hold', function () {
    config(['services.openai.key' => null]);

    $project = approvableProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.proposal.approve', $project), ['mockup_index' => 2])
        ->assertSessionHas('error', 'Opsi mockup yang dipilih tidak ditemukan. Generate proposal ulang.');

    expect($project->fresh()->latestProposal->status)->toBe('pending')
        ->and($project->fresh()->status)->toBe('request');
});

it('records that the client accepted none of the options', function () {
    config(['services.openai.key' => null]);

    $project = approvableProject();
    $checkpoints = app(PipelineCheckpointService::class);
    $checkpoints->recordSuccess($project, 'target_market', ['target_market' => 'Pecinta kopi specialty']);
    $checkpoints->recordSuccess($project, 'competitor_research', []);
    $checkpoints->recordSuccess($project, 'website_content', ['sitemap' => ['pages' => []]]);
    $checkpoints->recordSuccess($project, 'design_blueprint', [['design' => []]]);

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.proposal.revise', $project))
        ->assertRedirect()
        ->assertSessionHas('success');

    $proposal = $project->fresh()->latestProposal;
    $data = json_decode((string) $proposal->ai_reasoning, true);

    expect($proposal->status)->toBe('rejected')
        ->and($data['selected_mockup_index'])->toBeNull()
        // The round the client rejected stays readable...
        ->and($data['mockup_candidates'])->toHaveCount(2);

    // ...but the next run does not replay it: the audience came from the brief,
    // the words and the designs came from the options the client said no to.
    expect($checkpoints->nextStage($project))->toBe('website_content')
        ->and(PipelineCheckpoint::where('project_id', $project->id)->pluck('stage')->sort()->values()->all())
        ->toBe(['competitor_research', 'target_market']);
});
