<?php

use App\Exceptions\ProviderException;
use App\Services\AnalisisGeminiService;
use App\Services\CompetitorContentFetcher;
use App\Services\GoogleAnalyticsService;
use App\Services\SearchConsoleService;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('preserves ordinary prose and unrelated parameter names', function (string $text) {
    expect(ProviderException::sanitise($text))->toBe($text);
})->with([
    'secret destination', 'credential management', 'monkey=value',
    'secret destination=beach', 'credential management=enabled',
    'monkey=value&status=403', 'HTTP 429 RESOURCE_EXHAUSTED: retry after 30 seconds',
]);

it('keeps useful diagnostics alongside redacted credentials', function () {
    $safe = ProviderException::sanitise('HTTP 403 key=hidden-value&monkey=value; credential management; secret destination');
    expect($safe)->toContain('HTTP 403', 'monkey=value', 'credential management', 'secret destination')
        ->not->toContain('hidden-value');
});

it('preserves the original classified exception and retry metadata', function (int $status, string $body, string $classification, bool $retryable) {
    $response = new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response($status, [], $body.' api_key=hidden-value'));
    $failure = ProviderException::fromThrowable('openai', new \Illuminate\Http\Client\RequestException($response));
    expect(ProviderException::fromThrowable('pipeline', $failure))->toBe($failure)
        ->and($failure->provider)->toBe('openai')
        ->and($failure->httpStatus)->toBe($status)
        ->and($failure->errorCode)->toBe($classification)
        ->and($failure->isTransient())->toBe($retryable)
        ->and($failure->context()['retryable'])->toBe($retryable)
        ->and($failure->context()['http_status'])->toBe($status)
        ->and(json_encode($failure->context()))->not->toContain('hidden-value')
        ->and($failure->getPrevious())->toBeNull();
})->with([
    [401, 'Unauthorized', ProviderException::AUTHENTICATION_FAILED, false],
    [429, 'Quota exhausted', ProviderException::QUOTA_EXHAUSTED, false],
    [429, 'Rate limit reached', ProviderException::RATE_LIMITED, true],
    [503, 'Unavailable', ProviderException::PROVIDER_UNAVAILABLE, true],
]);

it('retains Gemini SDK status even when its message omits the HTTP code', function () {
    $failure = ProviderException::fromThrowable('gemini', new \Gemini\Exceptions\ErrorException([
        'code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'Request rejected; secret=hidden-value',
    ]));
    expect($failure->httpStatus)->toBe(403)
        ->and($failure->providerStatus)->toBe('PERMISSION_DENIED')
        ->and($failure->errorCode)->toBe(ProviderException::AUTHENTICATION_FAILED)
        ->and($failure->detail)->not->toContain('hidden-value');
});

it('sanitises directly constructed provider exceptions without losing classification', function () {
    $failure = new ProviderException('openai', ProviderException::RATE_LIMITED,
        'Retry later; token=hidden-value', 'secret=hidden-detail', 429);
    expect($failure->getMessage())->toContain('Retry later')->not->toContain('hidden-value')
        ->and($failure->detail)->not->toContain('hidden-detail')
        ->and($failure->isTransient())->toBeTrue();
});

it('sanitises provider and reference log paths', function (string $path, bool $transport) {
    $logs = [];
    foreach (['info', 'warning', 'error'] as $level) {
        Log::shouldReceive($level)->zeroOrMoreTimes()->andReturnUsing(function (...$args) use (&$logs) { $logs[] = $args; });
    }
    $secret = 'audit-private-value';
    Http::preventStrayRequests();
    Http::fake(function () use ($transport, $secret) {
        if ($transport) {
            throw new RuntimeException('Unauthorized; api_key='.$secret);
        }
        return Http::response(['error' => ['message' => 'Unauthorized; access_token='.$secret]], 403);
    });
    config(['services.google_ads.client_id' => 'test-id', 'services.google_ads.client_secret' => $secret,
        'services.google_analytics.refresh_token' => 'test-refresh', 'services.google_search_console.refresh_token' => 'test-refresh',
        'services.openai.key' => 'test-key']);
    match ($path) {
        'ga-token' => app(GoogleAnalyticsService::class)->getAccessToken(),
        'gsc-token' => app(SearchConsoleService::class)->getPerformance('https://example.com'),
        'reference' => app(CompetitorContentFetcher::class)->fetch('https://example.com?key='.$secret),
    };
    expect($logs)->not->toBeEmpty()
        ->and(json_encode($logs))->not->toContain($secret);
})->with(['ga-token', 'gsc-token', 'reference'])->with([false, true]);

it('sanitises Gemini helper exceptions', function () {
    Log::spy();
    $gemini = Mockery::mock();
    Gemini::swap($gemini);
    $gemini->shouldReceive('generativeModel')->once()->andThrow(new RuntimeException('Invalid API key: audit-private-value'));
    expect(fn () => app(AnalisisGeminiService::class)->callJson('test'))->toThrow(ProviderException::class);
    Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) =>
        !str_contains($message.json_encode($context), 'audit-private-value')
        && str_contains($message.json_encode($context), '[redacted]')
    )->once();
});

it('does not let Google property discovery propagate raw transport errors', function (string $provider) {
    Http::fake(fn () => throw new RuntimeException('Connection failed; token=audit-private-value'));
    Cache::put('gsc_access_token', 'fake-access-token');
    config(['services.google_ads.client_id' => 'id', 'services.google_ads.client_secret' => 'secret',
        'services.google_search_console.refresh_token' => 'refresh']);
    try {
        if ($provider === 'ga') {
            app(GoogleAnalyticsService::class)->resolveProperty('fake-access-token', 'https://example.com');
        } else {
            app(SearchConsoleService::class)->getPerformance('https://example.com');
        }
        $this->fail('Expected safe provider exception');
    } catch (ProviderException $e) {
        expect($e->provider)->toBe('google')
            ->and($e->errorCode)->toBe(ProviderException::PROVIDER_UNAVAILABLE)
            ->and($e->detail)->not->toContain('audit-private-value')
            ->and($e->getPrevious())->toBeNull();
    }
})->with(['ga', 'gsc']);
