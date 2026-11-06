<?php

use App\Services\AeoAuditService;
use App\Services\SitePageFetcher;

it('recognizes answer-ready content and reports missing AEO signals', function () {
    $html = <<<'HTML'
    <html><head>
      <meta property="article:modified_time" content="2026-10-01">
      <script type="application/ld+json">{"@context":"https://schema.org","@type":"FAQPage","dateModified":"2026-10-01"}</script>
    </head><body>
        <h1>FAQ: SEO</h1><article>
      <h2>Apa itu SEO?</h2>
      <p>SEO adalah proses meningkatkan visibilitas halaman website pada hasil pencarian organik.</p>
      <h2>Bagaimana cara memulai?</h2>
      <p>Mulailah dengan riset kata kunci, audit teknis, dan susun konten yang menjawab kebutuhan pengguna.</p>
      <a href="https://www.example.org/source">Sumber penelitian</a>
      <time datetime="2026-10-01">1 Oktober 2026</time>
      </article></body></html>
    HTML;

    $service = new AeoAuditService(app(SitePageFetcher::class));
    $result = $service->analyzeHtml($html, 'https://example.test/faq');

    expect($result['status'])->toBe('ok')
        ->and($result['score'])->toBe(100)
        ->and($result['issues'])->toBe([]);
});

it('flags missing direct answers, FAQ schema, evidence, and freshness', function () {
    $service = new AeoAuditService(app(SitePageFetcher::class));
    $result = $service->analyzeHtml('<html><body><h1>Welcome</h1><p>General content.</p></body></html>', 'https://example.test/');

    expect($result['score'])->toBeLessThan(100)
        ->and($result['issues'])->toHaveCount(6);
});

      it('gives meaningful credit to one clear question and concise answer without requiring FAQ schema', function () {
          $html = <<<'HTML'
          <html><body><main>
            <h1>Apa itu SEO?</h1>
            <p>SEO adalah cara meningkatkan visibilitas website pada hasil pencarian organik.</p>
          </main></body></html>
          HTML;

          $service = new AeoAuditService(app(SitePageFetcher::class));
          $result = $service->analyzeHtml($html, 'https://example.test/');

          expect($result['score'])->toBe(45)
        ->and(collect($result['checks'])->firstWhere('id', 'question_headings')['status'])->toBe('pass')
        ->and(collect($result['checks'])->firstWhere('id', 'direct_answers')['status'])->toBe('pass')
        ->and(collect($result['checks'])->firstWhere('id', 'faq_schema')['status'])->toBe('warn');
      });

