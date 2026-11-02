<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use App\Support\PageSnapshot;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Tangan GPT: empat alat yang dieksekusi di folder kerja satu kandidat.
 * cari_gambar mencari foto lewat Google Custom Search; unduh_gambar dan
 * tulis_berkas mengisi folder kerja (tulis_berkas tidak bisa keluar
 * foldernya); lihat_potret memotret index.html dan mengirim gambarnya
 * balik ke otak untuk kritik diri.
 *
 * Kegagalan alat dibungkus jadi teks "GAGAL: ..." supaya otak bisa
 * memperbaiki langkahnya sendiri; hanya kesiapan sistem yang tidak (kunci
 * API hilang, server menjawab error HTTP) yang melewati ProviderException.
 */
class MockupToolBox
{
    private string $workPath;

    /**
     * Banyaknya tulis_berkas yang benar-benar terjadi. Pemanggil memakai
     * selisih angka ini untuk tahu apakah satu ronde masih mengubah berkas.
     */
    private int $jumlahTulis = 0;

    public function __construct(string $workPath, private ?PageSnapshot $snapshot = null)
    {
        if (!is_dir($workPath) && !mkdir($workPath, 0775, true)) {
            throw new RuntimeException('Folder kerja kandidat tidak bisa dibuat: ' . $workPath);
        }

        $this->workPath = realpath($workPath);
    }

    public function jumlahTulis(): int
    {
        return $this->jumlahTulis;
    }

    public function definitions(): array
    {
        return [
            [
                'type' => 'function',
                'name' => 'cari_gambar',
                'description' => 'Cari foto di web lewat Google Custom Search. Kembalian: daftar JSON berisi url, thumbnail, sumber, dan judul tiap foto.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'kata_kunci' => ['type' => 'string', 'description' => 'kata kunci pencarian, boleh bahasa Inggris'],
                    ],
                    'required' => ['kata_kunci'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => 'unduh_gambar',
                'description' => 'Unduh gambar dari tautan langsung ke folder kerja kandidat ini, tersimpan dengan nama slot. Kembalian: nama slot berkas.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string', 'description' => 'tautan langsung ke berkas gambar'],
                        'slot' => ['type' => 'string', 'description' => 'nama slot huruf kecil, mis. hero atau galeri-1'],
                    ],
                    'required' => ['url', 'slot'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => 'tulis_berkas',
                'description' => 'Tulis berkas ke dalam folder kerja kandidat ini. Nama boleh memakai subfolder, mis. pages/tentang.html. Tidak bisa keluar folder kerja.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'nama' => ['type' => 'string'],
                        'isi' => ['type' => 'string'],
                    ],
                    'required' => ['nama', 'isi'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => 'lihat_potret',
                'description' => 'Lihat hasil render halaman index.html milik kandidat ini sebagai gambar, untuk kritik diri terhadap aturan desain.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Jalankan satu alat. Selalu mengembalikan potongan keluaran siap kirim
     * ke OpenAI: teks (input_text) atau gambar (input_image) untuk potret.
     *
     * @return list<array<string, mixed>>
     */
    public function execute(string $name, string $argumentsJson): array
    {
        $args = json_decode($argumentsJson, true);

        if (!is_array($args)) {
            return $this->textResult('GAGAL: argumen bukan JSON yang sah.');
        }

        return match ($name) {
            'cari_gambar' => $this->cariGambar((string) ($args['kata_kunci'] ?? '')),
            'unduh_gambar' => $this->unduhGambar((string) ($args['url'] ?? ''), (string) ($args['slot'] ?? '')),
            'tulis_berkas' => $this->tulisBerkas((string) ($args['nama'] ?? ''), (string) ($args['isi'] ?? '')),
            'lihat_potret' => $this->lihatPotret(),
            default => $this->textResult('GAGAL: alat tidak dikenal: ' . $name),
        };
    }

    private function cariGambar(string $kataKunci): array
    {
        if (trim($kataKunci) === '') {
            return $this->textResult('GAGAL: kata_kunci kosong.');
        }

        $key = config('services.google_custom_search.api_key');
        $engine = config('services.google_custom_search.engine_id');

        if (empty($key) || empty($engine)) {
            throw ProviderException::missingKey('google_custom_search');
        }

        try {
            $response = Http::timeout(30)->get('https://www.googleapis.com/customsearch/v1', [
                'key' => $key,
                'cx' => $engine,
                'q' => $kataKunci,
                'searchType' => 'image',
                'num' => 8,
                'safe' => 'active',
            ]);
        } catch (Throwable $e) {
            throw ProviderException::fromThrowable('google_custom_search', $e);
        }

        if ($response->failed()) {
            throw ProviderException::fromResponse('google_custom_search', $response);
        }

        $hasil = [];

        foreach ($response->json('items', []) as $item) {
            $hasil[] = [
                'url' => $item['link'] ?? null,
                'thumbnail' => $item['image']['thumbnailLink'] ?? null,
                'sumber' => $item['displayLink'] ?? null,
                'judul' => $item['title'] ?? null,
            ];
        }

        if ($hasil === []) {
            return $this->textResult('TIDAK ADA HASIL untuk: ' . $kataKunci);
        }

        return $this->textResult((string) json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function unduhGambar(string $url, string $slot): array
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $slot)) {
            return $this->textResult('GAGAL: slot harus 1-40 karakter huruf kecil, angka, atau dash, mulai dari huruf/angka.');
        }

        if (!preg_match('#^https?://#i', $url)) {
            return $this->textResult('GAGAL: url harus diawali http:// atau https://');
        }

        try {
            $response = Http::timeout(60)->get($url);
        } catch (Throwable $e) {
            return $this->textResult('GAGAL: unduhan error: ' . mb_substr($e->getMessage(), 0, 120));
        }

        if ($response->failed()) {
            return $this->textResult('GAGAL: server gambar menjawab HTTP ' . $response->status());
        }

        $extension = $this->imageExtension($response);

        if ($extension === null) {
            return $this->textResult('GAGAL: jawaban bukan gambar.');
        }

        try {
            file_put_contents($this->workPath . '/' . $slot . '.' . $extension, $response->body());
        } catch (Throwable) {
            return $this->textResult('GAGAL: gambar tidak bisa disimpan.');
        }

        return $this->textResult('slot: ' . $slot . '.' . $extension);
    }

    private function imageExtension(Response $response): ?string
    {
        $mime = strtolower((string) $response->header('Content-Type', ''));

        return match (true) {
            str_contains($mime, 'jpeg') => 'jpg',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif') => 'gif',
            str_contains($mime, 'image/') => 'img',
            default => null,
        };
    }

    private function tulisBerkas(string $nama, string $isi): array
    {
        $nama = str_replace('\\', '/', $nama);

        if ($nama === '' || str_starts_with($nama, '/') || str_contains($nama, '..')) {
            return $this->textResult('GAGAL: nama berkas tidak sah.');
        }

        $target = $this->workPath . '/' . $nama;
        $dir = dirname($target);

        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return $this->textResult('GAGAL: folder tujuan tidak bisa dibuat.');
        }

        // Pertahanan kedua setelah penolakan '..' di atas: pastikan hasil
        // normalisasi (mis. tautan simbolik) tetap di dalam folder kerja.
        $dirReal = realpath($dir);

        if ($dirReal === false
            || ($dirReal !== $this->workPath && !str_starts_with($dirReal, $this->workPath . DIRECTORY_SEPARATOR))) {
            return $this->textResult('GAGAL: nama berkas keluar dari folder kerja.');
        }

        try {
            file_put_contents($target, $isi);
        } catch (Throwable) {
            return $this->textResult('GAGAL: berkas tidak bisa ditulis.');
        }

        $this->jumlahTulis++;

        return $this->textResult('OK: ' . $nama);
    }

    private function lihatPotret(): array
    {
        $html = $this->workPath . '/index.html';

        if (!is_file($html)) {
            return $this->textResult('GAGAL: index.html belum ada di folder kerja.');
        }

        $snapshot = $this->snapshot ?? new PageSnapshot();
        $png = $snapshot->captureFile($html, $this->workPath . '/preview.png');

        return [[
            'type' => 'input_image',
            'detail' => 'high',
            'image_url' => 'data:image/png;base64,' . base64_encode((string) file_get_contents($png)),
        ]];
    }

    private function textResult(string $text): array
    {
        return [['type' => 'input_text', 'text' => $text]];
    }
}
