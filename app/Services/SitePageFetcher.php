<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Helper bersama untuk modul audit SEO: menyusun daftar halaman yang akan
 * diperiksa (homepage + halaman tambahan yang se-host & aman) dan mengambil
 * HTML-nya. Dipakai OnPageAuditService, TechnicalSeoService,
 * ContentExtractabilityService.
 */
class SitePageFetcher
{
    private const UA = 'Mozilla/5.0 (compatible; DashboardOperasionalBot/1.0; +internal-seo-audit)';

    /** @param  list<string>  $extraUrls @return list<string> */
    public function pages(string $baseUrl, array $extraUrls = [], int $max = 6): array
    {
        $base = $this->normalize($baseUrl, $baseUrl);
        if ($base === null) {
            return [];
        }

        $baseHost = strtolower((string) (parse_url($base, PHP_URL_HOST) ?? ''));
        $urls = [$base];

        foreach ($extraUrls as $candidate) {
            if (count($urls) >= $max) {
                break;
            }

            $url = $this->normalize((string) $candidate, $base);
            if ($url === null || in_array($url, $urls, true)) {
                continue;
            }
            if (strtolower((string) (parse_url($url, PHP_URL_HOST) ?? '')) !== $baseHost) {
                continue;
            }
            if (!CompetitorContentFetcher::isSafeUrl($url)) {
                continue;
            }

            $urls[] = $url;
        }

        return $urls;
    }

    public function html(string $url, int $timeout = 15): ?string
    {
        if (!CompetitorContentFetcher::isSafeUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout($timeout)
                ->retry(2, 400, throw: false)
                ->withHeaders(['User-Agent' => self::UA])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $body = $response->body();

            return trim($body) === '' ? null : mb_substr($body, 0, 3 * 1024 * 1024);
        } catch (\Throwable $e) {
            Log::info('SitePageFetcher: gagal mengambil ' . $url, ['error' => $e->getMessage()]);
            return null;
        }
    }

    /** GET mentah (untuk cek header / redirect), tanpa mengikuti redirect. */
    public function rawGet(string $url, int $timeout = 12): ?\Illuminate\Http\Client\Response
    {
        if (!CompetitorContentFetcher::isSafeUrl($url)) {
            return null;
        }

        try {
            return Http::timeout($timeout)
                ->withHeaders(['User-Agent' => self::UA])
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (\Throwable $e) {
            Log::info('SitePageFetcher: rawGet gagal ' . $url, ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function baseUrl(string $url): ?string
    {
        $normalized = $this->normalize($url, $url);
        if ($normalized === null) {
            return null;
        }

        $parts = parse_url($normalized);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port;
    }

    private function normalize(?string $url, string $baseUrl): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        } elseif (str_starts_with($url, '/')) {
            $url = rtrim($baseUrl, '/') . $url;
        } elseif (!preg_match('#^https?://#i', $url)) {
            if (!str_contains($url, '.') || str_contains($url, ' ')) {
                return null;
            }
            $url = 'https://' . $url;
        }

        return preg_match('#^https?://[^\s]+$#i', $url) ? $url : null;
    }
}
