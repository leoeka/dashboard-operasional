<?php

namespace App\Services;

/**
 * SEO — audit on-page dasar tiap halaman kunci: title & meta description,
 * struktur heading, alt gambar, canonical, atribut lang, noindex, jumlah
 * kata, internal link. Murni parsing HTML — tidak ada AI.
 */
class OnPageAuditService
{
    public function __construct(private SitePageFetcher $fetcher)
    {
    }

    /**
     * @param  list<string>  $extraUrls
     * @return array{checked_at:string,pages:list<array<string,mixed>>,summary:array<string,mixed>,recommendation:string}
     */
    public function analyze(string $baseUrl, array $extraUrls = []): array
    {
        $pages = [];

        foreach ($this->fetcher->pages($baseUrl, $extraUrls) as $url) {
            $html = $this->fetcher->html($url);
            $pages[] = $html === null
                ? [
                    'url' => $url,
                    'fetch_status' => 'error',
                    'score' => null,
                    'checks' => [],
                    'issues' => ['Halaman tidak bisa diambil.'],
                    'word_count' => 0,
                    'noindex' => false,
                ]
                : $this->analyzeHtml($html, $url);
        }

        return [
            'checked_at' => now()->toDateTimeString(),
            'pages' => $pages,
            'summary' => $this->summarize($pages),
            'recommendation' => $this->recommendation($pages),
        ];
    }

    /**
     * Audit satu halaman dari HTML mentah — tanpa HTTP, mudah dites.
     *
     * @return array<string,mixed>
     */
    public function analyzeHtml(string $html, string $url): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors(false);
        $xpath = new \DOMXPath($dom);

        $text = fn (string $q) => ($n = $xpath->query($q)->item(0)) ? trim($n->textContent) : '';
        $attr = function (string $q, string $a) use ($xpath): string {
            $n = $xpath->query($q)->item(0);
            return $n instanceof \DOMElement ? trim($n->getAttribute($a)) : '';
        };

        $checks = [];

        // --- title ---
        $title = $text('//head/title');
        $titleLen = mb_strlen($title);
        $checks[] = $this->check('title', 'Title tag', match (true) {
            $title === '' => ['fail', 'Tidak ada <title>.'],
            $titleLen < 30 => ['warn', "Terlalu pendek ({$titleLen} karakter, ideal 30-60)."],
            $titleLen > 65 => ['warn', "Terlalu panjang ({$titleLen} karakter, kemungkinan terpotong di hasil pencarian)."],
            default => ['pass', "{$titleLen} karakter."],
        });

        // --- meta description ---
        $desc = $attr('//meta[translate(@name,"DESCRIPTION","description")="description"]', 'content');
        $descLen = mb_strlen($desc);
        $checks[] = $this->check('meta_description', 'Meta description', match (true) {
            $desc === '' => ['fail', 'Tidak ada meta description.'],
            $descLen < 70 => ['warn', "Terlalu pendek ({$descLen} karakter, ideal 120-160)."],
            $descLen > 165 => ['warn', "Terlalu panjang ({$descLen} karakter, akan terpotong)."],
            default => ['pass', "{$descLen} karakter."],
        });

        // --- H1 ---
        $h1Count = $xpath->query('//h1')->length;
        $checks[] = $this->check('h1', 'Heading H1', match (true) {
            $h1Count === 0 => ['fail', 'Tidak ada H1.'],
            $h1Count > 1 => ['warn', "Ada {$h1Count} H1 — sebaiknya satu per halaman."],
            default => ['pass', 'Tepat satu H1.'],
        });

        // --- hierarki heading ---
        $levels = [];
        foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
            $levels[] = (int) substr($h->nodeName, 1);
        }
        $skips = 0;
        for ($i = 1; $i < count($levels); $i++) {
            if ($levels[$i] - $levels[$i - 1] > 1) {
                $skips++;
            }
        }
        $checks[] = $this->check('heading_order', 'Urutan heading', $skips > 0
            ? ['warn', "{$skips} lompatan level heading (mis. H2 langsung ke H4)."]
            : ['pass', 'Urutan heading rapi.']);

        // --- alt gambar ---
        $imgs = $xpath->query('//img');
        $noAlt = 0;
        foreach ($imgs as $img) {
            if ($img instanceof \DOMElement && trim($img->getAttribute('alt')) === '' && !$img->hasAttribute('aria-hidden')) {
                $noAlt++;
            }
        }
        $checks[] = $this->check('img_alt', 'Alt text gambar', match (true) {
            $imgs->length === 0 => ['pass', 'Tidak ada <img>.'],
            $noAlt === 0 => ['pass', "Semua {$imgs->length} gambar punya alt."],
            default => ['warn', "{$noAlt} dari {$imgs->length} gambar tanpa alt text."],
        });

        // --- canonical ---
        $canonical = $attr('//link[translate(@rel,"CANONICAL","canonical")="canonical"]', 'href');
        $checks[] = $this->check('canonical', 'Canonical URL', $canonical === ''
            ? ['warn', 'Tidak ada <link rel="canonical">.']
            : ['pass', $canonical]);

        // --- lang ---
        $lang = $attr('//html', 'lang');
        $checks[] = $this->check('html_lang', 'Atribut lang', $lang === ''
            ? ['warn', 'Tag <html> tanpa atribut lang.']
            : ['pass', $lang]);

        // --- noindex ---
        $robotsMeta = strtolower($attr('//meta[translate(@name,"ROBOTS","robots")="robots"]', 'content'));
        $noindex = str_contains($robotsMeta, 'noindex');
        $checks[] = $this->check('noindex', 'Indexability', $noindex
            ? ['fail', 'Halaman ini di-set noindex — tidak akan muncul di Google sama sekali.']
            : ['pass', 'Bisa diindeks.']);

        // --- jumlah kata ---
        foreach ($xpath->query('//script|//style|//noscript|//nav|//footer|//header') as $strip) {
            $strip->parentNode?->removeChild($strip);
        }
        $bodyNode = $xpath->query('//body')->item(0);
        $wordCount = str_word_count(trim(preg_replace('/\s+/', ' ', $bodyNode?->textContent ?? '') ?? ''));
        $checks[] = $this->check('word_count', 'Jumlah kata konten', $wordCount < 250
            ? ['warn', "Hanya ~{$wordCount} kata — kemungkinan konten tipis."]
            : ['pass', "~{$wordCount} kata."]);

        // --- internal link ---
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $internal = 0;
        $external = 0;
        foreach ($xpath->query('//a[@href]') as $a) {
            $href = $a instanceof \DOMElement ? trim($a->getAttribute('href')) : '';
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }
            $linkHost = strtolower((string) (parse_url($href, PHP_URL_HOST) ?? ''));
            if ($linkHost === '' || $linkHost === $host) {
                $internal++;
            } else {
                $external++;
            }
        }
        $checks[] = $this->check('internal_links', 'Internal link', $internal < 3
            ? ['warn', "Hanya {$internal} internal link — halaman kurang terhubung ke bagian situs lain."]
            : ['pass', "{$internal} internal, {$external} eksternal."]);

        $fails = count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));
        $warns = count(array_filter($checks, fn ($c) => $c['status'] === 'warn'));
        $score = max(0, min(100, 100 - $fails * 18 - $warns * 7));

        return [
            'url' => $url,
            'fetch_status' => 'ok',
            'score' => $score,
            'checks' => $checks,
            'issues' => array_values(array_map(
                fn ($c) => $c['label'] . ': ' . $c['detail'],
                array_filter($checks, fn ($c) => $c['status'] !== 'pass')
            )),
            'word_count' => $wordCount,
            'noindex' => $noindex,
        ];
    }

    /** @param array{0:string,1:string} $result */
    private function check(string $id, string $label, array $result): array
    {
        return ['id' => $id, 'label' => $label, 'status' => $result[0], 'detail' => $result[1]];
    }

    private function summarize(array $pages): array
    {
        $ok = array_values(array_filter($pages, fn ($p) => $p['fetch_status'] === 'ok'));
        $scores = array_column($ok, 'score');

        return [
            'pages_checked' => count($ok),
            'avg_score' => $scores === [] ? null : (int) round(array_sum($scores) / count($scores)),
            'pages_noindex' => count(array_filter($ok, fn ($p) => $p['noindex'])),
            'pages_with_issues' => count(array_filter($ok, fn ($p) => $p['issues'] !== [])),
        ];
    }

    private function recommendation(array $pages): string
    {
        $ok = array_values(array_filter($pages, fn ($p) => $p['fetch_status'] === 'ok'));
        if ($ok === []) {
            return 'Tidak ada halaman yang bisa diaudit.';
        }

        $noindex = array_filter($ok, fn ($p) => $p['noindex']);
        if ($noindex) {
            return 'PENTING: ' . count($noindex) . ' halaman di-set noindex — periksa apakah itu disengaja, karena halaman tersebut tidak akan pernah muncul di Google.';
        }

        $counts = [];
        foreach ($ok as $p) {
            foreach ($p['checks'] as $c) {
                if ($c['status'] !== 'pass') {
                    $counts[$c['label']] = ($counts[$c['label']] ?? 0) + 1;
                }
            }
        }
        arsort($counts);
        $top = array_slice(array_keys($counts), 0, 3);

        return $top === []
            ? 'On-page dasar semua halaman yang dicek sudah rapi.'
            : 'Isu on-page yang paling sering muncul: ' . implode(', ', $top) . '. Perbaiki ini lebih dulu di seluruh halaman.';
    }
}
