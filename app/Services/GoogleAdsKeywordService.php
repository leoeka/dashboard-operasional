<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAdsKeywordService
{
    private string $lastStatus = 'not_checked';

    /**
     * Return historical Google Ads metrics keyed by normalized input keyword.
     * Google Ads averages monthly searches over its historical window (normally
     * the last 12 months); this is not Search Console impressions.
     */
    public function getHistoricalMetrics(array $keywords, ?string $location = null): array
    {
        $this->lastStatus = 'not_configured';
        $keywords = collect($keywords)
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique(fn ($keyword) => mb_strtolower($keyword))
            ->take(10000)
            ->values();

        if ($keywords->isEmpty() || !$this->isConfigured()) {
            return [];
        }

        $geoTargets = config('services.google_ads.geo_target_constants', []);
        if (empty($geoTargets)) {
            $this->lastStatus = 'geo_targets_missing';
            Log::warning('GoogleAdsKeywordService: geo target constants are not configured.');
            return [];
        }

        $cacheKey = 'google_ads_keyword_metrics:' . hash('sha256', json_encode([
            $keywords->all(),
            $geoTargets,
            config('services.google_ads.language_constant'),
            config('services.google_ads.customer_id'),
            mb_strtolower(trim((string) $location)),
        ]));

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $this->lastStatus = empty($cached) ? 'no_metrics' : 'available';
            return $cached;
        }

        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                $this->lastStatus = 'authentication_failed';
                return [];
            }

            if (filled($location)) {
                $resolvedGeoTargets = $this->resolveGeoTargets($accessToken, $location);
                if (!empty($resolvedGeoTargets)) {
                    $geoTargets = $resolvedGeoTargets;
                }
            }

            $customerId = preg_replace('/\\D+/', '', (string) config('services.google_ads.customer_id'));
            $version = preg_replace('/[^a-zA-Z0-9]/', '', (string) config('services.google_ads.api_version', 'v26'));
            $headers = [
                'developer-token' => config('services.google_ads.developer_token'),
            ];
            $loginCustomerId = preg_replace('/\\D+/', '', (string) config('services.google_ads.login_customer_id'));
            if ($loginCustomerId !== '') {
                $headers['login-customer-id'] = $loginCustomerId;
            }

            $response = Http::withToken($accessToken)
                ->withHeaders($headers)
                ->timeout(30)
                ->post("https://googleads.googleapis.com/{$version}/customers/{$customerId}:generateKeywordHistoricalMetrics", [
                    'keywords' => $keywords->all(),
                    'geoTargetConstants' => array_values($geoTargets),
                    'language' => config('services.google_ads.language_constant', 'languageConstants/1022'),
                    'keywordPlanNetwork' => 'GOOGLE_SEARCH',
                ]);

            if (!$response->successful()) {
                $exception = ProviderException::fromResponse('google_ads', $response);
                $this->lastStatus = str_contains($response->body(), 'CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION')
                    ? 'project_not_approved'
                    : $exception->errorCode;
                Log::warning('GoogleAdsKeywordService: historical metrics request failed.', $exception->context());
                return [];
            }

            $metrics = $this->mapResults($keywords->all(), $response->json('results', []));
            $this->lastStatus = empty($metrics) ? 'no_metrics' : 'available';
            Cache::put($cacheKey, $metrics, now()->addHours(24));
            return $metrics;
        } catch (\Throwable $e) {
            $this->lastStatus = 'provider_unavailable';
            Log::warning('GoogleAdsKeywordService: historical metrics unavailable.', [
                'error' => ProviderException::sanitise($e->getMessage()),
            ]);
            return [];
        }
    }

    /** Return a safe, user-facing classification for the latest request. */
    public function historicalMetricsStatus(): string
    {
        return $this->lastStatus;
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google_ads.client_id'))
            && filled(config('services.google_ads.client_secret'))
            && filled(config('services.google_ads.refresh_token'))
            && filled(config('services.google_ads.developer_token'))
            && filled(config('services.google_ads.customer_id'));
    }

    private function getAccessToken(): ?string
    {
        $cacheKey = 'google_ads_access_token:' . hash('sha256', (string) config('services.google_ads.client_id'));

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_ads.client_id'),
            'client_secret' => config('services.google_ads.client_secret'),
            'refresh_token' => config('services.google_ads.refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            Log::warning('GoogleAdsKeywordService: OAuth token refresh failed.', [
                'provider_error' => ProviderException::fromResponse('google_ads', $response)->context(),
            ]);
            return null;
        }

        $accessToken = $response->json('access_token');
        if (is_string($accessToken) && $accessToken !== '') {
            Cache::put($cacheKey, $accessToken, now()->addMinutes(50));
            return $accessToken;
        }

        return null;
    }

    private function mapResults(array $requestedKeywords, array $results): array
    {
        $byVariant = [];
        foreach ($results as $result) {
            $metrics = $result['keywordMetrics'] ?? [];
            $normalizedMetrics = [
                'avg_monthly_searches' => isset($metrics['avgMonthlySearches']) ? (int) $metrics['avgMonthlySearches'] : null,
                'competition' => $metrics['competition'] ?? null,
                'competition_index' => isset($metrics['competitionIndex']) ? (int) $metrics['competitionIndex'] : null,
                'low_top_of_page_bid_micros' => isset($metrics['lowTopOfPageBidMicros']) ? (int) $metrics['lowTopOfPageBidMicros'] : null,
                'high_top_of_page_bid_micros' => isset($metrics['highTopOfPageBidMicros']) ? (int) $metrics['highTopOfPageBidMicros'] : null,
            ];

            foreach (array_merge([$result['text'] ?? ''], $result['closeVariants'] ?? []) as $variant) {
                if ($variant !== '') {
                    $byVariant[mb_strtolower(trim($variant))] = $normalizedMetrics;
                }
            }
        }

        $mapped = [];
        foreach ($requestedKeywords as $keyword) {
            $normalized = mb_strtolower(trim($keyword));
            if (isset($byVariant[$normalized])) {
                $mapped[$normalized] = $byVariant[$normalized];
            }
        }

        return $mapped;
    }

    private function resolveGeoTargets(string $accessToken, string $location): array
    {
        $version = preg_replace('/[^a-zA-Z0-9]/', '', (string) config('services.google_ads.api_version', 'v26'));
        $headers = ['developer-token' => config('services.google_ads.developer_token')];
        $loginCustomerId = preg_replace('/\\D+/', '', (string) config('services.google_ads.login_customer_id'));
        if ($loginCustomerId !== '') {
            $headers['login-customer-id'] = $loginCustomerId;
        }

        $response = Http::withToken($accessToken)
            ->withHeaders($headers)
            ->timeout(15)
            ->post("https://googleads.googleapis.com/{$version}/geoTargetConstants:suggest", [
                'locale' => 'id',
                'locationNames' => ['names' => [$location]],
            ]);

        if (!$response->successful()) {
            Log::info('GoogleAdsKeywordService: location lookup failed; configured default geo targets will be used.', [
                'provider_error' => ProviderException::fromResponse('google_ads', $response)->context(),
            ]);
            return [];
        }

        return collect($response->json('geoTargetConstantSuggestions', []))
            ->map(fn ($suggestion) => $suggestion['geoTargetConstant']['resourceName'] ?? null)
            ->filter()
            ->unique()
            ->take(1)
            ->values()
            ->all();
    }
}
