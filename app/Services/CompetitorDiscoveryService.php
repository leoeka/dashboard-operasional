<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompetitorDiscoveryService
{
    /**
     * Cari kandidat URL kompetitor otomatis lewat Google Places API,
     * berdasarkan tipe bisnis + topik yang sudah diidentifikasi dari situs
     * client sendiri. Ini BUKAN AI menebak — ini hasil pencarian bisnis
     * nyata di Google, hari ini, real-time.
     *
     * REVISI (Agustus 2026): sebelumnya pakai Google Custom Search API,
     * tapi Google MENUTUP fitur "search the entire web" buat search
     * engine baru sejak 20 Januari 2026 (kebijakan resmi Google, bukan
     * masalah setup) — search engine baru dibatasi cuma bisa cari di
     * daftar domain yang SUDAH ditentukan, jadi tidak berguna buat
     * "menemukan" kompetitor yang belum diketahui. Diganti pakai Places
     * API — cari BISNIS nyata (bukan sembarang halaman web), hasilnya
     * malah lebih relevan (pasti bisnis beneran, bukan blog/artikel).
     *
     * Kalau kredensial belum di-setup, gagal secara HALUS (array kosong),
     * supaya proses lanjut pakai kompetitor manual (kalau ada) atau lanjut
     * tanpa data kompetitor sama sekali — bukan bikin seluruh alur gagal.
     */
    public function findCompetitors(string $businessType, array $topics = [], string $excludeDomain = '', ?string $location = null): array
    {
        $apiKey = config('services.google_places.api_key');

        if (!$apiKey) {
            Log::info('CompetitorDiscoveryService: kredensial Places API belum di-setup, skip auto-discovery.');
            return [];
        }

        $query = $this->buildQuery($businessType, $topics, $location);

        try {
            // FieldMask dibatasi cuma 2 field (displayName + websiteUri) —
            // sengaja diminimalkan, karena Places API bertarif per FIELD
            // yang diminta (makin banyak field, makin mahal per
            // panggilan). Kita cuma butuh website-nya buat jadi daftar
            // kompetitor, tidak butuh rating/foto/review/dst.
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => 'places.displayName,places.websiteUri',
                ])
                ->post('https://places.googleapis.com/v1/places:searchText', [
                    'textQuery' => $query,
                ]);

            if (!$response->successful()) {
                Log::warning('CompetitorDiscoveryService: request gagal.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'query' => $query,
                ]);
                return [];
            }

            $places = $response->json('places', []);
            $excludeHost = $excludeDomain ? $this->normalizeHost(parse_url($excludeDomain, PHP_URL_HOST) ?? '') : null;

            return collect($places)
                ->map(fn($place) => $place['websiteUri'] ?? null)
                ->filter()
                ->filter(function ($url) use ($excludeHost) {
                    if (!$excludeHost) {
                        return true;
                    }
                    return $this->normalizeHost(parse_url($url, PHP_URL_HOST) ?? '') !== $excludeHost;
                })
                ->reject(function ($url) {
                    // Places kadang balikin link media sosial/marketplace
                    // sebagai "website" bisnis (banyak UMKM cuma pasang
                    // link Instagram/Facebook) — bukan kompetitor dalam
                    // artian "situs sendiri yang mau dibandingkan SEO-nya".
                    $genericHosts = ['facebook.com', 'instagram.com', 'tokopedia.com', 'shopee.co.id', 'wikipedia.org', 'youtube.com', 'tripadvisor.com', 'linktr.ee'];
                    $host = parse_url($url, PHP_URL_HOST) ?? '';
                    foreach ($genericHosts as $g) {
                        if (str_contains($host, $g)) {
                            return true;
                        }
                    }
                    return false;
                })
                ->unique()
                ->take(5)
                ->values()
                ->toArray();

        } catch (\Throwable $e) {
            Log::error('CompetitorDiscoveryService Exception: ' . $e->getMessage());
            return [];
        }
    }

    private function buildQuery(string $businessType, array $topics, ?string $location = null): string
    {
        $topicPart = !empty($topics) ? implode(' ', array_slice($topics, 0, 3)) : '';
        $locationPart = $location ? trim($location) : '';
        return trim("{$businessType} {$topicPart} {$locationPart}");
    }

    private function normalizeHost(string $host): string
    {
        return preg_replace('/^www\./', '', strtolower($host));
    }
}