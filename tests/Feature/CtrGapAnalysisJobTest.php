<?php

use App\Jobs\AnalyzeCtrGapJob;
use App\Http\Controllers\SeoBacklinkController;
use App\Models\Client;
use App\Models\Project;
use App\Services\CtrGapAnalyzer;
use App\Services\SearchConsoleService;
use App\Services\SeoSxoGeoReportService;
use Illuminate\Support\Facades\Cache;

it('analyzes the Search Console data already saved on the project without fetching it again', function () {
    Cache::flush();
    $client = Client::create(['company_name' => 'CTR Gap Test']);
    $project = Project::create([
        'client_id' => $client->id,
        'name' => 'CTR Gap Test',
        'client_name' => $client->company_name,
        'status' => 'in_progress',
        'wants_seo' => true,
        'seo_requirements' => [
            'target_url' => 'https://example.test',
            'search_console' => [
                'top_queries' => [[
                    'keys' => ['sample query'],
                    'clicks' => 20,
                    'impressions' => 4000,
                    'ctr' => 0.005,
                    'position' => 1.4,
                ]],
            ],
        ],
    ]);
    $console = Mockery::mock(SearchConsoleService::class);
    $console->shouldNotReceive('getTopQueries');

    (new AnalyzeCtrGapJob($project))->handle($console, new CtrGapAnalyzer());

    $project->refresh();
    expect($project->seo_requirements['ctr_gaps']['summary']['opportunity_count'])->toBe(1)
        ->and($project->seo_requirements['ctr_gaps']['opportunities'][0]['keyword'])->toBe('sample query')
        ->and(Cache::get(AnalyzeCtrGapJob::cacheKey($project->id))['status'])->toBe('done');
});

it('includes the AEO score and recommendations in the SEO SXO GEO report', function () {
    $project = new Project(['seo_requirements' => [
        'aeo_audit' => [
            'schema_version' => 2,
            'summary' => ['pages_checked' => 1, 'avg_score' => 67],
            'recommendation' => 'Tambahkan jawaban langsung.',
            'pages' => [[
                'url' => 'https://example.test/faq',
                'status' => 'ok',
                'score' => 67,
                'issues' => ['Schema FAQ / Q&A: Schema tidak ada.'],
            ]],
        ],
    ]]);

    $report = app(SeoSxoGeoReportService::class)->assemble($project);

    expect($report['scores']['aeo']['value'])->toBe(67)
        ->and($report['aeo']['recommendation'])->toBe('Tambahkan jawaban langsung.')
        ->and(collect($report['actions'])->pluck('layer'))->toContain('AEO');
});

it('computes CTR gaps when the traffic report fetches Search Console data', function () {
    $client = Client::create(['company_name' => 'Automatic CTR Gap Test']);
    $project = Project::create([
        'client_id' => $client->id,
        'name' => 'Automatic CTR Gap Test',
        'client_name' => $client->company_name,
        'status' => 'in_progress',
        'wants_seo' => true,
        'seo_requirements' => ['target_url' => 'https://example.test'],
    ]);

    $console = Mockery::mock(SearchConsoleService::class);
    $console->shouldReceive('getPerformance')->once()->with('https://example.test')->andReturn([
        'top_queries' => [],
        'ctr_gap_queries' => [[
            'keyword' => 'high-impression query',
            'clicks' => 20,
            'impressions' => 4000,
            'ctr' => 0.005,
            'position' => 1.4,
        ]],
    ]);

    $response = app(SeoBacklinkController::class)->analyzeSearchConsole(
        $project,
        $console,
        new CtrGapAnalyzer(),
    );

    expect($response->getData(true)['success'])->toBeTrue();
    $project->refresh();
    expect($project->seo_requirements['ctr_gaps']['summary']['opportunity_count'])->toBe(1)
        ->and($project->seo_requirements['ctr_gaps']['opportunities'][0]['keyword'])->toBe('high-impression query');
});