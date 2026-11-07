<?php

use App\Models\MockupAsset;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Models\User;
use App\Support\MockupCodexAssets;
use App\Support\MockupCodexReference;
use App\Support\MockupWorkspace;
use App\Support\PageSnapshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Chromium tidak dijalankan di tes. Yang diuji adalah apa yang dilakukan
 * controller dengan hasil potret, jadi potret palsunya cuma menulis berkas.
 */
class PotretAcuanPalsu extends PageSnapshot
{
    public function __construct(private bool $gagal = false)
    {
    }

    public function captureUrl(string $url, string $outputPath): string
    {
        if ($this->gagal) {
            throw new RuntimeException('situs tidak bisa dibuka');
        }

        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }

        file_put_contents($outputPath, "\x89PNG\r\n\x1a\npotret-palsuIEND");

        return $outputPath;
    }
}

function acuanProject(): Project
{
    static $nomor = 0;

    return Project::create([
        'code' => 'ACU-' . str_pad((string) ++$nomor, 4, '0', STR_PAD_LEFT),
        'name' => 'Studio Acuan Uji',
        'client_name' => 'Klien Acuan',
        'type' => 'Company',
        'status' => 'request',
        'website_name' => 'Studio Acuan',
        'target_market' => 'Pecinta desain',
        'description' => 'Studio arsitektur.',
    ]);
}

it('menyimpan gambar acuan di kolom referensi proyek, bukan di daftar bahan klien', function () {
    Storage::fake('public');
    $project = acuanProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.reference.store', $project), [
            'type' => 'image',
            'file' => UploadedFile::fake()->createWithContent('acuan-klien.png', 'isi gambar'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $project->refresh();

    expect($project->design_reference_type)->toBe('image')
        ->and($project->design_reference_url)->toBeNull()
        ->and($project->design_reference_path)->toStartWith('design-references/' . $project->id . '/')
        ->and(Storage::disk('public')->exists($project->design_reference_path))->toBeTrue()
        ->and(MockupAsset::count())->toBe(0);
});

it('menyimpan ZIP template dan menolak berkas yang bukan template', function () {
    Storage::fake('public');
    $project = acuanProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.reference.store', $project), [
            'type' => 'zip',
            'file' => UploadedFile::fake()->createWithContent('template.zip', 'isi arsip'),
        ])
        ->assertSessionHas('success');

    expect($project->refresh()->design_reference_type)->toBe('zip');

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.reference.store', $project), [
            'type' => 'zip',
            'file' => UploadedFile::fake()->createWithContent('ternyata-gambar.png', 'isi gambar'),
        ])
        ->assertSessionHas('error');

    expect($project->refresh()->design_reference_type)->toBe('zip');
});

it('menyimpan tautan acuan sebagai potret situs', function () {
    Storage::fake('public');
    app()->instance(PageSnapshot::class, new PotretAcuanPalsu);

    $project = acuanProject();
    $project->update([
        'design_reference_type' => 'image',
        'design_reference_path' => 'design-references/1/lama.png',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.reference.store', $project), [
            'type' => 'url',
            'url' => 'https://situs-klien.com',
        ])
        ->assertSessionHas('success', 'Situs berhasil dipotret dan dipakai sebagai acuan.');

    $project->refresh();

    expect($project->design_reference_type)->toBe('url')
        ->and($project->design_reference_url)->toBe('https://situs-klien.com')
        ->and($project->design_reference_path)->toStartWith('design-references/' . $project->id . '/potret-')
        ->and(Storage::disk('public')->exists($project->design_reference_path))->toBeTrue();
});

it('tetap menyimpan tautan ketika potret situs gagal', function () {
    Storage::fake('public');
    app()->instance(PageSnapshot::class, new PotretAcuanPalsu(gagal: true));

    $project = acuanProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.reference.store', $project), [
            'type' => 'url',
            'url' => 'https://situs-yang-mati.test',
        ])
        ->assertSessionHas('error');

    $project->refresh();

    expect($project->design_reference_url)->toBe('https://situs-yang-mati.test')
        ->and($project->design_reference_path)->toBeNull()
        ->and(session('error'))->toContain('gagal dipotret');
});

it('menulis ulang potret ketika potret diambil ulang', function () {
    Storage::fake('public');
    app()->instance(PageSnapshot::class, new PotretAcuanPalsu);

    $project = acuanProject();

    $kirim = fn() => $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.reference.store', $project), [
            'type' => 'url',
            'url' => 'https://situs-klien.com',
        ]);

    $kirim();
    $pertama = $project->refresh()->design_reference_path;

    usleep(1_100_000);
    $kirim();
    $kedua = $project->refresh()->design_reference_path;

    // Berkas baru, jadi potret lama tidak tertimpa di tengah kandidat yang
    // sedang berjalan.
    expect($kedua)->not->toBe($pertama)
        ->and(Storage::disk('public')->exists($pertama))->toBeTrue()
        ->and(Storage::disk('public')->exists($kedua))->toBeTrue();
});

it('menolak tautan yang bukan alamat web dan kosongkan lewat pilihan kosongkan', function () {
    $project = acuanProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.reference.store', $project), ['type' => 'url', 'url' => 'bukan url'])
        ->assertSessionHasErrors('url');

    $project->update(['design_reference_type' => 'url', 'design_reference_url' => 'https://lama.test']);

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.reference.store', $project), ['type' => 'none'])
        ->assertSessionHas('success');

    $project->refresh();

    expect($project->design_reference_type)->toBe('none')
        ->and($project->design_reference_url)->toBeNull();
});

it('memisahkan kartu acuan dari daftar bahan klien di meja kerja', function () {
    Storage::fake('public');
    $project = acuanProject();

    $project->mockupAssets()->create([
        'category' => 'logo',
        'original_name' => 'logo-baru.png',
        'file_path' => 'mockup-assets/1/logo-baru.png',
        'file_size' => 10,
        'mime_type' => 'image/png',
    ]);

    // Baris lama dari sebelum referensi punya kartu sendiri tidak boleh
    // dihitung sebagai bahan klien.
    $project->mockupAssets()->create([
        'category' => 'referensi',
        'original_name' => 'acuan-lama.png',
        'file_path' => 'design-references/acuan-lama.png',
        'file_size' => 10,
        'mime_type' => 'image/png',
    ]);

    $project->update([
        'design_reference_type' => 'url',
        'design_reference_url' => 'https://situs-klien.com',
    ]);

    // Panel bahan klien dan kartu acuan hanya muncul begitu ada kandidat.
    $kandidat = MockupCandidate::create([
        'project_id' => $project->id,
        'candidate_number' => 1,
        'status' => MockupCandidate::STATUS_AWAITING_REVIEW,
    ]);
    $kandidat->update(['folder' => (new MockupWorkspace())->relativeFolder($kandidat)]);

    $this->actingAs(User::factory()->create())
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('Referensi Desain Klien')
        ->assertSee('https://situs-klien.com')
        ->assertSee('1 File tersimpan')
        ->assertSee('Ambil ulang potret')
        ->assertSee('Potret belum berhasil diambil')
        ->assertDontSee('acuan-lama.png');
});

it('menampilkan potret situs di kartu acuan begitu potretnya ada', function () {
    Storage::fake('public');
    app()->instance(PageSnapshot::class, new PotretAcuanPalsu);

    $project = acuanProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.reference.store', $project), [
            'type' => 'url',
            'url' => 'https://situs-klien.com',
        ]);

    $kandidat = MockupCandidate::create([
        'project_id' => $project->id,
        'candidate_number' => 1,
        'status' => MockupCandidate::STATUS_AWAITING_REVIEW,
    ]);
    $kandidat->update(['folder' => (new MockupWorkspace())->relativeFolder($kandidat)]);

    $project->refresh();

    $this->actingAs(User::factory()->create())
        ->get('/project-workspace?project=' . $project->id)
        ->assertOk()
        ->assertSee('mengirim gambarnya ke OpenAI sebagai acuan')
        ->assertSee('alt="Potret situs referensi"', false);
});

/** PNG palsu dengan isi sebesar $ukuran byte. */
function acuanIsi(int $ukuran): string
{
    return "\x89PNG\r\n\x1a\n" . str_repeat('a', max(0, $ukuran - 12)) . 'IEND';
}

/** Arsip zip dari pasangan jalur => isi; mengembalikan jalur berkasnya. */
function acuanZip(array $isi): string
{
    $jalur = sys_get_temp_dir() . '/acuan-zip-' . uniqid() . '.zip';
    $arsip = new ZipArchive;
    $arsip->open($jalur, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($isi as $nama => $bytes) {
        $arsip->addFromString($nama, $bytes);
    }

    $arsip->close();

    return $jalur;
}

it('menyalin gambar acuan ke folder kerja Codex sebagai acuan/acuan.png', function () {
    Storage::fake('public');
    $project = acuanProject();

    Storage::disk('public')->put('design-references/1/acuan-klien.png', acuanIsi(1000));

    $project->update([
        'design_reference_type' => 'image',
        'design_reference_path' => 'design-references/1/acuan-klien.png',
    ]);

    $kerja = sys_get_temp_dir() . '/kerja-acuan-' . uniqid();
    \Illuminate\Support\Facades\File::ensureDirectoryExists($kerja);

    try {
        expect((new MockupCodexReference)->letakan($project, $kerja))->toBe('acuan/acuan.png')
            ->and(\Illuminate\Support\Facades\File::exists($kerja . '/acuan/acuan.png'))->toBeTrue()
            ->and(\Illuminate\Support\Facades\File::get($kerja . '/acuan/acuan.png'))->toBe(acuanIsi(1000));
    } finally {
        \Illuminate\Support\Facades\File::deleteDirectory($kerja);
    }
});

it('memakai gambar terbesar dari ZIP template sebagai acuan', function () {
    Storage::fake('public');
    $project = acuanProject();

    $zip = acuanZip([
        'template/screenshot.png' => acuanIsi(200),
        'template/home.png' => acuanIsi(9000),
        'template/style.css' => 'body{color:red}',
        'template/kecil.png' => acuanIsi(50),
    ]);

    Storage::disk('public')->put('design-references/1/template.zip', (string) file_get_contents($zip));
    $project->update([
        'design_reference_type' => 'zip',
        'design_reference_path' => 'design-references/1/template.zip',
    ]);

    $kerja = sys_get_temp_dir() . '/kerja-acuan-' . uniqid();
    \Illuminate\Support\Facades\File::ensureDirectoryExists($kerja);

    try {
        expect((new MockupCodexReference)->letakan($project, $kerja))->toBe('acuan/acuan.png')
            ->and(\Illuminate\Support\Facades\File::size($kerja . '/acuan/acuan.png'))->toBe(9000);
    } finally {
        \Illuminate\Support\Facades\File::deleteDirectory($kerja);
    }
});

it('menganggap tidak ada acuan kalau potretnya belum diambil atau bentuknya bukan gambar', function () {
    Storage::fake('public');
    $project = acuanProject();
    $kerja = sys_get_temp_dir() . '/kerja-acuan-' . uniqid();
    \Illuminate\Support\Facades\File::ensureDirectoryExists($kerja);

    $acuan = new MockupCodexReference;

    try {
        // Tautan tanpa potret: Codex tidak bisa membuka internet.
        $project->update([
            'design_reference_type' => 'url',
            'design_reference_url' => 'https://situs-klien.com',
            'design_reference_path' => null,
        ]);
        expect($acuan->letakan($project, $kerja))->toBeNull();

        // Berkasnya sudah tidak ada di storage.
        $project->update(['design_reference_path' => 'design-references/1/dihapus.png']);
        expect($acuan->letakan($project, $kerja))->toBeNull();

        // SVG tidak bisa dilihat Codex sebagai gambar.
        Storage::disk('public')->put('design-references/1/acuan.svg', '<svg/>');
        $project->update([
            'design_reference_type' => 'image',
            'design_reference_path' => 'design-references/1/acuan.svg',
        ]);
        expect($acuan->letakan($project, $kerja))->toBeNull()
            ->and(\Illuminate\Support\Facades\File::exists($kerja . '/acuan'))->toBeFalse();
    } finally {
        \Illuminate\Support\Facades\File::deleteDirectory($kerja);
    }
});

it('tidak menyalin acuan lama ke folder kerja Codex karena acuan bukan bahan klien', function () {
    Storage::fake('public');
    $project = acuanProject();

    $project->mockupAssets()->create([
        'category' => 'referensi',
        'original_name' => 'acuan-lama.png',
        'file_path' => 'design-references/acuan-lama.png',
        'file_size' => 12,
        'mime_type' => 'image/png',
    ]);

    Storage::disk('public')->put('design-references/acuan-lama.png', 'isi gambar');

    $kerja = sys_get_temp_dir() . '/acuan-' . uniqid();
    \Illuminate\Support\Facades\File::ensureDirectoryExists($kerja);

    try {
        expect((new MockupCodexAssets)->letakan($project, $kerja))->toBe([])
            ->and(\Illuminate\Support\Facades\File::exists($kerja . '/aset.txt'))->toBeFalse();
    } finally {
        \Illuminate\Support\Facades\File::deleteDirectory($kerja);
    }
});
