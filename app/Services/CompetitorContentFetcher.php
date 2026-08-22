<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompetitorContentFetcher
{
    /**
     * FIX (keamanan): cek URL sebelum di-fetch — pastikan skema http/https
     * dan host-nya tidak resolve ke IP privat/reserved. Perlu karena URL
     * yang difetch bisa berasal dari input MANUAL tim, bukan cuma hasil
     * Google Search.
     */

    public static function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            // FIX (reliabilitas, ditemukan saat testing): gethostbynamel()
            // kadang gagal resolve DNS sesaat padahal domainnya valid —
            // retry beberapa kali dulu sebelum benar-benar dianggap tidak
            // bisa diakses, supaya tidak salah tolak URL yang sah.
            $ips = [];
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $ips = @gethostbynamel($host) ?: [];
                if (!empty($ips)) {
                    break;
                }
                if ($attempt < 3) {
                    usleep(300000); // jeda 0.3 detik sebelum coba lagi
                }
            }
        }

        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ambil konten publik dari sebuah URL — judul, heading (h1-h3), dan teks
     * body-nya. Ini murni request HTTP biasa ke halaman yang PUBLIK diakses
     * siapapun (sama seperti buka situs itu di browser), bukan scraping data
     * privat atau bypass proteksi apapun.
     *
     * Return null kalau gagal (URL tidak valid, timeout, halaman kosong,
     * dll) — supaya caller bisa nge-handle dengan jelas, bukan dapat data
     * kosong yang keliatan seperti sukses.
     */
    public function fetch(string $url): ?array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || !self::isSafeUrl($url)) {
            Log::warning('CompetitorContentFetcher: URL tidak valid atau tidak diizinkan.', ['url' => $url]);
            return null;
        }

        try {
            $response = Http::withHeaders([
                // Beberapa situs block request tanpa User-Agent yang wajar,
                // dianggap bot. Kita identifikasi diri dengan jelas, bukan
                // menyamar jadi browser asli.
                'User-Agent' => 'Mozilla/5.0 (compatible; DashboardOperasionalBot/1.0; +internal-competitor-research)',
            ])
                ->timeout(20)
                ->get($url);

            if (!$response->successful()) {
                Log::warning('CompetitorContentFetcher: request gagal.', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $html = $response->body();

            if (empty(trim($html))) {
                return null;
            }

            return $this->extractContent($html, $url);

        } catch (\Throwable $e) {
            Log::error('CompetitorContentFetcher Exception: ' . $e->getMessage(), ['url' => $url]);
            return null;
        }
    }

    /**
     * Wrapper publik untuk extractContent() — dipakai saat HTML sudah
     * didapat lewat jalur lain (mis. Http::pool() untuk fetch paralel).
     */
    public function parseHtml(string $html, string $url): array
    {
        return $this->extractContent($html, $url);
    }

    /**
     * Parse HTML mentah jadi struktur teks yang bersih — judul, heading
     * (h1-h3), dan paragraf body. Script/style/nav/footer dibuang supaya
     * konten yang dikirim ke AI nanti fokus ke isi artikel, bukan noise
     * navigasi/menu yang berulang di setiap halaman.
     */
    private function extractContent(string $html, string $url): array
    {
        $dom = new \DOMDocument();

        // Suppress warning dari HTML yang tidak valid/malformed — banyak
        // situs di dunia nyata HTML-nya tidak 100% rapi, kita tetap coba
        // parse semaksimal mungkin.
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($dom);

        // Buang elemen yang bukan konten utama sebelum ekstrak teks
        foreach (['script', 'style', 'nav', 'footer', 'noscript', 'svg'] as $tag) {
            foreach ($xpath->query("//{$tag}") as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $titleNodes = $xpath->query('//title');
        $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : '';

        $headings = [];
        foreach (['h1', 'h2', 'h3'] as $tag) {
            foreach ($xpath->query("//{$tag}") as $node) {
                $text = trim($node->textContent);
                if ($text !== '') {
                    $headings[] = $text;
                }
            }
        }

        $paragraphs = [];
        foreach ($xpath->query('//p') as $node) {
            $text = trim($node->textContent);
            if (strlen($text) > 30) { // buang paragraf pendek (biasanya sisa UI, bukan konten)
                $paragraphs[] = $text;
            }
        }

        $bodyText = implode("\n", $paragraphs);

        // Batasi panjang teks yang dikirim ke AI — biar prompt tidak
        // membengkak dan biaya token tidak melonjak untuk halaman yang
        // sangat panjang. 6000 karakter biasanya cukup buat nangkep
        // konteks utama sebuah halaman.
        $bodyText = mb_substr($bodyText, 0, 6000);

        return [
            'url' => $url,
            'title' => $title,
            'headings' => array_slice($headings, 0, 20),
            'body_text' => $bodyText,
        ];
    }
}