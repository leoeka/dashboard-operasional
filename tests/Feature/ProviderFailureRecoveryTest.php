<?php

use App\Services\CompetitorDiscoveryService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('redacts Places credentials from failed responses and transport errors', function (bool $transport, string $detail, string $secret) {
    config(['services.google_places.api_key' => $secret]);
    Log::spy();
    Http::fake(function () use ($transport, $detail) {
        if ($transport) {
            throw new RuntimeException('Request failed with '.$detail);
        }

        return Http::response('CONSUMER_SUSPENDED '.$detail, 403);
    });

    expect(app(CompetitorDiscoveryService::class)->findCompetitors('Coffee'))->toBe([]);

    Log::shouldHaveReceived($transport ? 'error' : 'warning')->once()
        ->withArgs(function ($message, $context = []) use ($secret) {
            $logged = $message.json_encode($context);

            return !str_contains($logged, $secret) && str_contains($logged, '[redacted]');
        });
})->with([false, true])->with([
    'Google key' => ['AIzaSyTEST12345678901234567890', 'AIzaSyTEST12345678901234567890'],
    'query key' => ['https://example.test?key=plain-test-value&other=ok', 'plain-test-value'],
    'api_key' => ['api_key=plain-test-value', 'plain-test-value'],
    'apikey' => ['apikey=plain-test-value', 'plain-test-value'],
    'Bearer header' => ['Authorization: Bearer plain-test-value', 'plain-test-value'],
    'Bearer without colon' => ['Authorization Bearer plain-test-value', 'plain-test-value'],
    'access token' => ['access token=plain-test-value', 'plain-test-value'],
    'JSON access token' => ['{"access_token":"plain-test-value"}', 'plain-test-value'],
    'secret with spaces' => ['{"secret":"plain test value"}', 'plain test value'],
    'credential' => ["credential='plain test value'", 'plain test value'],
    'JSON api key' => ['{"api_key":"plain-test-value"}', 'plain-test-value'],
    'client secret' => ['client_secret=plain-test-value', 'plain-test-value'],
]);
