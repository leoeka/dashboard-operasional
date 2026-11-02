<?php

namespace App\Jobs;

use App\Models\MockupCandidate;
use App\Services\MockupCandidateRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menjalankan loop AI (rencana -> kritik diri -> markah -> kritik potret)
 * untuk satu kandidat mockup di belakang antrean, supaya request web tidak
 * menunggu menit-menitan panggilan model dan potret Chromium.
 *
 * Baris kandidat adalah klaim kerja: sukses berarti status naik ke
 * awaiting_review oleh runner; gagal berarti alasan gagalnya dicatat di
 * baris yang sama, jadi gerbang tim bisa menampilkan kenapa dan menawarkan
 * coba lagi. Kegagalan tidak dilempar ulang - tries = 1, jejaknya di baris.
 */
class GenerateMockupCandidateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public MockupCandidate $candidate)
    {
    }

    public function handle(): void
    {
        try {
            MockupCandidateRunner::make($this->candidate)
                ->run($this->candidate->project, $this->candidate);
        } catch (Throwable $e) {
            $this->recordFailure($e);
        }
    }

    /**
     * Pekerja antrean memanggil ini saat job dibunuh atau lewat batas waktu
     * (600 detik) - jalur itu tidak lewat catch di handle(), dan tanpa
     * pencatatan di sini barisnya akan menggantung "sedang dibuat" selamanya
     * tanpa tombol coba lagi di gerbang.
     */
    public function failed(Throwable $e): void
    {
        $this->recordFailure($e);
    }

    private function recordFailure(Throwable $e): void
    {
        $this->candidate->update([
            'failure_reason' => Str::limit($e->getMessage(), 500),
        ]);

        Log::warning('GenerateMockupCandidateJob gagal untuk kandidat #' . $this->candidate->id . ': ' . $e->getMessage());
    }
}
