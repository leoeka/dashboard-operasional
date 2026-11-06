<?php

use App\Models\MockupAsset;
use App\Models\Project;
use App\Models\User;
use App\Support\MockupAssetZip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Satu arsip ZIP dari isi nama Berkas => isi.
 *
 * @param  array<string, string>  $berkas
 */
function zipBuat(array $berkas): string
{
    $sementara = (string) tempnam(sys_get_temp_dir(), 'ujizip');
    $arsip = new ZipArchive;
    $arsip->open($sementara, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($berkas as $nama => $isi) {
        $arsip->addFromString($nama, $isi);
    }

    $arsip->close();
    $isiZip = (string) file_get_contents($sementara);
    unlink($sementara);

    return $isiZip;
}

function zipKodekan(): string
{
    return "\x89PNG\r\n\x1a\n" . str_repeat("\0", 300) . 'IEND';
}

function zipProject(): Project
{
    static $nomor = 0;

    return Project::create([
        'code' => 'ZIP-' . str_pad((string) ++$nomor, 4, '0', STR_PAD_LEFT),
        'name' => 'Kargo Zip Uji',
        'client_name' => 'Klien Zip',
        'type' => 'Company',
        'status' => 'request',
        'website_name' => 'Kargo Zip',
        'target_market' => 'Pengusaha domestik',
        'description' => 'Jasa angkut kargo.',
    ]);
}

function zipUnggah(Project $project, string $isiZip, string $kategori = 'pendukung')
{
    return test()->actingAs(User::factory()->create())
        ->post(route('pages.projects.files.store', $project), [
            'file' => UploadedFile::fake()->createWithContent('bahan-klien.zip', $isiZip),
            'category' => $kategori,
        ]);
}

/** Pesan balasan yang sedang mengantung di sesi, untuk diperiksa tes. */
function sesi(string $kunci): string
{
    return (string) session($kunci);
}

it('membuka satu ZIP menjadi aset per berkas dengan kategori dari nama folder', function () {
    Storage::fake('public');
    $project = zipProject();

    $respons = zipUnggah($project, zipBuat([
        'logo/logo-kargo.png' => zipKodekan(),
        'logo/nama-lain.png' => zipKodekan(),
        'foto/gudang.png' => zipKodekan(),
        'dokumen/profil.pdf' => 'isi pdf',
    ]));

    $respons->assertRedirect()->assertSessionHas('success');

    $aset = $project->mockupAssets()->orderBy('id')->get();

    expect($aset->pluck('category')->all())->toBe(['logo', 'logo', 'foto', 'dokumen'])
        ->and($aset->pluck('original_name')->all())->toBe(['logo-kargo.png', 'nama-lain.png', 'gudang.png', 'profil.pdf'])
        ->and(Storage::disk('public')->exists((string) $aset[0]->file_path))->toBeTrue();

    expect(sesi('success'))
        ->toContain('4 berkas masuk dari ZIP')
        ->toContain('Logo Klien 2')
        ->toContain('Dokumen 1');
});

it('memakai kategori pilihan penunggah untuk berkas di folder yang tidak dikenali', function () {
    Storage::fake('public');
    $project = zipProject();

    zipUnggah($project, zipBuat([
        'dokumen internal/brosur.png' => zipKodekan(),
        'foto-situs.png' => zipKodekan(),
    ]), 'foto');

    $aset = $project->mockupAssets()->orderBy('id')->get();

    expect($aset)->toHaveCount(2)
        ->and($aset->pluck('category')->unique()->all())->toBe(['foto']);
});

it('menolak berkas yang namanya menunjuk keluar folder penyimpanan', function () {
    Storage::fake('public');
    $project = zipProject();

    $respons = zipUnggah($project, zipBuat([
        '../../luar-biasa.png' => zipKodekan(),
        'logo/aman.png' => zipKodekan(),
    ]));

    expect($project->mockupAssets()->count())->toBe(1)
        ->and(sesi('success'))->toContain('1 dilewati')
        ->and(sesi('success'))->toContain('menunjuk keluar folder');
});

it('melewati sampah pembuat arsip dan berkas yang bukan bahan', function () {
    Storage::fake('public');
    $project = zipProject();

    $respons = zipUnggah($project, zipBuat([
        '__MACOSX/logo._png' => zipKodekan(),
        'logo/Thumbs.db' => 'sampah',
        'logo/pasang-aplikasi.exe' => 'biner',
        'logo/asli.png' => zipKodekan(),
    ]));

    expect($project->mockupAssets()->count())->toBe(1)
        ->and($project->mockupAssets()->first()->original_name)->toBe('asli.png')
        ->and(sesi('success'))->toContain('3 dilewati');
});

it('mengarahkan folder referensi ke kartu Referensi Desain, bukan menjadikannya aset', function () {
    Storage::fake('public');
    $project = zipProject();

    $respons = zipUnggah($project, zipBuat([
        'referensi/tata-letak-web-lain.png' => zipKodekan(),
    ]), 'pendukung');

    expect($project->mockupAssets()->count())->toBe(0)
        ->and(sesi('error'))->toContain('kartu Referensi Desain');
});

it('menolak ZIP yang isinya bukan bahan sama sekali', function () {
    Storage::fake('public');
    $project = zipProject();

    $respons = zipUnggah($project, zipBuat(['catatan/skrip.php' => '<?php //']));

    expect($project->mockupAssets()->count())->toBe(0)
        ->and((string) sesi('error'))->not->toBe('');
});

it('menolak ZIP yang tidak bisa dibuka', function () {
    Storage::fake('public');
    $project = zipProject();

    $respons = zipUnggah($project, 'bukan arsip zip sama sekali');

    $respons->assertSessionHas('error');

    expect($project->mockupAssets()->count())->toBe(0);
});

it('tetap menerima satu berkas biasa seperti sebelumnya', function () {
    Storage::fake('public');
    $project = zipProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.files.store', $project), [
            'file' => UploadedFile::fake()->createWithContent('logo-klien.png', zipKodekan()),
            'category' => 'logo',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    /** @var MockupAsset $aset */
    $aset = $project->mockupAssets()->first();

    expect($aset->category)->toBe('logo')
        ->and($aset->original_name)->toBe('logo-klien.png');
});

it('tidak lagi menerima kategori Referensi Desain lewat unggah aset', function () {
    Storage::fake('public');
    $project = zipProject();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.files.store', $project), [
            'file' => UploadedFile::fake()->createWithContent('acuan.png', zipKodekan()),
            'category' => 'referensi',
        ])
        ->assertSessionHasErrors('category');

    expect($project->mockupAssets()->count())->toBe(0);
});

it('meringkas ZIP besar tanpa menyebut lebih dari tiga berkas yang dilewati', function () {
    $berkas = ['logo/asli.png' => zipKodekan()];

    for ($i = 1; $i <= 6; $i++) {
        $berkas['logo/sampah-' . $i . '.db'] = 'x';
    }

    $pesan = (new MockupAssetZip)->ringkas([
        'masuk' => 1,
        'per_kategori' => ['logo' => 1],
        'dilewati' => [
            ['nama' => 'logo/sampah-1.db', 'alasan' => 'bukan gambar atau dokumen'],
            ['nama' => 'logo/sampah-2.db', 'alasan' => 'bukan gambar atau dokumen'],
            ['nama' => 'logo/sampah-3.db', 'alasan' => 'bukan gambar atau dokumen'],
            ['nama' => 'logo/sampah-4.db', 'alasan' => 'bukan gambar atau dokumen'],
        ],
    ]);

    expect($pesan)->toContain('Logo Klien 1')
        ->toContain('4 dilewati')
        ->toContain('dan 1 lainnya');
});
