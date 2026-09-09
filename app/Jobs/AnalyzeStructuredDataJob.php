<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\StructuredDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SEO + GEO — deteksi structured data (schema.org / JSON-LD) di homepage
 * klien + beberapa halaman teratas (dari Search Console / GA4 kalau sudah
 * pernah dianalisis). Hasil disimpan ke seo_requirements['structured_data'].
 * Pola sama dengan AnalyzePageSpeedJob.
 */
class AnalyzeStructuredDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public Project $project)
    {
    }

    public static function cacheKey(int $projectId): string
    {
        return "structured_data_progress:{$projectId}";
    }

    private function report(string $status, int $progress, string $message): void
    {
        Cache::put(self::cacheKey($this->project->id), [
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
        ], now()->addMinutes(10));
    }

    private function resolveWebsiteUrl(): ?string
    {
        return $this->project->seo_requirements['target_url']
            ?? $this->project->backlink_requirements['target_url']
            ?? null;
    }

    public function handle(StructuredDataService $service): void
    {
        $this->report('running', 15, 'Mengambil halaman & memeriksa schema...');

        $url = $this->resolveWebsiteUrl();
        if (!$url) {
            $this->report('failed', 0, 'URL website klien belum tersedia.');
            return;
        }

        $seo = $this->project->seo_requirements ?? [];

        $result = $service->analyze(
            $url,
            self::topPageUrls($seo),
            $this->isLocalBusiness($seo),
        );

        if ($result['summary']['pages_checked'] === 0) {
            $this->report('failed', 0, 'Tidak ada halaman yang bisa diambil untuk dianalisis.');
            return;
        }

        $seo = $this->project->fresh()->seo_requirements ?? [];
        $seo['structured_data'] = $result;
        $this->project->update(['seo_requirements' => $seo]);

        $missing = $result['summary']['pages_missing_recommended'];
        $this->project->logActivity(
            $missing > 0
                ? "Cek structured data selesai — {$missing} halaman masih kurang schema yang disarankan"
                : 'Cek structured data selesai — cakupan schema dasar lengkap'
        );

        $this->report('done', 100, 'Cek structured data selesai.');
    }

    /**
     * Halaman tambahan dari hasil Search Console / GA4 yang sudah tersimpan
     * (kalau ada) — supaya bukan cuma homepage yang dicek. Statik & publik
     * supaya dipakai ulang oleh job audit SEO lain.
     *
     * @return list<string>
     */
    public static function topPageUrls(array $seo): array
    {
        $urls = [];

        foreach (($seo['search_console']['top_pages'] ?? []) as $row) {
            $page = $row['keys'][0] ?? null;
            if (is_string($page) && $page !== '') {
                $urls[] = $page;
            }
        }

        foreach (($seo['google_analytics']['by_landing_page'] ?? []) as $row) {
            $page = $row['landing_page'] ?? null;
            if (is_string($page) && $page !== '' && $page !== '(not set)') {
                $urls[] = $page;
            }
        }

        return array_values(array_unique($urls));
    }

    private function isLocalBusiness(array $seo): bool
    {
        if (trim((string) ($seo['location'] ?? '')) !== '') {
            return true;
        }

        $type = strtolower((string) ($this->project->type ?? ''));
        foreach (['shop', 'store', 'toko', 'restaurant', 'resto', 'cafe', 'kafe', 'clinic', 'klinik', 'salon', 'bengkel', 'hotel', 'gym'] as $needle) {
            if (str_contains($type, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeStructuredDataJob failed: ' . $exception->getMessage(), ['project_id' => $this->project->id]);
        $this->report('failed', 0, 'Terjadi kesalahan tak terduga. Silakan coba lagi.');
    }
}
