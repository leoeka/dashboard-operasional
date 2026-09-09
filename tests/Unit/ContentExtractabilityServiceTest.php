<?php

use App\Models\Project;
use App\Services\AnalisisGeminiService;
use App\Services\CompetitorContentFetcher;
use App\Services\ContentExtractabilityService;
use App\Services\SitePageFetcher;

function extractSvc(): ContentExtractabilityService
{
    return new ContentExtractabilityService(
        new SitePageFetcher(),
        new CompetitorContentFetcher(),
        app(AnalisisGeminiService::class),
    );
}

it('builds a prompt that includes the page title, headings, and GEO criteria', function () {
    $project = new Project(['name' => 'Kopi Senja', 'type' => 'Coffee Shop']);

    $prompt = extractSvc()->buildPrompt($project, [
        'title' => 'Cara Menyeduh Kopi Manual',
        'headings' => ['Apa itu V60?', 'Rasio kopi dan air'],
        'body_text' => 'Manual brew adalah metode menyeduh kopi tanpa mesin.',
    ]);

    expect($prompt)
        ->toContain('Kopi Senja')
        ->toContain('Cara Menyeduh Kopi Manual')
        ->toContain('Apa itu V60?')
        ->toContain('direct_answers')
        ->toContain('quotable');
});

it('normalises a full Gemini result', function () {
    $r = extractSvc()->normalisePageResult('https://acme.test/blog/x', [
        'score' => 74,
        'direct_answers' => true,
        'tldr' => false,
        'stats_sourced' => true,
        'faq' => false,
        'quotable' => true,
        'suggestions' => ['Tambah blok FAQ', 'Beri ringkasan di awal', '', 42],
    ]);

    expect($r['status'])->toBe('ok')
        ->and($r['score'])->toBe(74)
        ->and($r['criteria']['direct_answers'])->toBeTrue()
        ->and($r['criteria']['tldr'])->toBeFalse()
        ->and($r['suggestions'])->toBe(['Tambah blok FAQ', 'Beri ringkasan di awal']);
});

it('derives a score from criteria when Gemini omits an explicit score', function () {
    $r = extractSvc()->normalisePageResult('https://acme.test/', [
        'direct_answers' => true,
        'tldr' => true,
        'stats_sourced' => false,
        'faq' => false,
        'quotable' => true,
    ]);

    // 3 criteria met * 20
    expect($r['score'])->toBe(60);
});

it('clamps an out-of-range score', function () {
    expect(extractSvc()->normalisePageResult('https://acme.test/', ['score' => 250])['score'])->toBe(100)
        ->and(extractSvc()->normalisePageResult('https://acme.test/', ['score' => -5])['score'])->toBe(0);
});
