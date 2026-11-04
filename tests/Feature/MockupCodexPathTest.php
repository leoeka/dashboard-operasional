<?php

use App\Models\MockupCandidate;
use App\Models\Project;
use App\Models\User;
use App\Services\MockupCodexBrief;
use App\Services\MockupCodexPhotos;
use App\Services\MockupCodexRunner;
use App\Support\MockupCodexLauncher;
use App\Support\MockupCodexState;
use App\Support\MockupWorkspace;
use App\Support\PageSnapshot;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->codexBase = sys_get_temp_dir() . '/siteflow-codex-' . uniqid();

    config([
        'services.mockup.codex.base' => $this->codexBase,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->codexBase);
});

function codexProject(): Project
{
    static $sequence = 0;

    return Project::create([
        'code' => 'CDX-' . str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT),
        'name' => 'Vila Codex Uji',
        'client_name' => 'Klien Codex',
        'type' => 'Company',
        'status' => 'request',
        'website_name' => 'Vila Codex',
        'target_market' => 'Wisatawan domestik',
        'description' => 'Vila keluarga dekat pantai.',
    ]);
}

function codexCandidate(Project $project, int $number, array $attributes = []): MockupCandidate
{
    $candidate = MockupCandidate::create(array_merge([
        'project_id' => $project->id,
        'candidate_number' => $number,
    ], $attributes));

    $candidate->update(['folder' => (new MockupWorkspace())->relativeFolder($candidate)]);

    return $candidate;
}

function codexPng(int $padding = 60000): string
{
    return "\x89PNG\r\n\x1a\n" . str_repeat("\0", $padding) . 'IEND';
}

it('menyusun brief Codex dengan alur rancangan visual lebih dulu', function () {
    $project = codexProject();
    $candidate = codexCandidate($project, 2);

    $brief = MockupCodexBrief::build($project, $candidate, 3, ['Vila biru yang tenang']);

    expect($brief)
        ->toContain('Vila Codex Uji')
        ->toContain('Kandidat 2 dari 3')
        ->toContain('Vila biru yang tenang')
        ->toContain('foto-slot.txt')
        ->toContain('<nama-slot>: <jalur-hasil-alat>')
        ->toContain('__EXITO_IMAGE:nama-slot')
        ->toContain('`hero` wajib')
        ->not->toContain('ATURAN-MOCKUP')
        ->not->toContain('Gaya kandidat ini');

    // Visual dibuat sebelum berkas apa pun ditulis, halaman sesudahnya.
    expect(strpos($brief, 'SATU gambar mockup'))
        ->toBeLessThan(strpos($brief, 'Buat `index.html` lengkap'));
});

it('menyalin foto dari catatan model lalu memulihkan sisanya dari cache', function () {
    $root = sys_get_temp_dir() . '/codex-foto-' . uniqid();
    File::ensureDirectoryExists($root . '/kandidat');
    File::ensureDirectoryExists($root . '/cache');

    $galeri = codexPng(61000);

    file_put_contents($root . '/hero-sumber.png', codexPng());
    file_put_contents($root . '/konsep-sumber.png', codexPng(63000));
    file_put_contents($root . '/cache/lama.png', codexPng(62000));
    file_put_contents($root . '/cache/baru.png', $galeri);
    touch($root . '/cache/lama.png', time() - 30);
    touch($root . '/cache/baru.png', time());

    // Baris `konsep` adalah gambar rancangan yang dibuat lebih dulu: ia tidak
    // boleh ikut tersalin sebagai foto halaman.
    file_put_contents(
        $root . '/kandidat/foto-slot.txt',
        'konsep: ' . $root . '/konsep-sumber.png' . "\n"
        . 'hero: ' . $root . '/hero-sumber.png' . "\n" . 'galeri-1: -' . "\n"
    );

    $hasil = (new MockupCodexPhotos())->recover(
        $root . '/kandidat',
        $root . '/kandidat/foto-slot.txt',
        $root . '/cache',
        ['hero', 'galeri-1'],
    );

    expect($hasil['jumlah'])->toBe(2)
        ->and($hasil['pesan'])->toBe('')
        ->and(is_file($root . '/kandidat/hero.png'))->toBeTrue()
        ->and(is_file($root . '/kandidat/galeri-1.png'))->toBeTrue()
        ->and(is_file($root . '/kandidat/konsep.png'))->toBeFalse()
        ->and(file_get_contents($root . '/kandidat/hero.png'))->toBe(codexPng())
        ->and(file_get_contents($root . '/kandidat/galeri-1.png'))->toBe($galeri);

    File::deleteDirectory($root);
});

it('menolak foto terpotong dan melaporkan jumlah yang kurang', function () {
    $root = sys_get_temp_dir() . '/codex-foto-' . uniqid();
    File::ensureDirectoryExists($root . '/kandidat');

    file_put_contents($root . '/kandidat/hero.png', "\x89PNG\r\n\x1a\n" . str_repeat("\0", 1000));

    $hasil = (new MockupCodexPhotos())->recover(
        $root . '/kandidat',
        $root . '/kandidat/foto-slot.txt',
        null,
        ['hero', 'galeri-1'],
    );

    expect($hasil['jumlah'])->toBe(0)
        ->and($hasil['pesan'])->toBe('foto tidak lengkap (0 dari 2): hero, galeri-1');

    File::deleteDirectory($root);
});

it('menulis status, membaca denyut basi sebagai berhenti, dan mengenali kunci', function () {
    $project = codexProject();
    $state = app(MockupCodexState::class);

    $state->write($project, [
        'keadaan' => MockupCodexState::KEADAAN_JALAN,
        'kandidat_ke' => 2,
        'jumlah' => 3,
        'tahap' => 'menulis halaman',
        'token' => 48120,
    ]);

    $data = $state->read($project);

    expect($data['keadaan'])->toBe('jalan')
        ->and($data['kandidat_ke'])->toBe(2)
        ->and($data['token'])->toBe(48120)
        ->and($state->stale($project))->toBeFalse();

    $path = $state->statusPath($project);
    $basi = json_decode((string) file_get_contents($path), true);
    $basi['denyut'] = now()->subMinutes(6)->toIso8601String();
    file_put_contents($path, json_encode($basi));

    expect($state->stale($project))->toBeTrue()
        ->and($state->visible($project)['keadaan'])->toBe('berhenti');

    $lock = fopen($state->lockPath($project), 'c');
    flock($lock, LOCK_EX | LOCK_NB);

    expect($state->terkunci($project))->toBeTrue();

    flock($lock, LOCK_UN);
    fclose($lock);

    expect($state->terkunci($project))->toBeFalse();
});

it('melepas runner Codex saat tombol buat 3 kandidat ditekan', function () {
    $this->mock(MockupCodexLauncher::class)
        ->shouldReceive('launch')
        ->once()
        ->andReturn(true);

    $project = codexProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.mockup.store', $project))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($project->mockupCandidates()->count())->toBe(3)
        ->and($project->mockupCandidates()->pluck('status')->unique()->all())->toBe([MockupCandidate::STATUS_DRAFT]);
});

it('menandai kandidat gagal kalau runner Codex tidak bisa dilepas', function () {
    $this->mock(MockupCodexLauncher::class)
        ->shouldReceive('launch')
        ->once()
        ->andReturn(false);

    $project = codexProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.mockup.store', $project))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($project->mockupCandidates()->whereNotNull('failure_reason')->count())->toBe(3);
});

it('melaporkan berhenti lewat endpoint status saat denyut runner basi', function () {
    $project = codexProject();
    codexCandidate($project, 1);
    codexCandidate($project, 2);
    codexCandidate($project, 3);

    $state = app(MockupCodexState::class);
    $state->write($project, [
        'keadaan' => MockupCodexState::KEADAAN_JALAN,
        'kandidat_ke' => 1,
        'jumlah' => 3,
        'tahap' => 'menulis halaman',
    ]);

    $path = $state->statusPath($project);
    $data = json_decode((string) file_get_contents($path), true);
    $data['denyut'] = now()->subMinutes(6)->toIso8601String();
    file_put_contents($path, json_encode($data));

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('pages.projects.mockup.status', $project))
        ->assertOk();

    expect($response->json('runner.state'))->toBe('berhenti')
        ->and($response->json('has_running'))->toBeFalse()
        ->and($response->json('candidates.0.stopped'))->toBeTrue()
        ->and($response->json('candidates.0.is_running'))->toBeFalse();
});

it('melepas draf basi dan melepas runner lagi saat kandidat dibuat ulang', function () {
    $this->mock(MockupCodexLauncher::class)
        ->shouldReceive('launch')
        ->once()
        ->andReturn(true);

    $project = codexProject();
    $lama = codexCandidate($project, 2);

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.mockup.regenerate', [$project, $lama]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $rows = $project->mockupCandidates()->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->status)->toBe(MockupCandidate::STATUS_DRAFT)
        ->and($rows[0]->failure_reason)->toBe('Proses berhenti sebelum selesai.')
        ->and($rows[1]->status)->toBe(MockupCandidate::STATUS_DRAFT)
        ->and($rows[1]->failure_reason)->toBeNull()
        ->and($rows[1]->candidate_number)->toBe(2);
});

it('menolak buat ulang selama runner Codex masih memegang kunci', function () {
    $this->mock(MockupCodexLauncher::class)->shouldNotReceive('launch');

    $project = codexProject();
    $candidate = codexCandidate($project, 1, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW]);

    $state = app(MockupCodexState::class);
    $lock = fopen($state->lockPath($project), 'c');
    flock($lock, LOCK_EX | LOCK_NB);

    try {
        $this->actingAs(User::factory()->create())
            ->post(route('pages.projects.mockup.regenerate', [$project, $candidate]))
            ->assertRedirect()
            ->assertSessionHas('error');

        expect($project->mockupCandidates()->count())->toBe(1);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

it('membuat ulang semua kandidat gagal lewat satu tombol tanpa menyentuh yang siap', function () {
    $this->mock(MockupCodexLauncher::class)
        ->shouldReceive('launch')
        ->once()
        ->andReturn(true);

    $project = codexProject();
    codexCandidate($project, 1, ['failure_reason' => 'foto tidak lengkap (0 dari 2).']);
    codexCandidate($project, 2, ['status' => MockupCandidate::STATUS_AWAITING_REVIEW, 'idea_sentence' => 'Kandidat siap']);
    codexCandidate($project, 3, ['failure_reason' => 'melewati batas waktu 20 menit.']);

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.mockup.regenerate-all', $project))
        ->assertRedirect()
        ->assertSessionHas('success');

    $rows = $project->mockupCandidates()->orderBy('id')->get();

    expect($rows)->toHaveCount(5)
        ->and($rows->where('candidate_number', 2))->toHaveCount(1)
        ->and($rows->where('candidate_number', 2)->first()->status)->toBe(MockupCandidate::STATUS_AWAITING_REVIEW)
        ->and($rows->where('candidate_number', 1)->last()->isRunning())->toBeTrue()
        ->and($rows->where('candidate_number', 3)->last()->isRunning())->toBeTrue();
});

it('menolak buat ulang semua selama runner Codex masih memegang kunci', function () {
    $this->mock(MockupCodexLauncher::class)->shouldNotReceive('launch');

    $project = codexProject();
    codexCandidate($project, 1, ['failure_reason' => 'gagal.']);

    $state = app(MockupCodexState::class);
    $lock = fopen($state->lockPath($project), 'c');
    flock($lock, LOCK_EX | LOCK_NB);

    try {
        $this->actingAs(User::factory()->create())
            ->post(route('pages.projects.mockup.regenerate-all', $project))
            ->assertRedirect()
            ->assertSessionHas('error');

        expect($project->mockupCandidates()->count())->toBe(1);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

it('menukar penanda foto dengan berkas nyata hanya pada salinan potret', function () {
    $dir = sys_get_temp_dir() . '/potret-' . uniqid();
    File::ensureDirectoryExists($dir);
    file_put_contents($dir . '/hero.png', codexPng(200));
    file_put_contents($dir . '/preview.png', codexPng(200));

    try {
        $html = '<img src="__EXITO_IMAGE:hero" alt="Hero">'
            . '<div style="background-image: url(\'__EXITO_IMAGE:galeri-1\')"></div>';

        $hasil = (new PageSnapshot())->htmlUntukPotret($html, $dir);

        expect($hasil)
            ->toContain('src="hero.png"')
            ->toContain("url('__EXITO_IMAGE:galeri-1')")
            ->not->toContain('__EXITO_IMAGE:hero');
    } finally {
        File::deleteDirectory($dir);
    }
});

it('menolak foto yang ditulis sebagai teks, bukan pada elemen gambar', function () {
    $dir = sys_get_temp_dir() . '/markah-' . uniqid();
    File::ensureDirectoryExists($dir);
    file_put_contents(
        $dir . '/index.html',
        '<figure class="hero">__EXITO_IMAGE:hero</figure><img src="__EXITO_IMAGE:galeri-1" alt="Galeri">'
    );

    $runner = app(MockupCodexRunner::class);
    $cek = new ReflectionMethod($runner, 'pastikanFotoDipakai');
    $cek->setAccessible(true);

    try {
        $cek->invoke($runner, $dir, ['galeri-1']);

        expect(fn () => $cek->invoke($runner, $dir, ['hero']))
            ->toThrow(RuntimeException::class, 'teks');
    } finally {
        File::deleteDirectory($dir);
    }
});

it('memakai nama foto yang dimintakan halaman, bukan dua nama baku', function () {
    $dir = sys_get_temp_dir() . '/slot-' . uniqid();
    File::ensureDirectoryExists($dir . '/pages');
    file_put_contents($dir . '/index.html', '<img src="__EXITO_IMAGE:hero"><img src="__EXITO_IMAGE:about">');
    file_put_contents($dir . '/pages/layanan.html', '<img src="__EXITO_IMAGE:service-truck"><img src="__EXITO_IMAGE:hero">');

    $runner = app(MockupCodexRunner::class);
    $baca = new ReflectionMethod($runner, 'slotDariHalaman');
    $baca->setAccessible(true);

    try {
        expect($baca->invoke($runner, $dir))->toBe(['hero', 'about', 'service-truck']);
    } finally {
        File::deleteDirectory($dir);
    }
});

it('melewati tahap foto ketika halaman tidak memakai foto sama sekali', function () {
    $dir = sys_get_temp_dir() . '/tanpa-foto-' . uniqid();
    File::ensureDirectoryExists($dir);
    file_put_contents($dir . '/index.html', '<h1>Trucking</h1>');

    $runner = app(MockupCodexRunner::class);
    $baca = new ReflectionMethod($runner, 'slotDariHalaman');
    $baca->setAccessible(true);

    try {
        expect($baca->invoke($runner, $dir))->toBe([]);
    } finally {
        File::deleteDirectory($dir);
    }
});
