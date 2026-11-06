<?php

namespace App\Support;

use App\Models\Project;

class MockupOpenAiLauncher
{
    public function __construct(private MockupCodexState $state) {}

    public function launch(Project $project, ?int $candidateNumber = null): bool
    {
        $masalah = $this->masalahMesin();

        if ($masalah !== null) {
            $this->markFailed($project, $masalah);

            return false;
        }

        $this->state->write($project, [
            'keadaan' => MockupCodexState::KEADAAN_JALAN,
            'kandidat_ke' => 0,
            'jumlah' => $candidateNumber === null ? 3 : 1,
            'tahap' => 'menunggu runner API',
            'token' => 0,
            'pesan' => '',
            'mulai' => now()->toIso8601String(),
            'selesai' => null,
            'proyek' => $project->name,
        ]);

        $command = [
            PHP_BINARY,
            base_path('artisan'),
            'mockup:openai',
            (string) $project->id,
        ];

        if ($candidateNumber !== null) {
            $command[] = '--candidate=' . $candidateNumber;
        }

        $log = $this->state->logPath($project);
        $nullDevice = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $descriptors = [
            0 => ['file', $nullDevice, 'r'],
            1 => ['file', $log, 'a'],
            2 => ['file', $log, 'a'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, base_path(), null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            $this->markFailed($project, 'Runner OpenAI tidak bisa dilepas.');

            return false;
        }

        $pid = (int) (proc_get_status($process)['pid'] ?? 0);

        if ($pid > 0) {
            file_put_contents($this->state->lockPath($project), (string) $pid);
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

        if (!function_exists('proc_open')) {
            return 'Server tidak mengizinkan proses background (proc_open).';
        }

        return null;
    }
}
