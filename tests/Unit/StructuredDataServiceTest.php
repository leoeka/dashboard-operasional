<?php

use App\Services\StructuredDataService;

function ld(string $json): string
{
    return '<html><head><script type="application/ld+json">' . $json . '</script></head><body></body></html>';
}

it('finds the type in a single JSON-LD object and flags the missing homepage schema', function () {
    $html = ld('{"@context":"https://schema.org","@type":"Organization","name":"Acme"}');

    $r = (new StructuredDataService())->analyzeHtml($html, 'https://acme.test/');

    expect($r['page_type_guess'])->toBe('homepage')
        ->and($r['types_found'])->toBe(['Organization'])
        ->and($r['jsonld_blocks'])->toBe(1)
        ->and($r['jsonld_invalid'])->toBe(0)
        ->and($r['missing'])->toBe(['WebSite']);
});

it('reads every type out of an @graph wrapper', function () {
    $html = ld('{"@context":"https://schema.org","@graph":[{"@type":"Organization","name":"Acme","sameAs":["https://instagram.com/acme"]},{"@type":"WebSite"},{"@type":"BreadcrumbList"}]}');

    $r = (new StructuredDataService())->analyzeHtml($html, 'https://acme.test/');

    expect($r['types_found'])->toBe(['BreadcrumbList', 'Organization', 'WebSite'])
        ->and($r['missing'])->toBe([])
        ->and($r['notes'])->toBe([]);
});

it('handles a top-level array of nodes and an array @type', function () {
    $html = ld('[{"@type":"WebPage"},{"@type":["Question","FAQPage"]}]');

    $r = (new StructuredDataService())->analyzeHtml($html, 'https://acme.test/faq');

    expect($r['page_type_guess'])->toBe('faq')
        ->and($r['types_found'])->toContain('FAQPage')
        ->and($r['missing'])->toBe([]);
});

it('counts an unparseable JSON-LD block as invalid and notes it', function () {
    $html = ld('{"@type":"Organization", bad json}');

    $r = (new StructuredDataService())->analyzeHtml($html, 'https://acme.test/');

    expect($r['jsonld_blocks'])->toBe(1)
        ->and($r['jsonld_invalid'])->toBe(1)
        ->and($r['types_found'])->toBe([])
        ->and($r['notes'][0])->toContain('gagal diparse');
});

it('detects microdata even without JSON-LD', function () {
    $html = '<html><body><div itemscope itemtype="https://schema.org/Product"><span itemprop="name">X</span></div></body></html>';

    $r = (new StructuredDataService())->analyzeHtml($html, 'https://acme.test/');

    expect($r['has_microdata'])->toBeTrue()
        ->and($r['types_found'])->toBe([])
        ->and($r['notes'][0])->toContain('Tidak ada JSON-LD');
});

it('guesses an article page from the URL and recommends Article schema', function () {
    $html = ld('{"@type":"WebPage"}');

    $r = (new StructuredDataService())->analyzeHtml($html, 'https://acme.test/blog/cara-memilih-kopi');

    expect($r['page_type_guess'])->toBe('article')
        ->and($r['recommended'])->toBe(['Article', 'BreadcrumbList'])
        ->and($r['missing'])->toBe(['Article', 'BreadcrumbList']);
});

it('treats LocalBusiness as satisfying the Organization requirement', function () {
    $html = ld('{"@type":"LocalBusiness","name":"Kopi Senja","sameAs":["https://instagram.com/kopisenja"]}');

    $r = (new StructuredDataService())->analyzeHtml($html, 'https://kopisenja.test/', isLocalBusiness: true);

    expect($r['recommended'])->toContain('LocalBusiness')
        ->and($r['recommended'])->toContain('Organization')
        ->and($r['missing'])->toBe(['WebSite']); // Organization satisfied by the LocalBusiness subtype
});

it('notes an Organization that is missing sameAs', function () {
    $html = ld('{"@type":"Organization","name":"Acme"}');

    $r = (new StructuredDataService())->analyzeHtml($html, 'https://acme.test/');

    expect(collect($r['notes'])->contains(fn ($n) => str_contains($n, 'sameAs')))->toBeTrue();
});
