<?php

namespace App\Services;

/** Deterministic Answer Engine Optimization readiness checks for key pages. */
class AeoAuditService
{
    private const MAX_PAGES = 3;

    public function __construct(private SitePageFetcher $fetcher)
    {
    }

    /**
     * @param list<string> $extraUrls
     * @return array{checked_at:string,pages:list<array<string,mixed>>,summary:array<string,mixed>,recommendation:string}
     */
    public function analyze(string $baseUrl, array $extraUrls = []): array
    {
        $pages = [];

        foreach ($this->fetcher->pages($baseUrl, $extraUrls, self::MAX_PAGES) as $url) {
            $html = $this->fetcher->html($url);
            $pages[] = $html === null
                ? ['url' => $url, 'status' => 'error', 'score' => null, 'checks' => [], 'issues' => ['Halaman tidak bisa diambil.']]
                : $this->analyzeHtml($html, $url);
        }

        $ok = array_values(array_filter($pages, fn ($page) => $page['status'] === 'ok'));
        $scores = array_column($ok, 'score');

        return [
            'schema_version' => 2,
            'checked_at' => now()->toDateTimeString(),
            'pages' => $pages,
            'summary' => [
                'pages_checked' => count($ok),
                'avg_score' => $scores === [] ? null : (int) round(array_sum($scores) / count($scores)),
                'pages_with_issues' => count(array_filter($ok, fn ($page) => $page['issues'] !== [])),
            ],
            'recommendation' => $this->recommendation($ok),
        ];
    }

    /** Analyze the answer-readiness signals in one HTML document. */
    public function analyzeHtml(string $html, string $url): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        $xpath = new \DOMXPath($dom);

        $questionHeadings = [];
        foreach ($xpath->query('//h1|//h2|//h3|//h4') as $heading) {
            $text = trim($heading->textContent);
            if ($this->isQuestion($text)) {
                $questionHeadings[] = $heading;
            }
        }

        $answerableQuestions = 0;
        foreach ($questionHeadings as $heading) {
            // Content systems often wrap answers in div/blockquote instead of
            // a direct <p>. Use the immediate next element and skip headings.
            $answer = $xpath->query('following-sibling::*[1]', $heading)?->item(0);
            if ($answer instanceof \DOMElement
                && !in_array(strtolower($answer->tagName), ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)
                && mb_strlen(trim($answer->textContent)) >= 20
                && mb_strlen(trim($answer->textContent)) <= 700) {
                $answerableQuestions++;
            }
        }

        $jsonLd = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $decoded = json_decode(trim($script->textContent), true);
            if (is_array($decoded)) {
                $jsonLd[] = $decoded;
            }
        }
        $schemaTypes = $this->schemaTypes($jsonLd);
        $bodyText = $xpath->query('//body')->item(0)?->textContent ?? '';
        $hasFaq = (bool) preg_match('/\b(faq|frequently asked questions|pertanyaan yang sering|tanya jawab)\b/i', $bodyText)
            || $xpath->query('//details[summary] | //dt[following-sibling::dd]')->length > 0
            || ($answerableQuestions > 0 && count($questionHeadings) >= 2);
        $hasSources = $this->hasSourceEvidence($xpath, $url);
        $hasFreshness = $xpath->query('//time[@datetime]')->length > 0
            || $xpath->query('//meta[@property="article:modified_time" or @property="article:published_time"]')->length > 0
            || (bool) array_intersect(['dateModified', 'datePublished'], $this->schemaProperties($jsonLd));

        $checks = [
            $this->check('question_headings', 'Heading berbentuk pertanyaan', count($questionHeadings) >= 1, count($questionHeadings) . ' heading pertanyaan terdeteksi.', 10),
            $this->check('direct_answers', 'Jawaban langsung', $answerableQuestions > 0 && $answerableQuestions >= (int) ceil(count($questionHeadings) / 2), $answerableQuestions . ' pertanyaan memiliki jawaban ringkas setelah heading.', 35),
            $this->check('faq_content', 'Konten FAQ', $hasFaq, $hasFaq ? 'Konten FAQ/tanya-jawab terdeteksi.' : 'Konten FAQ/tanya-jawab tidak terdeteksi.', 15),
            // Helpful structured data, but not a prerequisite: answer engines
            // can read clear page content without FAQ JSON-LD.
            $this->check('faq_schema', 'Schema FAQ / Q&A', (bool) array_intersect(['FAQPage', 'QAPage', 'Question'], $schemaTypes), 'Schema terdeteksi: ' . (implode(', ', $schemaTypes) ?: 'tidak ada') . '.', 5),
            $this->check('source_evidence', 'Sumber/fakta yang dapat diverifikasi', $hasSources, $hasSources ? 'Tautan eksternal atau penanda sitasi ditemukan.' : 'Sumber eksternal atau sitasi eksplisit tidak terdeteksi.', 20),
            $this->check('content_freshness', 'Tanggal publikasi/pembaruan', $hasFreshness, $hasFreshness ? 'Metadata tanggal konten ditemukan.' : 'Tanggal publikasi/pembaruan tidak terdeteksi.', 15),
        ];

        $score = array_sum(array_map(fn ($check) => $check['status'] === 'pass' ? $check['weight'] : 0, $checks));
        $issues = array_values(array_map(
            fn ($check) => $check['label'] . ': ' . $check['detail'],
            array_filter($checks, fn ($check) => $check['status'] !== 'pass')
        ));

        return [
            'url' => $url,
            'status' => 'ok',
            'score' => (int) round($score),
            'checks' => $checks,
            'issues' => $issues,
        ];
    }

    private function isQuestion(string $heading): bool
    {
        return str_contains($heading, '?')
            || preg_match('/^(apa|bagaimana|mengapa|kenapa|kapan|di mana|dimana|siapa|berapa|what|how|why|when|where|who)\b/i', $heading) === 1;
    }

    private function hasSourceEvidence(\DOMXPath $xpath, string $url): bool
    {
        $siteHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        // Restrict evidence links to main/article content where citations live,
        // not navigation, footer, or social-media links.
        foreach ($xpath->query('//main//a[@href] | //article//a[@href]') as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }
            $host = strtolower((string) parse_url($link->getAttribute('href'), PHP_URL_HOST));
                $label = trim($link->textContent . ' ' . $link->getAttribute('aria-label'));
                $isCitation = preg_match('/\b(sumber|referensi|source|citation|reference|bibliography)\b/i', $label)
                    || preg_match('/^\[?\d{1,3}\]?$/', $label)
                    || strtolower($link->getAttribute('rel')) === 'citation';
                if ($host !== '' && $host !== $siteHost && $isCitation) {
                return true;
            }
        }

        return $xpath->query('//cite | //*[@rel="citation"]')->length > 0;
    }

    private function schemaTypes(array $data): array
    {
        $types = [];
        $walk = function (mixed $node) use (&$walk, &$types): void {
            if (!is_array($node)) {
                return;
            }
            foreach ((array) ($node['@type'] ?? []) as $type) {
                if (is_string($type)) {
                    $types[] = basename(str_replace(['#', '/'], '/', $type));
                }
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($data);

        return array_values(array_unique($types));
    }

    private function schemaProperties(array $data): array
    {
        $properties = [];
        $walk = function (mixed $node) use (&$walk, &$properties): void {
            if (!is_array($node)) {
                return;
            }
            foreach (['dateModified', 'datePublished'] as $property) {
                if (isset($node[$property])) {
                    $properties[] = $property;
                }
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($data);

        return $properties;
    }

    private function check(string $id, string $label, bool $passed, string $detail, int $weight): array
    {
        return ['id' => $id, 'label' => $label, 'status' => $passed ? 'pass' : 'warn', 'detail' => $detail, 'weight' => $weight];
    }

    private function recommendation(array $pages): string
    {
        if ($pages === []) {
            return 'Belum ada halaman yang berhasil diperiksa untuk kesiapan Answer Engine.';
        }

        $issues = [];
        foreach ($pages as $page) {
            foreach ($page['issues'] as $issue) {
                $label = explode(':', $issue, 2)[0];
                $issues[$label] = true;
            }
        }

        return $issues === []
            ? 'Halaman yang diperiksa memiliki sinyal dasar konten yang siap menjawab pertanyaan.'
            : 'Prioritaskan perbaikan AEO berikut: ' . implode(', ', array_slice(array_keys($issues), 0, 4)) . '.';
    }
}