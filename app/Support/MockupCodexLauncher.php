<?php

namespace App\Support;

use App\Models\Project;

/**
 * Melepas runner Codex dari permintaan web tanpa menunggunya. Proses anak
 * menulis sendiri status.json; permintaan web cukup mencatat pid lalu
 * kembali, jadi halaman tidak pernah menggantung 13 menit.
 *
 * Sengaja memakai PHP_BINARY + artisan, bukan `codex.cmd` langsung:
 * CreateProcess di Windows tidak bisa menjalankan berkas .cmd.
 */
class MockupCodexLauncher
{
    public function __construct(private MockupCodexState $state) {}

    public function launch(Project $project, ?int $candidateNumber = null): bool
    {
        if ($this->state->masalahMesin() !== null) {
            return false;
        }

        $perintah = [
            PHP_BINARY,
            base_path('artisan'),
            'mockup:codex',
            (string) $project->id,
        ];

        if ($candidateNumber !== null) {
            $perintah[] = '--candidate=' . $candidateNumber;
        }

        $log = $this->state->logPath($project);
        $descriptors = [
            0 => ['file', DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null', 'r'],
            1 => ['file', $log, 'a'],
            2 => ['file', $log, 'a'],
        ];

        $process = @proc_open($perintah, $descriptors, $pipes, base_path(), null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            return false;
        }

        $pid = (int) (proc_get_status($process)['pid'] ?? 0);

        if ($pid > 0) {
            file_put_contents($this->state->lockPath($project), (string) $pid);
        }

        // Ganti status lama (mis. "gagal") supaya layar tidak sempat
        // menampilkan kegagalan sebelumnya saat halaman dimuat ulang.
        $this->state->write($project, [
            'keadaan' => MockupCodexState::KEADAAN_JALAN,
            'kandidat_ke' => 0,
            'jumlah' => $candidateNumber === null ? 3 : 1,
            'tahap' => 'menunggu runner',
            'token' => 0,
            'pesan' => '',
            'mulai' => now()->toIso8601String(),
            'selesai' => null,
            'proyek' => $project->name,
        ]);

        // Jangan proc_close(): ia menunggu proses selesai. Pegangan proses
        // dilepas saat permintaan berakhir, anaknya tetap hidup.
        return true;
    }
}
