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

it('completes successful Gemini analysis and reuses its checkpoint', function () {
    config(['services.proposal_ai_enabled' => true]);
    $gemini = Mockery::mock();
    Gemini::swap($gemini);
    $model = Mockery::mock();
    $gemini->shouldReceive('generativeModel')->once()->andReturn($model);
    $model->shouldReceive('withGenerationConfig')->once()->andReturnSelf();
    $model->shouldReceive('generateContent')->once()->andReturn(new class {
        public function text(): string
        {
            return json_encode(['sitemap' => ['pages' => [['name' => 'Home', 'sections' => [
                ['type' => 'hero', 'headline' => 'Explore our services'],
            ]]]]]);
        }
    });
    $project = Project::create(['name' => 'Success', 'client_name' => 'Client', 'type' => 'Company Profile', 'code' => 'SUCCESS-1', 'status' => 'request']);
    $checkpoints = app(PipelineCheckpointService::class);
    $work = fn () => app(AnalisisGeminiService::class)->analyzeProject($project, new Client());
    $result = $checkpoints->remember($project, 'gemini_analysis', $work);

    expect($result['sitemap']['pages'][0]['sections'][0]['headline'])->toBe('Explore our services')
        ->and($checkpoints->completed($project, 'gemini_analysis')['payload'])->toBe($result)
        ->and($checkpoints->remember($project, 'gemini_analysis', $work))->toBe($result)
        ->and($checkpoints->failure($project))->toBeNull();
});

it('keeps failed Gemini analysis retryable instead of caching local fallback', function () {
    config(['services.proposal_ai_enabled' => true]);
    Log::spy();
    $secret = 'AIzaSyTEST12345678901234567890';
    $gemini = Mockery::mock();
    Gemini::swap($gemini);
    $gemini->shouldReceive('generativeModel')->twice()
        ->andThrow(new RuntimeException('Invalid API key: '.$secret));

    $client = Client::create(['company_name' => 'Recovery Client']);
    $project = Project::create(['client_id' => $client->id, 'name' => 'Recovery', 'client_name' => 'Recovery Client', 'type' => 'Company Profile', 'code' => 'RECOVERY-1', 'status' => 'request']);
    $checkpoints = app(PipelineCheckpointService::class);
    $checkpoints->recordSuccess($project, 'competitor_research', []);
    $designer = Mockery::mock(GenerateMockupGptService::class);
    $designer->shouldNotReceive('generateMockupBlueprints');
    $designer->shouldNotReceive('renderCandidates');

    for ($attempt = 0; $attempt < 2; $attempt++) {
        expect(fn () => app(WebsiteBuilderController::class)->runProposalGeneration(
            $project, $designer, app(AnalisisGeminiService::class),
            app(CompetitorDiscoveryService::class), app(CompetitorContentFetcher::class), $checkpoints
        ))->toThrow(ProviderException::class);

        expect($checkpoints->completed($project, 'gemini_analysis'))->toBeNull()
            ->and($checkpoints->nextStage($project))->toBe('gemini_analysis')
            ->and($checkpoints->failure($project)->error_code)->toBe(ProviderException::AUTHENTICATION_FAILED)
            ->and($checkpoints->failure($project)->status)->toBe(PipelineCheckpoint::FAILED)
            ->and($checkpoints->failure($project)->payload)->toBeNull()
            ->and($checkpoints->failure($project)->error_detail)->not->toContain($secret);
    }

    Log::shouldNotHaveReceived('error', function ($message, $context = []) use ($secret) {
        return str_contains($message.json_encode($context), $secret);
    });
});

it('retains local analysis when AI is explicitly disabled', function () {
    config(['services.proposal_ai_enabled' => false]);
    $gemini = Mockery::mock();
    Gemini::swap($gemini);
    $gemini->shouldReceive('generativeModel')->never();

    $analysis = app(AnalisisGeminiService::class)->analyzeProject(
        new Project(['name' => 'Local Preview']), new Client()
    );

    expect($analysis['sitemap']['pages'][0]['sections'][0]['headline'])->toBe('Local Preview');
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
