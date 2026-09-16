<?php

use App\Services\SitePageFetcher;
use App\Services\TechnicalSeoService;

function tech(): TechnicalSeoService
{
    return new TechnicalSeoService(new SitePageFetcher());
}

it('extracts Sitemap URLs from robots.txt', function () {
    $robots = <<<TXT
    User-agent: *
    Disallow: /wp-admin/
    Sitemap: https://acme.test/sitemap.xml
    Sitemap:   https://acme.test/news-sitemap.xml
    TXT;

    expect(tech()->sitemapUrlsFromRobots($robots))
        ->toBe(['https://acme.test/sitemap.xml', 'https://acme.test/news-sitemap.xml']);
});

it('returns no Sitemap URLs when robots.txt has none', function () {
    expect(tech()->sitemapUrlsFromRobots("User-agent: *\nDisallow:\n"))->toBe([]);
});

it('counts <loc> entries in a sitemap', function () {
    $xml = '<urlset><url><loc>https://a.test/1</loc></url><url><loc> https://a.test/2 </loc></url></urlset>';

    expect(tech()->countLocs($xml))->toBe(2)
        ->and(tech()->countLocs('<sitemapindex></sitemapindex>'))->toBe(0);
});

it('collects only same-host links from homepage HTML', function () {
    $html = '<a href="/menu">m</a> <a href="https://acme.test/about">a</a> <a href="https://other.test/x">o</a> <a href="#top">t</a> <a href="mailto:x@acme.test">e</a>';

    expect(tech()->sameHostLinks($html, 'https://acme.test'))
        ->toBe(['https://acme.test/menu', 'https://acme.test/about']);
});
