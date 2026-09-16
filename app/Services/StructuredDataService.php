<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SEO + GEO — deteksi structured data (schema.org) di halaman klien.
 *
 * Schema markup melayani dua hal sekaligus:
 *  - SEO: memicu rich result di Google (bintang rating, FAQ dropdown, dll).
 *  - GEO: memberi mesin AI "label" yang jelas tentang apa isi halaman —
 *    ini bisnis apa, alamatnya, penulisnya, produknya, harganya — sehingga
 *    jawaban AI bisa mengutip fakta yang benar, bukan menebak.
 *
 * Fokus ke JSON-LD (`<script type="application/ld+json">`) karena itu
 * format yang direkomendasikan Google dan yang paling banyak dikonsumsi
 * mesin AI. Microdata / RDFa hanya dideteksi keberadaannya, tidak diparse.
 */
class StructuredDataService
{
    /** Maksimal halaman yang dianalisis dalam satu kali jalan. */
    public const MAX_PAGES = 6;

    /**
     * Schema yang diharapkan ada per jenis halaman. Dipakai untuk menghitung
     * `missing` — bukan aturan kaku, hanya baseline yang wajar untuk GEO/SEO.
     *
     * @var array<string,list<string>>
     */
    private const RECOMMENDED = [
        'homepage' => ['Organization', 'WebSite'],
        'article'  => ['Article', 'BreadcrumbList'],
        'product'  => ['Product', 'BreadcrumbList'],
        'faq'      => ['FAQPage'],
        'service'  => ['Service', 'BreadcrumbList'],
        'contact'  => ['LocalBusiness'],
        'about'    => ['Organization'],
        'generic'  => ['BreadcrumbList'],
    ];

    /**
     * Ambil beberapa halaman situs dan nilai structured data-nya.
     *
     * @param  list<string>  $extraUrls  URL tambahan (mis. top page dari Search Console / GA4).
     * @return array{
     *   checked_at:string,
     *   pages:list<array<string,mixed>>,
     *   summary:array{pages_checked:int,pages_with_schema:int,distinct_types:int,pages_missing_recommended:int},
     *   recommendation:string
     * }
     */
    public function analyze(string $baseUrl, array $extraUrls = [], bool $isLocalBusiness = false): array
    {
        $urls = $this->buildPageList($baseUrl, $extraUrls);
        $pages = [];

        foreach ($urls as $url) {
            $html = $this->fetchHtml($url);

            if ($html === null) {
                $pages[] = [
                    'url' => $url,
                    'fetch_status' => 'error',
                    'page_type_guess' => $this->guessPageType($url, ''),
                    'jsonld_blocks' => 0,
                    'jsonld_invalid' => 0,
                    'types_found' => [],
                    'has_microdata' => false,
                    'recommended' => [],
                    'missing' => [],
                    'notes' => ['Halaman tidak bisa diambil.'],
                ];
                continue;
            }

            $pages[] = $this->analyzeHtml($html, $url, $isLocalBusiness);
        }

        return [
            'checked_at' => now()->toDateTimeString(),
            'pages' => $pages,
            'summary' => $this->summarize($pages),
            'recommendation' => $this->recommendation($pages),
        ];
    }

    /**
     * Nilai structured data satu halaman dari HTML mentah — TANPA HTTP,
     * supaya gampang dites.
     *
     * @return array<string,mixed>
     */
    public function analyzeHtml(string $html, string $url, bool $isLocalBusiness = false): array
    {
        $jsonLd = $this->extractJsonLd($html);
        $typesFound = $this->collectTypes($jsonLd['blocks']);
        $pageType = $this->guessPageType($url, $html);

        $recommended = self::RECOMMENDED[$pageType] ?? self::RECOMMENDED['generic'];
        if ($pageType === 'homepage' && $isLocalBusiness) {
            $recommended[] = 'LocalBusiness';
        }

        // LocalBusiness adalah subtipe Organization — kalau salah satunya
        // ada, anggap kebutuhan "Organization" terpenuhi.
        $satisfied = $typesFound;
        if (array_intersect(['LocalBusiness', 'Store', 'Restaurant', 'MedicalBusiness'], $typesFound)) {
            $satisfied[] = 'Organization';
        }

        $missing = array_values(array_diff($recommended, $satisfied));

        return [
            'url' => $url,
            'fetch_status' => 'ok',
            'page_type_guess' => $pageType,
            'jsonld_blocks' => $jsonLd['raw_count'],
            'jsonld_invalid' => $jsonLd['invalid'],
            'types_found' => $typesFound,
            'has_microdata' => $this->hasMicrodata($html),
            'recommended' => array_values(array_unique($recommended)),
            'missing' => $missing,
            'notes' => $this->qualityNotes($jsonLd['blocks'], $typesFound, $jsonLd['invalid']),
        ];
    }

    /**
     * @return array{blocks:list<array<string,mixed>>, raw_count:int, invalid:int}
     */
    private function extractJsonLd(string $html): array
    {
        $blocks = [];
        $invalid = 0;
        $rawCount = 0;

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
            $rawCount++;
            $raw = trim($node->textContent);
            if ($raw === '') {
                $invalid++;
                continue;
            }

            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $invalid++;
                continue;
            }

            $blocks[] = $decoded;
        }

        return ['blocks' => $blocks, 'raw_count' => $rawCount, 'invalid' => $invalid];
    }

    /**
     * Semua @type unik dari sekumpulan blok JSON-LD, sadar `@graph` dan
     * array bertingkat.
     *
     * @param  list<array<string,mixed>>  $blocks
     * @return list<string>
     */
    private function collectTypes(array $blocks): array
    {
        $types = [];

        foreach ($this->flattenNodes($blocks) as $node) {
            foreach ((array) ($node['@type'] ?? []) as $t) {
                if (is_string($t) && $t !== '') {
                    $types[] = $this->shortType($t);
                }
            }
        }

        sort($types);

        return array_values(array_unique($types));
    }

    /**
     * Ratakan struktur JSON-LD (objek tunggal / array / `@graph` /
     * container umum seperti mainEntity) jadi daftar node datar.
     *
     * @return list<array<string,mixed>>
     */
    private function flattenNodes(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        // Daftar node
        if (array_is_list($data)) {
            $out = [];
            foreach ($data as $item) {
                $out = array_merge($out, $this->flattenNodes($item));
            }
            return $out;
        }

        $nodes = [];

        if (isset($data['@graph']) && is_array($data['@graph'])) {
            $nodes = array_merge($nodes, $this->flattenNodes($data['@graph']));
        }

        if (isset($data['@type'])) {
            $nodes[] = $data;
        }

        // Turun satu tingkat ke container yang lazim membungkus tipe lain.
        foreach (['mainEntity', 'itemListElement', 'about', 'publisher', 'author', 'offers'] as $key) {
            if (isset($data[$key])) {
                $nodes = array_merge($nodes, $this->flattenNodes($data[$key]));
            }
        }

        return $nodes;
    }

    private function shortType(string $type): string
    {
        $type = preg_replace('#^https?://schema\.org/#i', '', $type) ?? $type;

        return trim($type, '/');
    }

    private function hasMicrodata(string $html): bool
    {
        return (bool) preg_match('/\sitemscope[\s=>]/i', $html)
            || (bool) preg_match('/\stypeof\s*=\s*["\']/i', $html);
    }

    private function guessPageType(string $url, string $html): string
    {
        $path = strtolower(trim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/'));

        if ($path === '' || $path === 'index.html' || $path === 'home') {
            return 'homepage';
        }

        $map = [
            'article' => ['blog', 'artikel', 'article', 'news', 'berita', 'post'],
            'product' => ['product', 'produk', 'shop', 'toko', 'store'],
            'faq'     => ['faq', 'tanya-jawab', 'pertanyaan'],
            'service' => ['service', 'layanan', 'jasa'],
            'contact' => ['contact', 'kontak', 'hubungi'],
            'about'   => ['about', 'tentang', 'profil'],
        ];

        foreach ($map as $type => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($path, $needle)) {
                    return $type;
                }
            }
        }

        // Halaman dengan <article> yang jelas → perlakukan sebagai artikel.
        if (preg_match('/<article[\s>]/i', $html)) {
            return 'article';
        }

        return 'generic';
    }

    /**
     * @param  list<array<string,mixed>>  $blocks
     * @param  list<string>  $typesFound
     * @return list<string>
     */
    private function qualityNotes(array $blocks, array $typesFound, int $invalid): array
    {
        $notes = [];

        if ($invalid > 0) {
            $notes[] = "{$invalid} blok JSON-LD gagal diparse — schema rusak akan diabaikan Google & mesin AI.";
        }

        if ($typesFound === []) {
            $notes[] = 'Tidak ada JSON-LD sama sekali — mesin AI harus menebak isi halaman dari teks mentah.';
            return $notes;
        }

        $nodes = $this->flattenNodes($blocks);
        $has = fn (string $type, string $prop) => collect($nodes)->contains(
            fn ($n) => in_array($type, array_map(fn ($t) => $this->shortType((string) $t), (array) ($n['@type'] ?? [])), true)
                && !empty($n[$prop])
        );
        $typePresent = fn (string $type) => in_array($type, $typesFound, true);

        if (($typePresent('Organization') || $typePresent('LocalBusiness')) && !$has('Organization', 'sameAs') && !$has('LocalBusiness', 'sameAs')) {
            $notes[] = "Organization/LocalBusiness tanpa properti 'sameAs' — tambahkan link ke medsos/Wikidata supaya mesin AI mengenali entitas brand.";
        }

        if (($typePresent('Article') || $typePresent('BlogPosting')) && (!$has('Article', 'author') && !$has('BlogPosting', 'author'))) {
            $notes[] = "Artikel tanpa 'author' — sinyal E-E-A-T yang penting untuk kutipan AI.";
        }

        if ($typePresent('Product') && !$has('Product', 'offers')) {
            $notes[] = "Product tanpa 'offers' (harga/ketersediaan) — rich result & kutipan AI jadi tidak lengkap.";
        }

        return $notes;
    }

    /** @param  list<array<string,mixed>>  $pages */
    private function summarize(array $pages): array
    {
        $ok = array_values(array_filter($pages, fn ($p) => $p['fetch_status'] === 'ok'));
        $distinct = [];
        foreach ($ok as $p) {
            $distinct = array_merge($distinct, $p['types_found']);
        }

        return [
            'pages_checked' => count($ok),
            'pages_with_schema' => count(array_filter($ok, fn ($p) => $p['types_found'] !== [])),
            'distinct_types' => count(array_unique($distinct)),
            'pages_missing_recommended' => count(array_filter($ok, fn ($p) => $p['missing'] !== [])),
        ];
    }

    /** @param  list<array<string,mixed>>  $pages */
    private function recommendation(array $pages): string
    {
        $ok = array_values(array_filter($pages, fn ($p) => $p['fetch_status'] === 'ok'));

        if ($ok === []) {
            return 'Tidak ada halaman yang bisa dianalisis.';
        }

        $noSchema = array_values(array_filter($ok, fn ($p) => $p['types_found'] === []));
        if (count($noSchema) === count($ok)) {
            return 'Belum ada structured data sama sekali. Prioritas pertama: pasang JSON-LD Organization + WebSite di homepage, lalu schema sesuai jenis halaman (Article/Product/FAQPage/LocalBusiness).';
        }

        $allMissing = [];
        foreach ($ok as $p) {
            $allMissing = array_merge($allMissing, $p['missing']);
        }
        $allMissing = array_values(array_unique($allMissing));

        if ($allMissing === []) {
            return 'Cakupan schema dasar sudah lengkap. Lanjutkan dengan melengkapi properti tiap schema (sameAs, author, offers, aggregateRating) dan menambah FAQPage di halaman yang relevan.';
        }

        return 'Schema yang masih kurang di sejumlah halaman: ' . implode(', ', $allMissing)
            . '. Tambahkan JSON-LD untuk tipe tersebut sesuai jenis halamannya.';
    }

    /** @param  list<string>  $extraUrls @return list<string> */
    private function buildPageList(string $baseUrl, array $extraUrls): array
    {
        $baseHost = strtolower((string) (parse_url($baseUrl, PHP_URL_HOST) ?? ''));
        $urls = [$baseUrl];

        foreach ($extraUrls as $url) {
            $url = trim($url);
            if ($url === '' || in_array($url, $urls, true)) {
                continue;
            }
            // Relatif ("/blog/x") → jadikan absolut terhadap baseUrl.
            if (str_starts_with($url, '/')) {
                $url = rtrim($baseUrl, '/') . $url;
            }
            if (strtolower((string) (parse_url($url, PHP_URL_HOST) ?? '')) !== $baseHost) {
                continue;
            }
            if (!CompetitorContentFetcher::isSafeUrl($url)) {
                continue;
            }
            $urls[] = $url;
            if (count($urls) >= self::MAX_PAGES) {
                break;
            }
        }

        return $urls;
    }

    private function fetchHtml(string $url): ?string
    {
        if (!CompetitorContentFetcher::isSafeUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->retry(2, 400, throw: false)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; DashboardOperasionalBot/1.0; +internal-seo-audit)'])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $body = $response->body();

            return trim($body) === '' ? null : mb_substr($body, 0, 2 * 1024 * 1024);
        } catch (\Throwable $e) {
            Log::info('StructuredDataService: gagal mengambil ' . $url, ['error' => $e->getMessage()]);
            return null;
        }
    }
}
