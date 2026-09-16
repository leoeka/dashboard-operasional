<?php

namespace App\Services;

/**
 * SEO teknis tingkat-situs: robots.txt, sitemap.xml, HTTPS & redirect
 * homepage, header X-Robots-Tag, dan cek cepat sejumlah internal link
 * apakah ada yang mati. Murni HTTP + parsing — tidak ada AI.
 */
class TechnicalSeoService
{
    public function __construct(private SitePageFetcher $fetcher)
    {
    }

    /**
     * @return array{checked_at:string,checks:list<array<string,mixed>>,score:?int,recommendation:string}
     */
    public function analyze(string $url): array
    {
        $base = $this->fetcher->baseUrl($url);
        if ($base === null) {
            return [
                'checked_at' => now()->toDateTimeString(),
                'checks' => [],
                'score' => null,
                'recommendation' => 'URL website klien tidak valid.',
            ];
        }

        $checks = [];

        // --- HTTPS ---
        $isHttps = str_starts_with(strtolower($base), 'https://');
        if ($isHttps) {
            $checks[] = $this->check('https', 'HTTPS', 'pass', 'Situs berjalan di HTTPS.');
        } else {
            $httpResp = $this->fetcher->rawGet($base);
            $redirectsToHttps = $httpResp
                && in_array($httpResp->status(), [301, 302, 307, 308], true)
                && str_starts_with(strtolower((string) $httpResp->header('Location')), 'https://');
            $checks[] = $redirectsToHttps
                ? $this->check('https', 'HTTPS', 'warn', 'URL yang tersimpan masih http:// tapi redirect ke https://. Perbarui URL project ke versi https.')
                : $this->check('https', 'HTTPS', 'fail', 'Situs tidak memakai HTTPS dan tidak redirect ke HTTPS.');
        }

        // --- homepage redirect / status ---
        $homeResp = $this->fetcher->rawGet($base);
        if ($homeResp === null) {
            $checks[] = $this->check('homepage', 'Homepage', 'fail', 'Homepage tidak bisa diakses.');
        } elseif (in_array($homeResp->status(), [301, 302, 307, 308], true)) {
            $checks[] = $this->check('homepage', 'Homepage', 'warn', 'Homepage redirect (' . $homeResp->status() . ') ke ' . $homeResp->header('Location') . ' — pastikan URL project menunjuk langsung ke tujuan akhir.');
        } elseif ($homeResp->status() !== 200) {
            $checks[] = $this->check('homepage', 'Homepage', 'fail', 'Homepage membalas HTTP ' . $homeResp->status() . '.');
        } else {
            $xRobots = strtolower((string) $homeResp->header('X-Robots-Tag'));
            $checks[] = str_contains($xRobots, 'noindex')
                ? $this->check('homepage', 'Homepage', 'fail', 'Header X-Robots-Tag di homepage mengandung "noindex".')
                : $this->check('homepage', 'Homepage', 'pass', 'HTTP 200, tanpa X-Robots-Tag noindex.');
        }

        // --- robots.txt ---
        $robots = $this->fetcher->html($base . '/robots.txt', 8);
        if ($robots === null) {
            $checks[] = $this->check('robots', 'robots.txt', 'warn', 'Tidak ada robots.txt. Tidak wajib, tapi disarankan minimal untuk menunjuk ke sitemap.');
            $sitemapUrls = [];
        } else {
            $blocksAll = (bool) preg_match('/user-agent:\s*\*[\s\S]*?disallow:\s*\/\s*($|\r?\n)/i', $robots);
            $sitemapUrls = $this->sitemapUrlsFromRobots($robots);
            $checks[] = $blocksAll
                ? $this->check('robots', 'robots.txt', 'fail', 'robots.txt memblokir SELURUH situs untuk semua crawler (Disallow: /).')
                : $this->check('robots', 'robots.txt', $sitemapUrls === [] ? 'warn' : 'pass', $sitemapUrls === [] ? 'Ada, tapi tidak menunjuk ke sitemap.' : 'Ada dan menunjuk ke sitemap.');
        }

        // --- sitemap.xml ---
        $sitemapCandidates = $sitemapUrls ?: [$base . '/sitemap.xml', $base . '/sitemap_index.xml'];
        $sitemapFound = null;
        $locCount = 0;
        foreach ($sitemapCandidates as $candidate) {
            $xml = $this->fetcher->html($candidate, 8);
            if ($xml !== null && str_contains($xml, '<')) {
                $sitemapFound = $candidate;
                $locCount = $this->countLocs($xml);
                break;
            }
        }
        $checks[] = $sitemapFound === null
            ? $this->check('sitemap', 'sitemap.xml', 'fail', 'Sitemap tidak ditemukan — Google jadi lebih lambat menemukan halaman baru.')
            : $this->check('sitemap', 'sitemap.xml', $locCount === 0 ? 'warn' : 'pass', "Ditemukan di {$sitemapFound}" . ($locCount ? " (~{$locCount} URL)." : ' tapi kosong / berupa index.'));

        // --- internal link mati ---
        $homeHtml = $this->fetcher->html($base, 12);
        if ($homeHtml !== null) {
            $links = array_slice($this->sameHostLinks($homeHtml, $base), 0, 10);
            $broken = [];
            foreach ($links as $link) {
                $resp = $this->fetcher->rawGet($link, 8);
                if ($resp === null || $resp->status() >= 400) {
                    $broken[] = $link . ($resp ? ' (' . $resp->status() . ')' : ' (tidak terjangkau)');
                }
            }
            $checks[] = $broken === []
                ? $this->check('broken_links', 'Internal link', 'pass', count($links) . ' internal link dari homepage dicek, semua hidup.')
                : $this->check('broken_links', 'Internal link', 'warn', count($broken) . ' internal link bermasalah: ' . implode('; ', array_slice($broken, 0, 5)) . '.');
        }

        return [
            'checked_at' => now()->toDateTimeString(),
            'checks' => $checks,
            'score' => $this->score($checks),
            'recommendation' => $this->recommendation($checks),
        ];
    }

    /** @return list<string> */
    public function sitemapUrlsFromRobots(string $robotsTxt): array
    {
        preg_match_all('/^\s*sitemap:\s*(\S+)\s*$/im', $robotsTxt, $m);

        return array_values(array_unique(array_map('trim', $m[1] ?? [])));
    }

    public function countLocs(string $xml): int
    {
        return preg_match_all('/<loc>\s*[^<\s]/i', $xml);
    }

    /** @return list<string> */
    public function sameHostLinks(string $html, string $baseUrl): array
    {
        $host = strtolower((string) (parse_url($baseUrl, PHP_URL_HOST) ?? ''));
        preg_match_all('/<a\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i', $html, $m);

        $out = [];
        foreach ($m[1] ?? [] as $href) {
            $href = trim(html_entity_decode($href));
            if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript):/i', $href)) {
                continue;
            }
            if (str_starts_with($href, '/')) {
                $href = rtrim($baseUrl, '/') . $href;
            }
            $linkHost = strtolower((string) (parse_url($href, PHP_URL_HOST) ?? ''));
            if ($linkHost === $host && !in_array($href, $out, true)) {
                $out[] = $href;
            }
        }

        return $out;
    }

    private function check(string $id, string $label, string $status, string $detail): array
    {
        return ['id' => $id, 'label' => $label, 'status' => $status, 'detail' => $detail];
    }

    private function score(array $checks): ?int
    {
        if ($checks === []) {
            return null;
        }

        $fails = count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));
        $warns = count(array_filter($checks, fn ($c) => $c['status'] === 'warn'));

        return max(0, min(100, 100 - $fails * 22 - $warns * 9));
    }

    private function recommendation(array $checks): string
    {
        $problems = array_values(array_filter($checks, fn ($c) => $c['status'] !== 'pass'));
        if ($problems === []) {
            return 'Fondasi teknis situs sehat.';
        }

        $fails = array_values(array_filter($problems, fn ($c) => $c['status'] === 'fail'));
        $focus = $fails !== [] ? $fails : $problems;

        return 'Perbaiki dulu: ' . implode('; ', array_map(fn ($c) => $c['label'] . ' — ' . $c['detail'], array_slice($focus, 0, 3)));
    }
}
