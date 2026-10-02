<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Support\CompositionSpec;

/**
 * Hard content-integrity gate between the content stage and everything the
 * client sees.
 *
 * The content stage (Gemini) writes the whole website, and a language model
 * asked for a travel site will happily supply "12.000+ happy guests",
 * "Rina, Jakarta — amazing service" and a "Most Popular" package. None of that
 * is true unless the client said so. Prompting against it is not enough, so
 * this runs deterministically on the response before the sitemap becomes final.
 *
 * The only source of truth is what the client actually gave us: the project
 * and client records (see evidence()). Competitor pages and design references
 * are deliberately NOT evidence — they may shape structure and tone upstream,
 * but a number, quote or name that only exists there can never become a fact
 * about this client.
 *
 * Nothing is invented to fill a gap: an unsupported claim is removed, and a
 * section that was nothing but unsupported claims (testimonials, stats, a price
 * table without prices) is removed whole.
 */
class ContentIntegrityService
{
    /** Sentence-level claims that need no digit to be a factual assertion. */
    private const CLAIM_PHRASES = [
        'award',
        'penghargaan',
        'certified',
        'certification',
        'bersertifikat',
        'sertifikasi',
        'tersertifikasi',
        'accredited',
        'terakreditasi',
        'licensed',
        'berlisensi',
        'trusted by',
        'dipercaya oleh',
        'rated',
        'rating',
        'five-star',
        'five star',
        'bintang lima',
        '#1',
        'nomor satu',
        'number one',
        'terbaik di',
        'best in',
        'official partner',
        'mitra resmi',
        'partner resmi',
        'as seen on',
        'featured in',
        'diliput',
    ];

    private const OPERATIONAL_CLAIM_PHRASES = [
        'flight tracking',
        'track your flight',
        'track flights',
        'waiting fee',
        'waiting fees',
        'additional waiting fee',
        'additional waiting fees',
    ];

    /**
     * Badge wording removed from copy when the client never used it. Narrower
     * than HIGHLIGHT_PHRASES: words like "unggulan" or "favorit" are ordinary
     * copy ("Destinasi Unggulan") and must not be stripped from text.
     */
    private const BADGE_PHRASES = [
        'most popular',
        'paling populer',
        'best value',
        'best seller',
        'bestseller',
        'terlaris',
        'paling laris',
    ];

    /** Words by which the client may single out a tier themselves. */
    private const HIGHLIGHT_PHRASES = [
        'most popular',
        'paling populer',
        'recommended',
        'rekomendasi',
        'direkomendasikan',
        'best value',
        'best seller',
        'bestseller',
        'terlaris',
        'paling laris',
        'favorit',
        'favorite',
        'featured',
        'unggulan',
    ];

    private const HIGHLIGHT_KEYS = ['featured', 'is_featured', 'highlight', 'highlighted', 'recommended', 'popular', 'badge'];

    private const PRICE_KEYS = ['price', 'harga', 'amount', 'monthly', 'discount', 'diskon'];

    private string $evidence = '';

    /** @var array<int, string> normalised numbers the client actually stated */
    private array $evidenceNumbers = [];

    /** @var array<int, string> names of competitor/reference sites */
    private array $foreignNames = [];

    /** @var array<int, array{where:string, reason:string, text:string}> */
    private array $removed = [];

    /**
     * @return array the analysis with its sitemap sanitised, plus a
     *               `content_integrity` report listing every removal.
     */
    public function sanitize(array $analysis, Project $project, ?Client $client = null, array $competitorContents = []): array
    {
        $this->evidence = $this->normalise($this->evidence($project, $client));
        $this->evidenceNumbers = $this->numbers($this->evidence);
        $this->foreignNames = $this->foreignNames($project, $competitorContents);
        $this->removed = [];

        $sitemap = is_array($analysis['sitemap'] ?? null) ? $analysis['sitemap'] : [];

        foreach (['website_concept', 'global_cta'] as $key) {
            if (is_string($sitemap[$key] ?? null)) {
                $sitemap[$key] = $this->cleanText($sitemap[$key], "sitemap.{$key}");
            }
        }
        if (is_array($sitemap['seo'] ?? null)) {
            foreach ($sitemap['seo'] as $key => $value) {
                if (is_string($value)) {
                    $sitemap['seo'][$key] = $this->cleanText($value, "seo.{$key}");
                }
            }
        }

        $pages = [];
        foreach (is_array($sitemap['pages'] ?? null) ? $sitemap['pages'] : [] as $pageIndex => $page) {
            if (!is_array($page)) {
                continue;
            }

            $sections = [];
            foreach (array_values(is_array($page['sections'] ?? null) ? $page['sections'] : []) as $sectionIndex => $section) {
                if (!is_array($section)) {
                    continue;
                }

                $where = 'page ' . $pageIndex . ' "' . ($page['name'] ?? '') . '" section ' . $sectionIndex . ' "' . ($section['name'] ?? $section['type'] ?? '') . '"';
                $clean = $this->section($section, $where, $sectionIndex === 0);

                if ($clean !== null) {
                    $sections[] = $clean;
                }
            }

            $page['sections'] = $sections;
            $pages[] = $page;
        }

        $sitemap['pages'] = $pages;
        $analysis['sitemap'] = $sitemap;
        $analysis['content_integrity'] = ['checked' => true, 'removed' => $this->removed];

        return $analysis;
    }

    /** Everything the client told us, and nothing anybody else did. */
    public function evidence(Project $project, ?Client $client): string
    {
        $parts = [
            $project->name,
            $project->client_name,
            $project->website_name,
            $project->type,
            $project->description,
            $project->target_market,
            $this->flatten($project->seo_requirements),
            $this->flatten($project->backlink_requirements),
        ];

        if ($client) {
            foreach (['company_name', 'contact_name', 'email', 'phone', 'whatsapp', 'address', 'website', 'instagram', 'notes'] as $field) {
                $parts[] = $client->{$field};
            }
        }

        return implode("\n", array_filter(array_map(fn($p) => is_scalar($p) ? (string) $p : '', $parts)));
    }

    /** One section, or null when nothing truthful is left of it. */
    private function section(array $section, string $where, bool $isHero): ?array
    {
        // Shape is judged on what Gemini wrote, before anything is removed.
        $shape = $isHero ? 'hero' : CompositionSpec::sectionShape($section);
        $label = strtolower(($section['type'] ?? '') . ' ' . ($section['name'] ?? ''));
        $isTestimonials = $shape === 'testimonials' || $this->mentions($label, ['testimon', 'review', 'ulasan', 'kata mereka']);
        $isStats = $shape === 'stats' || $this->mentions($label, ['stat', 'angka', 'pencapaian', 'achievement', 'numbers', 'in numbers']);
        $isPricing = $shape === 'pricing' || $this->mentions($label, ['pricing', 'harga', 'paket', 'price']);

        foreach (['headline', 'description', 'cta', 'name'] as $key) {
            if (is_string($section[$key] ?? null)) {
                $section[$key] = $this->cleanText($section[$key], "{$where} {$key}");
            }
        }

        $items = [];
        foreach (is_array($section['items'] ?? null) ? array_values($section['items']) : [] as $itemIndex => $item) {
            $clean = $this->item($item, "{$where} item {$itemIndex}", $isTestimonials, $isStats);
            if ($clean !== null) {
                $items[] = $clean;
            }
        }

        $hadItems = !empty($section['items']);
        $section['items'] = $items;

        // A section whose whole purpose was an unsupported claim is removed,
        // not left standing as an empty frame or refilled with dummy data.
        if (!$isHero && $hadItems && !$items && ($isTestimonials || $isStats)) {
            $this->log($where, $isTestimonials ? 'testimonial section without client-supplied testimonials' : 'stats section without client-supplied figures', (string) ($section['headline'] ?? ''));

            return null;
        }

        if (!$isHero && $isPricing && $hadItems && !array_filter($items, fn($i) => is_array($i) && array_intersect_key($i, array_flip(self::PRICE_KEYS)))) {
            $this->log($where, 'pricing section without client-supplied prices', (string) ($section['headline'] ?? ''));

            return null;
        }

        return $section;
    }

    private function item(mixed $item, string $where, bool $isTestimonial, bool $isStat): ?array
    {
        if (!is_array($item)) {
            $text = $this->cleanText(is_scalar($item) ? (string) $item : '', $where);

            return $text !== '' ? ['title' => $text] : null;
        }

        // A testimonial is kept only when its words are the client's own.
        if ($isTestimonial) {
            $quote = (string) ($item['quote'] ?? $item['testimonial'] ?? $item['description'] ?? '');
            if (!$this->quoted($quote)) {
                $this->log($where, 'testimonial not supplied by the client', $quote);

                return null;
            }

            return $item;
        }

        // A statistic is kept only when its figure is one the client stated.
        if ($isStat) {
            $value = (string) ($item['value'] ?? $item['number'] ?? $item['stat'] ?? $item['angka'] ?? $item['title'] ?? '');
            if (!$this->numbers($value) || !$this->supportedNumbers($value)) {
                $this->log($where, 'statistic not supplied by the client', $value);

                return null;
            }

            return $item;
        }

        foreach (self::PRICE_KEYS as $key) {
            if (array_key_exists($key, $item) && !$this->supportedNumbers((string) (is_scalar($item[$key]) ? $item[$key] : ''))) {
                $this->log($where, 'price not supplied by the client', (string) (is_scalar($item[$key]) ? $item[$key] : ''));
                unset($item[$key]);
            }
        }

        $this->dropUnfoundedHighlight($item, $where);

        foreach ($item as $key => $value) {
            if (is_string($value)) {
                $item[$key] = $this->cleanText($value, "{$where} {$key}");
            } elseif (is_array($value) && array_is_list($value)) {
                $item[$key] = array_values(array_filter(array_map(
                    fn($v) => is_string($v) ? $this->cleanText($v, "{$where} {$key}") : $v,
                    $value
                ), fn($v) => $v !== ''));
            }
        }

        $hasContent = array_filter($item, fn($v) => (is_string($v) && trim($v) !== '') || (is_array($v) && $v));

        return $hasContent ? $item : null;
    }

    /**
     * A tier is highlighted only when the client's own words single it out
     * ("paket Favorit paling laris"), never because it sits in the middle.
     */
    private function dropUnfoundedHighlight(array &$item, string $where): void
    {
        $title = strtolower(trim((string) ($item['title'] ?? $item['name'] ?? '')));
        $founded = $title !== '' && $this->clientHighlights($title);

        foreach (self::HIGHLIGHT_KEYS as $key) {
            if (array_key_exists($key, $item) && !$founded) {
                $this->log($where, 'pricing highlight without client basis', $key);
                unset($item[$key]);
            }
        }

        if ($founded) {
            $item['featured'] = true;
        }
    }

    private function clientHighlights(string $title): bool
    {
        foreach (preg_split('/(?<=[.!?\n])\s*/u', $this->evidence) ?: [] as $sentence) {
            if (str_contains($sentence, $title) && $this->mentions($sentence, self::HIGHLIGHT_PHRASES)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes each sentence that asserts something the client never said: a
     * figure they did not give, an award/rating-style claim, a highlight badge,
     * or the name of a competitor/reference site.
     */
    private function cleanText(string $text, string $where): string
    {
        if (trim($text) === '') {
            return $text;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [$text];
        $kept = [];

        foreach ($sentences as $sentence) {
            $reason = $this->unsupportedClaim($sentence);
            if ($reason === null) {
                $kept[] = $sentence;
                continue;
            }

            $this->log($where, $reason, $sentence);
        }

        return trim(implode(' ', $kept));
    }

    private function unsupportedClaim(string $sentence): ?string
    {
        $lower = $this->normalise($sentence);

        if ($this->numbers($lower) && !$this->supportedNumbers($lower)) {
            return 'figure not supplied by the client';
        }

        foreach (self::CLAIM_PHRASES as $phrase) {
            if (
                $this->hasPhrase($lower, $phrase) && !$this->hasPhrase($this->evidence, $phrase)
            ) {
                return 'claim not supplied by the client';
            }
        }

        foreach (self::OPERATIONAL_CLAIM_PHRASES as $phrase) {
            if (
                $this->hasPhrase($lower, $phrase)
                && !$this->hasPhrase($this->evidence, $phrase)
            ) {
                return 'service capability not supplied by the client';
            }
        }

        foreach (self::HIGHLIGHT_PHRASES as $phrase) {
            if (
                $this->hasPhrase($lower, $phrase) && !$this->hasPhrase($this->evidence, $phrase)
            ) {
                return 'claim not supplied by the client';
            }
        }

        foreach ($this->foreignNames as $name) {
            if (preg_match('/\b' . preg_quote($name, '/') . '\b/u', $lower)) {
                return 'competitor/reference name in client content';
            }
        }

        return null;
    }

    private function quoted(string $quote): bool
    {
        $quote = $this->normalise(trim($quote, " \t\n\"'“”"));

        return mb_strlen($quote) >= 12 && str_contains($this->evidence, $quote);
    }

    private function supportedNumbers(string $text): bool
    {
        foreach ($this->numbers($text) as $number) {
            if (!in_array($number, $this->evidenceNumbers, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, string> every figure in the text, separators removed ("12.000" and "12,000" are the same) */
    private function numbers(string $text): array
    {
        preg_match_all('/\d+(?:[.,]\d+)*/u', $text, $matches);

        return array_values(array_unique(array_map(fn($n) => preg_replace('/\D/', '', $n), $matches[0])));
    }

    /**
     * Site names from competitor URLs and the design reference — the
     * registrable label and the first path segment ("goodlayers",
     * "traveltour") — unless the client's own details contain them.
     */
    private function foreignNames(Project $project, array $competitorContents): array
    {
        $urls = array_filter(array_merge(
            array_map(fn($c) => is_array($c) ? ($c['url'] ?? null) : null, $competitorContents),
            [$project->design_reference_url]
        ));

        $names = [];
        foreach ($urls as $url) {
            $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
            $labels = array_values(array_filter(explode('.', preg_replace('/^www\./', '', $host))));
            if (count($labels) >= 2) {
                $names[] = $labels[count($labels) - 2];
            }

            $segment = strtolower(trim(explode('/', trim((string) parse_url((string) $url, PHP_URL_PATH), '/'))[0] ?? ''));
            if ($segment !== '') {
                $names[] = $segment;
            }
        }

        return array_values(array_filter(array_unique($names), fn($name) => mb_strlen($name) >= 4 && !str_contains($this->evidence, $name)));
    }

    /** Whether any of the words starts a word in the text ("review" is not "preview"). */
    private function mentions(string $text, array $words): bool
    {
        foreach ($words as $word) {
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '/u', $text)) {
                return true;
            }
        }

        return false;
    }

    /** Whole-phrase match: "rated" is a claim, "curated" is not. */
    private function hasPhrase(string $text, string $phrase): bool
    {
        return (bool) preg_match('/(?<![\p{L}\p{N}])' . preg_quote($phrase, '/') . '(?![\p{L}\p{N}])/u', $text);
    }

    private function normalise(string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function flatten(mixed $value): string
    {
        if (is_array($value)) {
            return implode(' ', array_map(fn($v) => $this->flatten($v), $value));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function log(string $where, string $reason, string $text): void
    {
        $this->removed[] = ['where' => $where, 'reason' => $reason, 'text' => mb_substr($text, 0, 200)];
    }
}
