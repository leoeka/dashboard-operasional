<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompetitorDiscoveryService
{
    /**
     * Cari kandidat URL kompetitor otomatis lewat Google Custom Search,
     * berdasarkan tipe bisnis + topik yang sudah diidentifikasi dari situs
     * client sendiri. Ini BUKAN AI menebak — ini hasil pencarian Google
     * beneran, hari ini, real-time.
     *
     * Kalau kredensial belum di-setup, gagal secara HALUS (array kosong),
     * supaya proses lanjut pakai kompetitor manual (kalau ada) atau lanjut
     * tanpa data kompetitor sama sekali — bukan bikin seluruh alur gagal.
     */
    public function findCompetitors(string $businessType, array $topics = [], string $excludeDomain = ''): array
    {
        $apiKey = config('services.google_custom_search.api_key');
        $engineId = config('services.google_custom_search.engine_id');

        if (!$apiKey || !$engineId) {
            Log::info('CompetitorDiscoveryService: kredensial belum di-setup, skip auto-discovery.');
            return [];
        }

        $query = $this->buildQuery($businessType, $topics);

        try {
            $response = Http::timeout(15)->get('https://www.googleapis.com/customsearch/v1', [
                'key' => $apiKey,
                'cx' => $engineId,
                'q' => $query,
                'num' => 10,
            ]);

            if (!$response->successful()) {
                Log::warning('CompetitorDiscoveryService: request gagal.', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);
                return [];
            }

            $items = $response->json('items', []);
            $excludeHost = $excludeDomain ? parse_url($excludeDomain, PHP_URL_HOST) : null;

            return collect($items)
                ->map(fn($item) => $item['link'] ?? null)
                ->filter()
                // Buang hasil yang kebetulan URL situs client sendiri
                ->filter(function ($url) use ($excludeHost) {
                    if (!$excludeHost) {
                        return true;
                    }
                    return parse_url($url, PHP_URL_HOST) !== $excludeHost;
                })
                // Buang platform besar yang biasanya bukan "kompetitor asli"
                // (marketplace, media sosial, direktori umum)
                ->reject(function ($url) {
                    $genericHosts = ['facebook.com', 'instagram.com', 'tokopedia.com', 'shopee.co.id', 'wikipedia.org', 'youtube.com', 'tripadvisor.com'];
                    $host = parse_url($url, PHP_URL_HOST) ?? '';
                    foreach ($genericHosts as $g) {
                        if (str_contains($host, $g)) {
                            return true;
                        }
                    }
                    return false;
                })
                ->unique()
                ->take(5) // batasi maksimal 5 kompetitor per analisis
                ->values()
                ->toArray();

        } catch (\Throwable $e) {
            Log::error('CompetitorDiscoveryService Exception: ' . $e->getMessage());
            return [];
        }
    }

    private function buildQuery(string $businessType, array $topics): string
    {
        $topicPart = !empty($topics) ? implode(' ', array_slice($topics, 0, 3)) : '';
        return trim("{$businessType} {$topicPart}");
    }
}