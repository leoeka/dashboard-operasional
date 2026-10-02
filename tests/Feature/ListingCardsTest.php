<?php

use App\Models\Client;
use App\Models\Project;
use App\Services\ContentIntegrityService;
use App\Services\ElementorPageBuilderService;
use App\Support\CompositionSpec;
use App\Support\MockupSite;

/**
 * Tours, rooms and products render as listing cards — photo, location and
 * duration, rating, price and a details button — the format travel clients ask
 * for. The same facts reach the live demo and WordPress, and none of them is
 * ever invented: a rating or price the client never gave is removed upstream.
 */
function listingSection(array $overrides = []): array
{
    return array_merge([
        'type' => 'tours',
        'name' => 'Popular Tours',
        'headline' => 'Day trips our drivers know by heart',
        'composition' => 'listing_cards',
        'items' => [
            ['title' => 'Ayung River Rafting', 'description' => 'Rapids and jungle gorges.', 'location' => 'Ubud', 'duration' => '6 Hours', 'rating' => '4,9', 'reviews' => '128', 'price' => 'IDR 600.000', 'price_unit' => '/person'],
            ['title' => 'Uluwatu Sunset', 'description' => 'Clifftop temple at golden hour.', 'location' => 'Uluwatu'],
            ['title' => 'East Bali Palaces', 'description' => 'Water palaces and black-sand beaches.', 'location' => 'Karangasem'],
        ],
    ], $overrides);
}

function listingBlueprint(array $section, string $language = 'en'): array
{
    return [
        'language' => $language,
        'global_cta' => 'Book via WhatsApp',
        'design' => ['renderer_version' => 2, 'primary_color' => '#04293A', 'secondary_color' => '#F4F1EA', 'accent_color' => '#FF8A00'],
        'pages' => [['name' => 'Home', 'sections' => [
            ['type' => 'hero', 'name' => 'Hero', 'headline' => 'Bali, privately', 'composition' => 'centered_minimal'],
            $section,
        ]]],
    ];
}

function listingDemo(array $mockup): string
{
    return view('mockup.site', ['site' => MockupSite::build($mockup, ['brand' => 'Bali Private Tour', 'page' => 'home', 'fixed' => false])])->render();
}

function listingWordPress(array $mockup): string
{
    return (new ElementorPageBuilderService())->buildPages($mockup['pages'], $mockup['design'], [], $mockup['global_cta'], $mockup['language'])['home']['html'];
}

it('recognises a catalogue of tours as a listing, but keeps plans with included features as pricing', function () {
    expect(CompositionSpec::sectionShape(listingSection()))->toBe('listing')
        // Named like a catalogue, no listing fields yet.
        ->and(CompositionSpec::sectionShape(['name' => 'Bali Tour Packages', 'items' => [['title' => 'Ubud'], ['title' => 'Uluwatu']]]))->toBe('listing')
        // Products that merely carry a price are a catalogue, not a price table.
        ->and(CompositionSpec::sectionShape(['name' => 'Kopi', 'items' => [['title' => 'Gayo', 'price' => 'Rp 85.000']]]))->toBe('listing')
        ->and(CompositionSpec::sectionShape(['name' => 'Paket Harga', 'items' => [['title' => 'Basic', 'price' => 'Rp 1jt', 'features' => ['1 halaman']]]]))->toBe('pricing')
        // Ordinary features stay features.
        ->and(CompositionSpec::sectionShape(['name' => 'Keunggulan Produk', 'items' => [['title' => 'Kering'], ['title' => 'Padat']]]))->toBe('card_items');
});

it('still accepts every composition a catalogue section could have had before', function () {
    // Approved blueprints must resolve exactly as they did.
    foreach (CompositionSpec::compositionsForShape('card_items') as $composition) {
        expect(CompositionSpec::compositionsForShape('listing'))->toContain($composition);
    }

    expect(CompositionSpec::defaultCompositionForShape('listing'))->toBe('listing_cards');
});

it('draws the listing facts in the demo and the same facts in WordPress', function () {
    $mockup = listingBlueprint(listingSection());
    $demo = listingDemo($mockup);
    $wp = listingWordPress($mockup);

    expect($demo)
        ->toContain('class="listing"')
        ->toContain('Ubud')
        ->toContain('6 Hours')
        ->toContain('4,9')
        ->toContain('(128)')
        ->toContain('IDR 600.000')
        ->toContain('/person')
        ->toContain('View details');

    expect($wp)
        ->toContain('exito-listing-grid')
        ->toContain('Ubud  ·  6 Hours  ·  ★ 4,9 (128)')
        ->toContain('IDR 600.000 /person')
        ->toContain('View details')
        ->toContain('Uluwatu Sunset')
        ->toContain('East Bali Palaces');

    // Same number of cards in both.
    expect(substr_count($demo, 'class="listing"'))->toBe(3)
        ->and(substr_count($wp, 'View details'))->toBe(3);
});

it('shows no rating or price slot for a listing that has none', function () {
    $demo = listingDemo(listingBlueprint(listingSection(['items' => [
        ['title' => 'Uluwatu Sunset', 'description' => 'Clifftop temple.', 'location' => 'Uluwatu'],
    ]])));

    expect($demo)->toContain('Uluwatu')
        ->not->toContain('class="listing-rating"')
        ->not->toContain('class="listing-price"');
});

it('labels the details button in the site language', function () {
    $mockup = listingBlueprint(listingSection(), 'id');

    expect(listingDemo($mockup))->toContain('Lihat detail')
        ->and(listingWordPress($mockup))->toContain('Lihat detail');
});

it('removes a rating, duration or price the client never gave before a listing is drawn', function () {
    $client = Client::create(['company_name' => 'Bali Private Tour']);
    $project = Project::create([
        'client_id' => $client->id,
        'name' => 'Bali Private Tour',
        'client_name' => 'Bali Private Tour',
        'type' => 'Travel',
        'status' => 'request',
        'description' => 'Private tours in Bali. Ayung River Rafting costs IDR 600.000 per person.',
    ])->load('client');

    $analysis = ['sitemap' => ['pages' => [['name' => 'Home', 'sections' => [
        ['type' => 'hero', 'headline' => 'Bali, privately'],
        listingSection(),
    ]]]]];

    $clean = app(ContentIntegrityService::class)->sanitize($analysis, $project, $client);
    $first = $clean['sitemap']['pages'][0]['sections'][1]['items'][0];

    // The price was in the brief; the rating, review count and duration were not.
    expect($first['price'])->toBe('IDR 600.000')
        ->and($first['rating'] ?? '')->toBe('')
        ->and($first['reviews'] ?? '')->toBe('')
        ->and($first['duration'] ?? '')->toBe('')
        ->and($first['location'])->toBe('Ubud');

    $demo = listingDemo(listingBlueprint($clean['sitemap']['pages'][0]['sections'][1]));
    expect($demo)->not->toContain('4,9')->not->toContain('6 Hours')->toContain('IDR 600.000');
});
