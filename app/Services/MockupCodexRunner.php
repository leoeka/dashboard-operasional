<?php

namespace App\Services;

use App\Models\MockupCandidate;
use App\Models\Project;
use App\Support\MockupCodexAssets;
use App\Support\MockupCodexReference;
use App\Support\MockupCodexState;
use App\Support\MockupCodexText;
use App\Support\MockupGate;
use App\Support\MockupWorkspace;
use App\Support\PageSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Runner jalur Codex: satu proses per proyek, kandidat dikerjakan berurutan
 * 1..3. Tiap kandidat dijalankan `codex exec --json` di folder kerjanya;
 * baris JSON dipakai sebagai denyut, foto dipulihkan dari cache, lalu
 * pratinjau dipotret dengan Chromium yang sama dengan meja periksa tema.
 *
 * Kelas ini hanya memanggil Codex CLI. Baris kandidat tetap model yang sama,
 * jadi gerbang tim, proposal, dan build tema tidak perlu tahu bahwa
 * desainnya ditulis oleh Codex.
 */
class MockupCodexRunner
{
    /** Perkiraan tahap dari jenis baris yang dilaporkan Codex. */
    private const TAHAP_ITEM = [
        'reasoning' => 'menyusun rencana',
        'command_execution' => 'menulis berkas',
        'file_change' => 'menyimpan berkas',
        'mcp_tool_call' => 'memakai alat',
        'image_generation' => 'membuat foto',
        'agent_message' => 'menyusun jawaban',
    ];

    public function __construct(
        private MockupWorkspace $workspace,
        private MockupCodexState $state,
        private MockupGate $gate,
        private MockupCodexPhotos $photos,
        private PageSnapshot $snapshot,
        private MockupCodexAssets $aset,
        private MockupCodexReference $acuan,
    ) {
    }

    /**
     * @return int 0 = selesai (walau ada kandidat gagal), 1 = semua gagal
     */
    public function run(Project $project, ?int $onlyCandidate = null): int
    {
        $lock = $this->kunci($project);

        if ($lock === null) {
            Log::info('mockup:codex dilewati, runner lain masih berjalan', ['project' => $project->id]);

            return 0;
        }

        try {
            $kandidat = $this->kandidatUntuk($project, $onlyCandidate);

            if ($kandidat->isEmpty()) {
                $this->state->write($project, [
                    'keadaan' => MockupCodexState::KEADAAN_SELESAI,
                    'tahap' => 'selesai',
                    'pesan' => 'Tidak ada kandidat yang perlu dibuat.',
                    'selesai' => now()->toIso8601String(),
                ]);

                return 0;
            }

            $totalToken = 0;
            $gagal = [];

            $this->state->write($project, [
                'keadaan' => MockupCodexState::KEADAAN_JALAN,
                'kandidat_ke' => 0,
                'jumlah' => $kandidat->count(),
                'tahap' => 'menyiapkan',
                'token' => 0,
                'pesan' => '',
                'mulai' => now()->toIso8601String(),
                'selesai' => null,
                'versi' => $this->state->versiCodex(),
                'proyek' => $project->name,
            ]);

            foreach ($kandidat as $urutan => $row) {
                $nomorUrut = $urutan + 1;

                $this->state->write($project, [
                    'kandidat_ke' => $nomorUrut,
                    'tahap' => 'menyiapkan kandidat ' . $nomorUrut,
                ]);

                try {
                    $totalToken += $this->buatSatu($project, $row, $totalToken);
                } catch (Throwable $e) {
                    $this->catatGagal($row, $e->getMessage());
                    $gagal[] = 'Kandidat ' . $row->candidate_number . ': ' . $e->getMessage();

                    $this->state->write($project, [
                        'tahap' => 'kandidat ' . $nomorUrut . ' gagal',
                        'pesan' => $e->getMessage(),
                        'token' => $totalToken,
                    ]);
                }
            }

            $semuaGagal = count($gagal) === $kandidat->count();

            $this->state->write($project, [
                'keadaan' => $semuaGagal ? MockupCodexState::KEADAAN_GAGAL : MockupCodexState::KEADAAN_SELESAI,
                'tahap' => $semuaGagal ? 'gagal' : 'selesai',
                'pesan' => implode(' ', $gagal),
                'token' => $totalToken,
                'selesai' => now()->toIso8601String(),
            ]);

            return $semuaGagal ? 1 : 0;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return resource|null
     */
    private function kunci(Project $project)
    {
        $path = $this->state->lockPath($project);
        $handle = fopen($path, 'c');

        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return null;
        }

        ftruncate($handle, 0);
        fwrite($handle, (string) getmypid());
        fflush($handle);

        return $handle;
    }

    /**
     * @return \Illuminate\Support\Collection<int, MockupCandidate>
     */
    private function kandidatUntuk(Project $project, ?int $onlyCandidate)
    {
        $set = $this->gate->currentSet($project);

        if ($onlyCandidate !== null) {
            $row = $set->get($onlyCandidate);

            return $row !== null && $row->isRunning()
                ? collect([$row])
                : collect();
        }

        return $set->filter(fn(MockupCandidate $row) => $row->isRunning())->sortKeys()->values();
    }

    private function buatSatu(Project $project, MockupCandidate $candidate, int $tokenSebelumnya): int
    {
        $candidate->update([
            'folder' => $this->workspace->relativeFolder($candidate),
            'failure_reason' => null,
        ]);

        $dir = $this->workspace->candidatePath($candidate);
        $kerja = $this->state->kerjaDir($project, $candidate);
        $nomor = $candidate->candidate_number;

        try {
            $this->updateProgress($candidate, 15, 'Menyiapkan brief kandidat ' . $nomor);
            $this->tulisBrief($project, $candidate, $kerja, $dir);
            $this->aset->letakan($project, $kerja);
            @unlink($kerja . '/foto-slot.txt');

            $mulai = now();
            $this->state->write($project, [
                'tahap' => 'Codex merancang kandidat ' . $nomor,
                'token' => $tokenSebelumnya,
            ]);

            [$threadId, $token] = $this->jalankanCodex($project, $candidate, $kerja, $mulai, $tokenSebelumnya);

            $this->salinKerja($kerja, $dir);
            $this->pastikanHalaman($dir);

            $this->updateProgress($candidate, 70, 'Memeriksa foto');
            $this->state->write($project, ['tahap' => 'memeriksa foto kandidat ' . $nomor]);

            $cacheDir = $threadId !== null ? $this->state->cacheFolder($threadId) : null;

            if ($cacheDir === null) {
                $cacheDir = $this->state->cacheFolderTerbaru($mulai);
            }

            // Codex bebas menamai slot fotonya, jadi yang dituntut adalah nama
            // yang benar-benar dimintakan ke halaman - bukan sepasang nama baku.
            $slots = $this->slotDariHalaman($dir);

            if ($slots !== []) {
                $hasil = $this->photos->recover($dir, $dir . '/foto-slot.txt', $cacheDir, $slots);
                $this->pastikanFotoDipakai($dir, array_keys($hasil['found']));
            }

            $this->photos->bersihkanCache($cacheDir);

            $index = $dir . '/index.html';

            if (!is_file($index)) {
                throw new RuntimeException('Codex selesai tanpa menulis index.html.');
            }

            $this->updateProgress($candidate, 88, 'Memotret pratinjau');
            $this->state->write($project, ['tahap' => 'memotret kandidat ' . $nomor]);

            $this->snapshot->captureFile($index, $dir . '/preview.png');

            $candidate->update([
                'idea_sentence' => $this->bacaIde($dir),
                'png_path' => $candidate->folder . '/preview.png',
                'status' => MockupCandidate::STATUS_AWAITING_REVIEW,
                'failure_reason' => null,
                'critique_rounds' => 0,
            ]);

            $this->updateProgress($candidate, 100, 'Selesai');

            return $token;
        } finally {
            File::deleteDirectory($kerja);
        }
    }

    private function tulisBrief(Project $project, MockupCandidate $candidate, string $kerja, string $arsip): void
    {
        $lain = $this->gate->currentSet($project)
            ->reject(fn(MockupCandidate $row) => $row->id === $candidate->id)
            ->pluck('idea_sentence')
            ->filter(fn($ide) => is_string($ide) && trim($ide) !== '')
            ->take(3)
            ->values()
            ->all();

        // Acuan yang rusak bukan alasan kandidat gagal dikerjakan: tanpa
        // acuan Codex kembali ke arah desain biasa, jadi cukup dicatat.
        try {
            $acuan = $this->acuan->letakan($project, $kerja);
        } catch (Throwable $e) {
            Log::warning('Acuan desain tidak bisa disiapkan', [
                'project' => $project->id,
                'sebab' => $e->getMessage(),
            ]);

            $acuan = null;
        }

        $brief = MockupCodexBrief::build($project, $candidate, 3, $lain, $acuan);

        if (file_put_contents($kerja . '/brief.txt', $brief) === false) {
            throw new RuntimeException('Brief kandidat tidak bisa ditulis.');
        }

        file_put_contents($arsip . '/brief.txt', $brief);
    }

    /**
     * Salin seluruh hasil kerja Codex (HTML, halaman, catatan foto) dari
     * folder sementara ke folder kandidat yang dibaca pratinjau dan build.
     */
    private function salinKerja(string $kerja, string $dir): void
    {
        if (!File::copyDirectory($kerja, $dir)) {
            throw new RuntimeException('Hasil kerja Codex tidak bisa disalin ke folder kandidat.');
        }
    }

    /**
     * Tanpa satu berkas HTML pun, kegagalannya bukan soal foto. Pesan yang
     * jujur mencegah pemilik mengejar hal yang salah.
     */
    private function pastikanHalaman(string $dir): void
    {
        $halaman = array_merge(glob($dir . '/*.html') ?: [], glob($dir . '/pages/*.html') ?: []);

        if ($halaman === []) {
            throw new RuntimeException('Codex berhenti tanpa menulis halaman. Rincian ada di codex.log kandidat.');
        }
    }

    /**
     * @return array{0: string|null, 1: int} id sesi Codex dan token sesi ini
     */


    /**
     * GANTI method jalankanCodex() di MockupCodexRunner dengan versi ini.
     *
     * Perubahan:
     * 1. stdout/stderr Codex ditulis LANGSUNG ke berkas (events.jsonl, codex.log),
     *    bukan lewat pipe. Pipe proc_open di Windows tidak bisa non-blocking, jadi
     *    stream_get_contents() bisa menahan loop sampai Codex selesai. Akibatnya
     *    pengecekan timeout dan denyut tidak berjalan tepat waktu.
     * 2. Loop membaca baris baru dari berkas memakai offset.
     * 3. Cek "proses selesai" dilakukan SEBELUM cek timeout, jadi hasil yang
     *    sebenarnya sudah selesai tidak lagi dibuang sebagai "melewati batas waktu".
     */
    private function jalankanCodex(
        Project $project,
        MockupCandidate $candidate,
        string $dir,
        CarbonInterface $mulai,
        int $tokenSebelumnya,
    ): array {
        $masalah = $this->state->masalahMesin();

        if ($masalah !== null) {
            throw new RuntimeException($masalah);
        }

        $perintah = $this->perintah($dir);
        $eventsPath = $dir . '/events.jsonl';
        $logPath = $dir . '/codex.log';

        @unlink($eventsPath);
        @unlink($logPath);

        $descriptors = [
            0 => ['file', DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null', 'r'],
            1 => ['file', $eventsPath, 'w'],
            2 => ['file', $logPath, 'w'],
        ];

        $process = proc_open($perintah, $descriptors, $pipes, $dir, null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            throw new RuntimeException('Proses Codex tidak bisa dijalankan.');
        }

        $pid = (int) (proc_get_status($process)['pid'] ?? 0);
        $timeout = (int) config('services.mockup.codex.timeout', 2100);
        $deadline = time() + $timeout;
        $offset = 0;
        $buffer = '';
        $threadId = null;
        $token = 0;
        $tahapTerakhir = '';
        $denyutTerakhir = time();
        $kode = -1;

        $proses = function (string $baris) use (&$threadId, &$token, &$tahapTerakhir, &$denyutTerakhir, $project, $candidate, $tokenSebelumnya): void {
            [$idSesi, $tokenSesi, $tahap] = $this->uraiBaris($baris, $token);

            if ($idSesi !== null) {
                $threadId = $idSesi;
            }

            $token = $tokenSesi;

            if ($tahap !== null && $tahap !== $tahapTerakhir) {
                $tahapTerakhir = $tahap;
                $this->state->write($project, [
                    'tahap' => $tahap,
                    'token' => $tokenSebelumnya + $token,
                ]);
                $this->updateProgress($candidate, 55, ucfirst($tahap) . '...');
            } else {
                $this->state->write($project, []);
            }

            $denyutTerakhir = time();
        };

        try {
            while (true) {
                $status = proc_get_status($process);

                clearstatcache(true, $eventsPath);
                $baru = @file_get_contents($eventsPath, false, null, $offset);

                if (is_string($baru) && $baru !== '') {
                    $offset += strlen($baru);
                    $buffer .= $baru;

                    while (($posisi = strpos($buffer, "\n")) !== false) {
                        $baris = rtrim(substr($buffer, 0, $posisi), "\r");
                        $buffer = substr($buffer, $posisi + 1);

                        if ($baris !== '') {
                            $proses($baris);
                        }
                    }
                }

                // Selesai dicek lebih dulu: proses yang sudah rampung tidak dianggap timeout.
                if (!$status['running']) {
                    $kode = (int) ($status['exitcode'] ?? -1);
                    break;
                }

                if (time() - $denyutTerakhir >= 10) {
                    $this->state->write($project, ['token' => $tokenSebelumnya + $token]);
                    $denyutTerakhir = time();
                }

                if (time() > $deadline) {
                    $this->matikan($process, $pid);
                    throw new RuntimeException('melewati batas waktu ' . (int) round($timeout / 60) . ' menit.');
                }

                usleep(500000);
            }

            if (trim($buffer) !== '') {
                $proses(trim($buffer));
            }
        } finally {
            proc_close($process);
        }

        if ($kode !== 0) {
            $galat = (string) @file_get_contents($logPath);

            if (preg_match('/not logged in|login required|unauthorized|please log in|401/i', $galat)) {
                throw new RuntimeException('Codex belum masuk (login) di mesin ini.');
            }

            throw new RuntimeException('Codex berhenti sebelum selesai (kode ' . $kode . '). Rincian ada di codex.log kandidat.');
        }

        return [$threadId, $token];
    }
    /**
     * @return array{0: string|null, 1: int, 2: string|null} id sesi, token kumulatif, tahap
     */
    private function uraiBaris(string $baris, int $tokenSebelumnya): array
    {
        $data = json_decode($baris, true);

        if (!is_array($data)) {
            return [null, $tokenSebelumnya, null];
        }

        $jenis = $data['type'] ?? '';

        if ($jenis === 'thread.started') {
            $id = $data['thread_id'] ?? null;

            return [is_string($id) ? $id : null, $tokenSebelumnya, 'menyiapkan sesi Codex'];
        }

        if ($jenis === 'turn.completed') {
            $usage = $data['usage'] ?? [];
            $token = (int) ($usage['total_tokens']
                ?? ((int) ($usage['input_tokens'] ?? 0) + (int) ($usage['output_tokens'] ?? 0)));

            return [null, $tokenSebelumnya + $token, null];
        }

        if (str_starts_with($jenis, 'item.')) {
            $item = $data['item'] ?? [];
            $itemJenis = is_array($item) ? (string) ($item['type'] ?? '') : '';
            $tahap = self::TAHAP_ITEM[$itemJenis] ?? null;

            if ($itemJenis === 'command_execution' && is_array($item)) {
                $perintah = strtolower((string) ($item['command'] ?? ''));

                if (str_contains($perintah, 'image')) {
                    $tahap = 'membuat foto';
                }
            }

            return [null, $tokenSebelumnya, $tahap];
        }

        return [null, $tokenSebelumnya, null];
    }

    /**
     * @return list<string>
     */
    private function perintah(string $dir): array
    {
        $node = $this->state->nodeBinary();

        if ($node === null) {
            throw new RuntimeException('Node.js tidak ditemukan di mesin ini.');
        }

        $perintah = [
            $node,
            $this->state->codexScript(),
            'exec',
            '--json',
            '--skip-git-repo-check',
            '-s',
            'workspace-write',
            '-C',
            $dir,
        ];

        $model = config('services.mockup.codex.model');

        if (is_string($model) && $model !== '') {
            $perintah[] = '--model';
            $perintah[] = $model;
        }

        // Daftar bahan klien hanya ditulis kalau proyek memang punya aset,
        // jadi kandidat untuk proyek tanpa aset jalannya persis seperti dulu.
        $baca = is_file($dir . '/aset.txt') ? 'brief.txt dan aset.txt' : 'brief.txt';

        $perintah[] = 'Baca berkas ' . $baca . ' di folder kerja ini, lalu kerjakan seluruh isinya sampai selesai. Jangan bertanya.';

        return $perintah;
    }

    private function matikan($process, int $pid): void
    {
        if (DIRECTORY_SEPARATOR === '\\' && $pid > 0) {
            exec('taskkill /PID ' . $pid . ' /T /F 2>&1');
        } else {
            @proc_terminate($process, 9);
        }

        for ($i = 0; $i < 20; $i++) {
            usleep(100000);

            if (!proc_get_status($process)['running']) {
                return;
            }
        }
    }

    /**
     * Foto yang sudah disalin harus terpasang di tempat gambar, bukan
     * berdiri sendiri sebagai teks. Kalau tidak, potret dan tema website
     * menampilkan kalimat penanda, bukan fotonya.
     *
     * @param  list<string>  $slots
     */
    private function pastikanFotoDipakai(string $dir, array $slots): void
    {
        $markah = '';

        foreach ($this->berkasHalaman($dir) as $path) {
            $markah .= MockupCodexText::baca($path);
        }

        $telanjang = [];

        foreach ($slots as $slot) {
            if (!$this->penandaDiGambar($markah, '__EXITO_IMAGE:' . $slot)) {
                $telanjang[] = $slot;
            }
        }

        if ($telanjang !== []) {
            throw new RuntimeException('foto ' . implode(', ', $telanjang) . ' ditulis sebagai teks, bukan pada src="..." atau url(...).');
        }
    }

    /**
     * Nama foto yang benar-benar dimintakan ke halaman. Codex bebas membuat
     * namanya sendiri, jadi daftar nama tidak bisa lagi ditulis di sisi kita.
     *
     * @return list<string>
     */
    private function slotDariHalaman(string $dir): array
    {
        $nama = [];

        foreach ($this->berkasHalaman($dir) as $path) {
            preg_match_all('/__EXITO_IMAGE:([A-Za-z0-9][A-Za-z0-9_-]*)/', MockupCodexText::baca($path), $cocok);

            foreach ($cocok[1] as $slot) {
                $nama[$slot] = true;
            }
        }

        return array_keys($nama);
    }

    /**
     * @return list<string>
     */
    private function berkasHalaman(string $dir): array
    {
        return array_merge(glob($dir . '/*.html') ?: [], glob($dir . '/pages/*.html') ?: []);
    }

    private function penandaDiGambar(string $markah, string $penanda): bool
    {
        $p = preg_quote($penanda, '/');

        return preg_match('/\bsrc\s*=\s*(["\'])' . $p . '\1/i', $markah) === 1
            || preg_match('/\bsrcset\s*=\s*(["\'])[^"\']*' . $p . '/i', $markah) === 1
            || preg_match('/url\(\s*(["\']?)' . $p . '\1\s*\)/i', $markah) === 1;
    }

    private function bacaIde(string $dir): string
    {
        $ide = MockupCodexText::baca($dir . '/ide.txt');

        if (trim($ide) !== '') {
            return mb_substr(trim(strtok($ide, "\n") ?: $ide), 0, 300);
        }

        $html = MockupCodexText::baca($dir . '/index.html');

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $cocok)) {
            return mb_substr(trim(strip_tags($cocok[1])), 0, 300);
        }

        return '';
    }

    private function catatGagal(MockupCandidate $candidate, string $alasan): void
    {
        $pesan = mb_substr($alasan, 0, 500);

        $candidate->update(['failure_reason' => $pesan]);
        $this->updateProgress($candidate, 100, 'Gagal: ' . $pesan);

        Log::warning('mockup:codex kandidat gagal', [
            'candidate_id' => $candidate->id,
            'candidate_number' => $candidate->candidate_number,
            'reason' => $pesan,
        ]);
    }

    private function updateProgress(MockupCandidate $candidate, int $percent, string $message): void
    {
        Cache::put(
            "mockup_candidate_{$candidate->id}_progress",
            ['percent' => $percent, 'message' => $message],
            now()->addMinutes(30)
        );
    }
}
