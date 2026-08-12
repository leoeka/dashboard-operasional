<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleAdsKeywordService
{
    /**
     * Ambil volume pencarian asli + tingkat persaingan untuk daftar kandidat
     * keyword, lewat Keyword Planner (generateKeywordIdeas).
     *
     * PENTING: kalau developer token belum Basic Access (masih proses
     * approval), atau kredensial belum diisi di .env sama sekali, method
     * ini akan GAGAL SECARA HALUS — return array kosong, TIDAK melempar
     * exception. Ini supaya fitur generate keyword tetap bisa dipakai
     * (AI-only) selama approval masih berjalan, dan otomatis dapat data
     * lebih akurat begitu approval selesai — tanpa perlu ubah kode apapun.
     */
    public function getKeywordIdeas(array $seedKeywords, string $languageCode = 'id'): array
    {
        if (empty($seedKeywords)) {
            return [];
        }

        $accessToken = $this->getAccessToken();
        $customerId = config('services.google_ads.customer_id');
        $developerToken = config('services.google_ads.developer_token');

        if (!$accessToken || !$customerId || !$developerToken) {
            Log::info('GoogleAdsKeywordService: kredensial belum lengkap, skip pengecekan volume (fallback ke AI-only).');
            return [];
        }

        try {
            $headers = [
                'Authorization' => "Bearer {$accessToken}",
                'developer-token' => $developerToken,
                'Content-Type' => 'application/json',
            ];

            $loginCustomerId = config('services.google_ads.login_customer_id');
            if ($loginCustomerId) {
                $headers['login-customer-id'] = $loginCustomerId;
            }

            // CATATAN: endpoint & nama field REST Google Ads API bisa berubah
            // antar versi (v17 dipakai di sini per dokumentasi saat ini) —
            // kalau ternyata gagal terus, cek dokumentasi resmi terbaru di
            // developers.google.com/google-ads/api/rest/overview dulu
            // sebelum curiga ke kode ini.
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post("https://googleads.googleapis.com/v17/customers/{$customerId}:generateKeywordIdeas", [
                    'keywordSeed' => ['keywords' => array_values($seedKeywords)],
                    'geoTargetConstants' => ['geoTargetConstants/2360'], // 2360 = Indonesia
                    'keywordPlanNetwork' => 'GOOGLE_SEARCH',
                ]);

            if (!$response->successful()) {
                Log::warning('GoogleAdsKeywordService: request gagal, fallback ke AI-only.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $results = $response->json('results', []);

            return collect($results)
                ->map(function ($item) {
                    $metrics = $item['keywordIdeaMetrics'] ?? [];
                    return [
                        'keyword' => $item['text'] ?? null,
                        'avg_monthly_searches' => $metrics['avgMonthlySearches'] ?? null,
                        'competition' => $metrics['competition'] ?? null, // LOW / MEDIUM / HIGH
                    ];
                })
                ->filter(fn($k) => !empty($k['keyword']))
                ->values()
                ->toArray();

        } catch (\Throwable $e) {
            Log::error('GoogleAdsKeywordService Exception (fallback ke AI-only): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Refresh access token pakai refresh token yang tersimpan permanen di
     * .env — cache 50 menit (token asli berlaku 1 jam, kita refresh dikit
     * lebih awal buat jaga-jaga).
     */
    private function getAccessToken(): ?string
    {
        $refreshToken = config('services.google_ads.refresh_token');
        $clientId = config('services.google_ads.client_id');
        $clientSecret = config('services.google_ads.client_secret');

        if (!$refreshToken || !$clientId || !$clientSecret) {
            return null;
        }

        return Cache::remember('google_ads_access_token', now()->addMinutes(50), function () use ($refreshToken, $clientId, $clientSecret) {
            try {
                $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

                if (!$response->successful()) {
                    Log::error('GoogleAdsKeywordService: gagal refresh access token.', ['body' => $response->body()]);
                    return null;
                }

                return $response->json('access_token');
            } catch (\Throwable $e) {
                Log::error('GoogleAdsKeywordService: exception saat refresh token: ' . $e->getMessage());
                return null;
            }
        });
    }
}