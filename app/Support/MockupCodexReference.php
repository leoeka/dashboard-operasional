<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Menghantar acuan desain klien ke folder kerja Codex sebagai satu gambar.
 *
 * Codex berjalan tanpa akses internet, jadi tautan yang dikirim klien harus
 * sudah dipretretkan lebih dulu (oleh kartu referensi), dan ZIP template harus
 * dibuka isinya. Bagian 03 brief cuma mengenal satu jalur berkas, jadi semua
 * bentuk acuan dilebur ke jalur itu.
 */
class MockupCodexReference
{
    /**
     * Disimpan di subfolder, bukan di akar folder kerja: pembangun tema
     * mengumpulkan semua gambar di akar kandidat sebagai foto website, dan
     * acuan tidak boleh ikut terkirim ke ZIP tema.
     */
    private const NAMA_ACUAN = 'acuan/acuan';

    private const GAMBAR = ['png', 'jpg', 'jpeg', 'webp'];

    /**
     * Jalur berkas acuan di dalam folder kerja (mis. 'acuan/acuan.png'), atau
     * null kalau proyek ini belum punya acuan yang bisa dilihat.
     */
    public function letakan(Project $project, string $kerja): ?string
    {
        $path = (string) $project->design_reference_path;

        if ($path === '') {
            return null;
        }

        $asal = Storage::disk('public')->path($path);

        if (!is_file($asal)) {
            return null;
        }

        $ekstensi = strtolower((string) pathinfo($asal, PATHINFO_EXTENSION));
        $singgah = null;

        if ($ekstensi === 'zip') {
            $singgah = File::makeDirectory(sys_get_temp_dir() . '/acuan-' . uniqid(), 0775, true, false);
            $terbaik = $this->gambarTerbesar($asal, $singgah);

            if ($terbaik === null) {
                File::deleteDirectory($singgah);

                return null;
            }

            $asal = $terbaik;
            $ekstensi = strtolower((string) pathinfo($asal, PATHINFO_EXTENSION));
        }

        try {
            if (!in_array($ekstensi, self::GAMBAR, true)) {
                return null;
            }

            $relatif = self::NAMA_ACUAN . '.' . $ekstensi;
            File::ensureDirectoryExists(dirname($kerja . '/' . $relatif));

            return copy($asal, $kerja . '/' . $relatif) ? $relatif : null;
        } finally {
            if ($singgah !== null) {
                File::deleteDirectory($singgah);
            }
        }
    }

    /**
     * ZIP template berisi banyak berkas; yang paling mungkin memperlihatkan
     * tata letak adalah gambar paling besar di dalamnya.
     */
    private function gambarTerbesar(string $zipPath, string $tujuan): ?string
    {
        $arsip = new ZipArchive;

        if ($arsip->open($zipPath) !== true) {
            return null;
        }

        $terbaik = null;
        $ukuran = 0;

        try {
            for ($i = 0; $i < $arsip->numFiles; $i++) {
                $info = $arsip->statIndex($i);
                $nama = str_replace('\\', '/', (string) ($info['name'] ?? ''));

                if ($nama === '' || str_contains($nama, '..') || !in_array(strtolower((string) pathinfo($nama, PATHINFO_EXTENSION)), self::GAMBAR, true)) {
                    continue;
                }

                if ((int) $info['size'] <= $ukuran) {
                    continue;
                }

                if ($arsip->extractTo($tujuan, $nama) === false) {
                    continue;
                }

                $terbaik = $tujuan . '/' . $nama;
                $ukuran = (int) $info['size'];
            }
        } finally {
            $arsip->close();
        }

        return $terbaik !== null && is_file($terbaik) ? $terbaik : null;
    }
}
