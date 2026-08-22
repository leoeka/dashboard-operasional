<?php

use App\Models\Project;
use Tests\TestCase;

uses(TestCase::class);

it('renders the travel web proposal sections and project pricing', function () {
    $project = new Project([
        'name' => 'Bali Crown Travel World',
        'client_name' => 'PT. BALI CROWN TRAVEL WORLD',
        'type' => 'Business Package',
        'value' => 5000000,
        'code' => 'BCW-0001',
    ]);

    $html = view('pdf.proposal', [
        'project' => $project,
        'projectData' => [],
        'analysis' => [],
        'analysisSummary' => [],
        'mockup' => [
            'screenshot_path' => null,
        ],
    ])->render();

    expect($html)
        ->toContain('PT. BALI CROWN TRAVEL WORLD')
        ->toContain('Design Mock Up')
        ->toContain('Website Development Services COST')
        ->toContain('IDR 5.000.000')
        ->toContain('Setup Goole Ads +')
        ->toContain('Setup Meta Ads (Facebook &amp; Instagram Ads)')
        ->toContain('SEO Optimization')
        ->toContain('Agreement Contract must be done')
        ->toContain('Thank you for your interest');
});

it('allows proposal pricing and ZipWP site details to be mass assigned', function () {
    $project = new Project([
        'value' => 5000000,
        'zipwp_site_uuid' => 'site-123',
        'zipwp_site_url' => 'https://example.com',
    ]);

    expect($project->value)->toBe(5000000)
        ->and($project->zipwp_site_uuid)->toBe('site-123')
        ->and($project->zipwp_site_url)->toBe('https://example.com');
});
