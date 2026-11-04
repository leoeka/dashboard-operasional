<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Carbon;

/** Builds the client-facing SEO analysis report from saved GA4, GSC and Semrush inputs. */
class SeoAnalysisReportService
{
    public function assemble(Project $project): array
    {
        $seo = $project->seo_requirements ?? [];
        $ga4 = is_array($seo['google_analytics'] ?? null) ? $seo['google_analytics'] : null;
        $gsc = is_array($seo['search_console'] ?? null) ? $seo['search_console'] : null;
        $semrush = is_array($seo['semrush_report'] ?? null) ? $seo['semrush_report'] : [];

        return [
            'project' => $project,
            'generatedAt' => now()->format('d F Y H:i'),
            'targetUrl' => $seo['target_url'] ?? $project->backlink_requirements['target_url'] ?? null,
            'ga4' => $ga4,
            'gsc' => $gsc,
            'semrush' => $semrush,
            'semrushScreenshot' => $seo['manual_screenshots']['own_semrush'] ?? null,
            'ga4Findings' => $this->ga4Findings($ga4),
            'gscFindings' => $this->gscFindings($gsc),
            'semrushFindings' => $this->semrushFindings($semrush),
            'conclusion' => $this->conclusion($ga4, $gsc, $semrush),
            'recommendations' => $this->recommendations($ga4, $gsc, $semrush),
            'hasAnyData' => $ga4 !== null || $gsc !== null || $semrush !== [] || !empty($seo['manual_screenshots']['own_semrush']),
        ];
    }

    private function ga4Findings(?array $ga4): array
    {
        if (!$ga4) {
            return ['Data GA4 belum tersedia. Jalankan analisis GA4 dari tab Traffic Report.'];
        }

        $findings = [];
        $overall = $ga4['overall'] ?? [];
        $channels = array_values(array_filter($ga4['channels'] ?? [], 'is_array'));
        $sessions = (int) ($overall['sessions'] ?? 0);
        $engagement = (float) ($overall['engagement_rate'] ?? 0);

        if ($channels !== [] && $sessions > 0) {
            usort($channels, fn ($a, $b) => ($b['sessions'] ?? 0) <=> ($a['sessions'] ?? 0));
            $top = $channels[0];
            $findings[] = sprintf(
                '%s menjadi sumber sesi terbesar: %s sesi (%s dari seluruh sesi).',
                $top['channel'] ?? 'Saluran tidak diketahui',
                number_format((int) ($top['sessions'] ?? 0)),
                $this->percent((float) ($top['session_share'] ?? 0))
            );

            $lowQualityLeader = null;
            foreach ($channels as $channel) {
                if (($channel['session_share'] ?? 0) >= 0.2 && ($channel['engagement_rate'] ?? 0) < $engagement * 0.75) {
                    $lowQualityLeader = $channel;
                    break;
                }
            }
            if ($lowQualityLeader) {
                $findings[] = sprintf(
                    '%s menyumbang %s sesi, tetapi engagement rate %s berada di bawah rata-rata situs %s; tinjau relevansi landing page dan kualitas traffic.',
                    $lowQualityLeader['channel'], number_format((int) ($lowQualityLeader['sessions'] ?? 0)),
                    $this->percent((float) ($lowQualityLeader['engagement_rate'] ?? 0)), $this->percent($engagement)
                );
            }

            $best = $channels;
            usort($best, fn ($a, $b) => ($b['engagement_rate'] ?? 0) <=> ($a['engagement_rate'] ?? 0));
            if (count($best) > 1 && ($best[0]['channel'] ?? '') !== ($top['channel'] ?? '')) {
                $findings[] = sprintf(
                    '%s memiliki engagement rate tertinggi (%s), sementara volume sesi tertinggi berasal dari %s.',
                    $best[0]['channel'] ?? 'Saluran lain', $this->percent((float) ($best[0]['engagement_rate'] ?? 0)), $top['channel'] ?? 'saluran lain'
                );
            }
        }

        $conversions = (float) ($overall['conversions'] ?? $ga4['totals']['conversions'] ?? 0);
        $events = (int) ($overall['event_count'] ?? 0);
        if ($sessions > 0 && $conversions <= 0 && $events > 0) {
            $findings[] = 'Belum ada konversi tercatat pada periode ini, meski event GA4 tercatat. Pastikan event utama sudah ditandai sebagai conversion; angka nol tidak membuktikan tidak ada tindakan pengguna.';
        } elseif ($sessions > 0 && $conversions > 0) {
            $findings[] = sprintf('%s konversi tercatat dari %s sesi selama periode laporan.', number_format($conversions, 0), number_format($sessions));
        }

        $trend = $this->trendDirection($ga4['daily_trend'] ?? []);
        if ($trend !== null) {
            $findings[] = 'Tren sesi harian ' . $trend . '. Perubahan ini menunjukkan pola waktu, bukan penyebabnya; cocokkan dengan kampanye, musim, atau perubahan situs sebelum menyimpulkan.';
        }

        return $findings ?: ['Data GA4 tersedia, tetapi belum cukup untuk membentuk temuan perbandingan yang andal.'];
    }

    private function gscFindings(?array $gsc): array
    {
        if (!$gsc) {
            return ['Data Google Search Console belum tersedia. Jalankan analisis Search Console dari tab Traffic Report.'];
        }

        $findings = [];
        $totals = $gsc['totals'] ?? [];
        $ctr = isset($totals['ctr']) ? (float) $totals['ctr'] : null;
        $position = isset($totals['position']) ? (float) $totals['position'] : null;
        $impressions = (int) ($totals['impressions'] ?? 0);
        if ($ctr !== null && $impressions > 0 && $position !== null) {
            if ($ctr < 0.02 && $position <= 10) {
                $findings[] = sprintf('Impresi mencapai %s dengan posisi rata-rata %s, tetapi CTR %s masih rendah untuk visibilitas tersebut. Periksa kecocokan title dan meta description dengan maksud pencarian.', number_format($impressions), round($position, 1), $this->percent($ctr));
            } else {
                $findings[] = sprintf('Posisi rata-rata %s dengan CTR %s dari %s impresi. Nilai agregat ini perlu dibaca bersama query dan halaman karena campuran posisi dapat memengaruhi rata-rata.', round($position, 1), $this->percent($ctr), number_format($impressions));
            }
        }

        $queries = array_values(array_filter($gsc['top_queries'] ?? [], 'is_array'));
        if ($queries !== []) {
            usort($queries, fn ($a, $b) => ($b['clicks'] ?? 0) <=> ($a['clicks'] ?? 0));
            $query = $queries[0];
            $findings[] = sprintf('Query dengan klik terbanyak yang tercatat adalah “%s” (%s klik, posisi rata-rata %s).', $query['keys'][0] ?? '-', number_format((int) ($query['clicks'] ?? 0)), isset($query['position']) ? round((float) $query['position'], 1) : '-');
        }

        $pages = array_values(array_filter($gsc['top_pages'] ?? [], 'is_array'));
        if ($pages !== []) {
            usort($pages, fn ($a, $b) => ($b['clicks'] ?? 0) <=> ($a['clicks'] ?? 0));
            $page = $pages[0];
            $findings[] = sprintf('Halaman dengan klik terbanyak adalah %s (%s klik dari %s impresi).', $page['keys'][0] ?? '-', number_format((int) ($page['clicks'] ?? 0)), number_format((int) ($page['impressions'] ?? 0)));
        }

        $trend = $this->trendDirection($gsc['daily_trend'] ?? []);
        if ($trend !== null) {
            $findings[] = 'Tren klik harian Search Console ' . $trend . '. Data 3 hari terbaru dikecualikan karena biasanya belum lengkap.';
        }

        return $findings ?: ['Data Search Console tersedia, tetapi belum cukup untuk membentuk temuan tambahan.'];
    }

    private function semrushFindings(array $semrush): array
    {
        if ($semrush === []) {
            return ['Data Semrush belum diisi. Masukkan nilai yang terlihat pada screenshot agar ringkasan dan saran di bagian ini dapat dibuat.'];
        }

        $metrics = $semrush['metrics'] ?? [];
        $findings = [];
        $traffic = (int) ($metrics['organic_traffic'] ?? 0);
        $keywords = (int) ($metrics['organic_keywords'] ?? 0);
        if (isset($metrics['authority_score'])) {
            $findings[] = sprintf('Authority Score tercatat %s/100. Ini metrik Semrush untuk perbandingan, bukan ukuran resmi Google.', $metrics['authority_score']);
        }
        if (isset($metrics['ai_visibility_score'])) {
            $findings[] = sprintf('AI Visibility Score pada snapshot Semrush tercatat %s. Gunakan sebagai indikator tren pada Semrush, bukan sebagai ukuran ranking resmi Google.', $metrics['ai_visibility_score']);
        }
        if ($traffic > 0 || $keywords > 0) {
            $findings[] = sprintf('Semrush memperkirakan %s organic traffic dan %s organic keywords untuk tanggal snapshot %s. Perlakukan sebagai estimasi pihak ketiga, bukan data analytics aktual.', number_format($traffic), number_format($keywords), $semrush['as_of'] ?? 'yang dicatat');
        }
        if (!empty($semrush['ai_platforms'])) {
            $mentions = array_sum(array_map(fn ($row) => (int) ($row['first'] ?? 0), $semrush['ai_platforms']));
            $cited = array_sum(array_map(fn ($row) => (int) ($row['second'] ?? 0), $semrush['ai_platforms']));
            $findings[] = sprintf('Input visibilitas AI manual mencatat total %s mentions dan %s cited pages pada platform yang diisi.', number_format($mentions), number_format($cited));
        }
        if (!empty($semrush['countries'])) {
            $countries = $semrush['countries'];
            usort($countries, fn ($a, $b) => ($b['first'] ?? 0) <=> ($a['first'] ?? 0));
            $country = $countries[0];
            $findings[] = sprintf('Negara dengan estimasi traffic Semrush terbesar di antara baris yang dimasukkan adalah %s (%s traffic dan %s keywords).', $country['label'] ?? '—', number_format((int) ($country['first'] ?? 0)), number_format((int) ($country['second'] ?? 0)));
        }
        foreach (array_filter([$semrush['trend_summary'] ?? null, $semrush['key_points'] ?? null]) as $note) {
            $findings[] = $note;
        }

        return $findings ?: ['Screenshot Semrush tersimpan; belum ada nilai metrik atau catatan yang bisa diringkas.'];
    }

    private function conclusion(?array $ga4, ?array $gsc, array $semrush): string
    {
        $available = array_filter([
            $ga4 ? 'perilaku pengunjung GA4' : null,
            $gsc ? 'visibilitas pencarian Google Search Console' : null,
            $semrush !== [] ? 'estimasi pasar Semrush' : null,
        ]);

        return $available === []
            ? 'Belum ada data analisis yang cukup untuk menyusun kesimpulan. Lengkapi sumber data lalu buat laporan kembali.'
            : 'Laporan merangkum ' . implode(', ', $available) . '. Baca metrik sesuai sumber dan periode masing-masing; angka Semrush adalah estimasi pihak ketiga, sedangkan GA4 dan Search Console merepresentasikan data properti yang terhubung.';
    }

    private function recommendations(?array $ga4, ?array $gsc, array $semrush): array
    {
        $recommendations = [];
        $channels = $ga4['channels'] ?? [];
        foreach ($channels as $channel) {
            if (($channel['session_share'] ?? 0) >= 0.2 && ($channel['engagement_rate'] ?? 1) < 0.3) {
                $recommendations[] = 'Tinjau landing page dan kesesuaian pesan untuk ' . ($channel['channel'] ?? 'saluran dengan engagement rendah') . '; kanal ini memiliki volume berarti dengan engagement di bawah 30%.';
                break;
            }
        }
        $topQueries = $gsc['top_queries'] ?? [];
        foreach ($topQueries as $query) {
            if (($query['impressions'] ?? 0) >= 100 && ($query['position'] ?? 99) <= 10 && ($query['ctr'] ?? 1) < 0.02) {
                $recommendations[] = 'Optimalkan title dan meta description untuk query “' . ($query['keys'][0] ?? '-') . '”, lalu pantau CTR dan klik pada periode berikutnya.';
                break;
            }
        }
        if (!empty($semrush['recommendations'])) {
            $recommendations[] = $semrush['recommendations'];
        }

        return $recommendations ?: ['Pertahankan pemantauan pada periode berikutnya dan validasi setiap perubahan dengan data aktual.'];
    }

    private function trendDirection(array $rows): ?string
    {
        $values = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $date = $row['keys'][0] ?? $row['date'] ?? null;
            $value = $row['sessions'] ?? $row['clicks'] ?? null;
            if ($date && $value !== null) {
                $values[(string) $date] = (float) $value;
            }
        }
        if (count($values) < 14) {
            return null;
        }
        ksort($values);
        $values = array_values($values);
        $first = array_slice($values, 0, 7);
        $last = array_slice($values, -7);
        $firstAvg = array_sum($first) / count($first);
        $lastAvg = array_sum($last) / count($last);
        if ($firstAvg == 0.0) {
            return $lastAvg > 0 ? 'meningkat dari awal periode yang tidak memiliki traffic' : 'tetap tanpa traffic pada awal dan akhir periode';
        }
        $change = ($lastAvg - $firstAvg) / $firstAvg;
        if (abs($change) < 0.1) {
            return 'relatif stabil antara awal dan akhir periode';
        }

        return ($change > 0 ? 'meningkat sekitar ' : 'menurun sekitar ') . round(abs($change) * 100) . '% pada rata-rata harian tujuh hari terakhir dibanding tujuh hari pertama';
    }

    private function percent(float $value): string
    {
        return number_format($value * 100, 2, ',', '.') . '%';
    }
}
