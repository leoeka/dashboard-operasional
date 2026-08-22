<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\KeywordResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Versi "preview" dari GenerateKeywordRecommendationsJob — dipakai saat
 * tim klik "Analisis Sekarang" di form Request Order / Form Project
 * SEBELUM client & project benar-benar dibuat di database. Hasilnya
 * disimpan ke Cache pakai token acak (bukan project_id, karena project-
 * nya memang belum ada), lalu dipindahkan ke seo_requirements begitu
 * form akhirnya di-submit (lihat blok "analysis_token" di
 * RequestOrderController@store dan ProjectController@store/update).
 */
class RunKeywordPreviewAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 150;

    public function __construct(
        public string $token,
        public string $websiteUrl,
        public ?string $location = null,
        public ?string $businessName = null,
        public ?string $businessType = null,
    ) {
    }

    public static function cacheKey(string $token): string
    {
        return "keyword_preview:{$token}";
    }

    private function report(string $status, int $progress, string $message, array $extra = []): void
    {
        Cache::put(self::cacheKey($this->token), array_merge([
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
        ], $extra), now()->addMinutes(15));
    }

    public function handle(KeywordResearchService $service): void
    {
        $this->report('running', 5, 'Memulai proses...');

        // Project SENGAJA TIDAK disimpan (->exists tetap false) — cuma
        // dipakai sebagai wadah konteks (nama, tipe, lokasi) untuk
        // AiServices, karena prompt-nya memang menerima objek Project.
        $context = new Project([
            'name' => $this->businessName ?: '',
            'type' => $this->businessType ?: '',
        ]);
        $context->seo_requirements = [
            'location' => $this->location,
        ];

        try {
            $result = $service->analyze($context, $this->websiteUrl, [], function ($status, $progress, $message) {
                $this->report($status, $progress, $message);
            });
        } catch (\Throwable $e) {
            Log::error('RunKeywordPreviewAnalysisJob gagal: ' . $e->getMessage(), ['token' => $this->token]);
            $this->report('failed', 0, $e->getMessage());
            return;
        }

        $this->report('done', 100, 'Analisis selesai.', [
            'topics' => $result['topics'],
            'recommendations' => $result['recommendations'],
            'competitor_urls' => $result['competitor_urls'],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RunKeywordPreviewAnalysisJob failed: ' . $exception->getMessage(), ['token' => $this->token]);
        $this->report('failed', 0, 'Terjadi kesalahan tak terduga. Silakan coba lagi.');
    }
}