<?php

namespace App\Support;

use App\Models\MockupCandidate;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Meja kerja jalur Codex untuk satu proyek: status.json (yang dibaca layar),
 * kunci.lock (satu runner per proyek), runner.log, dan lokasi folder kerja
 * per kandidat.
 *
 * Status sengaja berbahasa Indonesia dan hanya berisi hal yang bisa dibaca
 * pemilik - bukan kode galat mentah. `denyut` adalah bukti proses masih
 * hidup; layar membandingkannya dengan jam sekarang.
 */
class MockupCodexState
{
    public const KEADAAN_MENUNGGU = 'menunggu';
    public const KEADAAN_JALAN = 'jalan';
    public const KEADAAN_SELESAI = 'selesai';
    public const KEADAAN_GAGAL = 'gagal';
    public const KEADAAN_BERHENTI = 'berhenti';

    /** Denyut lebih tua dari ini (detik) dianggap proses sudah mati. */
    public const DENYUT_BASI = 300;

    public function __construct(private MockupWorkspace $workspace) {}

    public function projectDir(Project $project): string
    {
        $base = config('services.mockup.codex.base') ?: $this->workspace->basePath();
        $path = rtrim((string) $base, '/\\') . '/' . $project->id . '/codex';

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    public function statusPath(Project $project): string
    {
        return $this->projectDir($project) . '/status.json';
    }

    public function lockPath(Project $project): string
    {
        return $this->projectDir($project) . '/kunci.lock';
    }

    public function logPath(Project $project): string
    {
        return $this->projectDir($project) . '/runner.log';
    }

    public function orderPath(Project $project): string
    {
        return $this->projectDir($project) . '/pesanan.json';
    }

    /**
     * Folder kerja Codex sengaja di luar repo. Kalau dijalankan di dalam
     * repo, Codex ikut membaca AGENTS.md/DESIGN.md milik aplikasi ini lalu
     * berhenti minta pilihan mode desain — bukan brief klien yang dikerjakan.
     * Hasilnya disalin kembali ke folder kandidat setelah selesai.
     */
    public function kerjaDir(Project $project, MockupCandidate $candidate): string
    {
        $base = config('services.mockup.codex.work');

        if (!is_string($base) || $base === '') {
            $base = rtrim(sys_get_temp_dir(), '/\\') . '/siteflow-codex-work';
        }

        $path = rtrim($base, '/\\') . '/' . $project->id . '-' . $candidate->id . '-' . uniqid();

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(Project $project): ?array
    {
        $path = $this->statusPath($project);

        if (!is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Tulis status dengan denyut baru. Tulis ke berkas sementara lalu
     * diganti, supaya layar tidak pernah membaca JSON setengah jadi.
     *
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function write(Project $project, array $patch): array
    {
        $data = array_merge($this->read($project) ?? [], $patch, [
            'denyut' => now()->toIso8601String(),
        ]);

        $path = $this->statusPath($project);
        $tmp = $path . '.tmp';

        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (!@rename($tmp, $path)) {
            file_put_contents($path, (string) file_get_contents($tmp));
            @unlink($tmp);
        }

        return $data;
    }

    public function heartbeatAgeSeconds(Project $project): ?int
    {
        $data = $this->read($project);
        $denyut = $data['denyut'] ?? null;

        if (!is_string($denyut) || $denyut === '') {
            return null;
        }

        try {
            return max(0, now()->getTimestamp() - Carbon::parse($denyut)->getTimestamp());
        } catch (\Throwable) {
            return null;
        }
    }

    public function stale(Project $project): bool
    {
        $data = $this->read($project);

        if (($data['keadaan'] ?? null) !== self::KEADAAN_JALAN) {
            return false;
        }

        $age = $this->heartbeatAgeSeconds($project);

        return $age !== null && $age > self::DENYUT_BASI;
    }

    /**
     * Keadaan yang pantas ditampilkan: `jalan` yang denyutnya basi dibaca
     * sebagai `berhenti`, bukan terus berputar.
     *
     * @return array<string, mixed>|null
     */
    public function visible(Project $project): ?array
    {
        $data = $this->read($project);

        if ($data === null) {
            return null;
        }

        if ($this->stale($project)) {
            $data['keadaan'] = self::KEADAAN_BERHENTI;
            $data['pesan'] = ($data['pesan'] ?? '') ?: 'Proses berhenti tanpa kabar. Klik coba lagi pada kandidat yang kosong.';
        }

        return $data;
    }

    /**
     * Apakah ada proses runner yang masih memegang kunci proyek ini.
     * Kunci sistem dilepas otomatis saat proses mati, jadi ini bukti yang
     * lebih kuat daripada isi status.json.
     */
    public function terkunci(Project $project): bool
    {
        $handle = @fopen($this->lockPath($project), 'c');

        if ($handle === false) {
            return false;
        }

        if (flock($handle, LOCK_EX | LOCK_NB)) {
            flock($handle, LOCK_UN);
            fclose($handle);

            return false;
        }

        fclose($handle);

        return true;
    }

    public function codexHome(): string
    {
        $configured = config('services.mockup.codex.home');

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/\\');
        }

        $env = getenv('CODEX_HOME');

        if (is_string($env) && $env !== '') {
            return rtrim($env, '/\\');
        }

        $home = getenv('USERPROFILE') ?: getenv('HOME') ?: '';

        return rtrim((string) $home, '/\\') . '/.codex';
    }

    public function codexScript(): string
    {
        $configured = config('services.mockup.codex.script');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $appData = getenv('APPDATA')
            ?: rtrim((string) (getenv('USERPROFILE') ?: ''), '/\\') . '/AppData/Roaming';

        return rtrim($appData, '/\\') . '/npm/node_modules/@openai/codex/bin/codex.js';
    }

    public function nodeBinary(): ?string
    {
        $configured = (string) config('services.mockup.codex.node', 'node');
        $found = (new ExecutableFinder())->find($configured);

        return is_string($found) ? $found : null;
    }

    /**
     * Alasan mesin Codex tidak bisa dipakai, atau null kalau siap.
     */
    public function masalahMesin(): ?string
    {
        if ($this->nodeBinary() === null) {
            return 'Node.js tidak ditemukan di mesin ini.';
        }

        if (!is_file($this->codexScript())) {
            return 'Codex CLI tidak ditemukan di jalur yang dikonfigurasi.';
        }

        return null;
    }

    public function versiCodex(): ?string
    {
        $package = dirname(dirname($this->codexScript())) . '/package.json';

        if (!is_file($package)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($package), true);

        return is_array($data) && is_string($data['version'] ?? null) ? $data['version'] : null;
    }

    public function cacheFolder(string $threadId): ?string
    {
        $path = $this->codexHome() . '/generated_images/' . $threadId;

        return is_dir($path) ? $path : null;
    }

    /**
     * Folder cache yang paling mungkin milik run ini saat baris thread tidak
     * terbaca: subfolder generated_images yang lahir setelah run dimulai.
     */
    public function cacheFolderTerbaru(CarbonInterface $since): ?string
    {
        $root = $this->codexHome() . '/generated_images';

        if (!is_dir($root)) {
            return null;
        }

        $batas = $since->getTimestamp() - 60;
        $kandidat = [];

        foreach (scandir($root) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $root . '/' . $name;

            if (!is_dir($path)) {
                continue;
            }

            $mtime = @filemtime($path);

            if ($mtime !== false && $mtime >= $batas) {
                $kandidat[$path] = $mtime;
            }
        }

        if ($kandidat === []) {
            return null;
        }

        arsort($kandidat);

        return (string) array_key_first($kandidat);
    }
}
