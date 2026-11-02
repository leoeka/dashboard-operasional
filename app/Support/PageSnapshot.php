<?php

namespace App\Support;

use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Mata dari loop mockup: me-render berkas HTML kerja kandidat menjadi PNG
 * lewat Chromium tanpa-jendela, supaya AI bisa melihat hasil desainnya
 * sendiri sebelum mengkritiknya.
 *
 * Berkas dirender persis dari lokasinya (bukan dari string), jadi rujukan
 * CSS/gambar relatif di folder kandidat ikut termuat.
 */
class PageSnapshot
{
    public const WIDTH = 1440;
    public const HEIGHT = 900;

    public function captureFile(string $htmlPath, string $outputPath): string
    {
        if (!is_file($htmlPath)) {
            throw new RuntimeException('Berkas HTML tidak ditemukan: ' . $htmlPath);
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        try {
            Browsershot::htmlFromFilePath($htmlPath)
                ->windowSize(self::WIDTH, self::HEIGHT)
                ->fullPage()
                ->noSandbox()
                ->timeout(90)
                ->save($outputPath);
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal memotret halaman: ' . mb_substr($e->getMessage(), 0, 200), 0, $e);
        }

        return $outputPath;
    }
}
