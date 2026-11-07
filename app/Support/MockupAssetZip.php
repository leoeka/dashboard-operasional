<?php

namespace App\Support;

use App\Models\MockupAsset;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Membuka satu ZIP berisi bahan klien menjadi aset per berkas, sama seperti
 * yang selama ini dibuat lewat unggah satu per satu.
 *
 * Kategori tiap berkas dibaca dari nama folder paling luar di dalam ZIP
 * (logo/, foto/, dokumen/, ...). Folder yang tidak dikenal jatuh ke kategori
 * yang dipilih penunggah.
 *
 * Batas yang tersisa di sini bukan batas bahan klien, tapi penjaga mesin:
 * nama berkas yang menunjuk keluar folder, isi yang mengembang terlalu besar,
 * dan berkas yang bukan bahan.
 */
class MockupAssetZip
{
    public const MAKS_BERKAS = 300;

    public const MAKS_TOTAL_MB = 1024;

    private const GAMBAR = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];

    private const DOKUMEN = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf'];

    /** Nama folder di dalam ZIP yang dikenali, per kategori. */
    private const FOLDER = [
        'logo' => ['logo', 'logos', 'brand', 'branding'],
        'foto' => ['foto', 'fotos', 'gambar', 'image', 'images', 'photo', 'photos'],
        'dokumen' => ['dokumen', 'document', 'documents', 'docs'],
        'company_profile' => ['companyprofile', 'company_profile', 'company-profile', 'company', 'profil', 'profile'],
        'pendukung' => ['pendukung', 'materi', 'support', 'other', 'lainnya', 'assets', 'aset'],
    ];

    /** Sampah yang selalu ada di ZIP dari Windows/macOS. */
    private const SAMPAH = ['__MACOSX', '.DS_Store', 'Thumbs.db', 'Desktop.ini', '@EADir'];

    /**
     * @return array{masuk: int, per_kategori: array<string, int>, dilewati: list<array{nama: string, alasan: string}>}
     */
    public function masukkan(Project $project, UploadedFile $zip, string $kategoriCadangan): array
    {
        $arsip = new ZipArchive;

        if ($arsip->open($zip->getRealPath()) !== true) {
            throw new RuntimeException('ZIP tidak bisa dibuka. Minta klien mengirim ulang arsipnya.');
        }

        $singgah = File::makeDirectory(storage_path('app/tmp-aset-' . uniqid()), 0775, true, false);
        $masuk = 0;
        $perKategori = [];
        $dilewati = [];
        $total = 0;

        try {
            for ($i = 0; $i < $arsip->numFiles; $i++) {
                $info = $arsip->statIndex($i);
                $nama = str_replace('\\', '/', (string) ($info['name'] ?? ''));

                if ($nama === '' || str_ends_with($nama, '/')) {
                    continue;
                }

                $dasar = $this->lewat($nama);

                if ($dasar !== null) {
                    $dilewati[] = ['nama' => $nama, 'alasan' => $dasar];

                    continue;
                }

                if ($masuk >= self::MAKS_BERKAS) {
                    $dilewati[] = ['nama' => $nama, 'alasan' => 'jumlah berkas dalam ZIP sudah lewat ' . self::MAKS_BERKAS];

                    continue;
                }

                $total += (int) ($info['size'] ?? 0);

                if ($total > self::MAKS_TOTAL_MB * 1024 * 1024) {
                    $dilewati[] = ['nama' => $nama, 'alasan' => 'isi ZIP sudah melewati ' . self::MAKS_TOTAL_MB . ' MB saat dibuka'];

                    continue;
                }

                $kategori = $this->kategori($nama, $kategoriCadangan);

                if ($kategori === 'referensi') {
                    $dilewati[] = ['nama' => $nama, 'alasan' => 'acuan desain bukan bahan klien, isinya lewat kartu Referensi Desain'];

                    continue;
                }

                if (!in_array($this->ekstensi($nama), array_merge(self::GAMBAR, self::DOKUMEN), true)) {
                    $dilewati[] = ['nama' => $nama, 'alasan' => 'bukan gambar atau dokumen'];

                    continue;
                }

                $asal = $singgah . '/' . $i . '/' . $nama;

                if ($arsip->extractTo($singgah . '/' . $i, $nama) === false || !is_file($asal)) {
                    $dilewati[] = ['nama' => $nama, 'alasan' => 'berkas di dalam ZIP rusak'];

                    continue;
                }

                $dimana = $this->simpan($project, $nama, $asal);

                if ($dimana === null) {
                    $dilewati[] = ['nama' => $nama, 'alasan' => 'berkas tidak bisa disimpan di server'];

                    continue;
                }

                MockupAsset::create([
                    'project_id' => $project->id,
                    'category' => $kategori,
                    'original_name' => pathinfo($nama, PATHINFO_BASENAME),
                    'file_path' => $dimana,
                    'file_size' => filesize($asal),
                    'mime_type' => (string) (mime_content_type($asal) ?: 'application/octet-stream'),
                ]);

                $masuk++;
                $perKategori[$kategori] = ($perKategori[$kategori] ?? 0) + 1;
            }
        } finally {
            $arsip->close();
            File::deleteDirectory($singgah);
        }

        return ['masuk' => $masuk, 'per_kategori' => $perKategori, 'dilewati' => $dilewati];
    }

    /**
     * Nama berkas yang tidak aman untuk dibuka di server, dan sampah pembuat
     * arsip. Null berarti boleh dilanjutkan.
     */
    private function lewat(string $nama): ?string
    {
        $potong = explode('/', $nama);

        foreach ($potong as $satu) {
            if ($satu === '..' || preg_match('/^[A-Za-z]:[\\\\\/]/', $satu) === 1 || str_starts_with($satu, '/')) {
                return 'nama berkas menunjuk keluar folder';
            }

            if (in_array($satu, self::SAMPAH, true)) {
                return 'sampah dari komputer pembuat ZIP';
            }
        }

        return null;
    }

    private function kategori(string $nama, string $cadangan): string
    {
        $terdepan = strtolower(trim((string) explode('/', $nama)[0]));
        $terdepan = str_replace([' ', '-', '_'], '', $terdepan);

        if ($terdepan === 'referensi' || $terdepan === 'reference' || $terdepan === 'references') {
            return 'referensi';
        }

        foreach (self::FOLDER as $kategori => $namaFolder) {
            if (in_array($terdepan, $namaFolder, true)) {
                return $kategori;
            }
        }

        return $cadangan;
    }

    private function ekstensi(string $nama): string
    {
        return strtolower((string) pathinfo($nama, PATHINFO_EXTENSION));
    }

    /**
     * Nama di dalam ZIP dipakai apa adanya supaya mudah dikenali klien, tapi
     * dipayungi nomor unik lebih dulu agar tidak menimpa berkas proyek lain.
     */
    private function simpan(Project $project, string $nama, string $asal): ?string
    {
        $bersih = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($nama, PATHINFO_BASENAME));
        $bersih = trim($bersih, '-.') ?: 'berkas';

        $dimana = 'mockup-assets/' . $project->id . '/' . substr(uniqid(), -6) . '-' . $bersih;

        return Storage::disk('public')->put($dimana, (string) file_get_contents($asal)) ? $dimana : null;
    }

    /**
     * Kalimat pendek untuk layar: berapa yang masuk dan kenapa sisanya tidak.
     *
     * @param  array{masuk: int, per_kategori: array<string, int>, dilewati: list<array{nama: string, alasan: string}>}  $hasil
     */
    public function ringkas(array $hasil): string
    {
        if ($hasil['masuk'] === 0 && $hasil['dilewati'] === []) {
            return 'ZIP tidak berisi bahan yang bisa dipakai.';
        }

        $per = [];

        foreach ($hasil['per_kategori'] as $kategori => $jumlah) {
            $per[] = MockupAsset::categoryLabels()[$kategori] . ' ' . $jumlah;
        }

        $pesan = $hasil['masuk'] . ' berkas masuk dari ZIP' . ($per === [] ? '' : ' (' . implode(', ', $per) . ')');

        if ($hasil['dilewati'] !== []) {
            $contoh = array_slice($hasil['dilewati'], 0, 3);
            $alasan = [];

            foreach ($contoh as $satu) {
                $alasan[] = $satu['nama'] . ' ' . $satu['alasan'];
            }

            $pesan .= '. ' . count($hasil['dilewati']) . ' dilewati: ' . implode('; ', $alasan);

            if (count($hasil['dilewati']) > 3) {
                $pesan .= ', dan ' . (count($hasil['dilewati']) - 3) . ' lainnya';
            }
        }

        return $pesan . '.';
    }
}
