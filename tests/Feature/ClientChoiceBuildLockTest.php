<?php

use App\Jobs\BuildWpThemeJob;
use App\Models\ClientProposal;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Models\User;
use App\Models\WpBuild;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

/**
 * Tiga PNG 1x1 dengan isi berbeda — dompdf menghitung objek gambar per jalur
 * berkas, jadi tiap kandidat butuh jalur sendiri supaya proposal bisa dibangun.
 */
function chcPngs(): array
{
    return [
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
    ];
}

function chcUser(): User
{
    return User::factory()->create();
}

beforeEach(function () {
    Bus::fake([BuildWpThemeJob::class]);
});

// Tes menulis PDF proposal ke storage/app sungguhan lewat endpoint, jadi setiap
// tes wajib mengangkat berkasnya sendiri — DB sqlite-nya hilang antar-tes, berkasnya tidak.
afterEach(function () {
    foreach (ClientProposal::all() as $proposal) {
        $absolute = storage_path('app/'.$proposal->pdf_path);
        if ($proposal->pdf_path && is_file($absolute)) {
            unlink($absolute);
        }
    }
    foreach (glob(storage_path('app/mockup-work/chc-*/*/preview.png')) ?: [] as $file) {
        unlink($file);
    }
    foreach (glob(storage_path('app/mockup-work/chc-*/*'), GLOB_ONLYDIR) ?: [] as $dir) {
        if (count(scandir($dir) ?: []) === 2) { rmdir($dir); }
    }
    foreach (glob(storage_path('app/mockup-work/chc-*'), GLOB_ONLYDIR) ?: [] as $dir) {
        if (count(scandir($dir) ?: []) === 2) { rmdir($dir); }
    }
    foreach (glob(storage_path('app/proposals/*'), GLOB_ONLYDIR) ?: [] as $dir) {
        if (count(scandir($dir) ?: []) === 2) { rmdir($dir); }
    }
});

function chcWebsiteProject(): Project
{
    static $sequence = 0;

    return Project::create([
        'code' => 'CHC-' . str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT),
        'name' => 'Website Pilihan Uji',
        'client_name' => 'Klien Pilihan',
        'type' => 'Company',
        'status' => 'request',
    ]);
}

function chcSeoProject(): Project
{
    static $sequence = 0;

    return Project::create([
        'code' => 'CHS-' . str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT),
        'name' => 'SEO Pilihan Uji',
        'client_name' => 'Klien Pilihan',
        'type' => null,
        'status' => 'request',
        'wants_seo' => true,
    ]);
}

function chcCandidate(Project $project, int $number, string $pngBase64, array $attributes = []): MockupCandidate
{
    $candidate = MockupCandidate::create(array_merge([
        'project_id' => $project->id,
        'candidate_number' => $number,
    ], $attributes));

    $path = "mockup-work/chc-{$project->id}/{$candidate->id}/preview.png";

    // Langsung ke storage/app, bukan Storage::disk('local') — root disk lokal
    // Laravel 12 adalah storage/app/private, sedangkan view PDF membaca dari storage/app.
    $absolute = storage_path('app/'.$path);
    if (! is_dir(dirname($absolute)) && ! mkdir($dirname = dirname($absolute), 0775, true) && ! is_dir($dirname)) {
        throw new \RuntimeException('Folder uji tidak bisa dibuat: '.$dirname);
    }
    if (file_put_contents($absolute, base64_decode($pngBase64)) === false) {
        throw new \RuntimeException('PNG uji tidak bisa ditulis: '.$absolute);
    }
    $candidate->update(['png_path' => $path]);

    return $candidate;
}

/** Tiga kandidat disetujui tim + PNG, siap dibuatkan proposal. */
function chcApprovedSet(Project $project): Collection
{
    return collect(chcPngs())->map(
        fn ($png, $i) => chcCandidate($project, $i + 1, $png, [
            'idea_sentence' => 'Ide desain pilihan ' . ($i + 1),
            'status' => MockupCandidate::STATUS_TEAM_APPROVED,
        ])
    );
}

/** Bangun proposal lewat endpoint — kandidat jadi 'sent', baris proposal lahir. */
function chcBuildProposal(Project $project, User $user): ClientProposal
{
    $user->refresh();
    test()->actingAs($user)
        ->post(route('pages.projects.proposals.store', $project))
        ->assertSessionHas('success');

    return ClientProposal::latest('id')->first();
}

it('refuses to record a client choice on a non-website project', function () {
    $project = chcSeoProject();
    $candidate = MockupCandidate::create([
        'project_id' => $project->id,
        'candidate_number' => 1,
    ]);
    $proposal = ClientProposal::create(['project_id' => $project->id]);

    $this->actingAs(chcUser())
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $candidate]))
        ->assertForbidden();
});

it('returns 404 when recording a choice on a proposal from another project', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);
    $other = chcWebsiteProject();

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$other, $proposal, $set->first()]))
        ->assertNotFound();
});

it('returns 404 when the chosen candidate is a replaced trace row', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);

    // Baris pengganti untuk slot 2 (id lebih baru) mengambil alih currentSet;
    // baris lama tinggal jejak dan tidak boleh dipilih meski masih terkirim.
    $project->mockupCandidates()->create([
        'candidate_number' => 2,
        'status' => MockupCandidate::STATUS_AWAITING_REVIEW,
    ]);

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $set->get(1)]))
        ->assertNotFound();

    expect($proposal->fresh()->chosen_candidate_id)->toBeNull();
});

it('records the client choice and locks the other candidates as rejected', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);
    $chosen = $set->get(1);

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $chosen]))
        ->assertSessionHas('success');

    $fresh = $proposal->fresh();
    expect($fresh->chosen_candidate_id)->toBe($chosen->id)
        ->and($fresh->chosen_at)->not->toBeNull();

    expect($project->mockupCandidates()->find($chosen->id)->status)
        ->toBe(MockupCandidate::STATUS_CHOSEN)
        ->and($project->mockupCandidates()->find($set->get(0)->id)->status)
        ->toBe(MockupCandidate::STATUS_REJECTED)
        ->and($project->mockupCandidates()->find($set->get(2)->id)->status)
        ->toBe(MockupCandidate::STATUS_REJECTED);
});

it('refuses a second client choice once the lock is recorded', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $set->first()]))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $set->get(1)]))
        ->assertSessionHas('error');

    expect($proposal->fresh()->chosen_candidate_id)->toBe($set->first()->id)
        ->and($project->mockupCandidates()->find($set->get(1)->id)->status)
        ->toBe(MockupCandidate::STATUS_REJECTED);
});

it('refuses the choice while a regenerated member is still awaiting review', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);

    // Kandidat 2 dibuat ulang setelah proposal terkirim: penggantinya belum
    // disetujui tim, jadi mencatat pilihan klien ditolak sampai beres.
    $project->mockupCandidates()->create([
        'candidate_number' => 2,
        'idea_sentence' => 'Ide pengganti',
        'status' => MockupCandidate::STATUS_AWAITING_REVIEW,
    ]);

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $set->first()]))
        ->assertSessionHas('error');

    expect($proposal->fresh()->chosen_candidate_id)->toBeNull()
        ->and($project->mockupCandidates()->find($set->first()->id)->status)
        ->toBe(MockupCandidate::STATUS_SENT);
});

it('refuses the build before any proposal exists', function () {
    $project = chcWebsiteProject();
    chcApprovedSet($project);

    $this->actingAs(chcUser())
        ->post(route('pages.projects.builds.store', $project))
        ->assertSessionHas('error');

    expect(WpBuild::count())->toBe(0);
});

it('refuses the build while the client choice is not recorded yet', function () {
    $project = chcWebsiteProject();
    chcApprovedSet($project);
    $proposal = chcBuildProposal($project, chcUser());

    expect($proposal->chosen_candidate_id)->toBeNull();

    $this->actingAs(chcUser())
        ->post(route('pages.projects.builds.store', $project))
        ->assertSessionHas('error');

    expect(WpBuild::count())->toBe(0);
});

it('refuses the build endpoint on a non-website project', function () {
    $project = chcSeoProject();

    $this->actingAs(chcUser())
        ->post(route('pages.projects.builds.store', $project))
        ->assertForbidden();
});

it('starts the build after the choice with the chosen candidate, born pending', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);
    $chosen = $set->get(2);

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $chosen]))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->post(route('pages.projects.builds.store', $project))
        ->assertSessionHas('success');

    $build = WpBuild::sole();
    expect($build->project_id)->toBe($project->id)
        ->and($build->mockup_candidate_id)->toBe($chosen->id)
        ->and($build->status)->toBe(WpBuild::STATUS_PENDING)
        ->and($build->candidate->candidate_number)->toBe(3);

    Bus::assertDispatched(BuildWpThemeJob::class);
});

it('refuses a second build for the same project', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $set->first()]))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->post(route('pages.projects.builds.store', $project))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->post(route('pages.projects.builds.store', $project))
        ->assertSessionHas('error');

    expect(WpBuild::count())->toBe(1);
});

it('allows retrying a build when it previously failed inspection', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $set->first()]))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->post(route('pages.projects.builds.store', $project))
        ->assertSessionHas('success');

    $build = WpBuild::sole();
    $build->update([
        'status' => WpBuild::STATUS_INSPECTION_FAILED,
        'inspection_results' => ['error' => 'Simulasi gagal'],
    ]);

    $this->actingAs($user)
        ->post(route('pages.projects.builds.store', $project))
        ->assertSessionHas('success');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_PENDING)
        ->and($fresh->inspection_results)->toBeNull()
        ->and(WpBuild::count())->toBe(1);
});

it('refuses to rebuild the proposal once the choice is locked', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $set->first()]))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.store', $project))
        ->assertSessionHas('error');

    expect(ClientProposal::count())->toBe(1);
});

it('refuses to regenerate a candidate once the choice is locked', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $set->first()]))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->post(route('pages.projects.mockup.regenerate', [$project, $set->get(1)]))
        ->assertSessionHas('error');

    expect(MockupCandidate::where('project_id', $project->id)->count())->toBe(3);
});

it('refuses to record a choice on a stale proposal that is no longer the latest', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $first = chcBuildProposal($project, $user);

    // Buat ulang proposal: baris kedua jadi yang terbaru, yang dibaca semua gerbang.
    $this->actingAs($user)
        ->post(route('pages.projects.proposals.store', $project))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $first, $set->first()]))
        ->assertConflict();

    expect(ClientProposal::count())->toBe(2)
        ->and(ClientProposal::oldest('id')->first()->chosen_candidate_id)->toBeNull()
        ->and(ClientProposal::latest('id')->first()->chosen_candidate_id)->toBeNull();
});

it('refuses a candidate that was never printed in the client pdf', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);

    // Kandidat 2 dibuat ulang setelah PDF terkirim, lalu disetujui tim — tapi
    // PDF yang klien pegang masih versi lama. Mencatat pengganti ditolak;
    // kandidat lama yang masih 'sent' tetap bisa dicatat.
    $replacement = $project->mockupCandidates()->create([
        'candidate_number' => 2,
        'idea_sentence' => 'Ide pengganti',
        'status' => MockupCandidate::STATUS_AWAITING_REVIEW,
    ]);
    $replacement->update(['status' => MockupCandidate::STATUS_TEAM_APPROVED]);

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $replacement]))
        ->assertUnprocessable();

    expect($proposal->fresh()->chosen_candidate_id)->toBeNull();

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $set->first()]))
        ->assertSessionHas('success');

    expect($proposal->fresh()->chosen_candidate_id)->toBe($set->first()->id);
});

it('walks the gate ui from proposal to locked choice to build status', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();

    // Belum ada proposal: tidak ada tombol pilihan dan tidak ada tombol build.
    $this->actingAs($user)
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertDontSee('Klien pilih opsi ini')
        ->assertDontSee('Mulai build WordPress');

    // Proposal terkirim: tombol pilihan muncul di tiap kartu, build masih belum.
    $proposal = chcBuildProposal($project, $user);
    $this->actingAs($user)
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('Klien pilih opsi ini')
        ->assertSee('Buat ulang proposal')
        ->assertDontSee('Mulai build WordPress')
        ->assertDontSee('Pilihan terkunci');

    // Pilihan dicatat: banner terkunci + tombol build, tombol buat ulang hilang.
    $chosen = $set->get(1);
    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $chosen]))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('Klien memilih Kandidat 2')
        ->assertSee('Pilihan terkunci')
        ->assertSee('Mulai build WordPress')
        ->assertSee('Dipilih klien')
        ->assertSee('Ditolak')
        ->assertDontSee('Buat ulang proposal')
        ->assertDontSee('Buat ulang')
        ->assertDontSee('Klien pilih opsi ini');

    // Build dibuat: banner menampilkan status, tombol build tidak muncul lagi.
    $this->actingAs($user)
        ->post(route('pages.projects.builds.store', $project))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('Build dibuat')
        ->assertSee('(pending)')
        ->assertDontSee('Mulai build WordPress');
});

it('keeps the review step checked on the workspace rail after the choice', function () {
    $project = chcWebsiteProject();
    $set = chcApprovedSet($project);
    $user = chcUser();
    $proposal = chcBuildProposal($project, $user);
    $chosen = $set->first();

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.choose', [$project, $proposal, $chosen]))
        ->assertSessionHas('success');

    // Setelah pilihan: satu chosen + dua rejected. Kalau 'rejected' tidak
    // dihitung selesai, langkah tinjau kebuka lagi di rel.
    $this->actingAs($user)
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('bx-check-circle', false);
});
