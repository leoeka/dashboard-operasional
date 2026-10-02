<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompetitorDiscoveryService
{
    /**
     * Domain yang BUKAN "situs sendiri" milik bisnis — media sosial, chat,
     * marketplace, direktori, OTA travel, dan link shortener. Places API
     * kadang mengisi kolom websiteUri dengan link seperti ini (banyak UMKM
     * cuma pasang link Instagram/WhatsApp/Klook), padahal yang kita butuh
     * adalah website yang bisa dibandingkan SEO & PageSpeed-nya.
     *
     * Dicocokkan persis atau sebagai subdomain (lihat isGenericHost()),
     * jadi m.facebook.com ikut terblokir, tapi box.com tidak dianggap x.com.
     */
    private const GENERIC_HOSTS = [
        // Media sosial & chat
        'facebook.com', 'fb.com', 'instagram.com', 'youtube.com', 'tiktok.com',
        'x.com', 'twitter.com', 'wa.me', 'whatsapp.com', 'linktr.ee',

        // Marketplace & direktori
        'tokopedia.com', 'shopee.co.id', 'wikipedia.org',
        'tripadvisor.com', 'tripadvisor.co.id',
        'business.site', 'sites.google.com', 'google.com',

        // OTA travel
        'klook.com', 'traveloka.com', 'tiket.com', 'viator.com',
        'getyourguide.com', 'booking.com', 'agoda.com',

        // Link shortener
        'bit.ly', 's.id',
    ];

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
     * REVISI (September 2026): hasil Places sekarang dinormalisasi ke
     * homepage (buang UTM/query string/path), disaring dari link non-website
     * (WhatsApp, OTA travel, dll), dan dideduplikasi per domain — supaya
     * 5 slot kompetitor terisi website bisnis sungguhan dan kuota PageSpeed
     * tidak terbuang ke link chat/direktori.
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
                    'provider_error' => ProviderException::fromResponse('google_places', $response)->context(),
                    'query' => $query,
                ]);
                return [];
            }

            $places = $response->json('places', []);

            // Host client sendiri, supaya situs client tidak ikut masuk
            // daftar kompetitornya sendiri. excludeDomain bisa berupa URL
            // lengkap ("https://www.domain.com/") atau domain polos
            // ("domain.com") — dua-duanya ditangani.
            $excludeHost = $excludeDomain ? $this->hostOf($excludeDomain) : null;

            return collect($places)
                ->map(fn($place) => $place['websiteUri'] ?? null)
                ->filter()                                             // buang tempat tanpa website
                ->map(fn($url) => $this->normalizeUrl($url))
                ->filter()                                             // buang URL yang tidak valid
                ->reject(fn($url) => $excludeHost && $this->hostOf($url) === $excludeHost)
                ->reject(fn($url) => $this->isGenericHost($this->hostOf($url)))
                ->unique(fn($url) => $this->hostOf($url))              // 1 domain = 1 kompetitor
                ->take(5)
                ->values()
                ->toArray();

        } catch (\Throwable $e) {
            Log::error('CompetitorDiscoveryService Exception: ' . ProviderException::sanitise($e->getMessage()));
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

    /**
     * Rapikan URL jadi "https://domain.com/" — buang query string (UTM dll),
     * fragment, dan path, supaya satu bisnis tidak tercatat dua kali hanya
     * karena alamatnya ditulis beda, dan PageSpeed selalu dijalankan ke
     * homepage. Skema http dipertahankan kalau memang dari sananya http
     * (beberapa situs UMKM belum pakai SSL).
     */
    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);

        // Places kadang balikin alamat tanpa skema ("www.domain.com")
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || !str_contains($host, '.')) {
            return null;
        }

        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? 'https') === 'http' ? 'http' : 'https';

        return "{$scheme}://" . strtolower($host) . '/';
    }

    /**
     * Ambil host yang sudah dinormalisasi (lowercase, tanpa www.) dari URL
     * lengkap maupun domain polos.
     */
    private function hostOf(string $urlOrDomain): string
    {
        $value = trim($urlOrDomain);

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        return $this->normalizeHost(parse_url($value, PHP_URL_HOST) ?? '');
    }

    /**
     * Cek apakah host termasuk domain generik (lihat GENERIC_HOSTS).
     * Cocok persis ("wa.me") atau subdomain ("m.facebook.com"),
     * tapi TIDAK sekadar mengandung ("box.com" bukan "x.com").
     */
    private function isGenericHost(string $host): bool
    {
        if ($host === '') {
            return true;
        }

        foreach (self::GENERIC_HOSTS as $g) {
            if ($host === $g || str_ends_with($host, '.' . $g)) {
                return true;
            }
        }

        return false;
    }
}
