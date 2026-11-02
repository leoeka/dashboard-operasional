<?php

namespace App\Support;

use RuntimeException;

/**
 * Perakit berkas aturan yang dikirim ke GPT di setiap ronde: id berkas AI
 * (resources/ai/aturan-mockup.md) + larangan/terukur/positif (bagian 2-4
 * .docs/aturan-desain-sisi-klien.md). Satu sumber untuk dua pembaca - prompt
 * desainer dan meja periksa - jadi aturan tidak pernah punya dua versi.
 */
class MockupRules
{
    public static function load(): string
    {
        $dasar = file_get_contents(base_path('.docs/aturan-desain-sisi-klien.md'));
        $aturan = file_get_contents(resource_path('ai/aturan-mockup.md'));

        if ($dasar === false || $aturan === false) {
            throw new RuntimeException('Berkas aturan desain tidak ditemukan.');
        }

        $km = self::potongBagian($dasar, '## 2.', '## 5.');

        if ($km === null) {
            throw new RuntimeException('Bagian 2-4 aturan-desain-sisi-klien.md tidak ketemu; struktur berkas berubah?');
        }

        return $aturan . "\n\n---\n\n# Lampiran: Larangan (K), Terukur mesin (M), Positif (P)\n\n" . $km;
    }

    private static function potongBagian(string $isi, string $mulaiDari, string $sampai): ?string
    {
        $mulai = strpos($isi, $mulaiDari);
        $akhir = strpos($isi, $sampai);

        if ($mulai === false || $akhir === false || $akhir <= $mulai) {
            return null;
        }

        return rtrim(substr($isi, $mulai, $akhir - $mulai));
    }
}
