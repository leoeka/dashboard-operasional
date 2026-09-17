<?php

namespace App\Services;

/**
 * SXO — nilai tiap landing page organik dari data engagement GA4 yang
 * SUDAH tersimpan (seo_requirements['google_analytics']['by_landing_page']).
 * Menandai halaman yang menerima trafik organik lumayan tapi pengunjungnya
 * cepat pergi — kandidat perbaikan pengalaman (relevansi konten dengan
 * maksud pencarian, kecepatan, kejelasan langkah berikutnya).
 *
 * Murni hitungan atas data yang sudah ada — tidak ada job, tidak ada
 * panggilan API. Dipanggil langsung oleh controller & laporan.
 */
class SxoScorecardService
{
    private const MIN_SESSIONS = 20;
    private const LOW_ENGAGEMENT_RATE = 0.5;
    private const SHORT_ENGAGEMENT_TIME = 15; // detik

    /**
     * @param  list<array{landing_page?:string,sessions?:int,engagement_rate?:float,avg_engagement_time?:float,conversions?:float}>  $byLandingPage
     * @return array{
     *   computed_at:string,
     *   summary:array{pages:int,avg_engagement_rate:float,avg_engagement_time:float,weak_pages:int},
     *   weak_pages:list<array<string,mixed>>,
     *   recommendation:string
     * }
     */
    public function build(array $byLandingPage): array
    {
        $rows = array_values(array_filter(
            $byLandingPage,
            fn ($r) => is_array($r) && (int) ($r['sessions'] ?? 0) > 0
        ));

        $totalSessions = array_sum(array_map(fn ($r) => (int) ($r['sessions'] ?? 0), $rows));

        $weightedRate = $totalSessions > 0
            ? array_sum(array_map(fn ($r) => (int) ($r['sessions'] ?? 0) * (float) ($r['engagement_rate'] ?? 0), $rows)) / $totalSessions
            : 0.0;
        $weightedTime = $totalSessions > 0
            ? array_sum(array_map(fn ($r) => (int) ($r['sessions'] ?? 0) * (float) ($r['avg_engagement_time'] ?? 0), $rows)) / $totalSessions
            : 0.0;

        $weak = [];
        foreach ($rows as $row) {
            $sessions = (int) ($row['sessions'] ?? 0);
            $rate = (float) ($row['engagement_rate'] ?? 0);
            $time = (float) ($row['avg_engagement_time'] ?? 0);

            if ($sessions < self::MIN_SESSIONS) {
                continue;
            }

            $reasons = [];
            if ($rate < self::LOW_ENGAGEMENT_RATE) {
                $reasons[] = 'engagement rate rendah (' . round($rate * 100) . '%)';
            }
            if ($time < self::SHORT_ENGAGEMENT_TIME) {
                $reasons[] = 'waktu kunjungan singkat (' . round($time) . ' dtk)';
            }
            if ($reasons === []) {
                continue;
            }

            $weak[] = [
                'landing_page' => (string) ($row['landing_page'] ?? '-'),
                'sessions' => $sessions,
                'engagement_rate' => round($rate, 3),
                'avg_engagement_time' => round($time, 1),
                'conversions' => (float) ($row['conversions'] ?? 0),
                'reason' => implode(' + ', $reasons),
                // Prioritas = sesi yang "terbuang" — makin banyak trafik di
                // halaman yang lemah, makin tinggi urgensinya.
                'weak_score' => round($sessions * (0.6 - min($rate, 0.6)), 1),
            ];
        }

        usort($weak, fn ($a, $b) => $b['weak_score'] <=> $a['weak_score']);

        return [
            'computed_at' => now()->toDateTimeString(),
            'summary' => [
                'pages' => count($rows),
                'avg_engagement_rate' => round($weightedRate, 3),
                'avg_engagement_time' => round($weightedTime, 1),
                'weak_pages' => count($weak),
            ],
            'weak_pages' => array_slice($weak, 0, 10),
            'recommendation' => $this->recommendation($weak),
        ];
    }

    private function recommendation(array $weak): string
    {
        if ($weak === []) {
            return 'Landing page organik menahan pengunjung dengan baik — tidak ada halaman trafik tinggi dengan engagement rendah.';
        }

        $top = array_slice(array_map(fn ($w) => $w['landing_page'], $weak), 0, 3);

        return count($weak) . ' halaman menerima trafik organik lumayan tapi pengunjung cepat pergi. '
            . 'Periksa: apakah isi halaman menjawab maksud pencarian, kecepatan muat, dan kejelasan langkah berikutnya (CTA). '
            . 'Prioritas: ' . implode(', ', $top) . '.';
    }
}
