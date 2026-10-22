<?php

namespace App\Jobs;

use App\Models\WpBuild;
use App\Services\WpThemeBuilder;
use App\Services\WpThemeInspector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menjalankan pembangunan tema WordPress dari kandidat mockup terpilih
 * di antrean background.
 *
 * Mengikuti pola job antrean sistem (tries 1, timeout 600).
 * Jika berhasil, WpThemeBuilder memajukan status ke inspection (siap meja periksa).
 * Jika gagal, kegagalan dicatat pada kolom inspection_results dan status diubah
 * ke inspection_failed agar tim dapat melihat kendalanya di dashboard.
 */
class BuildWpThemeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public WpBuild $build)
    {
    }

    public function handle(): void
    {
        $this->build->update(['status' => WpBuild::STATUS_BUILDING]);

        try {
            (new WpThemeBuilder())->build($this->build);
            (new WpThemeInspector())->inspectAndPackage($this->build);
        } catch (Throwable $e) {
            $this->recordFailure($e);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->recordFailure($e);
    }

    private function recordFailure(Throwable $e): void
    {
        Log::error('BuildWpThemeJob gagal untuk Build #' . $this->build->id . ': ' . $e->getMessage());

        $existing = $this->build->fresh()?->inspection_results ?? $this->build->inspection_results ?? [];

        $this->build->update([
            'status' => WpBuild::STATUS_INSPECTION_FAILED,
            'inspection_results' => array_merge($existing, [
                'error' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]),
        ]);
    }
}

