<?php

use App\Services\OnPageAuditService;
use App\Services\SitePageFetcher;

function audit(string $html, string $url = 'https://acme.test/'): array
{
    return (new OnPageAuditService(new SitePageFetcher()))->analyzeHtml($html, $url);
}

function checkStatus(array $result, string $id): string
{
    foreach ($result['checks'] as $c) {
        if ($c['id'] === $id) {
            return $c['status'];
        }
    }

    return 'missing';
}

it('passes a well-formed page', function () {
    $html = <<<HTML
    <html lang="id"><head>
        <title>Kopi Manual Brew Terbaik di Yogyakarta - Kopi Senja</title>
        <meta name="description" content="Kopi Senja menyajikan manual brew dan pastry di Yogyakarta. Kunjungi kedai kami untuk pengalaman kopi spesialti yang tenang dan hangat setiap hari.">
        <link rel="canonical" href="https://acme.test/">
    </head><body>
        <h1>Kopi Senja</h1><h2>Menu</h2><p>Kami menyajikan kopi manual brew pilihan dengan biji yang disangrai sendiri. Datang dan rasakan suasana kedai yang hangat serta menu pastry buatan tangan setiap hari di Yogyakarta bersama kami.</p>
        <a href="/menu">Menu</a><a href="/tentang">Tentang</a><a href="/kontak">Kontak</a>
    </body></html>
    HTML;

    $r = audit($html);

    expect($r['noindex'])->toBeFalse()
        ->and($r['score'])->toBeGreaterThanOrEqual(85)
        ->and(checkStatus($r, 'title'))->toBe('pass')
        ->and(checkStatus($r, 'h1'))->toBe('pass');
});

it('fails when the title is missing and flags noindex', function () {
    $html = '<html><head><meta name="robots" content="noindex,follow"></head><body><p>hi</p></body></html>';

    $r = audit($html);

    expect(checkStatus($r, 'title'))->toBe('fail')
        ->and(checkStatus($r, 'noindex'))->toBe('fail')
        ->and($r['noindex'])->toBeTrue()
        ->and($r['score'])->toBeLessThan(60);
});

it('warns on multiple H1s and images without alt', function () {
    $html = '<html lang="en"><head><title>'.str_repeat('x', 40).'</title></head><body><h1>A</h1><h1>B</h1><img src="a.jpg"><img src="b.jpg" alt="ok"></body></html>';

    $r = audit($html);

    expect(checkStatus($r, 'h1'))->toBe('warn')
        ->and(checkStatus($r, 'img_alt'))->toBe('warn');
});

it('warns on a skipped heading level', function () {
    $html = '<html><head><title>'.str_repeat('x', 40).'</title></head><body><h1>A</h1><h4>D</h4></body></html>';

    expect(checkStatus(audit($html), 'heading_order'))->toBe('warn');
});

it('aggregates a page that could not be fetched into the summary', function () {
    // analyze() path: give the fetcher nothing to fetch by using an unresolvable host
    $r = (new OnPageAuditService(new SitePageFetcher()))->analyze('https://definitely-not-a-real-domain-xyz-12345.test');

    expect($r['pages'][0]['fetch_status'])->toBe('error')
        ->and($r['summary']['pages_checked'])->toBe(0);
});
