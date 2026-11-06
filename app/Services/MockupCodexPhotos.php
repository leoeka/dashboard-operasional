<?php

namespace App\Services;

/**
 * Penyalin foto jalur Codex. Model hanya mencatat jalur berkas; kelas ini
 * yang menyalin, memeriksa keutuhan PNG, dan mengambil dari cache Codex
 * kalau catatan model kosong.
 *
 * Dua lapis sengaja: trial 2026-10-31 membuktikan model bisa membuat gambar
 * bagus tetapi gagal menyalinnya lewat perintah shell (batas panjang baris
 * perintah Windows). Isi gambar tidak boleh pernah lewat model.
 */
class MockupCodexPhotos
{
    public const UKURAN_MINIMAL = 51200;

    /**
     * @param  list<string>  $slots
     * @return array{found: array<string, string>, jumlah: int, dibutuhkan: int, pesan: string}
     */
    public function recover(string $candidateDir, string $manifestPath, ?string $cacheDir, array $slots): array
    {
        $found = [];
        $dipakai = [];
        $catatan = $this->bacaManifest($manifestPath);
        // Gambar rancangan (mockup) bukan foto website dan tidak boleh ikut terpasang.
        $kecuali = $this->hashRancangan(dirname($manifestPath) . '/desain-referensi.txt', $candidateDir);

        foreach ($slots as $slot) {
            $source = $catatan[$slot] ?? null;

            if ($source === null || $source === '-') {
                continue;
            }

            $path = $this->absolut($source, $candidateDir);

            if ($path === null || !$this->sah($path)) {
                continue;
            }

            $hash = (string) md5_file($path);

            // Berkas yang sama untuk dua slot, atau berkas rancangan: anggap hilang
            // agar dicoba dari cache, bukan dipasang dobel.
            if (isset($kecuali[$hash]) || isset($dipakai[$hash])) {
                continue;
            }

            $target = $candidateDir . '/' . $slot . '.png';

            if ($this->salin($path, $target)) {
                $found[$slot] = $target;
                $dipakai[$hash] = $slot;
            }
        }

        $kurang = array_values(array_diff($slots, array_keys($found)));

        if ($kurang !== [] && $cacheDir !== null && is_dir($cacheDir)) {
            $cache = [];

            foreach ($this->gambarCache($cacheDir) as $path) {
                $hash = (string) md5_file($path);

                if (isset($kecuali[$hash]) || isset($dipakai[$hash]) || isset($cache[$hash])) {
                    continue;
                }

                $cache[$hash] = $path;
            }

            $cache = $cache === [] ? [] : array_values($cache);

            foreach ($kurang as $i => $slot) {
                if (!isset($cache[$i])) {
                    break;
                }

                $target = $candidateDir . '/' . $slot . '.png';

                if ($this->salin($cache[$i], $target)) {
                    $found[$slot] = $target;
                }
            }
        }

        $jumlah = count($found);
        $dibutuhkan = count($slots);
        $pesan = $jumlah === $dibutuhkan
            ? ''
            : 'foto tidak lengkap (' . $jumlah . ' dari ' . $dibutuhkan . '): '
                . implode(', ', array_values(array_diff($slots, array_keys($found))));

        return ['found' => $found, 'jumlah' => $jumlah, 'dibutuhkan' => $dibutuhkan, 'pesan' => $pesan];
    }

    /**
     * Hash gambar rancangan yang dicatat Codex di desain-referensi.txt
     * (baris `design: <path>`), supaya tidak pernah dipakai sebagai foto.
     *
     * @return array<string, true>
     */
    private function hashRancangan(string $berkas, string $candidateDir): array
    {
        $isi = is_file($berkas) ? (string) file_get_contents($berkas) : '';

        if (!preg_match('/^\s*design\s*[:=]\s*(.+?)\s*$/im', $isi, $cocok)) {
            return [];
        }

        $path = $this->absolut($cocok[1], $candidateDir);

        if ($path === null || !is_file($path)) {
            return [];
        }

        return [(string) md5_file($path) => true];
    }

    /**
     * Hapus gambar cache milik satu sesi Codex setelah foto tersalin. Isi
     * folder hanya berkas gambar; folder induk tidak disentuh.
     */
    public function bersihkanCache(?string $cacheDir): void
    {
        if ($cacheDir === null || !is_dir($cacheDir)) {
            return;
        }

        foreach (scandir($cacheDir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $cacheDir . '/' . $name;

            if (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($cacheDir);
    }

    public function sah(string $path): bool
    {
        if (!is_file($path) || filesize($path) < self::UKURAN_MINIMAL) {
            return false;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $awal = fread($handle, 8);
        fseek($handle, max(0, filesize($path) - 512));
        $akhir = (string) fread($handle, 512);
        fclose($handle);

        return $awal === "\x89PNG\r\n\x1a\n" && str_contains($akhir, 'IEND');
    }

    /**
     * @return array<string, string>
     */
    private function bacaManifest(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $hasil = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $baris) {
            if (!preg_match('/^\s*([a-z0-9][a-z0-9-]*)\s*[:=]\s*(.+?)\s*$/i', $baris, $cocok)) {
                continue;
            }

            $nilai = trim($cocok[2], " \t\"'");

            if ($nilai !== '') {
                $hasil[strtolower($cocok[1])] = $nilai;
            }
        }

        return $hasil;
    }

    private function absolut(string $path, string $candidateDir): ?string
    {
        $path = str_replace('\\', '/', trim($path, " \t\"'"));

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path)) {
            return $path;
        }

        return $candidateDir . '/' . $path;
    }

    /**
     * Gambar utuh di folder cache, terbaru lebih dulu. Trial menunjukkan
     * percobaan terakhir yang biasanya jadi gambar final untuk sebuah slot.
     *
     * @return list<string>
     */
    private function gambarCache(string $dir): array
    {
        $gambar = [];

        foreach (scandir($dir) ?: [] as $name) {
            if (!str_ends_with(strtolower($name), '.png')) {
                continue;
            }

            $path = $dir . '/' . $name;

            if ($this->sah($path)) {
                $gambar[$path] = (int) filemtime($path);
            }
        }

        arsort($gambar);

        return array_keys($gambar);
    }

    private function salin(string $source, string $target): bool
    {
        $sourceReal = realpath($source);
        $targetReal = realpath($target);

        if ($sourceReal !== false && $targetReal !== false && $sourceReal === $targetReal) {
            return $this->sah($target);
        }

        if (!@copy($source, $target)) {
            return false;
        }

        return $this->sah($target);
    }
}