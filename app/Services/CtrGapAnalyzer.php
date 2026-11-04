<?php

namespace App\Services;

/**
 * SXO — cari halaman yang SUDAH ranking bagus di Google tapi CTR-nya jauh
 * di bawah rata-rata untuk posisinya. Biasanya berarti title tag / meta
 * description-nya kurang menarik: peringkatnya oke, tapi orang tidak
 * meng-klik. Perbaikan copy di sini menaikkan trafik tanpa perlu naik
 * ranking sama sekali.
 *
 * Murni hitungan dari data Search Console (query, impression, posisi, CTR).
 * Tidak ada panggilan API di sini — job yang mengambil datanya.
 */
class CtrGapAnalyzer
{
    /** Abaikan query dengan impression terlalu sedikit — noise. */
    private const MIN_IMPRESSIONS = 50;

    /** Selisih minimal (poin persen) di bawah ekspektasi supaya dihitung peluang. */
    private const GAP_THRESHOLD = 0.02;

    /** Peluang harus mewakili minimal sekian estimasi klik/bulan yang hilang. */
    private const MIN_MISSED_CLICKS = 5;

    /**
     * Rata-rata CTR organik per posisi (agregat industri, dibulatkan).
     * Dipakai sebagai garis "seharusnya" untuk membandingkan CTR nyata.
     */
    private const EXPECTED_CTR = [
        1 => 0.281, 2 => 0.152, 3 => 0.101, 4 => 0.069, 5 => 0.050,
        6 => 0.038, 7 => 0.030, 8 => 0.024, 9 => 0.020, 10 => 0.017,
    ];

    /**
     * @param  list<array{keyword?:string,clicks?:int,impressions?:int,ctr?:float,position?:float}>  $rows
     * @return array{
     *   checked_at:string, queries_analyzed:int,
     *   opportunities:list<array<string,mixed>>,
     *   period_days:int,
     *   summary:array{opportunity_count:int,estimated_missed_clicks:int},
     *   recommendation:string
     * }
     */
    public function analyze(array $rows, int $periodDays = 90): array
    {
        $opportunities = [];
        $analyzed = 0;
        $periodDays = max(1, $periodDays);

        foreach ($rows as $row) {
            $keyword = trim((string) ($row['keyword'] ?? ''));
            $impressions = (int) ($row['impressions'] ?? 0);
            $position = (float) ($row['position'] ?? 0);
            $actualCtr = (float) ($row['ctr'] ?? 0);

            if ($keyword === '' || $impressions < self::MIN_IMPRESSIONS || $position < 1 || $position > 10.5) {
                continue;
            }

            $analyzed++;

            $expectedCtr = $this->expectedCtr($position);
            $gap = $expectedCtr - $actualCtr;

            if ($gap < self::GAP_THRESHOLD) {
                continue;
            }

            $missedClicksInPeriod = (int) round($impressions * $gap);
            $missedClicksPerMonth = (int) round($missedClicksInPeriod * 30 / $periodDays);
            if ($missedClicksPerMonth < self::MIN_MISSED_CLICKS) {
                continue;
            }

            $opportunities[] = [
                'keyword' => $keyword,
                'position' => round($position, 1),
                'impressions' => $impressions,
                'actual_ctr' => round($actualCtr, 4),
                'expected_ctr' => round($expectedCtr, 4),
                'gap' => round($gap, 4),
                'missed_clicks' => $missedClicksPerMonth,
                'missed_clicks_in_period' => $missedClicksInPeriod,
            ];
        }

        usort($opportunities, fn ($a, $b) => $b['missed_clicks'] <=> $a['missed_clicks']);

        $totalMissed = array_sum(array_column($opportunities, 'missed_clicks'));

        return [
            'checked_at' => now()->toDateTimeString(),
            'period_days' => $periodDays,
            'queries_analyzed' => $analyzed,
            'opportunities' => $opportunities,
            'summary' => [
                'opportunity_count' => count($opportunities),
                'estimated_missed_clicks' => (int) $totalMissed,
            ],
            'recommendation' => $this->recommendation($opportunities, (int) $totalMissed, $analyzed),
        ];
    }

    /** CTR yang wajar untuk sebuah posisi (interpolasi linear antar posisi bulat). */
    public function expectedCtr(float $position): float
    {
        if ($position <= 1) {
            return self::EXPECTED_CTR[1];
        }
        if ($position >= 10) {
            return self::EXPECTED_CTR[10];
        }

        $low = (int) floor($position);
        $high = $low + 1;
        $frac = $position - $low;

        return self::EXPECTED_CTR[$low] + (self::EXPECTED_CTR[$high] - self::EXPECTED_CTR[$low]) * $frac;
    }

    private function recommendation(array $opportunities, int $totalMissed, int $analyzed): string
    {
        if ($opportunities === []) {
            if ($analyzed === 0) {
                return 'Belum cukup data untuk menilai CTR gap: tidak ada query yang memenuhi kriteria posisi halaman pertama dan minimal 50 impresi.';
            }

            return "Tidak ditemukan CTR gap yang signifikan pada {$analyzed} query yang memenuhi kriteria. Ini bukan jaminan semua title/meta sudah optimal; pantau lagi setelah data bertambah.";
        }

        $top = array_slice(array_map(fn ($o) => '"' . $o['keyword'] . '"', $opportunities), 0, 3);

        return count($opportunities) . ' query sudah ranking di halaman 1 tapi CTR-nya di bawah pembanding — estimasi sekitar '
            . $totalMissed . ' klik/bulan yang berpotensi diperoleh. Tinjau title tag & meta description halaman terkait '
            . '(perlakukan seperti teks iklan: manfaat + angka + ajakan). Prioritas: ' . implode(', ', $top) . '.';
    }
}
