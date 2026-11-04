<?php

use App\Models\Project;
use App\Services\AnalisisGeminiService;
use App\Services\CompetitorContentFetcher;
use App\Services\CompetitorDiscoveryService;
use App\Services\GoogleAdsKeywordService;
use App\Services\KeywordResearchService;
use App\Services\SearchConsoleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('uses actual Google Ads metrics and attaches matching Search Console metrics separately', function () {
    $ai = Mockery::mock(AnalisisGeminiService::class);
    $fetcher = Mockery::mock(CompetitorContentFetcher::class);
    $discovery = Mockery::mock(CompetitorDiscoveryService::class);
    $searchConsole = Mockery::mock(SearchConsoleService::class);
    $googleAds = Mockery::mock(GoogleAdsKeywordService::class);

    $fetcher->shouldReceive('fetch')->once()->with('https://8.8.8.8')->andReturn([
        'title' => 'Example business',
        'body_text' => 'Example business content',
    ]);
    $discovery->shouldReceive('findCompetitors')->once()->andReturn([]);
    $ai->shouldReceive('identifyTopicsFromWebsite')->once()->andReturn([
        'core_topics' => ['consulting'],
        'seed_keywords' => ['business consulting'],
    ]);
    $ai->shouldReceive('expandSeedKeywords')->once()->andReturn(['business consulting', 'consulting firm']);
    $googleAds->shouldReceive('getHistoricalMetrics')->once()->with(['business consulting', 'consulting firm'], null)->andReturn([
        'business consulting' => [
            'avg_monthly_searches' => 700,
            'competition' => 'MEDIUM',
            'competition_index' => 45,
        ],
    ]);
    $googleAds->shouldReceive('historicalMetricsStatus')->once()->andReturn('available');
    $searchConsole->shouldReceive('getTopQueries')->once()->with('https://8.8.8.8')->andReturn([
        ['keyword' => 'business consulting', 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.035, 'position' => 8.2],
    ]);
    $ai->shouldReceive('selectFinalKeywords')->once()->andReturn([
        'main_keywords' => [
            [
                'keyword' => 'business consulting',
                'avg_monthly_searches' => 9000,
                'competition' => 'HIGH',
                'selected' => false,
            ],
            [
                'keyword' => 'consulting firm',
                'avg_monthly_searches' => 1200,
                'competition' => 'MEDIUM',
            ],
        ],
        'data_source' => 'google_ads_api',
    ]);

    $service = new KeywordResearchService($ai, $fetcher, $discovery, $searchConsole, $googleAds);
    $result = $service->analyze(new Project(['name' => 'Example', 'type' => 'Consulting']), 'https://8.8.8.8', [], fn () => null);

    expect($result['recommendations']['data_source'])->toBe('google_ads_api')
        ->and($result['recommendations']['main_keywords'][0]['avg_monthly_searches'])->toBe(700)
        ->and($result['recommendations']['main_keywords'][0]['competition'])->toBe('MEDIUM')
        ->and($result['recommendations']['main_keywords'][0]['competition_index'])->toBe(45)
        ->and($result['recommendations']['main_keywords'][0]['search_console_metrics']['impressions'])->toBe(340)
        ->and($result['recommendations']['main_keywords'][0]['search_console_metrics']['clicks'])->toBe(12)
        ->and($result['recommendations']['main_keywords'][1]['search_console_metrics'])->toBeNull()
        ->and($result['recommendations']['main_keywords'][1]['avg_monthly_searches'])->toBeNull()
        ->and($result['recommendations']['main_keywords'][1]['competition'])->toBeNull();
});

it('requests and parses Google Ads historical keyword metrics', function () {
    Cache::flush();
    config([
        'services.google_ads.client_id' => 'test-client',
        'services.google_ads.client_secret' => 'test-secret',
        'services.google_ads.refresh_token' => 'test-refresh',
        'services.google_ads.developer_token' => 'test-developer-token',
        'services.google_ads.customer_id' => '123-456-7890',
        'services.google_ads.login_customer_id' => '987-654-3210',
        'services.google_ads.api_version' => 'v26',
        'services.google_ads.geo_target_constants' => ['geoTargetConstants/2356'],
        'services.google_ads.language_constant' => 'languageConstants/1022',
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token'], 200),
        'https://googleads.googleapis.com/v26/customers/1234567890:generateKeywordHistoricalMetrics' => Http::response([
            'results' => [[
                'text' => 'business consulting',
                'closeVariants' => ['business consultancy'],
                'keywordMetrics' => [
                    'avgMonthlySearches' => '720',
                    'competition' => 'MEDIUM',
                    'competitionIndex' => '41',
                    'lowTopOfPageBidMicros' => '1200000',
                    'highTopOfPageBidMicros' => '3500000',
                ],
            ]],
        ], 200),
    ]);

    $metrics = app(GoogleAdsKeywordService::class)->getHistoricalMetrics(['Business Consulting', 'business consultancy']);

    expect($metrics['business consulting']['avg_monthly_searches'])->toBe(720)
        ->and($metrics['business consulting']['competition'])->toBe('MEDIUM')
        ->and($metrics['business consulting']['competition_index'])->toBe(41)
        ->and($metrics['business consultancy']['avg_monthly_searches'])->toBe(720);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'generateKeywordHistoricalMetrics')
        && $request->hasHeader('Authorization', 'Bearer test-access-token')
        && $request->hasHeader('developer-token', 'test-developer-token')
        && $request->hasHeader('login-customer-id', '9876543210')
        && $request['geoTargetConstants'] === ['geoTargetConstants/2356']
        && $request['language'] === 'languageConstants/1022'
        && $request['keywordPlanNetwork'] === 'GOOGLE_SEARCH');
});

it('reports when Google Ads access is limited to test accounts', function () {
    Cache::flush();
    config([
        'services.google_ads.client_id' => 'test-client',
        'services.google_ads.client_secret' => 'test-secret',
        'services.google_ads.refresh_token' => 'test-refresh',
        'services.google_ads.developer_token' => 'test-developer-token',
        'services.google_ads.customer_id' => '123-456-7890',
        'services.google_ads.api_version' => 'v26',
        'services.google_ads.geo_target_constants' => ['geoTargetConstants/2356'],
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token'], 200),
        'https://googleads.googleapis.com/v26/customers/1234567890:generateKeywordHistoricalMetrics' => Http::response([
            'error' => [
                'details' => [[
                    'errors' => [[
                        'errorCode' => ['authorizationError' => 'CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION'],
                    ]],
                ]],
            ],
        ], 403),
    ]);

    $service = app(GoogleAdsKeywordService::class);
    expect($service->getHistoricalMetrics(['business consulting']))->toBe([])
        ->and($service->historicalMetricsStatus())->toBe('project_not_approved');
});
