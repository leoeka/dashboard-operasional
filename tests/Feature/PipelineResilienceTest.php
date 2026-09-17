<?php

use App\Exceptions\ProviderException;
use App\Models\PipelineCheckpoint;
use App\Models\Project;
use App\Models\Proposal;
use App\Services\GenerateMockupGptService;
use App\Services\PipelineCheckpointService;
use App\Services\ScreenshotService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * What happens when a provider says no.
 *
 * A quota running out used to throw the whole run away: the next attempt began
 * again at the business analysis, paying Gemini and the designer a second time
 * to reach the same failed step. Worse, some failures were swallowed — a design
 * call that failed quietly substituted invented colours, so a client could be
 * shown a "finished" proposal no stage had really produced.
 *
 * The rule now is: stop where it broke, keep everything already earned, say
 * exactly what went wrong, and resume at the failed stage on retry.
 */
class StubScreenshotService extends ScreenshotService
{
    public int $calls = 0;

    public function captureHtml(string $html, string $relativePath): ?string
    {
        $this->calls++;

        return $relativePath;
    }
}

function resilienceProject(): Project
{
    return Project::create([
        'name' => 'Website Kopi Nusantara',
        'client_name' => 'Kopi Nusantara',
        'code' => 'KN-7001',
        'type' => 'Coffee Shop',
        'status' => 'request',
        'target_market' => '',
    ]);
}

function resilienceAnalysis(): array
{
    return [
        'business_analysis' => ['value_proposition' => 'Kopi single origin.'],
        'sitemap' => [
            'website_concept' => 'Kopi single origin.',
            'global_cta' => 'Pesan',
            'pages' => [[
                'name' => 'Home',
                'sections' => [
                    ['name' => 'Hero', 'headline' => 'Kopi Nusantara', 'description' => 'Dari petani lokal.'],
                    ['name' => 'Kenapa', 'headline' => 'Kenapa Kami', 'items' => [['title' => 'Segar'], ['title' => 'Adil']]],
                    ['name' => 'Menu', 'headline' => 'Menu', 'items' => [['title' => 'Gayo'], ['title' => 'Toraja']]],
                ],
            ]],
        ],
    ];
}

function designerResponse(string $hero = 'split'): array
{
    return ['choices' => [['message' => ['content' => json_encode([
        'style' => 'Hangat',
        'primary_color' => '#1F3A5F',
        'secondary_color' => '#F8FAFC',
        'accent_color' => '#C87941',
        'font_heading' => 'Playfair Display',
        'font_body' => 'Inter',
        'sections' => [['role' => 'hero', 'composition' => $hero]],
    ])]]]];
}

beforeEach(function () {
    Storage::fake('public');
    config(['services.openai.key' => 'test-key', 'services.openai.mockup_candidate_count' => 2]);
});

it('classifies each way a provider can say no', function () {
    $classify = fn (int $status, string $body) => ProviderException::fromResponse(
        'openai',
        new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response($status, [], $body))
    )->errorCode;

    expect($classify(401, 'invalid api key'))->toBe(ProviderException::AUTHENTICATION_FAILED)
        ->and($classify(403, 'forbidden'))->toBe(ProviderException::AUTHENTICATION_FAILED)
        // 429 means two different things; only the body tells them apart.
        ->and($classify(429, 'Rate limit reached for requests'))->toBe(ProviderException::RATE_LIMITED)
        ->and($classify(429, 'You exceeded your current quota'))->toBe(ProviderException::QUOTA_EXHAUSTED)
        ->and($classify(400, 'Your credit balance is too low'))->toBe(ProviderException::QUOTA_EXHAUSTED)
        ->and($classify(402, 'payment required'))->toBe(ProviderException::QUOTA_EXHAUSTED)
        ->and($classify(503, 'overloaded'))->toBe(ProviderException::PROVIDER_UNAVAILABLE)
        ->and($classify(418, 'teapot'))->toBe(ProviderException::INVALID_RESPONSE)
        ->and(ProviderException::missingKey('openai')->errorCode)->toBe(ProviderException::MISSING_API_KEY);
});

it('marks rate limits and outages as worth retrying, and exhausted quota as not', function () {
    $transient = fn (string $code) => (new ProviderException('openai', $code))->isTransient();

    expect($transient(ProviderException::RATE_LIMITED))->toBeTrue()
        ->and($transient(ProviderException::PROVIDER_UNAVAILABLE))->toBeTrue()
        ->and($transient(ProviderException::QUOTA_EXHAUSTED))->toBeFalse()
        ->and($transient(ProviderException::MISSING_API_KEY))->toBeFalse();
});

it('never lets a credential reach a log or the database', function () {
    $leaky = 'Incorrect API key provided: sk-proj-ABCDEF1234567890. Also AIzaSyEXAMPLE123456 and Authorization: Bearer sk-ant-XYZ987654321';
    $clean = ProviderException::sanitise($leaky);

    expect($clean)->not->toContain('sk-proj-ABCDEF1234567890')
        ->not->toContain('AIzaSyEXAMPLE123456')
        ->not->toContain('sk-ant-XYZ987654321')
        ->toContain('[redacted]');
});

it('stops loudly instead of inventing a design when the designer quota is gone', function () {
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['error' => ['message' => 'You exceeded your current quota']], 429)]);

    $service = app(GenerateMockupGptService::class);
    $project = resilienceProject();

    // The old behaviour substituted fallback colours here and carried on, which
    // is how a "finished" proposal could contain a design nobody chose.
    expect(fn () => $service->generateMockupBlueprints($project, resilienceAnalysis()))
        ->toThrow(ProviderException::class);

    try {
        $service->generateMockupBlueprints($project, resilienceAnalysis());
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderException::QUOTA_EXHAUSTED)
            ->and($e->provider)->toBe('openai');
    }
});

it('stops when the designer key is missing rather than designing something itself', function () {
    config(['services.openai.key' => null]);

    try {
        app(GenerateMockupGptService::class)->generateMockupBlueprints(resilienceProject(), resilienceAnalysis());
        $this->fail('Expected a ProviderException.');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderException::MISSING_API_KEY)
            ->and($e->getMessage())->toContain('.env');
    }
});

it('reports the image quota as the reason, not a vague incomplete-candidate error', function () {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response(designerResponse('split')),
        'api.openai.com/v1/images/generations' => Http::response(['error' => ['message' => 'You exceeded your current quota']], 429),
    ]);
    app()->instance(ScreenshotService::class, new StubScreenshotService());

    $service = app(GenerateMockupGptService::class);
    $project = resilienceProject();
    $blueprints = $service->generateMockupBlueprints($project, resilienceAnalysis());

    try {
        $service->renderCandidates($project, $blueprints);
        $this->fail('Expected a ProviderException.');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderException::QUOTA_EXHAUSTED)
            ->and($e->getMessage())->toContain('home.hero');
    }
});

it('keeps finished stages and resumes at the one that failed', function () {
    $checkpoints = app(PipelineCheckpointService::class);
    $project = resilienceProject();

    $checkpoints->recordSuccess($project, 'competitor_research', []);
    $checkpoints->recordSuccess($project, 'gemini_analysis', resilienceAnalysis());
    $checkpoints->recordSuccess($project, 'design_blueprint', [['design' => [], 'pages' => []]]);
    $checkpoints->recordFailure($project, 'mockup_assets', new ProviderException('openai', ProviderException::QUOTA_EXHAUSTED));

    expect($checkpoints->nextStage($project))->toBe('mockup_assets')
        ->and($checkpoints->completed($project, 'gemini_analysis')['payload']['sitemap']['global_cta'])->toBe('Pesan')
        ->and($checkpoints->failure($project)->error_code)->toBe(ProviderException::QUOTA_EXHAUSTED);
});

it('replays a completed stage instead of running it a second time', function () {
    $checkpoints = app(PipelineCheckpointService::class);
    $project = resilienceProject();
    $runs = 0;

    $work = function () use (&$runs) {
        $runs++;

        return ['analysis' => 'done'];
    };

    expect($checkpoints->remember($project, 'gemini_analysis', $work))->toBe(['analysis' => 'done'])
        ->and($checkpoints->remember($project, 'gemini_analysis', $work))->toBe(['analysis' => 'done'])
        // The second call replayed the stored result — Gemini was not paid twice.
        ->and($runs)->toBe(1);
});

it('records why a stage failed, with no secret in the record', function () {
    $checkpoints = app(PipelineCheckpointService::class);
    $project = resilienceProject();

    $failed = fn () => throw ProviderException::fromResponse(
        'openai',
        new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response(429, [], 'quota exceeded for key sk-proj-SECRET123456')
        )
    );

    expect(fn () => $checkpoints->remember($project, 'mockup_assets', $failed))->toThrow(ProviderException::class);

    $record = PipelineCheckpoint::where('stage', 'mockup_assets')->first();

    expect($record->status)->toBe(PipelineCheckpoint::FAILED)
        ->and($record->error_code)->toBe(ProviderException::QUOTA_EXHAUSTED)
        ->and($record->payload)->toBeNull()
        ->and($record->error_detail)->not->toContain('sk-proj-SECRET123456')
        ->and($record->error_detail)->toContain('[redacted]');
});

it('resumes asset generation against the already-frozen blueprints once the quota is back', function () {
    app()->instance(ScreenshotService::class, $screenshots = new StubScreenshotService());
    $checkpoints = app(PipelineCheckpointService::class);
    $service = app(GenerateMockupGptService::class);
    $project = resilienceProject();

    // One mutable fake for the whole test: a second Http::fake() would not
    // replace the first, the earlier stub keeps matching.
    $imagesHealthy = false;
    $designerCalls = 0;

    Http::fake([
        'api.openai.com/v1/chat/completions' => function () use (&$designerCalls) {
            $designerCalls++;

            return Http::response(designerResponse('split'));
        },
        'api.openai.com/v1/images/generations' => function () use (&$imagesHealthy) {
            return $imagesHealthy
                ? Http::response(['data' => [['b64_json' => base64_encode('PHOTO')]]])
                : Http::response(['error' => ['message' => 'You exceeded your current quota']], 429);
        },
    ]);

    // The stages before this one already succeeded in a real run.
    $checkpoints->recordSuccess($project, 'competitor_research', []);
    $checkpoints->recordSuccess($project, 'gemini_analysis', resilienceAnalysis());

    // Attempt 1: the designs succeed, the photographs do not.
    $blueprints = $checkpoints->remember($project, 'design_blueprint', fn () => $service->generateMockupBlueprints($project, resilienceAnalysis()));
    $frozenHero = $blueprints[0]['pages'][0]['sections'][0]['composition'];
    $designerCallsAfterFirst = $designerCalls;

    expect(fn () => $checkpoints->remember($project, 'mockup_assets', fn () => $service->renderCandidates($project, $blueprints)))
        ->toThrow(ProviderException::class);

    // Nothing was screenshotted: a design missing a photo it requires is not
    // shown to anyone.
    expect($screenshots->calls)->toBe(0)
        ->and($checkpoints->nextStage($project))->toBe('mockup_assets');

    // Attempt 2: the provider is healthy again.
    $imagesHealthy = true;

    $replayed = $checkpoints->remember($project, 'design_blueprint', fn () => $service->generateMockupBlueprints($project, resilienceAnalysis()));
    $candidates = $checkpoints->remember($project, 'mockup_assets', fn () => $service->renderCandidates($project, $replayed));

    expect($replayed[0]['pages'][0]['sections'][0]['composition'])->toBe($frozenHero)
        ->and($candidates)->not->toBeEmpty()
        ->and($screenshots->calls)->toBeGreaterThan(0)
        ->and($checkpoints->nextStage($project))->toBe('proposal_document');

    // The designer was never asked again — the frozen designs were replayed
    // from the checkpoint, so the retry cost only the photographs.
    expect($designerCalls)->toBe($designerCallsAfterFirst);
});

it('writes no proposal at all when a required AI stage fails', function () {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response(designerResponse('split')),
        'api.openai.com/v1/images/generations' => Http::response(['error' => ['message' => 'quota']], 429),
    ]);
    app()->instance(ScreenshotService::class, new StubScreenshotService());

    $checkpoints = app(PipelineCheckpointService::class);
    $service = app(GenerateMockupGptService::class);
    $project = resilienceProject();

    $blueprints = $checkpoints->remember($project, 'design_blueprint', fn () => $service->generateMockupBlueprints($project, resilienceAnalysis()));

    try {
        $checkpoints->remember($project, 'mockup_assets', fn () => $service->renderCandidates($project, $blueprints));
    } catch (ProviderException) {
        // expected
    }

    // The proposal is written only after every required stage has succeeded, so
    // there is nothing half-finished for a client to be shown.
    expect(Proposal::where('project_id', $project->id)->exists())->toBeFalse()
        ->and($checkpoints->completed($project, 'proposal_document'))->toBeNull()
        ->and($project->fresh()->status)->toBe('request');
});

it('does not duplicate assets or screenshots when the same stage runs twice', function () {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response(designerResponse('split')),
        'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('PHOTO')]]]),
    ]);
    app()->instance(ScreenshotService::class, new StubScreenshotService());

    $service = app(GenerateMockupGptService::class);
    $project = resilienceProject();
    $blueprints = $service->generateMockupBlueprints($project, resilienceAnalysis());

    $service->renderCandidates($project, $blueprints);
    $afterFirst = Storage::disk('public')->allFiles('mockup-assets');

    $service->renderCandidates($project, $blueprints);
    $afterSecond = Storage::disk('public')->allFiles('mockup-assets');

    // Deterministic paths, so a repeat overwrites rather than accumulating.
    expect($afterSecond)->toBe($afterFirst);
});

it('classifies a Claude billing failure the same way', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Your credit balance is too low']], 400)]);

    try {
        app(\App\Services\ClaudeWordPressBuilderService::class)->build(resilienceProject(), ['mockup' => [], 'assets' => []]);
        $this->fail('Expected a ProviderException.');
    } catch (ProviderException $e) {
        expect($e->provider)->toBe('anthropic')
            ->and($e->errorCode)->toBe(ProviderException::QUOTA_EXHAUSTED)
            ->and($e->getMessage())->toContain('Claude');
    }
});

it('leaves no bundle record behind when the WordPress build fails', function () {
    config(['services.anthropic.key' => null]);
    $project = resilienceProject();

    Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => 'approved',
        'ai_reasoning' => json_encode(['mockup' => ['pages' => []], 'analysis' => []]),
    ]);

    expect(fn () => app(\App\Services\BundleBuilderService::class)->build($project->fresh()))
        ->toThrow(ProviderException::class);

    expect(\App\Models\ProjectBundle::where('project_id', $project->id)->exists())->toBeFalse();
});
