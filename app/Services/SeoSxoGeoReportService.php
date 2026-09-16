<?php

namespace App\Services;

use App\Models\Project;

/**
 * Rakit laporan SEO / SXO / GEO dari data yang SUDAH tersimpan di
 * projects.seo_requirements (PageSpeed, structured data, akses AI crawler,
 * CTR gap, GA4). Tidak ada panggilan API di sini — cuma menyusun,
 * memberi skor, dan mengurutkan daftar perbaikan. Dipakai oleh
 * SeoBacklinkController::downloadSeoSxoGeoReport.
 */
class SeoSxoGeoReportService
{
    public function __construct(private SxoScorecardService $sxoScorecard)
    {
    }

    public function assemble(Project $project): array
    {
        $seo = $project->seo_requirements ?? [];

        $pagespeed = is_array($seo['pagespeed']['mobile'] ?? null) ? $seo['pagespeed']['mobile'] : null;
        $structured = is_array($seo['structured_data'] ?? null) ? $seo['structured_data'] : null;
        $crawler = is_array($seo['ai_crawler_access'] ?? null) ? $seo['ai_crawler_access'] : null;
        $ctr = is_array($seo['ctr_gaps'] ?? null) ? $seo['ctr_gaps'] : null;
        $onpage = is_array($seo['onpage_audit'] ?? null) ? $seo['onpage_audit'] : null;
        $technical = is_array($seo['technical_seo'] ?? null) ? $seo['technical_seo'] : null;
        $extract = is_array($seo['content_extractability'] ?? null) ? $seo['content_extractability'] : null;
        $byLandingPage = $seo['google_analytics']['by_landing_page'] ?? [];
        $scorecard = !empty($byLandingPage) ? $this->sxoScorecard->build($byLandingPage) : null;

        return [
            'project' => $project,
            'generatedAt' => now()->format('d F Y H:i'),
            'scores' => [
                'seo' => $this->seoScore($pagespeed, $structured, $onpage, $technical),
                'sxo' => $this->sxoScore($pagespeed, $ctr, $scorecard),
                'geo' => $this->geoScore($crawler, $structured, $extract),
            ],
            'pagespeed' => $pagespeed,
            'structured' => $structured,
            'crawler' => $crawler,
            'ctr' => $ctr,
            'onpage' => $onpage,
            'technical' => $technical,
            'extract' => $extract,
            'scorecard' => $scorecard,
            'actions' => $this->actionList($pagespeed, $structured, $crawler, $ctr, $scorecard, $onpage, $technical, $extract),
            'analysedNothing' => !$pagespeed && !$structured && !$crawler && !$ctr && !$scorecard && !$onpage && !$technical && !$extract,
        ];
    }

    // ---- skor per lapis -------------------------------------------------

    private function seoScore(?array $pagespeed, ?array $structured, ?array $onpage, ?array $technical): array
    {
        $w = config('seo_scoring.weights.seo');
        $parts = [];

        if ($pagespeed !== null) {
            $parts[] = $this->part('Skor SEO Lighthouse', $pagespeed['scores']['seo'] ?? null, $w['lighthouse_seo']);
        }
        if ($structured !== null) {
            $parts[] = $this->part('Cakupan structured data', $this->structuredCoverageScore($structured), $w['structured_data']);
        }
        if ($onpage !== null) {
            $parts[] = $this->part('On-page tiap halaman', $onpage['summary']['avg_score'] ?? null, $w['onpage']);
        }
        if ($technical !== null) {
            $parts[] = $this->part('SEO teknis situs', $technical['score'] ?? null, $w['technical']);
        }

        return $this->rollUp($parts);
    }

    private function sxoScore(?array $pagespeed, ?array $ctr, ?array $scorecard): array
    {
        $w = config('seo_scoring.weights.sxo');
        $parts = [];

        if ($pagespeed !== null) {
            $parts[] = $this->part('Core Web Vitals', $this->cwvScore($pagespeed), $w['core_web_vitals']);
            $parts[] = $this->part('Aksesibilitas', $pagespeed['scores']['accessibility'] ?? null, $w['accessibility']);
        }
        if ($ctr !== null) {
            $count = (int) ($ctr['summary']['opportunity_count'] ?? 0);
            $parts[] = $this->part('CTR judul & meta', max(0, 100 - min(60, $count * 10)), $w['ctr']);
        }
        if ($scorecard !== null) {
            $rate = (float) ($scorecard['summary']['avg_engagement_rate'] ?? 0);
            $parts[] = $this->part('Engagement pengunjung', round(min(1, $rate) * 100), $w['engagement']);
        }

        return $this->rollUp($parts);
    }

    private function geoScore(?array $crawler, ?array $structured, ?array $extract): array
    {
        $w = config('seo_scoring.weights.geo');
        $parts = [];

        if ($crawler !== null) {
            $parts[] = $this->part('Akses crawler AI', $this->crawlerScore($crawler), $w['ai_crawler_access']);
            $parts[] = $this->part('llms.txt', ($crawler['llms_txt'] ?? 'missing') === 'found' ? 100 : 40, $w['llms_txt']);
        }
        if ($structured !== null) {
            $distinct = (int) ($structured['summary']['distinct_types'] ?? 0);
            $parts[] = $this->part('Schema untuk mesin AI', min(100, $distinct * 15), $w['structured_data']);
        }
        if ($extract !== null) {
            $parts[] = $this->part('Konten mudah dikutip AI', $extract['summary']['avg_score'] ?? null, $w['extractability']);
        }

        return $this->rollUp($parts);
    }

    // ---- sub-skor -----------------------------------------------------

    private function structuredCoverageScore(array $structured): float
    {
        $checked = max(1, (int) ($structured['summary']['pages_checked'] ?? 0));
        $withSchema = (int) ($structured['summary']['pages_with_schema'] ?? 0);
        $missing = (int) ($structured['summary']['pages_missing_recommended'] ?? 0);

        return max(0, min(100, ($withSchema / $checked) * 100 - $missing * 10));
    }

    private function cwvScore(array $pagespeed): ?float
    {
        $map = ['good' => 100, 'needs_improvement' => 60, 'poor' => 25];
        $scores = [];

        foreach (['lcp', 'cls', 'inp'] as $metric) {
            $status = $pagespeed['metrics'][$metric]['status'] ?? null;
            if (isset($map[$status])) {
                $scores[] = $map[$status];
            }
        }

        return $scores === [] ? null : array_sum($scores) / count($scores);
    }

    private function crawlerScore(array $crawler): float
    {
        $answerBots = array_values(array_filter(
            $crawler['crawlers'] ?? [],
            fn ($c) => ($c['purpose'] ?? '') === 'answers'
        ));

        if ($answerBots === []) {
            return 50;
        }

        $points = 0;
        foreach ($answerBots as $bot) {
            $points += match ($bot['access'] ?? '') {
                'allowed' => 1,
                'partial' => 0.7,
                default => 0,
            };
        }

        return round($points / count($answerBots) * 100);
    }

    // ---- helpers skor ----------------------------------------------

    private function part(string $label, float|int|null $score, float $weight): array
    {
        return [
            'label' => $label,
            'score' => $score === null ? null : (int) round($score),
            'weight' => $weight,
        ];
    }

    private function rollUp(array $parts): array
    {
        $scored = array_values(array_filter($parts, fn ($p) => $p['score'] !== null));

        if ($scored === []) {
            return ['value' => null, 'status' => 'unknown', 'parts' => $parts];
        }

        $totalWeight = array_sum(array_column($scored, 'weight'));
        $weighted = array_sum(array_map(fn ($p) => $p['score'] * $p['weight'], $scored));
        $value = (int) round($weighted / $totalWeight);

        $t = config('seo_scoring.thresholds');
        $status = $value >= $t['good'] ? 'good' : ($value >= $t['needs_improvement'] ? 'needs_improvement' : 'poor');

        return ['value' => $value, 'status' => $status, 'parts' => $parts];
    }

    // ---- daftar perbaikan prioritas ------------------------------------

    /** @return list<array{text:string,layer:string,impact:int}> */
    private function actionList(?array $pagespeed, ?array $structured, ?array $crawler, ?array $ctr, ?array $scorecard, ?array $onpage = null, ?array $technical = null, ?array $extract = null): array
    {
        $actions = [];
        $add = function (string $layer, string $text, int|float $impact) use (&$actions) {
            $actions[] = ['text' => $text, 'layer' => $layer, 'impact' => (int) max(1, min(100, round($impact)))];
        };

        foreach (array_slice($ctr['opportunities'] ?? [], 0, 5) as $o) {
            $add('SXO', "Tulis ulang title & meta untuk \"{$o['keyword']}\" — posisi {$o['position']}, sekitar {$o['missed_clicks']} klik/bulan hilang.", $o['missed_clicks']);
        }

        foreach (array_slice($scorecard['weak_pages'] ?? [], 0, 5) as $p) {
            $add('SXO', "Perbaiki pengalaman halaman {$p['landing_page']} — {$p['reason']} ({$p['sessions']} sesi organik).", $p['weak_score'] / 2);
        }

        foreach ($crawler['crawlers'] ?? [] as $c) {
            if (($c['access'] ?? '') !== 'blocked') {
                continue;
            }
            $answers = ($c['purpose'] ?? '') === 'answers';
            $add(
                'GEO',
                $answers
                    ? "Izinkan {$c['bot']} ({$c['vendor']}) di robots.txt — selama diblokir, situs tidak akan pernah dikutip di jawaban {$c['vendor']}."
                    : "Pertimbangkan mengizinkan {$c['bot']} ({$c['vendor']}) di robots.txt untuk jangkauan mesin AI yang lebih luas.",
                $answers ? 85 : 30
            );
        }

        $missingSeen = [];
        foreach ($structured['pages'] ?? [] as $page) {
            foreach ($page['missing'] ?? [] as $type) {
                if (isset($missingSeen[$type])) {
                    continue;
                }
                $missingSeen[$type] = true;
                $add('SEO/GEO', "Tambah schema {$type} (mis. di {$page['url']}) sesuai jenis halamannya.", 45);
            }
            foreach ($page['notes'] ?? [] as $note) {
                if (str_contains($note, 'gagal diparse') || str_contains($note, 'sameAs') || str_contains($note, "'author'") || str_contains($note, "'offers'")) {
                    $add('GEO', $note . ' (' . $page['url'] . ')', 35);
                }
            }
        }

        foreach (['lcp' => 'LCP', 'cls' => 'CLS', 'inp' => 'INP'] as $key => $label) {
            $m = $pagespeed['metrics'][$key] ?? null;
            if (($m['status'] ?? null) === 'poor') {
                $add('SXO', "Perbaiki {$label} versi mobile: {$m['value']}{$m['unit']} — masih kategori buruk.", 55);
            }
        }

        $a11y = $pagespeed['scores']['accessibility'] ?? null;
        if ($a11y !== null && $a11y < 90) {
            $add('SXO', "Naikkan skor aksesibilitas Lighthouse (sekarang {$a11y}) — bantu pengguna & sinyal kualitas.", 30);
        }

        $seoScore = $pagespeed['scores']['seo'] ?? null;
        if ($seoScore !== null && $seoScore < 90) {
            $add('SEO', "Bereskan isu SEO teknis di audit Lighthouse (skor sekarang {$seoScore}).", 40);
        }

        // On-page: halaman noindex dulu (kritis), lalu isu on-page paling sering.
        $onpageCounts = [];
        foreach ($onpage['pages'] ?? [] as $page) {
            if (!empty($page['noindex'])) {
                $add('SEO', "Halaman {$page['url']} di-set noindex — cek apakah disengaja, karena tidak akan muncul di Google.", 90);
            }
            foreach ($page['checks'] ?? [] as $c) {
                if (($c['status'] ?? '') === 'fail') {
                    $onpageCounts[$c['label']] = ($onpageCounts[$c['label']] ?? 0) + 1;
                }
            }
        }
        arsort($onpageCounts);
        foreach (array_slice(array_keys($onpageCounts), 0, 3) as $label) {
            $add('SEO', "Perbaiki \"{$label}\" di {$onpageCounts[$label]} halaman (audit on-page).", 50);
        }

        // Teknis: setiap check yang fail.
        foreach ($technical['checks'] ?? [] as $c) {
            if (($c['status'] ?? '') === 'fail') {
                $add('SEO', "SEO teknis — {$c['label']}: {$c['detail']}", 60);
            } elseif (($c['status'] ?? '') === 'warn' && in_array($c['id'] ?? '', ['sitemap', 'broken_links'], true)) {
                $add('SEO', "SEO teknis — {$c['label']}: {$c['detail']}", 35);
            }
        }

        // Extractability: saran per halaman dari Gemini.
        foreach ($extract['pages'] ?? [] as $page) {
            if (($page['status'] ?? '') !== 'ok') {
                continue;
            }
            foreach (array_slice($page['suggestions'] ?? [], 0, 2) as $s) {
                $add('GEO', "Konten {$page['url']}: {$s}", max(20, 60 - (int) ($page['score'] ?? 0) / 2));
            }
        }

        usort($actions, fn ($a, $b) => $b['impact'] <=> $a['impact']);

        return array_slice($actions, 0, 12);
    }
}
