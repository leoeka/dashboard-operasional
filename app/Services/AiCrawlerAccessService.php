<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GEO check: apakah situs klien MENGIZINKAN crawler mesin AI membacanya.
 * Kalau robots.txt memblokir GPTBot / PerplexityBot / Google-Extended dst,
 * situs itu tidak akan pernah dikutip di jawaban ChatGPT / Perplexity /
 * Google AI Overviews — sekencang apa pun SEO klasiknya.
 *
 * Yang dicek cuma robots.txt (+ keberadaan /llms.txt). Blokir di level
 * server / CDN / WAF tidak kelihatan dari sini — itu perlu dicek manual.
 *
 * Parsing robots.txt di sini disederhanakan: yang dinilai hanya "apakah
 * SELURUH situs (`/`) diblokir untuk user-agent ini". Aturan path spesifik
 * (mis. `Disallow: /wp-admin/`) dihitung sebagai "sebagian", bukan blokir
 * penuh — cukup untuk keperluan laporan GEO, bukan implementasi RFC 9309
 * yang lengkap.
 */
class AiCrawlerAccessService
{
    /**
     * Crawler mesin AI yang dicek. `purpose`:
     *  - 'answers'  = dipakai untuk MENJAWAB pertanyaan user secara langsung
     *                 (paling menentukan untuk GEO — ini yang bikin situs
     *                 muncul/dikutip di jawaban AI)
     *  - 'training' = dipakai mengumpulkan data latih model
     *
     * @var list<array{bot:string,vendor:string,purpose:string}>
     */
    public const AI_CRAWLERS = [
        ['bot' => 'GPTBot',             'vendor' => 'OpenAI',      'purpose' => 'training'],
        ['bot' => 'OAI-SearchBot',      'vendor' => 'OpenAI',      'purpose' => 'answers'],
        ['bot' => 'ChatGPT-User',       'vendor' => 'OpenAI',      'purpose' => 'answers'],
        ['bot' => 'Google-Extended',    'vendor' => 'Google',      'purpose' => 'answers'],
        ['bot' => 'PerplexityBot',      'vendor' => 'Perplexity',  'purpose' => 'answers'],
        ['bot' => 'Perplexity-User',    'vendor' => 'Perplexity',  'purpose' => 'answers'],
        ['bot' => 'ClaudeBot',          'vendor' => 'Anthropic',   'purpose' => 'training'],
        ['bot' => 'Claude-User',        'vendor' => 'Anthropic',   'purpose' => 'answers'],
        ['bot' => 'Claude-SearchBot',   'vendor' => 'Anthropic',   'purpose' => 'answers'],
        ['bot' => 'CCBot',              'vendor' => 'Common Crawl', 'purpose' => 'training'],
        ['bot' => 'Applebot-Extended',  'vendor' => 'Apple',       'purpose' => 'training'],
        ['bot' => 'Bytespider',         'vendor' => 'ByteDance',   'purpose' => 'training'],
        ['bot' => 'Meta-ExternalAgent', 'vendor' => 'Meta',        'purpose' => 'training'],
    ];

    /**
     * Ambil robots.txt + llms.txt situs lalu nilai aksesnya.
     *
     * @return array{
     *   checked_at:string, robots_url:string,
     *   robots_status:'found'|'missing'|'error',
     *   has_sitemap_directive:bool, llms_txt:'found'|'missing'|'unknown',
     *   crawlers:list<array{bot:string,vendor:string,purpose:string,access:string,matched_group:?string}>,
     *   summary:array{allowed:int,blocked:int,partial:int},
     *   recommendation:string
     * }
     */
    public function analyze(string $url): array
    {
        $base = $this->baseUrl($url);

        if ($base === null) {
            return $this->baseResult($url, 'error') + [
                'crawlers' => $this->defaultCrawlers('unknown'),
                'summary' => ['allowed' => 0, 'blocked' => 0, 'partial' => 0],
                'recommendation' => 'URL website klien tidak valid, tidak bisa mengecek robots.txt.',
            ];
        }

        $robots = $this->fetchText($base . '/robots.txt');
        $llms = $this->fetchText($base . '/llms.txt');
        $llmsStatus = $llms === null ? 'missing' : 'found';

        // robots.txt tidak ada / tidak terjangkau → secara default SEMUA
        // crawler diizinkan (perilaku standar: "no robots.txt = allow all").
        if ($robots === null) {
            $crawlers = $this->defaultCrawlers('allowed');

            return $this->baseResult($base . '/robots.txt', 'missing') + [
                'llms_txt' => $llmsStatus,
                'has_sitemap_directive' => false,
                'crawlers' => $crawlers,
                'summary' => ['allowed' => count($crawlers), 'blocked' => 0, 'partial' => 0],
                'recommendation' => 'Tidak ada robots.txt — semua crawler AI otomatis diizinkan. Pastikan saja tidak ada blokir di level server / CDN / WAF.',
            ];
        }

        $evaluated = $this->evaluate($robots);

        return $this->baseResult($base . '/robots.txt', 'found') + [
            'llms_txt' => $llmsStatus,
            'has_sitemap_directive' => $evaluated['has_sitemap_directive'],
            'crawlers' => $evaluated['crawlers'],
            'summary' => $evaluated['summary'],
            'recommendation' => $this->recommendation($evaluated['crawlers']),
        ];
    }

    /**
     * Nilai isi robots.txt mentah terhadap daftar crawler AI — TANPA HTTP,
     * supaya gampang dites. `analyze()` di atas yang mengurus fetch-nya.
     *
     * @return array{
     *   crawlers:list<array{bot:string,vendor:string,purpose:string,access:string,matched_group:?string}>,
     *   summary:array{allowed:int,blocked:int,partial:int},
     *   has_sitemap_directive:bool
     * }
     */
    public function evaluate(string $robotsTxt): array
    {
        $groups = $this->parseRobots($robotsTxt);

        $crawlers = [];
        $allowed = $blocked = $partial = 0;

        foreach (self::AI_CRAWLERS as $crawler) {
            [$access, $matched] = $this->accessFor($crawler['bot'], $groups);
            $crawlers[] = $crawler + ['access' => $access, 'matched_group' => $matched];

            match ($access) {
                'blocked' => $blocked++,
                'partial' => $partial++,
                default => $allowed++,
            };
        }

        return [
            'crawlers' => $crawlers,
            'summary' => ['allowed' => $allowed, 'blocked' => $blocked, 'partial' => $partial],
            'has_sitemap_directive' => (bool) preg_match('/^\s*sitemap\s*:/im', $robotsTxt),
        ];
    }

    /**
     * robots.txt → daftar grup [{agents:[...lowercased], rules:[{type,path}]}].
     * User-agent yang berurutan berbagi satu blok aturan; user-agent yang
     * muncul SETELAH baris aturan memulai grup baru (perilaku standar).
     *
     * @return list<array{agents:list<string>,rules:list<array{type:string,path:string}>}>
     */
    private function parseRobots(string $text): array
    {
        $groups = [];
        $current = null;
        $lastWasRule = false;

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $rawLine) {
            $line = trim(preg_replace('/#.*$/', '', $rawLine) ?? '');
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = explode(':', $line, 2);
            $field = strtolower(trim($field));
            $value = trim($value);

            if ($field === 'user-agent') {
                if ($current === null || $lastWasRule) {
                    if ($current !== null) {
                        $groups[] = $current;
                    }
                    $current = ['agents' => [], 'rules' => []];
                    $lastWasRule = false;
                }
                if ($value !== '') {
                    $current['agents'][] = strtolower($value);
                }
            } elseif ($field === 'allow' || $field === 'disallow') {
                if ($current === null) {
                    // Aturan sebelum user-agent apa pun — anggap global.
                    $current = ['agents' => ['*'], 'rules' => []];
                }
                $current['rules'][] = ['type' => $field, 'path' => $value];
                $lastWasRule = true;
            }
        }

        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * @param  list<array{agents:list<string>,rules:list<array{type:string,path:string}>}>  $groups
     * @return array{0:'allowed'|'blocked'|'partial',1:?string}  [access, label grup yang dipakai]
     */
    private function accessFor(string $bot, array $groups): array
    {
        $needle = strtolower($bot);
        $group = null;
        $label = null;

        foreach ($groups as $g) {
            if (in_array($needle, $g['agents'], true)) {
                $group = $g;
                $label = $bot;
                break;
            }
        }

        if ($group === null) {
            foreach ($groups as $g) {
                if (in_array('*', $g['agents'], true)) {
                    $group = $g;
                    $label = '*';
                    break;
                }
            }
        }

        if ($group === null) {
            return ['allowed', null];
        }

        $rootTokens = ['/', '/*', '*'];
        $blocksRoot = false;
        $allowsRoot = false;
        $hasRealDisallow = false;

        foreach ($group['rules'] as $rule) {
            $path = $rule['path'];

            if ($rule['type'] === 'disallow') {
                if ($path === '') {
                    continue; // "Disallow:" kosong = izinkan semua, abaikan
                }
                $hasRealDisallow = true;
                if (in_array($path, $rootTokens, true)) {
                    $blocksRoot = true;
                }
            } elseif ($rule['type'] === 'allow' && in_array($path, $rootTokens, true)) {
                $allowsRoot = true;
            }
        }

        if ($blocksRoot && !$allowsRoot) {
            return ['blocked', $label];
        }

        if ($hasRealDisallow && !$blocksRoot) {
            return ['partial', $label];
        }

        return ['allowed', $label];
    }

    private function recommendation(array $crawlers): string
    {
        $blocked = array_values(array_filter($crawlers, fn ($c) => $c['access'] === 'blocked'));

        if ($blocked === []) {
            return 'Semua crawler AI diizinkan membaca situs. Bagus untuk GEO — tinggal fokus ke struktur konten & schema.';
        }

        $answerBots = array_values(array_filter($blocked, fn ($c) => $c['purpose'] === 'answers'));
        $names = implode(', ', array_map(fn ($c) => $c['bot'], $blocked));

        $msg = count($blocked) . ' crawler AI diblokir di robots.txt (' . $names . '). ';

        if ($answerBots !== []) {
            $answerNames = implode(', ', array_map(fn ($c) => $c['bot'] . ' (' . $c['vendor'] . ')', $answerBots));
            $msg .= 'Yang paling berdampak: ' . $answerNames . ' — selama diblokir, situs klien tidak akan pernah dikutip di jawaban mesin AI tersebut. '
                . 'Hapus baris Disallow untuk user-agent itu, atau kalau blokirnya ada di grup `*`, tambahkan grup khusus yang mengizinkannya. ';
        }

        if (in_array('Google-Extended', array_map(fn ($c) => $c['bot'], $blocked), true)) {
            $msg .= 'Catatan: memblokir Google-Extended TIDAK menurunkan ranking Google Search biasa — hanya menutup situs dari Gemini & AI Overviews.';
        }

        return trim($msg);
    }

    /** @return list<array{bot:string,vendor:string,purpose:string,access:string,matched_group:?string}> */
    private function defaultCrawlers(string $access): array
    {
        return array_map(
            fn ($c) => $c + ['access' => $access, 'matched_group' => null],
            self::AI_CRAWLERS
        );
    }

    /** @return array{checked_at:string,robots_url:string,robots_status:string} */
    private function baseResult(string $robotsUrl, string $status): array
    {
        return [
            'checked_at' => now()->toDateTimeString(),
            'robots_url' => $robotsUrl,
            'robots_status' => $status,
        ];
    }

    /** scheme://host[:port] dari sebuah URL, atau null kalau tidak valid. */
    private function baseUrl(string $url): ?string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . '://' . $host . $port;
    }

    private function fetchText(string $url): ?string
    {
        try {
            $response = Http::timeout(10)
                ->retry(2, 400, throw: false)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; DashboardOperasionalBot/1.0; +internal-seo-audit)'])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $body = trim($response->body());

            // robots.txt / llms.txt yang wajar ukurannya kecil — batasi
            // supaya halaman HTML 404 kustom yang balas 200 tidak ikut
            // diparse sebagai robots.txt raksasa.
            return $body === '' ? null : mb_substr($body, 0, 512 * 1024);
        } catch (\Throwable $e) {
            Log::info('AiCrawlerAccessService: gagal mengambil ' . $url, ['error' => $e->getMessage()]);
            return null;
        }
    }
}
