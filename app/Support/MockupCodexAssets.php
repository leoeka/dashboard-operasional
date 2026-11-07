<?php

namespace App\Support;

use App\Models\MockupAsset;
use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Menyalin bahan milik klien sendiri (logo, foto) ke folder kerja Codex dan
 * menulis daftar beserta cara pakainya di aset.txt.
 *
 * Codex dijalankan dengan sandbox workspace-write di folder kerjanya, jadi
 * berkas di storage tidak bisa dibacanya sendiri: harus dihantar ke sana.
 * Proyek tanpa aset tidak menulis apa pun, jadi jalurnya tetap persis seperti
 * sebelum aset dibuka.
 *
 * Tidak ada batas jumlah atau ukuran: bahan klien dipakai apa adanya. Yang
 * disaring hanya jenis berkas — dokumen tidak ikut karena Codex tidak bisa
 * memakainya sebagai gambar.
 */
class MockupCodexAssets
{
    private const EKSTENSI_GAMBAR = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

    /** Kategori yang boleh dipakai sebagai gambar; dokumen ditinggalkan. */
    private const KATEGORI_GAMBAR = ['logo', 'foto'];

    /**
     * @return list<array{jalur: string, kategori: string, asal: string}>
     */
    public function letakan(Project $project, string $kerja): array
    {
        $berkas = [];
        $nomor = [];

        foreach ($this->asetGambar($project) as $asset) {
            $asal = Storage::disk('public')->path((string) $asset->file_path);

            if (!is_file($asal)) {
                continue;
            }

            $ekstensi = strtolower((string) pathinfo($asal, PATHINFO_EXTENSION));
            $nomor[$asset->category] = ($nomor[$asset->category] ?? 0) + 1;
            $jalur = 'aset/' . $asset->category . '-' . $nomor[$asset->category] . '.' . $ekstensi;

            File::ensureDirectoryExists($kerja . '/aset');

            if (!File::copy($asal, $kerja . '/' . $jalur)) {
                continue;
            }

            $berkas[] = [
                'jalur' => $jalur,
                'kategori' => $asset->category,
                'asal' => (string) $asset->original_name,
            ];
        }

        if ($berkas !== [] && file_put_contents($kerja . '/aset.txt', $this->daftar($berkas)) === false) {
            throw new RuntimeException('Daftar aset klien tidak bisa ditulis ke folder kerja.');
        }

        return $berkas;
    }

    /**
     * Aset bergambar milik proyek, tertua lebih dulu supaya nama berkas tidak
     * berubah-ubah tiap kali kandidat dibuat ulang.
     *
     * @return \Illuminate\Support\Collection<int, MockupAsset>
     */
    private function asetGambar(Project $project)
    {
        return MockupAsset::query()
            ->where('project_id', $project->id)
            ->whereIn('category', self::KATEGORI_GAMBAR)
            ->orderBy('id')
            ->get()
            ->filter(fn(MockupAsset $asset) => in_array(
                strtolower((string) pathinfo((string) $asset->file_path, PATHINFO_EXTENSION)),
                self::EKSTENSI_GAMBAR,
                true
            ));
    }

    /**
     * @param  list<array{jalur: string, kategori: string, asal: string}>  $berkas
     */
    private function daftar(array $berkas): string
    {
        $b = [];

        $b[] = '# ASET MILIK KLIEN';
        $b[] = '';
        $b[] = 'Berkas di bawah ini bahan asli dari klien, sudah tersedia di folder kerja ini:';
        $b[] = '';

        foreach ($berkas as $baris) {
            $label = MockupAsset::categoryLabels()[$baris['kategori']] ?? ucfirst($baris['kategori']);
            $b[] = '- `' . $baris['jalur'] . '` — ' . $label . ' (nama asli: ' . $baris['asal'] . ')';
        }

        $b[] = '';
        $b[] = 'Cara pakai:';
        $b[] = '- Logo dipakai apa adanya sebagai gambar di header/footer. Jangan digambar ulang, jangan dipakai sebagai foto konten.';
        $b[] = '- Foto klien dipakai apa adanya pada slot yang subjeknya cocok. Setiap foto klien yang dipasang mengurangi satu foto yang harus dibuat.';
        $b[] = '- Foto hasil AI tetap mengikuti aturan brief: tanpa teks, tanpa logo, tanpa watermark.';

        return implode("\n", $b) . "\n";
    }
}
