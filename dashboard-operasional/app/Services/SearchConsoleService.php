<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ambil data performa pencarian (klik, tayang, CTR, posisi, top query)
 * dari Google Search Console — pakai 1 akun kantor yang sudah punya
 * akses ke Search Console banyak client sekaligus (bukan akun per-
 * client). Sama polanya seperti GoogleAdsKeywordService: refresh token
 * disimpan di .env, gagal secara HALUS (return null) kalau kredensial
 * belum lengkap — tidak bikin fitur lain ikut gagal.
 */
class SearchConsoleService
{
    private function getAccessToken(): ?string
    {
        $refreshToken = config('services.google_search_console.refresh_token');
        $clientId = config('services.google_ads.client_id');
        $clientSecret = config('services.google_ads.client_secret');

        if (!$refreshToken || !$clientId || !$clientSecret) {
            return null;
        }

        return Cache::remember('gsc_access_token', now()->addMinutes(50), function () use ($refreshToken, $clientId, $clientSecret) {
            try {
                $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

                if (!$response->successful()) {
                    Log::error('SearchConsoleService: gagal refresh access token.', ['body' => $response->body()]);
                    return null;
                }

                return $response->json('access_token');
            } catch (\Throwable $e) {
                Log::error('SearchConsoleService: exception saat refresh token: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Ambil ringkasan performa untuk 1 website, 28 hari terakhir (data
     * 3 hari terakhir sengaja dilewati — Search Console sering belum
     * lengkap datanya untuk beberapa hari paling baru).
     *
     * Return null kalau: kredensial belum lengkap, atau website ini
     * TIDAK ketemu di daftar property yang terverifikasi di akun.
     */
    public function getPerformance(string $siteUrl, int $days = 28): ?array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::info('SearchConsoleService: kredensial belum lengkap, skip.');
            return null;
        }

        $property = $this->resolveVerifiedProperty($accessToken, $siteUrl);
        if (!$property) {
            Log::warning('SearchConsoleService: URL ini tidak ketemu di daftar property terverifikasi akun.', ['url' => $siteUrl]);
            return null;
        }

        $startDate = now()->subDays($days)->format('Y-m-d');
        $endDate = now()->subDays(3)->format('Y-m-d');

        try {
            $totals = $this->query($accessToken, $property, $startDate, $endDate, []);
            $topQueries = $this->query($accessToken, $property, $startDate, $endDate, ['query'], 10);
            $byDevice = $this->query($accessToken, $property, $startDate, $endDate, ['device'], 10);

            return [
                'property' => $property,
                'totals' => $totals[0] ?? null,
                'top_queries' => $topQueries,
                'by_device' => $byDevice,
                'period' => ['start' => $startDate, 'end' => $endDate],
            ];
        } catch (\Throwable $e) {
            Log::error('SearchConsoleService Exception: ' . $e->getMessage(), ['site' => $siteUrl]);
            return null;
        }
    }

    /**
     * Cari property Search Console yang cocok dengan URL project —
     * dicek dari daftar site yang BENERAN terverifikasi & bisa diakses
     * akun ini (lewat endpoint sites.list), dicocokkan berdasarkan
     * hostname. Ini menghindari tebak-tebak format URL-prefix vs
     * domain-property (dua format berbeda yang dipakai Search Console),
     * karena kita tanya langsung ke Google properti apa saja yang
     * sebenarnya ada, bukan asumsi sendiri.
     */
    private function resolveVerifiedProperty(string $accessToken, string $siteUrl): ?string
    {
        $host = parse_url($siteUrl, PHP_URL_HOST) ?? $siteUrl;
        $host = preg_replace('/^www\./', '', strtolower($host));

        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->get('https://www.googleapis.com/webmasters/v3/sites');

        if (!$response->successful()) {
            Log::warning('SearchConsoleService: gagal ambil daftar site.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $sites = $response->json('siteEntry', []);

        foreach ($sites as $site) {
            $entry = $site['siteUrl'] ?? '';
            $entryHost = str_starts_with($entry, 'sc-domain:')
                ? substr($entry, strlen('sc-domain:'))
                : (parse_url($entry, PHP_URL_HOST) ?? '');
            $entryHost = preg_replace('/^www\./', '', strtolower($entryHost));

            if ($entryHost === $host) {
                return $entry;
            }
        }

        return null;
    }

    private function query(string $accessToken, string $property, string $startDate, string $endDate, array $dimensions, int $rowLimit = 1): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(20)
            ->post('https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($property) . '/searchAnalytics/query', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => $dimensions,
                'rowLimit' => $rowLimit,
            ]);

        if (!$response->successful()) {
            Log::warning('SearchConsoleService: query gagal.', [
                'property' => $property,
                'dimensions' => $dimensions,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        return $response->json('rows', []);
    }
}