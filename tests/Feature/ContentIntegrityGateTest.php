<?php

use App\Models\Client;
use App\Models\Project;
use App\Services\AnalisisGeminiService;
use App\Services\ContentIntegrityService;
use App\Services\ElementorPageBuilderService;
use App\Support\MockupSite;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;

/**
 * Nothing the client did not supply — testimonials, figures, prices, pricing
 * badges, competitor facts — may reach the blueprint the client is shown.
 */
function integrityProject(array $attributes = [], array $client = []): Project
{
    $clientModel = Client::create(array_merge(['company_name' => 'Bali Private Tour'], $client));

    return Project::create(array_merge([
        'client_id' => $clientModel->id,
        'name' => 'Bali Private Tour & Airport Transfer',
        'client_name' => 'Bali Private Tour',
        'type' => 'Travel',
        'status' => 'request',
        'description' => 'Private tours and airport transfers in Bali for international tourists.',
        'design_reference_type' => 'url',
        'design_reference_url' => 'https://demo.goodlayers.com/traveltour/',
    ], $attributes))->load('client');
}

/** A Gemini sitemap written the way a model left to itself writes one. */
function slopAnalysis(array $homeSections = []): array
{
    return [
        'business_analysis' => ['value_proposition' => 'Private tours in Bali.'],
        'competitor_analysis' => ['real_competitors_found' => [['url' => 'https://balitours.example.com', 'observation' => '9,000 reviews']]],
        'sitemap' => [
            'website_concept' => 'Private tours and transfers in Bali.',
            'global_cta' => 'Book via WhatsApp',
            'pages' => [[
                'name' => 'Home',
                'sections' => array_merge([
                    ['type' => 'hero', 'name' => 'Hero', 'headline' => 'Bali, privately', 'description' => 'Trusted by 12,000+ customers. Your own driver, your own pace.'],
                ], $homeSections),
            ]],
        ],
    ];
}

function sanitize(array $analysis, ?Project $project = null, array $competitors = []): array
{
    $project ??= integrityProject();

    return app(ContentIntegrityService::class)->sanitize($analysis, $project, $project->client, $competitors);
}

function homeSections(array $analysis): array
{
    return $analysis['sitemap']['pages'][0]['sections'];
}

it('removes an invented testimonial section before it reaches the blueprint', function () {
    $clean = sanitize(slopAnalysis([
        ['type' => 'testimonial', 'name' => 'Testimonials', 'headline' => 'What guests say', 'items' => [
            ['quote' => 'Best tour we ever had in Bali!', 'author' => 'Rina', 'role' => 'Jakarta'],
            ['quote' => 'Our driver was amazing.', 'author' => 'Tom', 'role' => 'Sydney'],
        ]],
    ]));

    expect(collect(homeSections($clean))->pluck('type'))->not->toContain('testimonial')
        ->and(json_encode($clean['sitemap']))->not->toContain('Rina')
        ->and(collect($clean['content_integrity']['removed'])->pluck('reason'))->toContain('testimonial section without client-supplied testimonials');
});

it('removes an unsourced figure such as "12,000+ customers" everywhere', function () {
    $clean = sanitize(slopAnalysis([
        ['type' => 'stats', 'name' => 'In numbers', 'headline' => 'Our track record', 'items' => [
            ['value' => '12,000+', 'label' => 'customers'],
            ['value' => '4.9', 'label' => 'average rating'],
        ]],
        ['type' => 'about', 'name' => 'About', 'headline' => 'About us', 'description' => 'Since 2009 we have driven 98% of guests back. Local Balinese drivers who know every road.'],
    ]));

    $json = json_encode($clean['sitemap']);

    expect($json)->not->toContain('12,000')->not->toContain('4.9')->not->toContain('2009')->not->toContain('98%')
        ->and(collect(homeSections($clean))->pluck('type'))->not->toContain('stats')
        // the truthful part of the copy survives
        ->and(homeSections($clean)[0]['description'])->toBe('Your own driver, your own pace.')
        ->and(collect(homeSections($clean))->firstWhere('type', 'about')['description'])->toBe('Local Balinese drivers who know every road.');
});

it('keeps a testimonial the client actually supplied', function () {
    $project = integrityProject([], ['notes' => 'Review from Sarah (UK): "Wayan made our honeymoon unforgettable, always on time."']);

    $clean = sanitize(slopAnalysis([
        ['type' => 'testimonial', 'name' => 'Testimonials', 'headline' => 'What guests say', 'items' => [
            ['quote' => 'Wayan made our honeymoon unforgettable, always on time.', 'author' => 'Sarah', 'role' => 'UK'],
            ['quote' => 'Invented praise from nobody.', 'author' => 'Ghost'],
        ]],
    ]), $project);

    $testimonials = collect(homeSections($clean))->firstWhere('type', 'testimonial');

    expect($testimonials['items'])->toHaveCount(1)
        ->and($testimonials['items'][0]['author'])->toBe('Sarah');
});

it('keeps statistics the client actually supplied', function () {
    $project = integrityProject(['description' => 'Private tours in Bali. We have served 3,500 guests since 2016.']);

    $clean = sanitize(slopAnalysis([
        ['type' => 'stats', 'name' => 'In numbers', 'headline' => 'Our track record', 'items' => [
            ['value' => '3,500', 'label' => 'guests'],
            ['value' => '2016', 'label' => 'founded'],
            ['value' => '50+', 'label' => 'drivers'],
        ]],
    ]), $project);

    $stats = collect(homeSections($clean))->firstWhere('type', 'stats');

    expect(collect($stats['items'])->pluck('value')->all())->toBe(['3,500', '2016']);
});

it('keeps prices the client actually supplied', function () {
    $project = integrityProject(['description' => 'Airport transfer Rp 350.000. Full-day tour Rp 900.000.']);

    $clean = sanitize(slopAnalysis([
        ['type' => 'pricing', 'name' => 'Pricing', 'headline' => 'Transfers & tours', 'items' => [
            ['title' => 'Airport transfer', 'price' => 'Rp 350.000'],
            ['title' => 'Full-day tour', 'price' => 'Rp 900.000'],
        ]],
    ]), $project);

    $pricing = collect(homeSections($clean))->firstWhere('type', 'pricing');

    expect(collect($pricing['items'])->pluck('price')->all())->toBe(['Rp 350.000', 'Rp 900.000']);
});

it('never lets an invented price reach the client', function () {
    $clean = sanitize(slopAnalysis([
        ['type' => 'pricing', 'name' => 'Pricing', 'headline' => 'Packages', 'items' => [
            ['title' => 'Half day', 'price' => '$45', 'features' => ['Driver']],
            ['title' => 'Full day', 'price' => '$75', 'features' => ['Driver', 'Fuel']],
        ]],
        ['type' => 'services', 'name' => 'Tours', 'headline' => 'Tours', 'items' => [
            ['title' => 'Ubud tour', 'description' => 'Rice terraces and temples. From $39 per car.', 'price' => '$39'],
        ]],
    ]));

    $json = json_encode($clean['sitemap']);
    $tours = collect(homeSections($clean))->firstWhere('type', 'services');

    expect($json)->not->toContain('$45')->not->toContain('$75')->not->toContain('39')
        // a pricing table left with no real price is removed, not shown empty
        ->and(collect(homeSections($clean))->pluck('type'))->not->toContain('pricing')
        // a service keeps its truthful description, minus the invented price
        ->and($tours['items'][0])->not->toHaveKey('price')
        ->and($tours['items'][0]['description'])->toBe('Rice terraces and temples.');
});

it('drops a "Most Popular" highlight the client never asked for', function () {
    $project = integrityProject(['description' => 'Half day Rp 500.000, full day Rp 800.000, two days Rp 1.500.000.']);

    $clean = sanitize(slopAnalysis([
        ['type' => 'pricing', 'name' => 'Pricing', 'headline' => 'Packages', 'items' => [
            ['title' => 'Half day', 'price' => 'Rp 500.000'],
            ['title' => 'Full day', 'price' => 'Rp 800.000', 'featured' => true, 'badge' => 'Most Popular', 'description' => 'Most Popular. Our longest route.'],
            ['title' => 'Two days', 'price' => 'Rp 1.500.000'],
        ]],
    ]), $project);

    $middle = collect(homeSections($clean))->firstWhere('type', 'pricing')['items'][1];

    expect($middle)->not->toHaveKey('featured')->not->toHaveKey('badge')
        ->and($middle['description'])->toBe('Our longest route.');

    // …and neither renderer highlights anything by position.
    $blueprint = ['design' => ['renderer_version' => 2], 'pages' => $clean['sitemap']['pages']];
    $site = MockupSite::build($blueprint);
    $html = view('mockup.site', ['site' => $site])->render();
    $wp = (new ElementorPageBuilderService())->buildPages($blueprint['pages'], $blueprint['design'])['home']['html'];

    expect($html)->not->toContain('class="plan plan--featured"')
        // a featured plan is a primary-coloured group inside the plans grid
        ->and(substr($wp, strpos($wp, 'exito-plans')))->not->toContain('background-color:#1F2937');
});

it('keeps a highlight the client explicitly asked for', function () {
    $project = integrityProject(['description' => 'Half day Rp 500.000, full day Rp 800.000. Full day is our best seller, please feature it.']);

    $clean = sanitize(slopAnalysis([
        ['type' => 'pricing', 'name' => 'Pricing', 'headline' => 'Packages', 'items' => [
            ['title' => 'Half day', 'price' => 'Rp 500.000'],
            ['title' => 'Full day', 'price' => 'Rp 800.000', 'featured' => true],
        ]],
    ]), $project);

    $items = collect(homeSections($clean))->firstWhere('type', 'pricing')['items'];
    $html = view('mockup.site', ['site' => MockupSite::build(['design' => ['renderer_version' => 2], 'pages' => $clean['sitemap']['pages']])])->render();

    expect($items[1]['featured'])->toBeTrue()
        ->and($items[0])->not->toHaveKey('featured')
        ->and($html)->toContain('class="plan plan--featured"');
});

it('never turns competitor or reference facts into client facts', function () {
    $clean = sanitize(slopAnalysis([
        ['type' => 'about', 'name' => 'About', 'headline' => 'Why travellers choose us', 'description' => 'Inspired by TravelTour and GoodLayers. We plan every day around you.'],
        ['type' => 'testimonial', 'name' => 'Reviews', 'headline' => 'Reviews', 'items' => [
            ['quote' => 'Amazing trip to Nusa Penida with the best guide!', 'author' => 'Emily'],
        ]],
    ]), null, [['url' => 'https://balitours.example.com', 'text' => 'Amazing trip to Nusa Penida with the best guide! Over 9,000 reviews.']]);

    $json = json_encode($clean['sitemap']);

    expect($json)->not->toContain('TravelTour')->not->toContain('GoodLayers')->not->toContain('9,000')
        ->not->toContain('Nusa Penida with the best guide')
        ->and(collect(homeSections($clean))->firstWhere('type', 'about')['description'])->toBe('We plan every day around you.');
});

it('runs on every live Gemini response before the sitemap is final', function () {
    config(['services.proposal_ai_enabled' => true]);
    $json = json_encode(slopAnalysis([
        ['type' => 'testimonial', 'name' => 'Testimonials', 'headline' => 'What guests say', 'items' => [['quote' => 'Fantastic!', 'author' => 'Rina']]],
    ]));
    Gemini::fake([GenerateContentResponse::fake(['candidates' => [['content' => ['parts' => [['text' => $json]], 'role' => 'model']]]])]);

    $project = integrityProject();
    $analysis = app(AnalisisGeminiService::class)->analyzeProject($project, $project->client);

    expect($analysis['content_integrity']['checked'])->toBeTrue()
        ->and(json_encode($analysis['sitemap']))->not->toContain('Rina')->not->toContain('12,000');
});
