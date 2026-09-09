<?php

use App\Services\AiCrawlerAccessService;

/** Ambil hasil satu bot dari output evaluate(). */
function crawler(array $result, string $bot): array
{
    foreach ($result['crawlers'] as $c) {
        if ($c['bot'] === $bot) {
            return $c;
        }
    }

    return [];
}

it('treats an empty robots.txt as everything allowed', function () {
    $result = (new AiCrawlerAccessService())->evaluate('');

    expect($result['summary']['blocked'])->toBe(0)
        ->and($result['summary']['partial'])->toBe(0)
        ->and($result['summary']['allowed'])->toBe(count(AiCrawlerAccessService::AI_CRAWLERS));
});

it('marks every AI crawler blocked when the wildcard group disallows the whole site', function () {
    $result = (new AiCrawlerAccessService())->evaluate("User-agent: *\nDisallow: /\n");

    expect($result['summary']['blocked'])->toBe(count(AiCrawlerAccessService::AI_CRAWLERS));
    expect(crawler($result, 'GPTBot')['access'])->toBe('blocked')
        ->and(crawler($result, 'GPTBot')['matched_group'])->toBe('*');
});

it('blocks only the bot named in its own group', function () {
    $robots = <<<TXT
    User-agent: GPTBot
    Disallow: /

    User-agent: *
    Disallow:
    TXT;

    $result = (new AiCrawlerAccessService())->evaluate($robots);

    expect(crawler($result, 'GPTBot')['access'])->toBe('blocked')
        ->and(crawler($result, 'GPTBot')['matched_group'])->toBe('GPTBot')
        ->and(crawler($result, 'PerplexityBot')['access'])->toBe('allowed')
        ->and($result['summary']['blocked'])->toBe(1);
});

it('reports a path-only disallow as partial, not blocked', function () {
    $result = (new AiCrawlerAccessService())->evaluate("User-agent: *\nDisallow: /wp-admin/\n");

    expect(crawler($result, 'ClaudeBot')['access'])->toBe('partial')
        ->and($result['summary']['blocked'])->toBe(0)
        ->and($result['summary']['partial'])->toBe(count(AiCrawlerAccessService::AI_CRAWLERS));
});

it('lets an explicit Allow: / override a Disallow: / in the same group', function () {
    $robots = <<<TXT
    User-agent: PerplexityBot
    Disallow: /
    Allow: /
    TXT;

    $result = (new AiCrawlerAccessService())->evaluate($robots);

    expect(crawler($result, 'PerplexityBot')['access'])->toBe('allowed');
});

it('matches a specific group case-insensitively', function () {
    $result = (new AiCrawlerAccessService())->evaluate("User-agent: gptbot\nDisallow: /\n");

    expect(crawler($result, 'GPTBot')['access'])->toBe('blocked');
});

it('detects a Sitemap directive', function () {
    $withSitemap = (new AiCrawlerAccessService())->evaluate("Sitemap: https://example.com/sitemap.xml\nUser-agent: *\nDisallow:\n");
    $without = (new AiCrawlerAccessService())->evaluate("User-agent: *\nDisallow:\n");

    expect($withSitemap['has_sitemap_directive'])->toBeTrue()
        ->and($without['has_sitemap_directive'])->toBeFalse();
});

it('ignores comment lines when parsing groups', function () {
    $robots = <<<TXT
    # block AI training bots
    User-agent: CCBot
    Disallow: / # no crawling please

    User-agent: *
    Disallow:
    TXT;

    $result = (new AiCrawlerAccessService())->evaluate($robots);

    expect(crawler($result, 'CCBot')['access'])->toBe('blocked')
        ->and(crawler($result, 'GPTBot')['access'])->toBe('allowed');
});
