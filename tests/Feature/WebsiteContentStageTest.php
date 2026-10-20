<?php

use App\Http\Controllers\WebsiteBuilderController;
use App\Models\Client;
use App\Models\PipelineCheckpoint;
use App\Models\Project;
use App\Models\Proposal;
use App\Services\AnalisisGeminiService;
use App\Services\CompetitorContentFetcher;
use App\Services\CompetitorDiscoveryService;
use App\Services\GenerateMockupGptService;
use App\Services\PipelineCheckpointService;
use App\Services\ScreenshotService;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Who writes a website.
 *
 * The team files a request, one model works out who the site is for, and a
 * second model writes the site itself. The order matters more than it looks:
 * while Gemini authored the copy, a Gemini outage stopped a proposal that the
 * team could otherwise have finished, and the designer was choosing paint over
 * words nobody had approved.
 *
 * These run the real pipeline so the split is checked where it is wired, not
 * one service at a time.
 */
class StageScreenshotService extends ScreenshotService
{
    public function captureHtml(string $html, string $relativePath): ?string
    {
        return $relativePath;
    }
}

function stageProject(array $attributes = []): Project
{
    $client = Client::create(['company_name' => 'Kopi Sunda', 'address' => 'Bandung']);

    return Project::create(array_merge([
        'client_id' => $client->id,
        'name' => 'Website Kopi Sunda',
        'client_name' => 'Kopi Sunda',
        'code' => 'KS-'.random_int(1000, 9999),
        'type' => 'Coffee Shop',
        'status' => 'request',
        'target_market' => '',
        'description' => 'A roastery workshop in Bandung selling single-origin beans to people who brew at home.',
    ], $attributes))->load('client');
}

/** What the audience reader answers: the market, and how to search for it. */
function geminiAudience(): object
{
    return new class {
        public function text(): string
        {
            return json_encode([
                'target_market' => 'Pecinta kopi spesialitas di Bandung',
                'business_type' => 'specialty coffee shop',
                'topics' => ['kopi spesialitas bandung'],
            ]);
        }
    };
}

/** The whole website as GPT writes it: analysis, sitemap, and the copy itself. */
function gptContent(): string
{
    return json_encode([
        'language' => 'id',
        'business_analysis' => ['value_proposition' => 'Kopi single origin disangrai di Bandung.'],
        'sitemap' => [
            'website_concept' => 'Kopi single origin dari Bandung.',
            'global_cta' => 'Pesan',
            'pages' => [[
                'name' => 'Home',
                'sections' => [
                    ['name' => 'Hero', 'headline' => 'Kopi dari tanah Sunda', 'description' => 'Disangrai tiap minggu.'],
                    ['name' => 'Kenapa', 'headline' => 'Kenapa Kami', 'items' => [['title' => 'Segar'], ['title' => 'Adil']]],
                    ['name' => 'Menu', 'headline' => 'Daftar Biji', 'items' => [['title' => 'Gayo'], ['title' => 'Toraja']]],
                ],
            ]],
        ],
    ]);
}

function stageGemini(int $expectedCalls = 1): void
{
    $gemini = Mockery::mock();
    Gemini::swap($gemini);
    $model = Mockery::mock();
    $gemini->shouldReceive('generativeModel')->times($expectedCalls)->andReturn($model);
    $model->shouldReceive('withGenerationConfig')->times($expectedCalls)->andReturnSelf();
    $model->shouldReceive('generateContent')->times($expectedCalls)->andReturn(geminiAudience());
}

function stageRun(Project $project): void
{
    app(WebsiteBuilderController::class)->runProposalGeneration(
        $project,
        app(GenerateMockupGptService::class),
        app(AnalisisGeminiService::class),
        app(CompetitorDiscoveryService::class),
        app(CompetitorContentFetcher::class),
        app(PipelineCheckpointService::class)
    );
}

/** The designer's answer: paint for one option, never a word of its own. */
function designerTokens(): array
{
    return [
        'style' => 'Hangat',
        'primary_color' => '#1F3A5F',
        'secondary_color' => '#F8FAFC',
        'accent_color' => '#C87941',
        'font_heading' => 'Playfair Display',
        'font_body' => 'Inter',
        'sections' => [['role' => 'hero', 'composition' => 'split']],
    ];
}

beforeEach(function () {
    Storage::fake('public');
    config([
        'services.openai.key' => 'test-key',
        'services.openai.mockup_candidate_count' => 1,
    ]);
    app()->instance(ScreenshotService::class, new StageScreenshotService());

    // One fake for the whole test: a second Http::fake() would not replace the
    // first, so a test that needs the writer to answer differently changes the
    // body it streams instead.
    $this->writtenContent = gptContent();

    Http::fake([
        'api.openai.com/v1/responses' => fn () => openAiStreamResponse($this->writtenContent),
        'api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode(designerTokens())]]],
        ]),
        'api.openai.com/v1/images/generations' => Http::response([
            'data' => [['b64_json' => base64_encode('PHOTO')]],
        ]),
        // No Places key in tests, so this only guards against a stray request.
        'places.googleapis.com/*' => Http::response(['places' => []]),
    ]);
});

it('writes the site with GPT after Gemini has read out its audience', function () {
    stageGemini();
    $project = stageProject();

    stageRun($project);

    // One Gemini call for the whole run: the audience. Every word of the site
    // came from the writer instead.
    $checkpoints = app(PipelineCheckpointService::class);
    expect(PipelineCheckpoint::where('project_id', $project->id)->where('status', PipelineCheckpoint::COMPLETED)->pluck('stage')->sort()->values()->all())
        ->toBe(collect(PipelineCheckpointService::STAGES)->sort()->values()->all());

    $mockup = json_decode(Proposal::where('project_id', $project->id)->first()->ai_reasoning, true)['mockup'];

    expect($mockup['pages'][0]['sections'][0]['headline'])->toBe('Kopi dari tanah Sunda')
        ->and($mockup['design']['primary_color'])->toBe('#1F3A5F')
        ->and($checkpoints->nextStage($project->fresh()))->toBeNull();
});

it('records the audience it read out of the story onto a project that had none', function () {
    stageGemini();
    $project = stageProject();

    stageRun($project);

    expect($project->fresh()->target_market)->toBe('Pecinta kopi spesialitas di Bandung');
});

it('never overwrites the audience the team already typed', function () {
    stageGemini();
    $project = stageProject(['target_market' => 'Wisatawan Australia yang berlibur ke Bali']);

    stageRun($project);

    $stored = app(PipelineCheckpointService::class)->completed($project, 'target_market')['payload'];

    expect($project->fresh()->target_market)->toBe('Wisatawan Australia yang berlibur ke Bali')
        // The writer is told this one came from the client, not from a guess.
        ->and($stored['from_team'])->toBeTrue();
});

it('stops rather than showing a proposal whose words no model wrote', function () {
    stageGemini();
    // An answer with no sitemap in it: the writer failed, so the run must stop
    // rather than ship a page of locally-invented copy in the client's name.
    $this->writtenContent = json_encode(['business_analysis' => ['value_proposition' => 'Kopi.']]);
    $project = stageProject();

    expect(fn () => stageRun($project))->toThrow(\App\Exceptions\ProviderException::class);

    expect(Proposal::where('project_id', $project->id)->exists())->toBeFalse()
        ->and(app(PipelineCheckpointService::class)->nextStage($project->fresh()))->toBe('website_content');
});

it('asks the writer for a capped answer, because the cap is what one call bills', function () {
    stageGemini();
    stageRun(stageProject());

    $writer = collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0])
        ->first(fn ($request) => str_contains($request->url(), 'api.openai.com/v1/responses'));

    expect($writer)->not->toBeNull()
        // gpt-5.6 charges per output token, so this number is a spending limit,
        // not a length preference: at the official $30/M it caps one call at
        // ~$0,49 instead of the ~$0,98 the old 32.768 allowed.
        ->and(json_decode($writer->body(), true)['max_output_tokens'])->toBe(16384);
});
