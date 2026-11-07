<?php

namespace App\Support;

use App\Jobs\GenerateMockupCandidate;
use App\Models\MockupCandidate;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Throwable;

class MockupOpenAiLauncher
{
    public function __construct(
        private MockupCodexState $state,
        private MockupGate $gate,
    ) {}

    public function launch(Project $project, ?int $candidateNumber = null): bool
    {
        $masalah = $this->masalahMesin();

        if ($masalah !== null) {
            $this->markFailed($project, $masalah);

            return false;
        }

        $candidates = $this->gate->currentSet($project)
            ->filter(fn (MockupCandidate $candidate) => $candidate->isRunning())
            ->when($candidateNumber !== null, fn ($set) => $set->filter(
                fn (MockupCandidate $candidate) => $candidate->candidate_number === $candidateNumber
            ))
            ->values();

        if ($candidates->isEmpty()) {
            $this->markFailed($project, 'Tidak ada kandidat yang siap dimasukkan ke antrean.');

            return false;
        }

        $this->state->write($project, [
            'keadaan' => MockupCodexState::KEADAAN_MENUNGGU,
            'kandidat_ke' => 0,
            'jumlah' => $candidates->count(),
            'tahap' => 'menunggu worker queue',
            'token' => 0,
            'pesan' => '',
            'mulai' => now()->toIso8601String(),
            'selesai' => null,
            'proyek' => $project->name,
            'queued_candidate_ids' => $candidates->pluck('id')->all(),
        ]);

        try {
            foreach ($candidates as $candidate) {
                GenerateMockupCandidate::dispatch(
                    $project->id,
                    $candidate->id,
                    $candidate->candidate_number,
                    $candidates->count(),
                );
            }
        } catch (Throwable $exception) {
            Log::error('Gagal memasukkan kandidat mockup ke queue.', [
                'project_id' => $project->id,
                'exception' => $exception->getMessage(),
            ]);
            $this->markFailed($project, 'Kandidat tidak bisa dimasukkan ke antrean: ' . $exception->getMessage());
            $candidates->each(fn (MockupCandidate $candidate) => $candidate->update([
                'failure_reason' => 'Kandidat tidak bisa dimasukkan ke antrean.',
            ]));

            return false;
        }

        return true;
    }

    private function markFailed(Project $project, string $message): void
    {
        $this->state->write($project, [
            'keadaan' => MockupCodexState::KEADAAN_GAGAL,
            'tahap' => 'gagal',
            'pesan' => $message,
            'selesai' => now()->toIso8601String(),
        ]);
    }

    public function masalahMesin(): ?string
    {
        $key = config('services.openai.key');

        if (!is_string($key) || trim($key) === '') {
            return 'OPENAI_API_KEY belum diatur pada server.';
        }

        if (in_array(config('queue.connections.' . config('queue.default') . '.driver'), ['sync', 'null'], true)) {
            return 'Queue belum memakai worker latar. Atur QUEUE_CONNECTION=database dan aktifkan worker agar mockup tidak berjalan di request web.';
        }

        return null;
    }
}
