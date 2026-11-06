<?php

namespace App\Jobs;

use App\Models\MockupCandidate;
use App\Models\Project;
use App\Services\MockupOpenAiRunner;
use App\Support\MockupCodexState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateMockupCandidate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 100;
    public int $maxExceptions = 1;
    public int $timeout = 2400;

    public function __construct(
        public int $projectId,
        public int $candidateId,
        public int $candidateNumber,
        public int $queuedCandidates,
    ) {
        $this->onQueue('default');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('mockup-project-' . $this->projectId))
                ->releaseAfter(20)
                ->expireAfter(2460),
        ];
    }

    public function handle(MockupOpenAiRunner $runner, MockupCodexState $state): void
    {
        $project = Project::find($this->projectId);
        $candidate = MockupCandidate::query()
            ->whereKey($this->candidateId)
            ->where('project_id', $this->projectId)
            ->first();

        if ($project === null || $candidate === null || !$candidate->isRunning()) {
            $remaining = $this->finishQueuedCandidate($state, 'Kandidat tidak lagi tersedia untuk diproses.');

            if ($remaining === 0 && $project !== null) {
                $state->write($project, [
                    'keadaan' => MockupCodexState::KEADAAN_GAGAL,
                    'tahap' => 'gagal',
                    'pesan' => 'Kandidat tidak lagi tersedia untuk diproses.',
                    'selesai' => now()->toIso8601String(),
                ]);
            }

            return;
        }

        $runner->run($project, $this->candidateNumber, $this->candidateId);
        $this->finishQueuedCandidate($state);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateMockupCandidate gagal.', [
            'project_id' => $this->projectId,
            'candidate_id' => $this->candidateId,
            'exception' => $exception->getMessage(),
        ]);

        $candidate = MockupCandidate::query()
            ->whereKey($this->candidateId)
            ->where('project_id', $this->projectId)
            ->first();

        if ($candidate?->isRunning()) {
            $candidate->update(['failure_reason' => 'Proses antrean gagal. Periksa log aplikasi.']);
        }

        $state = app(MockupCodexState::class);
        $remaining = $this->finishQueuedCandidate($state, 'Proses antrean kandidat gagal.');

        if ($remaining === 0) {
            $state->write(Project::findOrFail($this->projectId), [
                'keadaan' => MockupCodexState::KEADAAN_GAGAL,
                'tahap' => 'gagal',
                'pesan' => 'Proses antrean kandidat gagal. Periksa alasan pada kartu kandidat.',
                'selesai' => now()->toIso8601String(),
            ]);
        }
    }

    private function finishQueuedCandidate(MockupCodexState $state, ?string $message = null): int
    {
        $project = Project::find($this->projectId);

        if ($project === null) {
            return 0;
        }

        $status = $state->read($project) ?? [];
        $queued = array_values(array_filter(
            (array) ($status['queued_candidate_ids'] ?? []),
            fn ($id) => (int) $id !== $this->candidateId
        ));

        if ($queued !== []) {
            $state->write($project, [
                'keadaan' => MockupCodexState::KEADAAN_MENUNGGU,
                'kandidat_ke' => 0,
                'jumlah' => $this->queuedCandidates,
                'tahap' => 'menunggu kandidat berikutnya',
                'pesan' => $message ?? '',
                'queued_candidate_ids' => $queued,
            ]);

            return count($queued);
        }

        $state->write($project, [
            'queued_candidate_ids' => [],
            'pesan' => $message ?? ($status['pesan'] ?? ''),
        ]);

        return 0;
    }
}
