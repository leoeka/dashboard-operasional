<?php

namespace App\Services;

use App\Models\Project;

/**
 * Perakit ringkas data proyek (form Request) untuk brief kandidat. Baris yang
 * datanya kosong tidak ditulis, supaya model tidak menebak dari ruang kosong.
 *
 * Kolom "Fitur yang diminta" (teks bebas) diterjemahkan menjadi struktur
 * website yang pasti: halaman apa yang wajib dibuat (pages/<slug>.html) dan
 * bagian tambahan apa yang masuk ke beranda. Struktur ini dibaca brief Codex
 * dan bisa dipakai runner untuk memeriksa bahwa semua halaman benar-benar ada.
 */
class MockupBrief
{
    /** Jumlah maksimal halaman tambahan (di luar beranda). */
    public const MAKS_HALAMAN = 5;

    /** Permintaan yang hanya menyebut beranda. */
    private const POLA_BERANDA = '/^(?:halaman\s+utama|beranda|home(?:page)?|landing(?:\s+page)?)/i';

    /** Halaman yang dikenali dari kata kunci. Urutan = prioritas pencocokan. */
    private const KATALOG = [
        'tentang' => [
            'judul' => 'Tentang Kami',
            'pola' => '/tentang|about|profil|siapa kami|\bvisi\b|\bmisi\b|sejarah/i',
            'isi' => 'cerita dan nilai bisnis dari data proyek; visi, misi, dan tim hanya bila ada di data proyek; ajakan menghubungi di akhir',
        ],
        'layanan' => [
            'judul' => 'Layanan',
            'pola' => '/layanan|service|jasa/i',
            'isi' => 'tiap layanan yang disebut di data proyek dengan judul, deskripsi singkat, dan manfaat; alur kerja singkat bila relevan; CTA',
        ],
        'produk' => [
            'judul' => 'Produk',
            'pola' => '/produk|product|katalog/i',
            'isi' => 'kategori atau daftar produk dari data proyek tanpa harga karangan; CTA',
        ],
        'portofolio' => [
            'judul' => 'Portofolio',
            'pola' => '/portofolio|portfolio|galeri|gallery|\bkarya\b|proyek kami/i',
            'isi' => 'grid karya atau galeri dengan foto dan caption yang cocok dengan isinya; satu bagian cerita karya unggulan',
        ],
        'harga' => [
            'judul' => 'Harga',
            'pola' => '/harga|pricing|tarif|biaya/i',
            'isi' => 'tampilkan angka hanya bila ada di data proyek; bila tidak ada, jelaskan cara meminta penawaran tanpa angka',
        ],
        'tim' => [
            'judul' => 'Tim',
            'pola' => '/\btim\b|\bteam\b/i',
            'isi' => 'anggota tim hanya bila datanya ada; bila tidak ada, ganti dengan bagian nilai dan budaya kerja',
        ],
        'testimoni' => [
            'judul' => 'Testimoni',
            'pola' => '/testimoni|testimonial|ulasan|review/i',
            'isi' => 'kutipan klien hanya bila ada di data proyek; bila tidak ada, ganti dengan bagian cara kami bekerja',
        ],
        'blog' => [
            'judul' => 'Blog',
            'pola' => '/blog|artikel|berita|news/i',
            'isi' => 'daftar artikel dengan judul dan ringkasan singkat yang relevan dengan bisnis, tanpa tanggal, nama penulis, atau angka karangan',
        ],
        'faq' => [
            'judul' => 'FAQ',
            'pola' => '/faq|pertanyaan/i',
            'isi' => '6-8 pertanyaan umum yang relevan dengan bisnis, pertanyaan sebagai judul kecil dan jawaban sebagai paragraf singkat tanpa klaim angka',
        ],
        'karir' => [
            'judul' => 'Karir',
            'pola' => '/karir|karier|career|lowongan/i',
            'isi' => 'budaya kerja dan cara melamar tanpa lowongan karangan',
        ],
        'komunitas' => [
            'judul' => 'Komunitas',
            'pola' => '/komunitas|community/i',
            'isi' => 'kegiatan dan nilai komunitas dari data proyek; ajakan bergabung',
        ],
        'kontak' => [
            'judul' => 'Kontak',
            'pola' => '/kontak|contact|hubungi/i',
            'isi' => 'cara menghubungi hanya dari data proyek (WhatsApp, email, alamat bila ada); bila tidak ada, ajakan menghubungi tanpa data karangan; tanpa formulir, pakai tautan ajakan',
        ],
    ];

    /** Halaman bawaan bila proyek tidak menyebut satu pun halaman yang dikenali. */
    private const HALAMAN_BAWAAN = ['tentang', 'layanan', 'kontak'];

    /**
     * Baris data proyek yang dibaca brief Codex.
     *
     * @return list<string>
     */
    public static function dataProyek(Project $project): array
    {
        $baris = [
            'Proyek: ' . $project->name . ' (jenis: ' . ($project->type ?: 'website') . ')',
            'Klien: ' . $project->client_name,
        ];

        foreach (self::barisOpsional($project) as $label => $nilai) {
            if (is_string($nilai) && trim($nilai) !== '') {
                $baris[] = $label . ': ' . $nilai;
            }
        }

        return $baris;
    }

    /**
     * Halaman tambahan yang wajib ada: slug => judul.
     *
     * @return array<string, string>
     */
    public static function halamanWajib(Project $project): array
    {
        $hasil = [];

        foreach (self::rencanaSitus($project)['halaman'] as $slug => $halaman) {
            $hasil[$slug] = $halaman['judul'];
        }

        return $hasil;
    }

    /**
     * Struktur website dalam bentuk data.
     *
     * @return array{
     *     halaman: array<string, array{judul: string, permintaan: string|null}>,
     *     bagian: list<string>,
     *     beranda: list<string>,
     *     bawaan: bool
     * }
     */
    public static function rencanaSitus(Project $project): array
    {
        $halaman = [];
        $bagian = [];
        $beranda = [];

        foreach (self::daftarFitur($project) as $item) {
            if (preg_match(self::POLA_BERANDA, $item) === 1) {
                $beranda[] = $item;
                continue;
            }

            $slug = self::slugKatalog($item);

            if ($slug === null) {
                $bagian[] = $item;
                continue;
            }

            if (isset($halaman[$slug])) {
                continue;
            }

            if (count($halaman) >= self::MAKS_HALAMAN) {
                $bagian[] = $item;
                continue;
            }

            $halaman[$slug] = ['judul' => self::KATALOG[$slug]['judul'], 'permintaan' => $item];
        }

        $bawaan = false;

        if ($halaman === []) {
            $bawaan = true;

            foreach (self::HALAMAN_BAWAAN as $slug) {
                $halaman[$slug] = ['judul' => self::KATALOG[$slug]['judul'], 'permintaan' => null];
            }
        }

        return ['halaman' => $halaman, 'bagian' => $bagian, 'beranda' => $beranda, 'bawaan' => $bawaan];
    }

    /**
     * Struktur website sebagai baris teks untuk brief.
     *
     * @return list<string>
     */
    public static function strukturSitus(Project $project): array
    {
        $rencana = self::rencanaSitus($project);
        $baris = [];

        $baris[] = $rencana['bawaan']
            ? 'Proyek tidak merinci halaman yang dikenali, jadi pakai struktur bawaan berikut.'
            : 'Struktur berikut diturunkan dari "Fitur yang diminta" klien. Semua wajib dibuat.';
        $baris[] = '';
        $baris[] = '- Beranda: `index.html`';

        foreach ($rencana['beranda'] as $catatan) {
            $baris[] = '  Permintaan klien untuk beranda: ' . $catatan;
        }

        $nomor = 1;

        foreach ($rencana['halaman'] as $slug => $halaman) {
            $baris[] = '- Halaman ' . $nomor . ': ' . $halaman['judul'] . ' di `pages/' . $slug . '.html`';

            if ($halaman['permintaan'] !== null) {
                $baris[] = '  Permintaan klien: ' . $halaman['permintaan'];
            }

            $baris[] = '  Isi: ' . self::KATALOG[$slug]['isi'] . '.';
            $nomor++;
        }

        if ($rencana['bagian'] !== []) {
            $baris[] = '';
            $baris[] = 'Fitur lain yang dijadikan bagian (section) di beranda, bukan halaman sendiri:';

            foreach ($rencana['bagian'] as $bagian) {
                $baris[] = '- ' . $bagian;
            }
        }

        return $baris;
    }

    /**
     * @return list<string>
     */
    private static function daftarFitur(Project $project): array
    {
        $teks = $project->website_features;

        if (!is_string($teks) || trim($teks) === '') {
            return [];
        }

        $hasil = [];

        foreach (preg_split('/[\r\n;,|]+/u', $teks) ?: [] as $potong) {
            $item = trim((string) preg_replace('/^[\s\-*\x{2022}\x{00B7}\d.)(]+/u', '', $potong));

            if ($item !== '') {
                $hasil[] = $item;
            }
        }

        return $hasil;
    }

    private static function slugKatalog(string $item): ?string
    {
        foreach (self::KATALOG as $slug => $halaman) {
            if (preg_match($halaman['pola'], $item) === 1) {
                return $slug;
            }
        }

        return null;
    }

    private static function barisOpsional(Project $project): array
    {
        return [
            'Nama website' => $project->website_name,
            'Target pasar' => $project->target_market,
            'Fitur yang diminta' => $project->website_features,
            'Cerita klien' => $project->description,
        ];
    }
}