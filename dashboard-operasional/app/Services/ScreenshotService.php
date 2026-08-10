<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class ScreenshotService
{
    /**
     * Ambil screenshot dari sebuah URL (pakai Chromium headless via Browsershot)
     * lalu simpan ke storage disk 'public'.
     *
     * @param string $url          URL situs yang mau di-screenshot (mis. site_url dari ZipWP)
     * @param string $relativePath Path relatif tujuan penyimpanan, mis. "mockups/12.png"
     * @return string|null         Path relatif (sama dengan $relativePath) kalau sukses, null kalau gagal
     */
    public function capture(string $url, string $relativePath): ?string
    {
        try {
            // Pastikan folder tujuan ada
            $fullPath = Storage::disk('public')->path($relativePath);
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            Browsershot::url($url)
                ->windowSize(1440, 900)
                ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox']) // sering wajib di Windows/Linux tertentu
                ->waitUntilNetworkIdle()   // tunggu sampai situs selesai load (biar gak nangkep halaman blank/loading)
                ->timeout(60)
                ->save($fullPath);

            return $relativePath;
        } catch (\Throwable $e) {
            Log::warning('ScreenshotService (Browsershot): gagal ambil screenshot - ' . $e->getMessage(), [
                'url' => $url,
            ]);
            return null;
        }
    }
}