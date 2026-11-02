<?php

use App\Models\ClientProposal;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Tiga PNG 1x1 dengan isi berbeda (transparan / merah / hitam) — sengaja beda
 * berkas karena dompdf mengenali gambar dari jalur berkas: tiga jalur berbeda
 * = tiga objek gambar tersendiri di dalam PDF, jadi terhitung benar-benar 3.
 */
function prpPngs(): array
{
    return [
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
    ];
}

function prpUser(): User
{
    return User::factory()->create();
}

// Tes menulis ke storage/app sungguhan (bukan Storage fake), jadi setiap tes
// wajib mengangkat berkasnya sendiri — DB sqlite-nya hilang antar-tes, berkasnya tidak.
afterEach(function () {
    foreach (ClientProposal::all() as $proposal) {
        $absolute = storage_path('app/'.$proposal->pdf_path);
        if ($proposal->pdf_path && is_file($absolute)) {
            unlink($absolute);
        }
    }
    foreach (glob(storage_path('app/mockup-work/prp-*/*/preview.png')) ?: [] as $file) {
        unlink($file);
    }
    foreach (glob(storage_path('app/mockup-work/prp-*/*'), GLOB_ONLYDIR) ?: [] as $dir) {
        if (count(scandir($dir) ?: []) === 2) { rmdir($dir); }
    }
    foreach (glob(storage_path('app/mockup-work/prp-*'), GLOB_ONLYDIR) ?: [] as $dir) {
        if (count(scandir($dir) ?: []) === 2) { rmdir($dir); }
    }
    foreach (glob(storage_path('app/proposals/*'), GLOB_ONLYDIR) ?: [] as $dir) {
        if (count(scandir($dir) ?: []) === 2) { rmdir($dir); }
    }
});

function prpWebsiteProject(): Project
{
    static $sequence = 0;

    return Project::create([
        'code' => 'PRP-' . str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT),
        'name' => 'Website Proposal Uji',
        'client_name' => 'Klien Proposal',
        'type' => 'Company',
        'status' => 'request',
    ]);
}

function prpSeoProject(): Project
{
    static $sequence = 0;

    return Project::create([
        'code' => 'PRS-' . str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT),
        'name' => 'SEO Proposal Uji',
        'client_name' => 'Klien Proposal',
        'type' => null,
        'status' => 'request',
        'wants_seo' => true,
    ]);
}

function prpCandidate(Project $project, int $number, string $pngBase64, array $attributes = []): MockupCandidate
{
    $candidate = MockupCandidate::create(array_merge([
        'project_id' => $project->id,
        'candidate_number' => $number,
    ], $attributes));

    $path = "mockup-work/prp-{$project->id}/{$candidate->id}/preview.png";

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

/** Tiga kandidat siap-tinjau, masing-masing dengan PNG kecil tersendiri. */
function prpReviewSet(Project $project): Collection
{
    return collect(prpPngs())->map(
        fn ($png, $i) => prpCandidate($project, $i + 1, $png, [
            'idea_sentence' => 'Ide desain opsi ' . ($i + 1),
            'status' => MockupCandidate::STATUS_AWAITING_REVIEW,
        ])
    );
}

/** Setelah tombol setujui di gerbang: ketiganya team_approved + PNG. */
function prpApprovedSet(Project $project): Collection
{
    return collect(prpPngs())->map(
        fn ($png, $i) => prpCandidate($project, $i + 1, $png, [
            'idea_sentence' => 'Ide desain opsi ' . ($i + 1),
            'status' => MockupCandidate::STATUS_TEAM_APPROVED,
        ])
    );
}

it('refuses to build the proposal before all three candidates are approved', function () {
    $project = prpWebsiteProject();
    $set = prpApprovedSet($project);
    $set->last()->update(['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);

    $this->actingAs(prpUser())
        ->post(route('pages.projects.proposals.store', $project))
        ->assertSessionHas('error');

    expect(ClientProposal::count())->toBe(0);
});

it('refuses the proposal endpoint for a non-website project', function () {
    $project = prpSeoProject();
    prpApprovedSet($project);

    $this->actingAs(prpUser())
        ->post(route('pages.projects.proposals.store', $project))
        ->assertForbidden();
});

it('builds a valid pdf file containing the three candidate images', function () {
    $project = prpWebsiteProject();
    prpApprovedSet($project);

    $this->actingAs(prpUser())
        ->post(route('pages.projects.proposals.store', $project))
        ->assertSessionHas('success');

    $proposal = ClientProposal::sole();
    $absolute = storage_path('app/'.$proposal->pdf_path);
    expect($proposal->pdf_path)->not->toBeNull()
        ->and(is_file($absolute))->toBeTrue();

    $raw = file_get_contents($absolute);
    expect(str_starts_with($raw, '%PDF'))->toBeTrue()
        ->and(str_contains($raw, '%%EOF'))->toBeTrue()
        ->and(strlen($raw))->toBeGreaterThan(10000);

    // Batas bawah = gambar chrome templat (sampul/latar pita/logo) + tiga PNG
    // kandidat — jadi lolos hanya kalau ketiga gambar kandidat benar-benar masuk.
    $chrome = collect(['images/cover-bg.jpg', 'images/header-bg.jpg', 'images/logo-transparent.png'])
        ->filter(fn ($img) => file_exists(public_path($img)))
        ->count();

    $normalized = str_replace('/Subtype /Image', '/Subtype/Image', $raw);
    expect(substr_count($normalized, '/Subtype/Image'))->toBeGreaterThanOrEqual($chrome + 3);
});

it('renders one client tick box per candidate page', function () {
    $project = prpWebsiteProject();
    $candidates = prpApprovedSet($project);

    $html = view('pdf.proposal', [
        'project' => $project,
        'candidates' => $candidates,
        'date' => '10/22/2026',
    ])->render();

    expect(substr_count($html, 'class="choice-box"'))->toBe(3)
        ->and($html)->toContain('Design Mock Up — Option 1')
        ->and($html)->toContain('Design Mock Up — Option 3')
        ->and($html)->toContain('Ide desain opsi 2')
        ->and($html)->toContain(strtoupper($project->client_name));
});

it('marks the approved candidates as sent once the proposal is built', function () {
    $project = prpWebsiteProject();
    prpApprovedSet($project);

    $this->actingAs(prpUser())
        ->post(route('pages.projects.proposals.store', $project))
        ->assertSessionHas('success');

    expect($project->mockupCandidates()->pluck('status')->unique()->values()->all())
        ->toBe([MockupCandidate::STATUS_SENT]);
});

it('keeps older proposal files when the proposal is rebuilt', function () {
    $project = prpWebsiteProject();
    prpApprovedSet($project);
    $user = prpUser();

    $this->actingAs($user)
        ->post(route('pages.projects.proposals.store', $project))
        ->assertSessionHas('success');
    $first = ClientProposal::sole();

    // Setelah proposal pertama, kandidat sudah 'sent' — buat-ulang tetap boleh.
    $this->actingAs($user)
        ->post(route('pages.projects.proposals.store', $project))
        ->assertSessionHas('success');

    expect(ClientProposal::count())->toBe(2)
        ->and(ClientProposal::latest('id')->first()->pdf_path)->not->toBe($first->pdf_path)
        ->and(is_file(storage_path('app/'.$first->pdf_path)))->toBeTrue()
        ->and(is_file(storage_path('app/'.ClientProposal::latest('id')->first()->pdf_path)))->toBeTrue();
});

it('downloads the pdf and stamps sent_at only on the first download', function () {
    $project = prpWebsiteProject();
    prpApprovedSet($project);

    $this->actingAs(prpUser())
        ->post(route('pages.projects.proposals.store', $project));

    $proposal = ClientProposal::sole();
    expect($proposal->sent_at)->toBeNull();

    $this->get(route('pages.projects.proposals.download', [$project, $proposal]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $sentAt = $proposal->fresh()->sent_at;
    expect($sentAt)->not->toBeNull();

    $this->get(route('pages.projects.proposals.download', [$project, $proposal]))
        ->assertOk();

    expect($proposal->fresh()->sent_at->toIso8601String())->toBe($sentAt->toIso8601String());
});

it('returns 404 when downloading a proposal that belongs to another project', function () {
    $project = prpWebsiteProject();
    prpApprovedSet($project);
    $other = prpWebsiteProject();

    $this->actingAs(prpUser())
        ->post(route('pages.projects.proposals.store', $project));

    $this->actingAs(prpUser())
        ->get(route('pages.projects.proposals.download', [$other, ClientProposal::sole()]))
        ->assertNotFound();
});

it('shows the proposal actions on the gate only when the set is fully reviewed', function () {
    $project = prpWebsiteProject();
    $user = prpUser();

    // Belum ada kandidat: tidak ada tombol proposal sama sekali.
    $this->actingAs($user)
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertDontSee('Buat proposal PDF');

    // Baru disetujui dua dari tiga: tombol belum muncul.
    $set = prpApprovedSet($project);
    $set->last()->update(['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);
    $this->actingAs($user)
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertDontSee('Buat proposal PDF');

    // Ketiganya disetujui: tombol buat muncul.
    $set->last()->update(['status' => MockupCandidate::STATUS_TEAM_APPROVED]);
    $this->actingAs($user)
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('Buat proposal PDF');

    // Proposal sudah dibuat: tombol ganti jadi unduh + buat ulang.
    $this->actingAs($user)
        ->post(route('pages.projects.proposals.store', $project));
    $this->actingAs($user)
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('Unduh proposal PDF')
        ->assertSee('Buat ulang proposal');
});
