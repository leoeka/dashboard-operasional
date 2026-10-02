<?php

use App\Services\ElementorPageBuilderService;
use App\Services\MockupAssetService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

/**
 * An OpenAI account on tier 1 may create 5 images a minute. Sending every
 * photo of three candidates at once made OpenAI reject everything past the
 * fifth (HTTP 429), and proposals failed with "Baru 1 dari 3 opsi mockup yang
 * lengkap". Photos now go out in waves that fit the limit.
 */
beforeEach(function () {
    Storage::fake('public');
    config(['services.openai.key' => 'test-key', 'services.openai.images_per_minute' => 2, 'services.openai.image_time_budget' => 300]);
    Sleep::fake(syncWithCarbon: true);
});

function rateLimitMockup(): array
{
    return [
        'design' => ['primary_color' => '#1F3A5F', 'accent_color' => '#C87941', 'layout_variant' => 'split-right'],
        'pages' => [['name' => 'Home', 'sections' => [
            ['name' => 'Hero', 'headline' => 'Kopi Nusantara Pilihan', 'description' => 'Dari petani lokal.'],
            ['name' => 'Kenapa', 'headline' => 'Kenapa Kami', 'items' => [['title' => 'Segar'], ['title' => 'Adil']]],
            ['name' => 'Menu', 'headline' => 'Menu Unggulan', 'items' => [['title' => 'Kopi Gayo'], ['title' => 'Kopi Toraja']]],
        ]]],
    ];
}

function rateLimitProject(): \App\Models\Project
{
    return \App\Models\Project::create(['name' => 'Kopi', 'client_name' => 'Kopi', 'code' => 'RL-0001', 'type' => 'Coffee Shop', 'status' => 'request']);
}

it('never sends more photos in a minute than the account allows', function () {
    $sentAt = [];
    Http::fake(['api.openai.com/*' => function () use (&$sentAt) {
        $sentAt[] = now()->getTimestamp();

        return Http::response(['data' => [['b64_json' => base64_encode('PHOTO')]]]);
    }]);

    $result = (new MockupAssetService(new ElementorPageBuilderService()))->generateForCandidate(rateLimitProject(), rateLimitMockup(), 1);

    // hero + 2 menu photos = 3, at 2 per minute: two waves, a wait between.
    expect($sentAt)->toHaveCount(3)
        ->and($result['degraded'])->toBeFalse()
        ->and($sentAt[2] - $sentAt[0])->toBeGreaterThanOrEqual(60);

    Sleep::assertSleptTimes(1);
});

it('waits out a 429 rate limit and retries the photo instead of failing the option', function () {
    $calls = 0;
    Http::fake(['api.openai.com/*' => function () use (&$calls) {
        $calls++;

        return $calls === 1
            ? Http::response(['error' => ['message' => 'Rate limit reached for gpt-image-1 on images per min: Limit 5, Used 5, Requested 1.', 'code' => 'rate_limit_exceeded']], 429)
            : Http::response(['data' => [['b64_json' => base64_encode('PHOTO')]]]);
    }]);

    $result = (new MockupAssetService(new ElementorPageBuilderService()))->generateForCandidate(rateLimitProject(), rateLimitMockup(), 1);

    expect($result['degraded'])->toBeFalse()
        ->and($result['missing'])->toBe([])
        ->and($calls)->toBe(4);
});

it('stops at the time budget and leaves the rest for the retry', function () {
    config(['services.openai.images_per_minute' => 1, 'services.openai.image_time_budget' => 30]);
    Http::fake(['api.openai.com/*' => Http::response(['data' => [['b64_json' => base64_encode('PHOTO')]]])]);

    $result = (new MockupAssetService(new ElementorPageBuilderService()))->generateForCandidate(rateLimitProject(), rateLimitMockup(), 1);

    // One photo, then a 60 s wait passes the 30 s budget: the rest are missing, not hung.
    Http::assertSentCount(1);
    $disk = Storage::disk('public');
    expect($disk->exists('mockup-assets/rl-0001/candidate-1/hero.jpg'))->toBeTrue()
        ->and($disk->exists('mockup-assets/rl-0001/candidate-1/section-2-item-0.jpg'))->toBeFalse();
});
