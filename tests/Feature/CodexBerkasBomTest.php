<?php

use App\Services\MockupCodexPhotos;
use App\Services\MockupCodexRunner;
use App\Support\MockupCodexText;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Berkas hasil Codex di Windows bisa ber-BOM, UTF-16, atau CRLF. Semua
 * pembaca sudah lewat satu pintu, jadi tes ini memakai berkas sungguhan
 * di folder sementara, bukan string jadi.
 */

function bomPng(int $padding = 60000): string
{
    return "\x89PNG\r\n\x1a\n" . str_repeat("\0", $padding) . 'IEND';
}

function bomRoot(string $nama): string
{
    $root = sys_get_temp_dir() . '/' . $nama . '-' . uniqid();
    File::ensureDirectoryExists($root . '/kandidat');

    return $root;
}

function utf16le(string $isi): string
{
    return "\xFF\xFE" . mb_convert_encoding($isi, 'UTF-16LE', 'UTF-8');
}

it('membuang BOM UTF-8, UTF-16, dan CRLF pada satu pintu baca', function () {
    $root = bomRoot('bom-teks');

    file_put_contents($root . '/bom.txt', "\xEF\xBB\xBF" . "satu\ndua\n");
    file_put_contents($root . '/utf16.txt', utf16le("tiga\nempat\n"));
    file_put_contents($root . '/crlf.txt', "lima\r\nenam\r\n");

    expect(MockupCodexText::baca($root . '/bom.txt'))->toBe("satu\ndua\n")
        ->and(MockupCodexText::baca($root . '/utf16.txt'))->toBe("tiga\nempat\n")
        ->and(MockupCodexText::baca($root . '/crlf.txt'))->toBe("lima\nenam\n");

    File::deleteDirectory($root);
});

it('mengembalikan string kosong untuk berkas yang tidak ada', function () {
    expect(MockupCodexText::baca(sys_get_temp_dir() . '/bom-tiada-' . uniqid() . '/ide.txt'))
        ->toBe('');
});

it('gagal keras ketika berkas hasil Codex bukan UTF-8 yang valid', function () {
    $root = bomRoot('bom-galat');
    file_put_contents($root . '/halaman.html', "\xE2\x28\xA1");

    expect(fn () => MockupCodexText::baca($root . '/halaman.html'))
        ->toThrow(RuntimeException::class, 'bukan UTF-8 yang valid');

    File::deleteDirectory($root);
});

it('memasang foto hero walau foto-slot.txt ber-BOM', function () {
    $root = bomRoot('bom-manifest');
    file_put_contents($root . '/hero-sumber.png', bomPng());
    file_put_contents(
        $root . '/kandidat/foto-slot.txt',
        "\xEF\xBB\xBF" . 'hero: ' . $root . '/hero-sumber.png' . "\n"
    );

    $hasil = (new MockupCodexPhotos())->recover(
        $root . '/kandidat',
        $root . '/kandidat/foto-slot.txt',
        null,
        ['hero'],
    );

    expect($hasil['jumlah'])->toBe(1)
        ->and($hasil['pesan'])->toBe('')
        ->and(is_file($root . '/kandidat/hero.png'))->toBeTrue();

    File::deleteDirectory($root);
});

it('memasang foto hero walau foto-slot.txt ditulis UTF-16LE', function () {
    $root = bomRoot('bom-utf16');
    file_put_contents($root . '/hero-sumber.png', bomPng());
    file_put_contents(
        $root . '/kandidat/foto-slot.txt',
        utf16le('hero: ' . $root . '/hero-sumber.png' . "\n")
    );

    $hasil = (new MockupCodexPhotos())->recover(
        $root . '/kandidat',
        $root . '/kandidat/foto-slot.txt',
        null,
        ['hero'],
    );

    expect($hasil['jumlah'])->toBe(1)
        ->and(is_file($root . '/kandidat/hero.png'))->toBeTrue();

    File::deleteDirectory($root);
});

it('tidak meninggalkan tanda baris Windows di akhir jalur foto', function () {
    $root = bomRoot('bom-crlf');
    file_put_contents($root . '/hero-sumber.png', bomPng());
    file_put_contents(
        $root . '/kandidat/foto-slot.txt',
        'hero: ' . $root . '/hero-sumber.png' . "\r\n"
    );

    $hasil = (new MockupCodexPhotos())->recover(
        $root . '/kandidat',
        $root . '/kandidat/foto-slot.txt',
        null,
        ['hero'],
    );

    expect($hasil['jumlah'])->toBe(1);

    File::deleteDirectory($root);
});

it('mengecualikan gambar rancangan walau desain-referensi.txt ber-BOM', function () {
    $root = bomRoot('bom-rancangan');
    file_put_contents($root . '/konsep-sumber.png', bomPng());
    file_put_contents(
        $root . '/kandidat/foto-slot.txt',
        'konsep: ' . $root . '/konsep-sumber.png' . "\n"
    );
    file_put_contents(
        $root . '/kandidat/desain-referensi.txt',
        "\xEF\xBB\xBF" . 'design: ' . $root . '/konsep-sumber.png' . "\n"
    );

    $hasil = (new MockupCodexPhotos())->recover(
        $root . '/kandidat',
        $root . '/kandidat/foto-slot.txt',
        null,
        ['konsep'],
    );

    expect($hasil['jumlah'])->toBe(0)
        ->and(is_file($root . '/kandidat/konsep.png'))->toBeFalse();

    File::deleteDirectory($root);
});

it('membaca ide.txt ber-BOM tanpa sampah di depan kalimat', function () {
    $root = bomRoot('bom-ide');
    file_put_contents($root . '/ide.txt', "\xEF\xBB\xBF" . "Vila keluarga dekat pantai.\n");

    $runner = app(MockupCodexRunner::class);
    $baca = new ReflectionMethod($runner, 'bacaIde');
    $baca->setAccessible(true);

    try {
        expect($baca->invoke($runner, $root))->toBe('Vila keluarga dekat pantai.');
    } finally {
        File::deleteDirectory($root);
    }
});

it('menemukan slot foto di halaman yang ditulis UTF-16LE', function () {
    $root = bomRoot('bom-slot');
    file_put_contents($root . '/index.html', utf16le('<img src="__EXITO_IMAGE:hero">'));

    $runner = app(MockupCodexRunner::class);
    $baca = new ReflectionMethod($runner, 'slotDariHalaman');
    $baca->setAccessible(true);

    try {
        expect($baca->invoke($runner, $root))->toBe(['hero']);
    } finally {
        File::deleteDirectory($root);
    }
});

it('mencatat setiap foto yang datang dari cache Codex', function () {
    Log::spy();

    $root = bomRoot('bom-cache');
    File::ensureDirectoryExists($root . '/cache');
    file_put_contents($root . '/cache/baru.png', bomPng(62000));
    file_put_contents($root . '/kandidat/foto-slot.txt', '');

    $hasil = (new MockupCodexPhotos())->recover(
        $root . '/kandidat',
        $root . '/kandidat/foto-slot.txt',
        $root . '/cache',
        ['hero'],
    );

    expect($hasil['jumlah'])->toBe(1);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $pesan) => str_contains($pesan, 'Foto kandidat dipulihkan dari cache Codex'))
        ->atLeast()->once();

    File::deleteDirectory($root);
});
