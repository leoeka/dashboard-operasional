<?php

use App\Models\ClientProposal;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Models\User;
use App\Models\WpBuild;
use App\Support\MockupCodexLauncher;
use App\Support\MockupCodexState;
use App\Support\MockupWorkspace;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->gateBase = sys_get_temp_dir() . '/siteflow-gate-' . uniqid();

    config(['services.mockup.codex.base' => $this->gateBase]);
});

afterEach(function () {
    File::deleteDirectory($this->gateBase);
});

/**
 * Runner Codex palsu: permintaan web mengira proses latar sudah dilepas.
 *
 * @return Mockery\MockInterface&MockupCodexLauncher
 */
function launcherSpy()
{
    $spy = \Mockery::mock(MockupCodexLauncher::class);
    $spy->shouldReceive('launch')->andReturn(true);
    app()->instance(MockupCodexLauncher::class, $spy);

    return $spy;
}

/**
 * Meniru runner yang masih hidup: kunci proyek dipegang selama pegangan
 * berkas ini dipegang, jadi layar dan controller membaca "sedang jalan".
 *
 * @return array{string, resource}
 */
function pegangKunci(Project $project): array
{
    $path = app(MockupCodexState::class)->lockPath($project);
    $handle = fopen($path, 'c');
    flock($handle, LOCK_EX);

    return [$path, $handle];
}

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

it('creates three draft candidates and releases one Codex runner for the batch', function () {
    $spy = launcherSpy();
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

    $spy->shouldHaveReceived('launch')->once();
});

it('refuses a new batch while any candidate is still being built', function () {
    $spy = launcherSpy();
    $project = gateWebsiteProject();
    gateCandidate($project, 1);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.store', $project))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($project->mockupCandidates()->count())->toBe(1);
    $spy->shouldNotHaveReceived('launch');
});

it('refuses a second batch even after the first set finished', function () {
    $spy = launcherSpy();
    $project = gateWebsiteProject();
    gateCandidate($project, 1, ['status' => MockupCandidate::STATUS_TEAM_APPROVED]);
    gateCandidate($project, 2, ['status' => MockupCandidate::STATUS_TEAM_APPROVED]);
    gateCandidate($project, 3, ['status' => MockupCandidate::STATUS_TEAM_APPROVED]);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.store', $project))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($project->mockupCandidates()->count())->toBe(3);
    $spy->shouldNotHaveReceived('launch');
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
    $spy = launcherSpy();
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

    $spy->shouldHaveReceived('launch')->once()->withArgs(fn (...$args) => $args[1] === 2);
});

it('returns 404 when regenerating a candidate that belongs to another project', function () {
    $project = gateWebsiteProject();
    $other = gateWebsiteProject();
    $candidate = gateCandidate($other, 1);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.regenerate', [$project, $candidate]))
        ->assertNotFound();
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

it('renders three cards with images and the proposal action when all are ready', function () {
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
        ->assertSee('Pratinjau ukuran penuh')
        ->assertSee('Buat proposal PDF');
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

it('shows the inspection checklist and its errors when the theme build failed', function () {
    $project = gateWebsiteProject();
    $candidate = gateCandidate($project, 1, ['status' => MockupCandidate::STATUS_CHOSEN]);

    ClientProposal::create([
        'project_id' => $project->id,
        'chosen_candidate_id' => $candidate->id,
        'chosen_at' => now(),
    ]);

    WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
        'status' => WpBuild::STATUS_INSPECTION_FAILED,
        'inspection_results' => [
            'passed' => false,
            'error' => 'Meja periksa gagal: Berkas wajib tema functions.php tidak ditemukan.',
            'checks' => [
                'required_files' => false,
                'color_contrast' => false,
            ],
            'errors' => ['Berkas wajib tema functions.php tidak ditemukan.'],
        ],
    ]);

    $this->actingAs(gateUser())
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('Klien memilih Kandidat 1')
        ->assertSee('Berkas wajib tema: gagal')
        ->assertSee('Kontras warna (M-1/M-2): gagal')
        ->assertSee('Berkas wajib tema functions.php tidak ditemukan.');
});

it('refuses regeneration posted from a superseded card while the slot is still being built', function () {
    $spy = launcherSpy();
    $project = gateWebsiteProject();
    $superseded = gateCandidate($project, 2, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);
    $running = gateCandidate($project, 2);

    [$path, $handle] = pegangKunci($project);

    $this->actingAs(gateUser())
        ->post(route('pages.projects.mockup.regenerate', [$project, $superseded]))
        ->assertRedirect()
        ->assertSessionHas('error');

    fclose($handle);

    expect($project->mockupCandidates()->count())->toBe(2)
        ->and($running->fresh()->isRunning())->toBeTrue();
    $spy->shouldNotHaveReceived('launch');
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

it('returns asynchronous mockup status JSON for background polling without page refresh', function () {
    $project = gateWebsiteProject();
    $c1 = gateCandidate($project, 1);
    $c2 = gateCandidate($project, 2, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);

    \Illuminate\Support\Facades\Cache::put(
        "mockup_candidate_{$c1->id}_progress",
        ['percent' => 60, 'message' => 'Menyusun kode HTML & CSS'],
        now()->addMinutes(10)
    );

    $response = $this->actingAs(gateUser())
        ->get(route('pages.projects.mockup.status', $project))
        ->assertOk()
        ->assertJsonStructure([
            'has_running',
            'is_building',
            'candidates' => [
                '*' => ['id', 'candidate_number', 'status', 'is_running', 'percent', 'message'],
            ],
        ]);

    $data = $response->json();
    expect($data['has_running'])->toBeTrue();

    $cand1 = collect($data['candidates'])->firstWhere('candidate_number', 1);
    expect($cand1['percent'])->toBe(60)
        ->and($cand1['message'])->toBe('Menyusun kode HTML & CSS');
});

it('rejects status polling for a non-website project', function () {
    $project = gateSeoProject();

    $this->actingAs(gateUser())
        ->get(route('pages.projects.mockup.status', $project))
        ->assertForbidden();
});
