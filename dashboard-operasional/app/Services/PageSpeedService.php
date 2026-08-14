<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Panggil Google PageSpeed Insights API (v5) — ini menjalankan audit
 * Lighthouse LIVE untuk satu URL, satu strategy (mobile/desktop).
 * Gratis, tidak perlu OAuth, cukup API key (opsional tapi disarankan
 * supaya kuota lebih longgar — tanpa key tetap jalan tapi limit sangat
 * ketat, cuma beberapa request/hari).
 *
 * Setiap panggilan wajar makan waktu 15-30 detik karena Google memang
 * menjalankan audit sungguhan, bukan ambil dari cache instan.
 */
class PageSpeedService
{
    public function analyze(string $url, string $strategy = 'mobile'): ?array
    {
        $apiKey = config('services.pagespeed.api_key');

        try {
            // FIX: PageSpeed API butuh parameter "category" DIULANG di
            // query string (category=PERFORMANCE&category=ACCESSIBILITY&..),
            // bukan dikirim sebagai array PHP — kalau dikirim sebagai
            // array, Laravel/Guzzle serialize jadi category[0]=..&category[1]=..
            // yang tidak dikenali Google (diam-diam diabaikan, bukan error).
            // Makanya query string-nya dibangun manual di sini.
            $queryParams = array_filter([
                'url' => $url,
                'key' => $apiKey,
                'strategy' => $strategy,
            ]);

            $query = http_build_query($queryParams)
                . '&category=performance&category=accessibility&category=best-practices&category=seo';

            $response = Http::timeout(60)->get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . $query);

            // TAMBAHKAN BARIS INI UNTUK DEBUGGING
            Log::info('Cek Kategori PageSpeed:', [
                'categories_ditemukan' => array_keys($response->json()['lighthouseResult']['categories'] ?? [])
            ]);

            if (!$response->successful()) {
                Log::warning('PageSpeedService: request gagal.', [
                    'url' => $url,
                    'strategy' => $strategy,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $this->parseResult($response->json());

        } catch (\Throwable $e) {
            Log::error('PageSpeedService Exception: ' . $e->getMessage(), [
                'url' => $url,
                'strategy' => $strategy,
            ]);
            return null;
        }
    }

    private function parseResult(array $data): array
    {
        $categories = $data['lighthouseResult']['categories'] ?? [];
        $audits = $data['lighthouseResult']['audits'] ?? [];
        $field = $data['loadingExperience']['metrics'] ?? [];

        return [
            'scores' => [
                'performance' => $this->scoreToPercent($categories['performance']['score'] ?? null),
                'accessibility' => $this->scoreToPercent($categories['accessibility']['score'] ?? null),
                'best_practices' => $this->scoreToPercent($categories['best-practices']['score'] ?? null),
                'seo' => $this->scoreToPercent($categories['seo']['score'] ?? null),
            ],
            'metrics' => [
                'lcp' => $this->metricFromMs('LARGEST_CONTENTFUL_PAINT_MS', $field, 'largest-contentful-paint', $audits),
                'cls' => $this->metricCls($field, $audits),
                'inp' => $this->metricInp($field, $audits),
                'fcp' => $this->metricFromMs('FIRST_CONTENTFUL_PAINT_MS', $field, 'first-contentful-paint', $audits),
                'speed_index' => $this->metricLabOnly('speed-index', $audits, 's', 1000),
            ],
        ];
    }

    private function scoreToPercent(?float $score): ?int
    {
        return $score === null ? null : (int) round($score * 100);
    }

    /**
     * Setiap metrik diambil dari FIELD DATA dulu kalau ada (data
     * pengguna nyata dari Chrome UX Report — ini yang sama dipakai
     * Search Console, lebih otoritatif), fallback ke LAB DATA
     * (simulasi Lighthouse) kalau situsnya belum cukup traffic untuk
     * masuk CrUX.
     */
    private function metricFromMs(string $fieldKey, array $field, string $auditKey, array $audits): array
    {
        if (isset($field[$fieldKey]['percentile'])) {
            return [
                'value' => round($field[$fieldKey]['percentile'] / 1000, 2),
                'unit' => 's',
                'status' => $this->cruxToStatus($field[$fieldKey]['category'] ?? null),
                'source' => 'field',
            ];
        }

        if (isset($audits[$auditKey]['numericValue'])) {
            return [
                'value' => round($audits[$auditKey]['numericValue'] / 1000, 2),
                'unit' => 's',
                'status' => $this->scoreToStatus($audits[$auditKey]['score'] ?? null),
                'source' => 'lab',
            ];
        }

        return $this->emptyMetric('s');
    }

    private function metricCls(array $field, array $audits): array
    {
        if (isset($field['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'])) {
            return [
                'value' => round($field['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] / 100, 3),
                'unit' => '',
                'status' => $this->cruxToStatus($field['CUMULATIVE_LAYOUT_SHIFT_SCORE']['category'] ?? null),
                'source' => 'field',
            ];
        }

        if (isset($audits['cumulative-layout-shift']['numericValue'])) {
            return [
                'value' => round($audits['cumulative-layout-shift']['numericValue'], 3),
                'unit' => '',
                'status' => $this->scoreToStatus($audits['cumulative-layout-shift']['score'] ?? null),
                'source' => 'lab',
            ];
        }

        return $this->emptyMetric('');
    }

    private function metricInp(array $field, array $audits): array
    {
        // INP (Interaction to Next Paint) field data resmi menggantikan
        // FID. Lighthouse (lab) tidak bisa hasilkan INP langsung (perlu
        // interaksi user sungguhan) — dipakai Total Blocking Time
        // sebagai proxy lab terdekat.
        if (isset($field['INTERACTION_TO_NEXT_PAINT']['percentile'])) {
            return [
                'value' => (int) $field['INTERACTION_TO_NEXT_PAINT']['percentile'],
                'unit' => 'ms',
                'status' => $this->cruxToStatus($field['INTERACTION_TO_NEXT_PAINT']['category'] ?? null),
                'source' => 'field',
            ];
        }

        if (isset($audits['total-blocking-time']['numericValue'])) {
            return [
                'value' => (int) round($audits['total-blocking-time']['numericValue']),
                'unit' => 'ms (estimasi TBT)',
                'status' => $this->scoreToStatus($audits['total-blocking-time']['score'] ?? null),
                'source' => 'lab',
            ];
        }

        return $this->emptyMetric('ms');
    }

    private function metricLabOnly(string $auditKey, array $audits, string $unit, float $divisor): array
    {
        if (!isset($audits[$auditKey]['numericValue'])) {
            return $this->emptyMetric($unit);
        }

        return [
            'value' => round($audits[$auditKey]['numericValue'] / $divisor, 2),
            'unit' => $unit,
            'status' => $this->scoreToStatus($audits[$auditKey]['score'] ?? null),
            'source' => 'lab',
        ];
    }

    private function emptyMetric(string $unit): array
    {
        return ['value' => null, 'unit' => $unit, 'status' => null, 'source' => null];
    }

    private function cruxToStatus(?string $category): ?string
    {
        return match ($category) {
            'FAST' => 'good',
            'AVERAGE' => 'needs_improvement',
            'SLOW' => 'poor',
            default => null,
        };
    }

    private function scoreToStatus(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 0.9 => 'good',
            $score >= 0.5 => 'needs_improvement',
            default => 'poor',
        };
    }
}