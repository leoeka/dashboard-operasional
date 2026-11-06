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

        // Berkas arsip tetap memuat penanda (dipakai pembangun tema), tetapi
        // potret harus memperlihatkan fotonya. Salinan sementara di folder
        // yang sama menjaga rujukan relatif tetap hidup.
        $renderPath = $htmlPath;
        $sementara = null;
        $html = (string) file_get_contents($htmlPath);

        if (str_contains($html, '__EXITO_IMAGE:')) {
            $sementara = dirname($htmlPath) . '/.potret-' . uniqid() . '.html';

            if (file_put_contents($sementara, $this->htmlUntukPotret($html, dirname($htmlPath))) === false) {
                throw new RuntimeException('Berkas potret sementara tidak bisa ditulis.');
            }

            $renderPath = $sementara;
        }

        try {
            $this->configureBrowser(Browsershot::htmlFromFilePath($renderPath))
                ->windowSize(self::WIDTH, self::HEIGHT)
                ->fullPage()
                ->noSandbox()
                ->timeout(90)
                ->save($outputPath);
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal memotret halaman: ' . mb_substr($e->getMessage(), 0, 200), 0, $e);
        } finally {
            if ($sementara !== null) {
                @unlink($sementara);
            }
        }

        return $outputPath;
    }

    /**
     * Potret satu situs luar ukuran penuh, dipakai sebagai gambar acuan waktu
     * klien cuma mengirim tautan. Codex tidak punya akses internet, jadi
     * tautan harus jadi berkas gambar lebih dulu.
     */
    public function captureUrl(string $url, string $outputPath): string
    {
        $dir = dirname($outputPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        try {
            $this->configureBrowser(Browsershot::url($url))
                ->windowSize(self::WIDTH, self::HEIGHT)
                ->waitUntilNetworkIdle()
                ->fullPage()
                ->noSandbox()
                ->timeout(90)
                ->save($outputPath);
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal memotret situs: ' . mb_substr($e->getMessage(), 0, 200), 0, $e);
        }

        if (!is_file($outputPath)) {
            throw new RuntimeException('Gagal memotret situs: Chromium tidak menulis berkas gambar.');
        }

        return $outputPath;
    }

    /**
     * Tukar tiap penanda foto dengan nama berkas nyata di folder yang sama.
     * Slot tanpa berkas dibiarkan apa adanya supaya tidak diam-diam hilang.
     */
    public function htmlUntukPotret(string $html, string $folder): string
    {
        $berkas = [];

        foreach (glob($folder . '/*.{jpg,jpeg,png,webp,gif,svg}', GLOB_BRACE) ?: [] as $path) {
            $nama = basename($path);

            if ($nama === 'preview.png') {
                continue;
            }

            $berkas[strtolower(pathinfo($nama, PATHINFO_FILENAME))] = $nama;
        }

        return (string) preg_replace_callback(
            '/__EXITO_IMAGE:([a-zA-Z0-9_.-]+)/',
            function (array $match) use ($berkas): string {
                $slot = strtolower((string) preg_replace('/\.(jpe?g|png|webp|gif|svg)$/i', '', $match[1]));

                return $berkas[$slot] ?? $match[0];
            },
            $html
        );
    }

    private function configureBrowser(Browsershot $browser): Browsershot
    {
        $nodeBinary = $this->setting('services.browsershot.node_binary', 'node');
        if (is_string($nodeBinary) && trim($nodeBinary) !== '') {
            $browser->setNodeBinary($nodeBinary);
        }

        $nodeModulePath = $this->setting(
            'services.browsershot.node_module_path',
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'node_modules'
        );
        if (is_string($nodeModulePath) && trim($nodeModulePath) !== '') {
            $browser->setNodeModulePath($nodeModulePath);
        }

        $chromePath = $this->setting('services.browsershot.chrome_path');
        if (is_string($chromePath) && trim($chromePath) !== '') {
            $browser->setChromePath($chromePath);
        }

        return $browser;
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return function_exists('app') && app()->bound('config')
            ? config($key, $default)
            : $default;
    }
}
