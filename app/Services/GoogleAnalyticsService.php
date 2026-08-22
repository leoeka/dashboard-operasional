<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ambil laporan Google Analytics (GA4) — sessions organik, engagement,
 * konversi, new vs returning, per landing page. Sama polanya dengan
 * SearchConsoleService: 1 akun kantor, refresh token di .env.
 *
 * BEDA dari Search Console: GA4 identifikasi situs pakai "Property ID"
 * (nomor, bukan URL) — jadi sebelum bisa tarik data, sistem ini coba
 * CARI OTOMATIS Property ID yang cocok (cocokkan hostname project ke
 * URL data stream tiap Property yang bisa diakses akun). Kalau ketemu
 * PERSIS 1 → langsung dipakai. Kalau ketemu LEBIH dari 1 atau TIDAK
 * ketemu sama sekali → controller yang minta tim pilih/isi manual,
 * disimpan sekali ke seo_requirements['ga4_property_id'] supaya next
 * time tidak perlu resolve ulang.
 */
class GoogleAnalyticsService
{
    public function getAccessToken(): ?string
    {
        $refreshToken = config('services.google_analytics.refresh_token');
        $clientId = config('services.google_ads.client_id');
        $clientSecret = config('services.google_ads.client_secret');

        if (!$refreshToken || !$clientId || !$clientSecret) {
            return null;
        }

        return Cache::remember('ga4_access_token', now()->addMinutes(50), function () use ($refreshToken, $clientId, $clientSecret) {
            try {
                $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

                if (!$response->successful()) {
                    Log::error('GoogleAnalyticsService: gagal refresh access token.', ['body' => $response->body()]);
                    return null;
                }

                return $response->json('access_token');
            } catch (\Throwable $e) {
                Log::error('GoogleAnalyticsService: exception saat refresh token: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Ambil daftar SEMUA Property yang bisa diakses akun ini, lengkap
     * dengan hostname data stream web-nya — di-cache 6 jam supaya tidak
     * berkali-kali manggil Admin API (jumlah panggilannya = 1 + jumlah
     * property, bisa lumayan banyak kalau client-nya sudah puluhan).
     */
    private function listPropertiesWithUrls(string $accessToken): array
    {
        return Cache::remember('ga4_properties_map', now()->addHours(6), function () use ($accessToken) {
            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', ['pageSize' => 200]);

            if (!$response->successful()) {
                Log::warning('GoogleAnalyticsService: gagal ambil accountSummaries.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $propertyList = [];
            foreach ($response->json('accountSummaries', []) as $account) {
                foreach ($account['propertySummaries'] ?? [] as $prop) {
                    $id = str_replace('properties/', '', $prop['property'] ?? '');
                    if ($id !== '') {
                        $propertyList[] = ['id' => $id, 'name' => $prop['displayName'] ?? ''];
                    }
                }
            }

            $result = [];
            foreach ($propertyList as $prop) {
                $streamsResponse = Http::withToken($accessToken)
                    ->timeout(15)
                    ->get("https://analyticsadmin.googleapis.com/v1beta/properties/{$prop['id']}/dataStreams");

                if (!$streamsResponse->successful()) {
                    continue;
                }

                foreach ($streamsResponse->json('dataStreams', []) as $stream) {
                    $uri = $stream['webStreamData']['defaultUri'] ?? null;
                    if (!$uri) {
                        continue;
                    }

                    $host = parse_url($uri, PHP_URL_HOST) ?? '';
                    $host = preg_replace('/^www\./', '', strtolower($host));

                    $result[] = [
                        'property_id' => $prop['id'],
                        'name' => $prop['name'],
                        'host' => $host,
                        'url' => $uri,
                    ];
                }
            }

            return $result;
        });
    }

    /**
     * @return array{status: string, property_id: ?string, candidates: array}
     * status salah satu: 'found' | 'ambiguous' | 'not_found'
     */
    public function resolveProperty(string $accessToken, string $siteUrl): array
    {
        $host = parse_url($siteUrl, PHP_URL_HOST) ?? $siteUrl;
        $host = preg_replace('/^www\./', '', strtolower($host));

        $properties = $this->listPropertiesWithUrls($accessToken);
        $matches = array_values(array_filter($properties, fn($p) => $p['host'] === $host));

        if (count($matches) === 1) {
            return ['status' => 'found', 'property_id' => $matches[0]['property_id'], 'candidates' => []];
        }

        if (count($matches) > 1) {
            return ['status' => 'ambiguous', 'property_id' => null, 'candidates' => $matches];
        }

        return ['status' => 'not_found', 'property_id' => null, 'candidates' => []];
    }

    /**
     * Ambil laporan 28 hari terakhir untuk 1 Property GA4: total sessions
     * organik + users + conversions, new vs returning users, dan top 10
     * landing page (sessions, engagement rate, rata-rata waktu
     * engagement, conversions) — semua difilter cuma traffic organik
     * (sessionDefaultChannelGroup = "Organic Search"), sesuai yang
     * diminta di spec dashboard ("Total Organic Sessions", dst).
     */
    public function getReport(string $accessToken, string $propertyId, int $days = 28): ?array
    {
        try {
            $dateRange = [['startDate' => "{$days}daysAgo", 'endDate' => 'yesterday']];
            $organicFilter = [
                'filter' => [
                    'fieldName' => 'sessionDefaultChannelGroup',
                    'stringFilter' => ['value' => 'Organic Search'],
                ],
            ];

            // TOTALS
            $totalsRows = $this->runReport($accessToken, $propertyId, [
                'dateRanges' => $dateRange,
                'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
                'metrics' => [['name' => 'sessions'], ['name' => 'totalUsers'], ['name' => 'conversions']],
            ]);

            $organicRow = collect($totalsRows)->first(fn($r) => ($r['dimensionValues'][0]['value'] ?? null) === 'Organic Search');

            $totals = [
                'organic_sessions' => (int) ($organicRow['metricValues'][0]['value'] ?? 0),
                'total_users' => (int) ($organicRow['metricValues'][1]['value'] ?? 0),
                'conversions' => (float) ($organicRow['metricValues'][2]['value'] ?? 0),
            ];

            // NEW VS RETURNING (organik)
            $nvrRows = $this->runReport($accessToken, $propertyId, [
                'dateRanges' => $dateRange,
                'dimensions' => [['name' => 'newVsReturning']],
                'metrics' => [['name' => 'totalUsers']],
                'dimensionFilter' => $organicFilter,
            ]);

            $newVsReturning = ['new' => 0, 'returning' => 0];
            foreach ($nvrRows as $row) {
                $label = $row['dimensionValues'][0]['value'] ?? '';
                $value = (int) ($row['metricValues'][0]['value'] ?? 0);
                if ($label === 'new') {
                    $newVsReturning['new'] = $value;
                } elseif ($label === 'returning') {
                    $newVsReturning['returning'] = $value;
                }
            }

            // TOP LANDING PAGE (organik)
            $pageRows = $this->runReport($accessToken, $propertyId, [
                'dateRanges' => $dateRange,
                'dimensions' => [['name' => 'landingPagePlusQueryString']],
                'metrics' => [
                    ['name' => 'sessions'],
                    ['name' => 'engagementRate'],
                    ['name' => 'userEngagementDuration'],
                    ['name' => 'conversions'],
                ],
                'dimensionFilter' => $organicFilter,
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit' => 10,
            ]);

            $byLandingPage = [];
            foreach ($pageRows as $row) {
                $sessions = (int) ($row['metricValues'][0]['value'] ?? 0);
                // userEngagementDuration itu TOTAL detik (bukan rata-rata)
                // — dibagi sessions di sini biar dapat rata-rata per
                // sesi, sesuai "Average Engagement Time" yang diminta.
                $engagementDurationTotal = (float) ($row['metricValues'][2]['value'] ?? 0);

                $byLandingPage[] = [
                    'landing_page' => $row['dimensionValues'][0]['value'] ?? '-',
                    'sessions' => $sessions,
                    'engagement_rate' => (float) ($row['metricValues'][1]['value'] ?? 0),
                    'avg_engagement_time' => $sessions > 0 ? round($engagementDurationTotal / $sessions, 1) : 0,
                    'conversions' => (float) ($row['metricValues'][3]['value'] ?? 0),
                ];
            }

            return [
                'property_id' => $propertyId,
                'totals' => $totals,
                'new_vs_returning' => $newVsReturning,
                'by_landing_page' => $byLandingPage,
                'period' => [
                    'start' => now()->subDays($days)->format('Y-m-d'),
                    'end' => now()->subDay()->format('Y-m-d'),
                ],
            ];

        } catch (\Throwable $e) {
            Log::error('GoogleAnalyticsService Exception: ' . $e->getMessage(), ['property_id' => $propertyId]);
            return null;
        }
    }

    private function runReport(string $accessToken, string $propertyId, array $body): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(20)
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", $body);

        if (!$response->successful()) {
            Log::warning('GoogleAnalyticsService: runReport gagal.', [
                'property_id' => $propertyId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        return $response->json('rows', []);
    }
}