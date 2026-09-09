<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\CtrGapAnalyzer;
use App\Services\SearchConsoleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SXO — cek query yang sudah ranking bagus tapi CTR-nya di bawah rata-rata
 * (title/meta kurang menarik). Ambil query dari Search Console lalu hitung
 * lewat CtrGapAnalyzer. Hasil ke seo_requirements['ctr_gaps'].
 */
class AnalyzeCtrGapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 90;

    public function __construct(public Project $project)
    {
    }

    public static function cacheKey(int $projectId): string
    {
        return "ctr_gap_progress:{$projectId}";
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

    public function handle(SearchConsoleService $console, CtrGapAnalyzer $analyzer): void
    {
        $this->report('running', 20, 'Mengambil query dari Search Console...');

        $url = $this->resolveWebsiteUrl();
        if (!$url) {
            $this->report('failed', 0, 'URL website klien belum tersedia.');
            return;
        }

        $rows = $console->getTopQueries($url);

        if ($rows === []) {
            $this->report('failed', 0, 'Belum ada data Search Console untuk situs ini — situs baru / belum terindeks / belum terverifikasi di akun kantor.');
            return;
        }

        $this->report('running', 60, 'Menghitung selisih CTR...');
        $result = $analyzer->analyze($rows);

        $seo = $this->project->fresh()->seo_requirements ?? [];
        $seo['ctr_gaps'] = $result;
        $this->project->update(['seo_requirements' => $seo]);

        $count = $result['summary']['opportunity_count'];
        $this->project->logActivity(
            $count > 0
                ? "Analisis CTR gap selesai — {$count} query di halaman 1 dengan CTR di bawah normal"
                : 'Analisis CTR gap selesai — tidak ada query yang menonjol di bawah normal'
        );

        $this->report('done', 100, 'Analisis CTR gap selesai.');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeCtrGapJob failed: ' . $exception->getMessage(), ['project_id' => $this->project->id]);
        $this->report('failed', 0, 'Terjadi kesalahan tak terduga. Silakan coba lagi.');
    }
}
