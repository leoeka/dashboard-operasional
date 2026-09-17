<?php

use App\Exceptions\ProviderException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\Proposal;
use App\Services\GenerateMockupGptService;
use App\Services\MockupAssetService;
use App\Services\PipelineCheckpointService;
use App\Services\ScreenshotService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Cost idempotency, as distinct from file idempotency.
 *
 * Deterministic asset paths already stopped a retry producing duplicate FILES.
 * They did nothing about duplicate paid REQUESTS: a run that got the hero and
 * one card before the quota ran out would, on retry, ask OpenAI to draw all four
 * again. These tests hold the stricter rule — a photograph already sitting on
 * disk for the same slot and the same subject is reused, never re-bought — and
 * the matching rule for the proposal stage, which must not re-render its PDF or
 * re-log its activity when the job runs again.
 */
class CountingScreenshotService extends ScreenshotService
{
    public int $calls = 0;

    public function captureHtml(string $html, string $relativePath): ?string
    {
        $this->calls++;

        return $relativePath;
    }
}

function idempotencyProject(): Project
{
    return Project::create([
        'name' => 'Website Kopi Nusantara',
        'client_name' => 'Kopi Nusantara',
        'code' => 'KN-8001',
        'type' => 'Coffee Shop',
        'status' => 'request',
    ]);
}

function idempotencyBlueprint(): array
{
    return [
        'design' => ['primary_color' => '#1F3A5F', 'accent_color' => '#C87941'],
        'pages' => [[
            'name' => 'Home',
            'sections' => [
                ['name' => 'Hero', 'headline' => 'Kopi Nusantara', 'description' => 'Dari petani lokal.', 'composition' => 'split'],
                ['name' => 'Kenapa', 'headline' => 'Kenapa Kami', 'items' => [['title' => 'Segar'], ['title' => 'Adil']]],
                ['name' => 'Menu', 'headline' => 'Menu', 'composition' => 'standard_cards', 'image_required' => true, 'items' => [
                    ['title' => 'Gayo'], ['title' => 'Toraja'], ['title' => 'Kintamani'],
                ]],
            ],
        ]],
    ];
}

/**
 * Installs ONE image-API fake for the whole test and returns a handle to it.
 *
 * Registered once on purpose: a second Http::fake() does not replace the first,
 * the earlier stub keeps matching. So the quota is raised through the handle
 * instead — $api['budget'] is how many more photographs the provider will
 * serve, and $api['asked'] records every subject it was actually asked to draw,
 * which is what these tests measure.
 */
function imageApi(int $budget): ArrayObject
{
    $api = new ArrayObject(['budget' => $budget, 'asked' => []]);

    Http::fake(['api.openai.com/v1/images/generations' => function ($request) use ($api) {
        preg_match('/Subject: "(.*?)"/', (string) ($request->data()['prompt'] ?? ''), $m);
        $subject = $m[1] ?? 'unknown';

        $asked = $api['asked'];
        $asked[] = $subject;
        $api['asked'] = $asked;

        if ($api['budget'] > 0) {
            $api['budget'] = $api['budget'] - 1;

            return Http::response(['data' => [['b64_json' => base64_encode('PHOTO:' . $subject)]]]);
        }

        return Http::response(['error' => ['message' => 'You exceeded your current quota']], 429);
    }]);

    return $api;
}

/** Subjects requested since the last call, then resets the tally. */
function drainAsked(ArrayObject $api): array
{
    $asked = $api['asked'];
    $api['asked'] = [];

    return $asked;
}

beforeEach(function () {
    Storage::fake('public');
    config(['services.openai.key' => 'test-key']);
});

it('reuses photographs an earlier attempt already paid for', function () {
    $project = idempotencyProject();
    $assets = new MockupAssetService(new \App\Services\ElementorPageBuilderService());

    // Attempt 1: hero and the first card succeed, the rest hit the quota.
    $api = imageApi(2);
    $first = $assets->generateForCandidate($project, idempotencyBlueprint(), 1);

    expect($first['degraded'])->toBeTrue()
        ->and(drainAsked($api))->toHaveCount(4);

    $heroBytes = Storage::disk('public')->get('mockup-assets/kn-8001/candidate-1/hero.jpg');

    // Attempt 2: the provider is healthy again.
    $api['budget'] = 99;
    $second = $assets->generateForCandidate($project, idempotencyBlueprint(), 1);
    $secondAsked = drainAsked($api);

    expect($second['degraded'])->toBeFalse()
        // Only the slots that were still missing were bought.
        ->and($secondAsked)->toHaveCount(2)
        // Nothing already on disk was re-requested.
        ->and($secondAsked)->not->toContain('Kopi Nusantara')
        ->and($secondAsked)->not->toContain('Gayo');

    // And the bytes already on disk were left exactly as they were.
    expect(Storage::disk('public')->get('mockup-assets/kn-8001/candidate-1/hero.jpg'))->toBe($heroBytes);
});

it('asks for nothing at all when every photograph is already on disk', function () {
    $project = idempotencyProject();
    $assets = new MockupAssetService(new \App\Services\ElementorPageBuilderService());

    $api = imageApi(99);
    $assets->generateForCandidate($project, idempotencyBlueprint(), 1);
    expect(drainAsked($api))->toHaveCount(4);

    $result = $assets->generateForCandidate($project, idempotencyBlueprint(), 1);

    expect(drainAsked($api))->toBeEmpty()
        ->and($result['degraded'])->toBeFalse();
});

it('does not reuse a photograph drawn for different content', function () {
    $project = idempotencyProject();
    $assets = new MockupAssetService(new \App\Services\ElementorPageBuilderService());

    $api = imageApi(99);
    $assets->generateForCandidate($project, idempotencyBlueprint(), 1);
    drainAsked($api);

    // Same positions, different copy — the filenames are positional, so without
    // the subject check this would keep showing pictures of the old headlines.
    $edited = idempotencyBlueprint();
    $edited['pages'][0]['sections'][0]['headline'] = 'Kopi Arabika Terbaik';

    $assets->generateForCandidate($project, $edited, 1);

    expect(drainAsked($api))->toContain('Kopi Arabika Terbaik');
});

it('treats a partial set of options as an unfinished stage, not a smaller success', function () {
    app()->instance(ScreenshotService::class, new CountingScreenshotService());
    config(['services.openai.mockup_candidate_count' => 2]);

    $project = idempotencyProject();
    $service = app(GenerateMockupGptService::class);

    $blueprints = [idempotencyBlueprint(), idempotencyBlueprint()];
    $blueprints[1]['pages'][0]['sections'][0]['composition'] = 'asymmetric_split';

    // Enough photographs for the first option only.
    imageApi(4);

    try {
        $service->renderCandidates($project, $blueprints);
        test()->fail('Expected a ProviderException.');
    } catch (ProviderException $e) {
        expect($e->getMessage())->toContain('Baru 1 dari 2')
            ->toContain('tetap tersimpan');
    }

    // The completed option's photographs survive for the retry to reuse.
    expect(Storage::disk('public')->exists('mockup-assets/kn-8001/candidate-1/hero.jpg'))->toBeTrue();
});

it('completes once the missing option is filled in on retry, without redoing the finished one', function () {
    app()->instance(ScreenshotService::class, new CountingScreenshotService());
    config(['services.openai.mockup_candidate_count' => 2]);

    $project = idempotencyProject();
    $service = app(GenerateMockupGptService::class);

    $blueprints = [idempotencyBlueprint(), idempotencyBlueprint()];
    $blueprints[1]['pages'][0]['sections'][0]['composition'] = 'asymmetric_split';

    $api = imageApi(4);
    try {
        $service->renderCandidates($project, $blueprints);
    } catch (ProviderException) {
        // expected
    }
    $firstRoundRequests = count(drainAsked($api));

    $api['budget'] = 99;
    $candidates = $service->renderCandidates($project, $blueprints);
    $retryAsked = drainAsked($api);

    expect($candidates)->toHaveCount(2)
        // Option 1 was already complete, so only option 2's photographs were bought.
        ->and(count($retryAsked))->toBeLessThan($firstRoundRequests);

    foreach ($candidates as $candidate) {
        expect($candidate['degraded'])->toBeFalse()
            ->and($candidate['screenshot_path'])->not->toBeNull();
    }
});

it('replays a finished proposal instead of rendering the PDF again', function () {
    $checkpoints = app(PipelineCheckpointService::class);
    $project = idempotencyProject();
    $runs = 0;

    $work = function () use (&$runs, $project) {
        $runs++;
        Proposal::updateOrCreate(['project_id' => $project->id], [
            'client_name' => $project->client_name,
            'pdf_path' => 'proposals/x.pdf',
            'version' => 1,
            'status' => 'pending',
        ]);
        $project->logActivity('AI business analysis and website mockup blueprint generated');

        return ['pdf_path' => 'proposals/x.pdf'];
    };

    $checkpoints->remember($project, 'proposal_document', $work);
    $checkpoints->remember($project, 'proposal_document', $work);
    $checkpoints->remember($project, 'proposal_document', $work);

    expect($runs)->toBe(1)
        ->and(Proposal::where('project_id', $project->id)->count())->toBe(1)
        // The activity log is the giveaway for a silently repeated stage.
        ->and(ActivityLog::where('project_id', $project->id)->count())->toBe(1);
});

it('runs no stage twice when the whole pipeline is already finished', function () {
    $checkpoints = app(PipelineCheckpointService::class);
    $project = idempotencyProject();
    $executed = [];

    foreach (PipelineCheckpointService::STAGES as $stage) {
        $checkpoints->remember($project, $stage, function () use ($stage, &$executed) {
            $executed[] = $stage;

            return ['stage' => $stage];
        });
    }

    expect($executed)->toHaveCount(count(PipelineCheckpointService::STAGES))
        ->and($checkpoints->nextStage($project))->toBeNull();

    // Second full pass: everything replays, nothing runs.
    $executedAgain = [];
    foreach (PipelineCheckpointService::STAGES as $stage) {
        $result = $checkpoints->remember($project, $stage, function () use ($stage, &$executedAgain) {
            $executedAgain[] = $stage;

            return ['stage' => 'recomputed'];
        });

        expect($result)->toBe(['stage' => $stage]);
    }

    expect($executedAgain)->toBeEmpty();
});
