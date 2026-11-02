<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Services\SeoAnalysisReportService;

function seoAnalysisReportProject(array $seo = []): Project
{
    $client = Client::create(['company_name' => 'PT Report Test']);

    return Project::create([
        'client_id' => $client->id,
        'name' => 'SEO Report Test',
        'client_name' => $client->company_name,
        'status' => 'in_progress',
        'wants_seo' => true,
        'seo_requirements' => array_merge(['target_url' => 'https://example.test'], $seo),
    ]);
}

it('assembles evidence-based findings from GA4, Search Console and manual Semrush data', function () {
    $project = seoAnalysisReportProject([
        'google_analytics' => [
            'period' => ['start' => '2026-07-01', 'end' => '2026-07-30'],
            'overall' => ['sessions' => 1000, 'engaged_sessions' => 500, 'engagement_rate' => 0.5, 'event_count' => 5000, 'conversions' => 0],
            'channels' => [
                ['channel' => 'Cross-network', 'sessions' => 700, 'session_share' => 0.7, 'engaged_sessions' => 250, 'engagement_rate' => 0.35, 'avg_engagement_time' => 20, 'events_per_session' => 4, 'conversions' => 0],
                ['channel' => 'Organic Search', 'sessions' => 300, 'session_share' => 0.3, 'engaged_sessions' => 250, 'engagement_rate' => 0.83, 'avg_engagement_time' => 50, 'events_per_session' => 8, 'conversions' => 0],
            ],
            'daily_trend' => array_map(fn ($i) => ['date' => sprintf('202607%02d', $i), 'sessions' => $i < 8 ? 10 : 20, 'conversions' => 0], range(1, 20)),
            'totals' => ['conversions' => 0],
        ],
        'search_console' => [
            'period' => ['start' => '2026-07-01', 'end' => '2026-07-30'],
            'totals' => ['clicks' => 100, 'impressions' => 10000, 'ctr' => 0.01, 'position' => 7.5],
            'top_queries' => [['keys' => ['example keyword'], 'clicks' => 30, 'impressions' => 3000, 'ctr' => 0.01, 'position' => 5]],
            'top_pages' => [['keys' => ['https://example.test/'], 'clicks' => 60, 'impressions' => 5000, 'ctr' => 0.012, 'position' => 6]],
            'daily_trend' => array_map(fn ($i) => ['keys' => [sprintf('202607%02d', $i)], 'clicks' => $i < 8 ? 2 : 5, 'impressions' => 100, 'ctr' => 0.02, 'position' => 7], range(1, 20)),
        ],
        'semrush_report' => [
            'as_of' => '2026-07-30',
            'metrics' => ['authority_score' => 25, 'organic_traffic' => 4600, 'organic_keywords' => 2100],
            'ai_platforms' => [['label' => 'Gemini', 'first' => 10, 'second' => 7]],
            'countries' => [['label' => 'Indonesia', 'first' => 2900, 'second' => 381]],
            'trend_summary' => 'Traffic meningkat pada pertengahan bulan.',
            'key_points' => 'Indonesia menjadi kontributor traffic terbesar.',
            'recommendations' => 'Pantau tren dan perkuat konten organik.',
        ],
    ]);

    $report = app(SeoAnalysisReportService::class)->assemble($project);

    expect($report['ga4Findings'])->toContain('Belum ada konversi tercatat pada periode ini, meski event GA4 tercatat. Pastikan event utama sudah ditandai sebagai conversion; angka nol tidak membuktikan tidak ada tindakan pengguna.')
        ->and(implode(' ', $report['ga4Findings']))->toContain('Cross-network')
        ->and(implode(' ', $report['gscFindings']))->toContain('CTR 1,00%')
        ->and(implode(' ', $report['semrushFindings']))->toContain('Authority Score tercatat 25/100')
        ->and($report['recommendations'])->toContain('Pantau tren dan perkuat konten organik.')
        ->and($report['conclusion'])->toContain('estimasi pasar Semrush');
});

it('renders a separate Analysis SEO PDF from the workspace report route', function () {
    $project = seoAnalysisReportProject();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('pages.projects.seo-analysis.report.download', $project));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('Analysis-SEO-');
});

it('stores manually transcribed Semrush metrics and narrative without changing proposal data', function () {
    $project = seoAnalysisReportProject(['manual_screenshots' => ['own_semrush' => 'manual-screenshots/1/semrush.png']]);

    $response = $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.seo-analysis.semrush.store', $project), [
            'as_of' => '2026-07-30',
            'metrics' => ['authority_score' => 25, 'organic_traffic' => 4600, 'organic_keywords' => 2100],
            'ai_platforms_text' => "Gemini|10|7\nChatGPT|7|28",
            'countries_text' => "Indonesia|2900|381",
            'trend_summary' => 'Traffic naik pertengahan bulan.',
            'key_points' => 'Organic stabil.',
            'recommendations' => 'Perkuat konten.',
        ]);

    $response->assertRedirect(route('pages.project-workspace', ['project' => $project->id]) . '#traffic');
    $project->refresh();
    expect($project->seo_requirements['semrush_report']['metrics']['authority_score'])->toBe(25)
        ->and($project->seo_requirements['semrush_report']['ai_platforms'][0])->toMatchArray(['label' => 'Gemini', 'first' => 10, 'second' => 7])
        ->and($project->seo_requirements['manual_screenshots']['own_semrush'])->toBe('manual-screenshots/1/semrush.png')
        ->and($project->seo_requirements)->not->toHaveKey('proposal');
});

it('validates Semrush percentage-like scores within their documented range', function () {
    $project = seoAnalysisReportProject();

    $this->actingAs(User::factory()->create())
        ->from(route('pages.project-workspace', ['project' => $project->id]))
        ->post(route('pages.projects.seo-analysis.semrush.store', $project), [
            'metrics' => ['authority_score' => 120],
        ])
        ->assertSessionHasErrors('metrics.authority_score');
});
