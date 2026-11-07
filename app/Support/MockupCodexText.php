<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Satu pintu baca untuk semua berkas teks hasil Codex.
 *
 * Codex di Windows sering menulis lewat shell, jadi isinya bisa ber-BOM,
 * UTF-16, atau CRLF. Kalau byte itu sampai ke pembaca, baris pertama
 * foto-slot.txt tidak dikenali dan foto hero hilang tanpa pesan apa pun.
 */
final class MockupCodexText
{
    private const BOM_UTF8 = "\xEF\xBB\xBF";
    private const BOM_UTF16_LE = "\xFF\xFE";
    private const BOM_UTF16_BE = "\xFE\xFF";

    /**
     * Berkas yang tidak ada mengembalikan string kosong: masalahnya sudah
     * ditangani pemanggil. Berkas yang ada tetapi tidak bisa diurai adalah
     * kegagalan, bukan keadaan kosong.
     */
    public static function baca(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $isi = @file_get_contents($path);

        if (!is_string($isi)) {
            throw new RuntimeException('Berkas hasil Codex tidak bisa dibaca: ' . $path);
        }

        return self::normalisasi($isi, $path);
    }

    private static function normalisasi(string $isi, string $path): string
    {
        $sebelum = $isi;

        if (str_starts_with($isi, self::BOM_UTF16_LE)) {
            $isi = (string) mb_convert_encoding(substr($isi, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($isi, self::BOM_UTF16_BE)) {
            $isi = (string) mb_convert_encoding(substr($isi, 2), 'UTF-8', 'UTF-16BE');
        }

        $isi = str_replace(self::BOM_UTF8, '', $isi);
        $isi = str_replace(["\r\n", "\r"], "\n", $isi);

        if ($isi !== $sebelum) {
            Log::info('Berkas Codex dinormalisasi sebelum dibaca', ['path' => $path]);
        }

        if (!mb_check_encoding($isi, 'UTF-8')) {
            throw new RuntimeException('Berkas hasil Codex bukan UTF-8 yang valid: ' . $path);
        }

        return $isi;
    }
}
