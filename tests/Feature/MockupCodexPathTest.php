<?php

use App\Models\MockupCandidate;
use App\Models\Project;
use App\Models\User;
use App\Jobs\GenerateMockupCandidate;
use App\Services\MockupCodexBrief;
use App\Services\MockupCodexPhotos;
use App\Services\MockupCodexRunner;
use App\Support\MockupCodexAssets;
use App\Support\MockupOpenAiLauncher;
use App\Support\MockupCodexState;
use App\Support\MockupWorkspace;
use Illuminate\Support\Facades\Bus;
use App\Support\PageSnapshot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

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

/** Simpan satu berkas aset klien di disk public + baris mockup_assets-nya. */
function codexAset(Project $project, string $kategori, string $nama, string $isi): void
{
    $jalur = 'mockup-assets/' . $project->id . '/' . $nama;

    Storage::disk('public')->put($jalur, $isi);

    $project->mockupAssets()->create([
        'category' => $kategori,
        'original_name' => $nama,
        'file_path' => $jalur,
        'file_size' => strlen($isi),
        'mime_type' => str_ends_with($nama, '.pdf') ? 'application/pdf' : 'image/png',
    ]);
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

it('memasukkan acuan desain klien ke bagian 03 brief saat potretnya ada', function () {
    Storage::fake('public');
    $project = codexProject();
    $candidate = codexCandidate($project, 1);

    Storage::disk('public')->put('design-references/acuan.png', codexPng(2000));
    $project->update([
        'design_reference_type' => 'url',
        'design_reference_url' => 'https://situs-klien.com',
        'design_reference_path' => 'design-references/acuan.png',
    ]);

    $dir = $this->codexBase . '/brief-' . uniqid();
    File::ensureDirectoryExists($dir . '/kerja');
    File::ensureDirectoryExists($dir . '/arsip');

    $tulis = new ReflectionMethod(MockupCodexRunner::class, 'tulisBrief');
    $tulis->setAccessible(true);
    $tulis->invoke(app(MockupCodexRunner::class), $project, $candidate, $dir . '/kerja', $dir . '/arsip');

    $brief = File::get($dir . '/arsip/brief.txt');

    // Berkasan acuan ikut tersalin ke folder kerja, bukan cuma disebut.
    expect($brief)
        ->toContain('## 03 — Desain referensi (acuan mengikat)')
        ->toContain('`acuan/acuan.png`')
        ->toContain('design: acuan/acuan.png')
        ->not->toContain('Buat SATU gambar mockup halaman utama')
        ->and(File::exists($dir . '/kerja/acuan/acuan.png'))->toBeTrue();
});

it('tetap memakai arah desain biasa saat proyek belum punya acuan', function () {
    Storage::fake('public');
    $project = codexProject();
    $candidate = codexCandidate($project, 1);

    $dir = $this->codexBase . '/brief-' . uniqid();
    File::ensureDirectoryExists($dir . '/kerja');
    File::ensureDirectoryExists($dir . '/arsip');

    $tulis = new ReflectionMethod(MockupCodexRunner::class, 'tulisBrief');
    $tulis->setAccessible(true);
    $tulis->invoke(app(MockupCodexRunner::class), $project, $candidate, $dir . '/kerja', $dir . '/arsip');

    expect(File::get($dir . '/arsip/brief.txt'))
        ->toContain('## 03 — Arah desain dan design tokens')
        ->and(File::exists($dir . '/kerja/acuan'))->toBeFalse();
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

it('melepas runner OpenAI saat tombol buat 3 kandidat ditekan', function () {
    $this->mock(MockupOpenAiLauncher::class)
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

it('menandai kandidat gagal kalau runner OpenAI tidak bisa dilepas', function () {
    $launcher = $this->mock(MockupOpenAiLauncher::class);
    $launcher->shouldReceive('launch')
        ->once()
        ->andReturn(false);
    $launcher->shouldReceive('masalahMesin')->andReturn(null);

    $project = codexProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.mockup.store', $project))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($project->mockupCandidates()->whereNotNull('failure_reason')->count())->toBe(3);
});

it('mencatat kegagalan status bila API key belum dikonfigurasi', function () {
    config(['services.openai.key' => null]);
    $project = codexProject();

    expect(app(MockupOpenAiLauncher::class)->launch($project))->toBeFalse()
        ->and(app(MockupCodexState::class)->read($project))
        ->toMatchArray([
            'keadaan' => MockupCodexState::KEADAAN_GAGAL,
            'pesan' => 'OPENAI_API_KEY belum diatur pada server.',
        ]);
});

it('memasukkan kandidat ke queue satu per satu tanpa menjalankan proses dari request web', function () {
    config([
        'services.openai.key' => 'test-openai-key',
        'queue.default' => 'database',
    ]);
    Bus::fake();

    $project = codexProject();
    $candidates = collect([1, 2, 3])->map(fn (int $number) => codexCandidate($project, $number));

    expect(app(MockupOpenAiLauncher::class)->launch($project))->toBeTrue();

    Bus::assertDispatchedTimes(GenerateMockupCandidate::class, 3);
    Bus::assertDispatched(GenerateMockupCandidate::class, fn (GenerateMockupCandidate $job) =>
        $job->projectId === $project->id
        && $job->candidateId === $candidates[0]->id
        && $job->candidateNumber === 1
        && $job->queuedCandidates === 3
    );

    expect(app(MockupCodexState::class)->read($project))
        ->toMatchArray([
            'keadaan' => MockupCodexState::KEADAAN_MENUNGGU,
            'queued_candidate_ids' => $candidates->pluck('id')->all(),
        ]);
});

it('menolak queue sync agar generasi panjang tidak berjalan dalam request web', function () {
    config([
        'services.openai.key' => 'test-openai-key',
        'queue.default' => 'sync',
    ]);
    Bus::fake();

    $project = codexProject();

    expect(app(MockupOpenAiLauncher::class)->masalahMesin())
        ->toBe('Queue belum memakai worker latar. Atur QUEUE_CONNECTION=database dan aktifkan worker agar mockup tidak berjalan di request web.')
        ->and(app(MockupOpenAiLauncher::class)->launch($project))->toBeFalse();

    Bus::assertNothingDispatched();
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
    $this->mock(MockupOpenAiLauncher::class)
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

it('menolak buat ulang selama runner masih memegang kunci', function () {
    $this->mock(MockupOpenAiLauncher::class)->shouldNotReceive('launch');

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
    $this->mock(MockupOpenAiLauncher::class)
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

it('menolak buat ulang semua selama runner masih memegang kunci', function () {
    $this->mock(MockupOpenAiLauncher::class)->shouldNotReceive('launch');

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

it('menyalin logo dan foto klien ke folder kerja Codex beserta daftarnya', function () {
    Storage::fake('public');
    $project = codexProject();

    codexAset($project, 'logo', 'logo-klien.png', codexPng(2000));
    codexAset($project, 'foto', 'gudang.jpg', codexPng(2000));

    $kerja = $this->codexBase . '/aset-' . uniqid();
    File::ensureDirectoryExists($kerja);

    $berkas = (new MockupCodexAssets)->letakan($project, $kerja);

    expect($berkas)->toBe([
        ['jalur' => 'aset/logo-1.png', 'kategori' => 'logo', 'asal' => 'logo-klien.png'],
        ['jalur' => 'aset/foto-1.jpg', 'kategori' => 'foto', 'asal' => 'gudang.jpg'],
    ])->and(File::exists($kerja . '/aset/logo-1.png'))->toBeTrue();

    $daftar = (string) file_get_contents($kerja . '/aset.txt');

    expect($daftar)->toContain('ASET MILIK KLIEN')
        ->toContain('`aset/logo-1.png` — Logo Klien (nama asli: logo-klien.png)')
        ->toContain('mengurangi satu foto yang harus dibuat')
        ->not->toContain('Referensi Desain');
});

it('melewati berkas yang bukan gambar dan tidak menulis daftar untuk proyek kosong', function () {
    Storage::fake('public');
    $project = codexProject();

    codexAset($project, 'dokumen', 'profil.pdf', 'bukan gambar');
    codexAset($project, 'foto', 'brosur.zip', 'bukan gambar');
    codexAset($project, 'logo', 'besar.png', str_repeat("\0", 10 * 1024 * 1024));

    $kerja = $this->codexBase . '/kosong-' . uniqid();
    File::ensureDirectoryExists($kerja);

    // Ukuran tidak lagi menyaring: bahan klien sebesar apa pun ikut dipakai.
    $berkas = (new MockupCodexAssets)->letakan($project, $kerja);

    expect($berkas)->toBe([
        ['jalur' => 'aset/logo-1.png', 'kategori' => 'logo', 'asal' => 'besar.png'],
    ])->and(File::exists($kerja . '/aset/logo-1.png'))->toBeTrue();

    // Proyek tanpa satu pun aset: folder kerja tetap hanya berisi brief.txt.
    $tanpaAset = codexProject();

    expect((new MockupCodexAssets)->letakan($tanpaAset, $kerja))->toBe([]);
});

it('menyalin seluruh foto klien tanpa membatasi jumlah', function () {
    Storage::fake('public');
    $project = codexProject();

    for ($i = 1; $i <= 10; $i++) {
        codexAset($project, 'foto', 'foto-' . $i . '.png', codexPng(500));
    }

    $kerja = $this->codexBase . '/sepuluh-' . uniqid();
    File::ensureDirectoryExists($kerja);

    $berkas = (new MockupCodexAssets)->letakan($project, $kerja);

    expect($berkas)->toHaveCount(10)
        ->and($berkas[0]['jalur'])->toBe('aset/foto-1.png')
        ->and($berkas[9]['jalur'])->toBe('aset/foto-10.png');
});

it('menyebut daftar aset di perintah Codex hanya kalau asetnya ada', function () {
    if (app(MockupCodexState::class)->nodeBinary() === null) {
        $this->markTestSkipped('Node.js tidak ditemukan di mesin ini.');
    }

    $kerja = $this->codexBase . '/perintah-' . uniqid();
    File::ensureDirectoryExists($kerja);

    $runner = app(MockupCodexRunner::class);
    $baca = new ReflectionMethod($runner, 'perintah');
    $baca->setAccessible(true);

    $perintahTanpa = $baca->invoke($runner, $kerja);
    $tanpaAset = (string) end($perintahTanpa);

    File::put($kerja . '/aset.txt', '# ASET MILIK KLIEN');

    $perintahDengan = $baca->invoke($runner, $kerja);
    $denganAset = (string) end($perintahDengan);

    expect($tanpaAset)->toContain('brief.txt')
        ->not->toContain('aset.txt')
        ->and($denganAset)->toContain('brief.txt dan aset.txt');
});
