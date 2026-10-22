<?php

use App\Jobs\GenerateMockupCandidateJob;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Models\User;
use App\Support\MockupWorkspace;
use Illuminate\Support\Facades\Bus;

function gateUser(): User
{
    return User::factory()->create();
}

function gateWebsiteProject(): Project
{
    static $sequence = 0;

    return Project::create([
        'code' => 'GTW-' . str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT),
        'name' => 'Website Gerbang Uji',
        'client_name' => 'Klien Uji',
        'type' => 'Company',
        'status' => 'request',
    ]);
}

function gateSeoProject(): Project
{
    static $sequence = 0;

    return Project::create([
        'code' => 'GTS-' . str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT),
        'name' => 'SEO Gerbang Uji',
        'client_name' => 'Klien Uji',
        'type' => null,
        'status' => 'request',
        'wants_seo' => true,
    ]);
}

function gateCandidate(Project $project, int $number, array $attributes = []): MockupCandidate
{
    $candidate = MockupCandidate::create(array_merge([
        'project_id' => $project->id,
        'candidate_number' => $number,
    ], $attributes));

    $candidate->update(['folder' => (new MockupWorkspace())->relativeFolder($candidate)]);

    return $candidate;
}

it('shows the empty gate with a generate button for a website project without candidates', function () {
    $project = gateWebsiteProject();

    $this->actingAs(gateUser())
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('Gerbang tinjau mockup')
        ->assertSee('Belum ada kandidat mockup')
        ->assertSee(route('pages.projects.mockup.store', $project), false);
});

it('does not show the mockup track for a non-website project', function () {
    $project = gateSeoProject();

    $this->actingAs(gateUser())
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertDontSee('Gerbang tinjau mockup');
});

it('creates three draft candidates and dispatches three jobs', function () {
    Bus::fake();
    $project = gateWebsiteProject();

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.store', $project))
        ->assertRedirect()
        ->assertSessionHas('success');

    $candidates = $project->mockupCandidates()->orderBy('candidate_number')->get();

    expect($candidates)->toHaveCount(3)
        ->and($candidates->pluck('candidate_number')->all())->toBe([1, 2, 3])
        ->and($candidates->every(fn ($c) => $c->status === MockupCandidate::STATUS_DRAFT))->toBeTrue()
        ->and($candidates->every(fn ($c) => $c->isRunning()))->toBeTrue();

    Bus::assertDispatchedTimes(GenerateMockupCandidateJob::class, 3);
});

it('refuses a new batch while any candidate is still being built', function () {
    Bus::fake();
    $project = gateWebsiteProject();
    gateCandidate($project, 1);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.store', $project))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($project->mockupCandidates()->count())->toBe(1);
    Bus::assertNothingDispatched();
});

it('refuses a second batch even after the first set finished', function () {
    Bus::fake();
    $project = gateWebsiteProject();
    gateCandidate($project, 1, ['status' => MockupCandidate::STATUS_TEAM_APPROVED]);
    gateCandidate($project, 2, ['status' => MockupCandidate::STATUS_TEAM_APPROVED]);
    gateCandidate($project, 3, ['status' => MockupCandidate::STATUS_TEAM_APPROVED]);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.store', $project))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($project->mockupCandidates()->count())->toBe(3);
    Bus::assertNothingDispatched();
});

it('marks the review step complete only when all three slots are approved', function () {
    $incomplete = gateWebsiteProject();
    gateCandidate($incomplete, 1, ['status' => MockupCandidate::STATUS_TEAM_APPROVED]);

    $this->actingAs(gateUser())
        ->get('/project-workspace?project=' . $incomplete->id)
        ->assertOk()
        ->assertDontSee('bx-check-circle');

    $complete = gateWebsiteProject();
    gateCandidate($complete, 1, ['status' => MockupCandidate::STATUS_TEAM_APPROVED]);
    gateCandidate($complete, 2, ['status' => MockupCandidate::STATUS_TEAM_APPROVED]);
    gateCandidate($complete, 3, ['status' => MockupCandidate::STATUS_TEAM_APPROVED]);

    $this->actingAs(gateUser())
        ->get('/project-workspace?project=' . $complete->id)
        ->assertOk()
        ->assertSee('bx-check-circle');
});

it('refuses the batch endpoint for a non-website project', function () {
    $project = gateSeoProject();

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.store', $project))
        ->assertForbidden();
});

it('regenerates one candidate by adding a new row with the same number', function () {
    Bus::fake();
    $project = gateWebsiteProject();
    $old = gateCandidate($project, 2, [
        'status' => MockupCandidate::STATUS_AWAITING_REVIEW,
        'idea_sentence' => 'Vila biru yang tenang',
    ]);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.regenerate', [$project, $old]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $rows = $project->mockupCandidates()->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[1]->candidate_number)->toBe(2)
        ->and($rows[1]->status)->toBe(MockupCandidate::STATUS_DRAFT)
        ->and($rows[1]->id)->toBeGreaterThan($rows[0]->id)
        ->and($rows[0]->status)->toBe(MockupCandidate::STATUS_AWAITING_REVIEW)
        ->and($rows[0]->idea_sentence)->toBe('Vila biru yang tenang');

    Bus::assertDispatchedTimes(GenerateMockupCandidateJob::class, 1);
});

it('returns 404 when regenerating a candidate that belongs to another project', function () {
    $project = gateWebsiteProject();
    $other = gateWebsiteProject();
    $candidate = gateCandidate($other, 1);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.regenerate', [$project, $candidate]))
        ->assertNotFound();
});

it('refuses to regenerate a slot that is still being built', function () {
    Bus::fake();
    $project = gateWebsiteProject();
    $running = gateCandidate($project, 3);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.regenerate', [$project, $running]))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($project->mockupCandidates()->count())->toBe(1);
    Bus::assertNothingDispatched();
});

it('approves every candidate that is ready for review', function () {
    $project = gateWebsiteProject();
    gateCandidate($project, 1, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);
    gateCandidate($project, 2, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);
    gateCandidate($project, 3, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.approve', $project))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($project->mockupCandidates()->where('status', MockupCandidate::STATUS_TEAM_APPROVED)->count())->toBe(3);
});

it('refuses approval when nothing is ready', function () {
    $project = gateWebsiteProject();
    gateCandidate($project, 1, ['status' => MockupCandidate::STATUS_TEAM_APPROVED]);
    gateCandidate($project, 2);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.approve', $project))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($project->mockupCandidates()->where('status', MockupCandidate::STATUS_TEAM_APPROVED)->count())->toBe(1);
});

it('serves the stored preview image and 404s when it does not exist', function () {
    $project = gateWebsiteProject();
    $candidate = gateCandidate($project, 1, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);

    $this->actingAs(gateUser())
        ->get(route('pages.projects.mockup.preview', $candidate))
        ->assertNotFound();

    $folder = (new MockupWorkspace())->candidatePath($candidate);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    file_put_contents($folder . '/preview.png', $png);
    $candidate->update(['png_path' => $candidate->folder . '/preview.png']);

    $this->actingAs(gateUser())
        ->get(route('pages.projects.mockup.preview', $candidate))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('renders three cards with images and the approve button when all are ready', function () {
    $project = gateWebsiteProject();

    foreach ([1, 2, 3] as $number) {
        $candidate = gateCandidate($project, $number, [
            'status' => MockupCandidate::STATUS_AWAITING_REVIEW,
            'idea_sentence' => 'Konsep nomor ' . $number,
            'png_path' => 'mockup-work/' . $project->id . '/candidate-x/preview.png',
        ]);
        $candidate->update(['png_path' => $candidate->folder . '/preview.png']);
    }

    $this->actingAs(gateUser())
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('Konsep nomor 1')
        ->assertSee('Konsep nomor 2')
        ->assertSee('Konsep nomor 3')
        ->assertSee('Setujui 3 kandidat');
});

it('shows the failure reason and a retry button for a failed candidate', function () {
    $project = gateWebsiteProject();
    gateCandidate($project, 1, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);
    gateCandidate($project, 2, [
        'status' => MockupCandidate::STATUS_DRAFT,
        'failure_reason' => 'Loop alat mentok di batas ronde 12.',
    ]);
    gateCandidate($project, 3, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);

    $this->actingAs(gateUser())
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('Loop alat mentok di batas ronde 12.')
        ->assertSee('Coba lagi');
});

it('records the failure reason on the row when the AI loop throws', function () {
    config(['services.openai.key' => null]);
    $project = gateWebsiteProject();
    $candidate = gateCandidate($project, 1);

    (new GenerateMockupCandidateJob($candidate))->handle();

    expect($candidate->fresh()->failure_reason)->not->toBeNull()
        ->and($candidate->fresh()->status)->toBe(MockupCandidate::STATUS_DRAFT);
});

it('records the failure reason when the worker kills or times out the job', function () {
    $project = gateWebsiteProject();
    $candidate = gateCandidate($project, 1);

    (new GenerateMockupCandidateJob($candidate))->failed(new RuntimeException('worker timeout'));

    expect($candidate->fresh()->failure_reason)->toContain('worker timeout');
});

it('refuses regeneration posted from a superseded card while the slot is still being built', function () {
    Bus::fake();
    $project = gateWebsiteProject();
    $superseded = gateCandidate($project, 2, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);
    gateCandidate($project, 2);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.regenerate', [$project, $superseded]))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($project->mockupCandidates()->count())->toBe(2);
    Bus::assertNothingDispatched();
});

it('approves only the current set and leaves superseded rows untouched', function () {
    $project = gateWebsiteProject();
    gateCandidate($project, 1, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);
    $superseded = gateCandidate($project, 2, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);
    gateCandidate($project, 2);
    gateCandidate($project, 3, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.approve', $project))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($superseded->fresh()->status)->toBe(MockupCandidate::STATUS_AWAITING_REVIEW)
        ->and($project->mockupCandidates()->where('status', MockupCandidate::STATUS_TEAM_APPROVED)->pluck('candidate_number')->all())->toBe([1, 3]);
});
