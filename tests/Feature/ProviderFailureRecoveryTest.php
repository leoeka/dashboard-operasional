<?php

use App\Exceptions\ProviderException;
use App\Http\Controllers\WebsiteBuilderController;
use App\Models\Client;
use App\Models\PipelineCheckpoint;
use App\Models\Project;
use App\Services\AnalisisGeminiService;
use App\Services\CompetitorDiscoveryService;
use App\Services\CompetitorContentFetcher;
use App\Services\GenerateMockupGptService;
use App\Services\PipelineCheckpointService;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('completes successful content writing and reuses its checkpoint', function () {
    config(['services.openai.key' => 'test-key']);
    Http::fake([
        'api.openai.com/v1/responses' => openAiStreamResponse(json_encode([
            'sitemap' => ['pages' => [['name' => 'Home', 'sections' => [
                ['type' => 'hero', 'headline' => 'Explore our services'],
            ]]]],
        ])),
    ]);

    $project = Project::create(['name' => 'Success', 'client_name' => 'Client', 'type' => 'Company Profile', 'code' => 'SUCCESS-1', 'status' => 'request', 'target_market' => '']);
    $checkpoints = app(PipelineCheckpointService::class);
    $work = fn () => app(GenerateMockupGptService::class)->writeWebsiteContent(
        $project, new Client(), ['target_market' => '', 'business_type' => 'Company Profile', 'topics' => [], 'from_team' => false]
    );

    $result = $checkpoints->remember($project, 'website_content', $work);

    expect($result['sitemap']['pages'][0]['sections'][0]['headline'])->toBe('Explore our services')
        ->and($checkpoints->completed($project, 'website_content')['payload'])->toBe($result)
        // The second call replays the stored copy: GPT is not paid twice.
        ->and($checkpoints->remember($project, 'website_content', $work))->toBe($result)
        ->and($checkpoints->failure($project))->toBeNull();
});

it('keeps a failed content stage retryable instead of caching a local fallback', function () {
    Log::spy();
    $openAiSecret = 'sk-proj-TEST12345678901234567890';
    $geminiSecret = 'AIzaSyTEST12345678901234567890';
    config(['services.openai.key' => 'test-key']);
    Http::fake([
        'api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'Incorrect API key provided: '.$openAiSecret]], 401),
    ]);

    // Gemini down costs the run a weaker brief, nothing more: the target-market
    // stage fails soft, so the pipeline still reaches the writer — and stops
    // there, loudly, because the writer is the stage that cannot be improvised.
    $gemini = Mockery::mock();
    Gemini::swap($gemini);
    $gemini->shouldReceive('generativeModel')->andThrow(new RuntimeException('Invalid API key: '.$geminiSecret));

    $client = Client::create(['company_name' => 'Recovery Client']);
    $project = Project::create(['client_id' => $client->id, 'name' => 'Recovery', 'client_name' => 'Recovery Client', 'type' => 'Company Profile', 'code' => 'RECOVERY-1', 'status' => 'request', 'target_market' => '']);
    $checkpoints = app(PipelineCheckpointService::class);
    $aiService = Mockery::mock(GenerateMockupGptService::class)->makePartial();
    $aiService->shouldNotReceive('generateMockupBlueprints');
    $aiService->shouldNotReceive('renderCandidates');

    for ($attempt = 0; $attempt < 2; $attempt++) {
        expect(fn () => app(WebsiteBuilderController::class)->runProposalGeneration(
            $project, $aiService, app(AnalisisGeminiService::class),
            app(CompetitorDiscoveryService::class), app(CompetitorContentFetcher::class), $checkpoints
        ))->toThrow(ProviderException::class);

        expect($checkpoints->completed($project, 'target_market'))->not->toBeNull()
            ->and($checkpoints->completed($project, 'website_content'))->toBeNull()
            ->and($checkpoints->nextStage($project))->toBe('website_content')
            ->and($checkpoints->failure($project)->error_code)->toBe(ProviderException::AUTHENTICATION_FAILED)
            ->and($checkpoints->failure($project)->status)->toBe(PipelineCheckpoint::FAILED)
            ->and($checkpoints->failure($project)->payload)->toBeNull()
            ->and($checkpoints->failure($project)->error_detail)->not->toContain($openAiSecret);
    }

    Log::shouldNotHaveReceived('error', function ($message, $context = []) use ($openAiSecret, $geminiSecret) {
        return str_contains($message.json_encode($context), $openAiSecret)
            || str_contains($message.json_encode($context), $geminiSecret);
    });
});

it('takes the words of the website from GPT and only the audience from Gemini', function () {
    config(['services.openai.key' => 'test-key']);

    // ->once() is the point of this test: Gemini is asked for the audience and
    // never again, because writing the site is no longer its job.
    $gemini = Mockery::mock();
    Gemini::swap($gemini);
    $model = Mockery::mock();
    $gemini->shouldReceive('generativeModel')->once()->andReturn($model);
    $model->shouldReceive('withGenerationConfig')->once()->andReturnSelf();
    $model->shouldReceive('generateContent')->once()->andReturn(new class {
        public function text(): string
        {
            return json_encode([
                'target_market' => 'Pecinta kopi spesialitas di Bandung',
                'business_type' => 'specialty coffee shop',
                'topics' => ['kopi bandung'],
            ]);
        }
    });

    Http::fake([
        'api.openai.com/v1/responses' => openAiStreamResponse(json_encode([
            'sitemap' => ['pages' => [['name' => 'Home', 'sections' => [
                ['type' => 'hero', 'headline' => 'Kopi dari tanah Sunda'],
            ]]]],
        ])),
    ]);

    $client = Client::create(['company_name' => 'Kopi Sunda']);
    $project = Project::create([
        'client_id' => $client->id,
        'name' => 'Website Kopi Sunda',
        'client_name' => 'Kopi Sunda',
        'type' => 'Coffee Shop',
        'code' => 'KS-1001',
        'status' => 'request',
        'target_market' => '',
        'description' => 'A workshop roastery selling single-origin beans to home brewers in Bandung.',
    ]);

    $market = app(AnalisisGeminiService::class)->deriveTargetMarket($project, $client);
    $analysis = app(GenerateMockupGptService::class)->writeWebsiteContent($project, $client, $market);

    expect($market['target_market'])->toBe('Pecinta kopi spesialitas di Bandung')
        ->and($market['from_team'])->toBeFalse()
        ->and($analysis['sitemap']['pages'][0]['sections'][0]['headline'])->toBe('Kopi dari tanah Sunda');
});

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
