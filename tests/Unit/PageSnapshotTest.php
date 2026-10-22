<?php

use App\Support\PageSnapshot;

function snapshotWorkspace(): array
{
    $dir = sys_get_temp_dir() . '/page-snapshot-' . uniqid();
    mkdir($dir, 0775, true);

    return [$dir, $dir . '/index.html', $dir . '/preview.png'];
}

function snapshotChromiumReady(): bool
{
    $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';

    return is_dir(getcwd() . '/node_modules/puppeteer')
        && is_dir($home . '/.cache/puppeteer/chrome');
}

it('refuses to snapshot a missing html file', function () {
    [, $html, $png] = snapshotWorkspace();

    (new PageSnapshot())->captureFile($html, $png);
})->throws(RuntimeException::class, 'Berkas HTML tidak ditemukan');

it('renders a local html file into a full-page png', function () {
    if (!snapshotChromiumReady()) {
        $this->markTestSkipped('Chromium puppeteer belum terpasang di mesin ini.');
    }

    [$dir, $html, $png] = snapshotWorkspace();

    // CSS relatif di folder yang sama. Kalau ikut termuat, isi halaman
    // setinggi 3000px dan pembuktian fullPage ikut jalan.
    file_put_contents($dir . '/styles.css', 'body { margin: 0; } .panjang { height: 3000px; background: #2f5bc4; }');
    file_put_contents($html, '<!doctype html><html><head><link rel="stylesheet" href="styles.css"></head>'
        . '<body><div class="panjang"></div></body></html>');

    (new PageSnapshot())->captureFile($html, $png);

    expect(is_file($png))->toBeTrue();

    [$width, $height] = getimagesize($png);

    expect($width)->toBe(PageSnapshot::WIDTH);
    expect($height)->toBeGreaterThan(PageSnapshot::HEIGHT);
});
