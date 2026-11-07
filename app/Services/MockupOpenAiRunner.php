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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MockupOpenAiRunner
{
    public function __construct(
        private MockupWorkspace $workspace,
        private MockupCodexState $state,
        private MockupGate $gate,
        private MockupOpenAiClient $client,
        private PageSnapshot $snapshot,
        private MockupCodexAssets $assets,
        private MockupCodexReference $reference,
    ) {
    }

    public function run(Project $project, ?int $onlyCandidate = null, ?int $onlyCandidateId = null): int
    {
        $lock = $this->lock($project);

        if ($lock === null) {
            Log::info('mockup:openai dilewati, runner lain masih berjalan', ['project' => $project->id]);

            return 0;
        }

        try {
            $candidates = $this->candidates($project, $onlyCandidate, $onlyCandidateId);

            if ($candidates->isEmpty()) {
                $this->state->write($project, [
                    'keadaan' => MockupCodexState::KEADAAN_SELESAI,
                    'tahap' => 'selesai',
                    'pesan' => 'Tidak ada kandidat yang perlu dibuat.',
                    'selesai' => now()->toIso8601String(),
                ]);

                return 0;
            }

            $totalTokens = 0;
            $failures = [];

            $this->state->write($project, [
                'keadaan' => MockupCodexState::KEADAAN_JALAN,
                'kandidat_ke' => 0,
                'jumlah' => $candidates->count(),
                'tahap' => 'menyiapkan',
                'token' => 0,
                'pesan' => '',
                'mulai' => now()->toIso8601String(),
                'selesai' => null,
                'versi' => config('services.mockup.openai.model'),
                'proyek' => $project->name,
            ]);

            foreach ($candidates as $index => $candidate) {
                $position = $index + 1;
                $this->state->write($project, [
                    'kandidat_ke' => $position,
                    'tahap' => 'menyiapkan kandidat ' . $position,
                ]);

                try {
                    $totalTokens += $this->buildCandidate($project, $candidate, $totalTokens);
                } catch (Throwable $e) {
                    $this->recordFailure($candidate, $e->getMessage());
                    $failures[] = 'Kandidat ' . $candidate->candidate_number . ': ' . $e->getMessage();
                    $this->state->write($project, [
                        'tahap' => 'kandidat ' . $position . ' gagal',
                        'pesan' => $e->getMessage(),
                        'token' => $totalTokens,
                    ]);
                }
            }

            $allFailed = count($failures) === $candidates->count();
            $this->state->write($project, [
                'keadaan' => $allFailed ? MockupCodexState::KEADAAN_GAGAL : MockupCodexState::KEADAAN_SELESAI,
                'tahap' => $allFailed ? 'gagal' : 'selesai',
                'pesan' => implode(' ', $failures),
                'token' => $totalTokens,
                'selesai' => now()->toIso8601String(),
            ]);

            return $allFailed ? 1 : 0;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return resource|null
     */
    private function lock(Project $project)
    {
        $handle = fopen($this->state->lockPath($project), 'c');

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
    private function candidates(Project $project, ?int $onlyCandidate, ?int $onlyCandidateId = null)
    {
        $set = $this->gate->currentSet($project);

        if ($onlyCandidate !== null) {
            $candidate = $set->get($onlyCandidate);

            return $candidate !== null
                && ($onlyCandidateId === null || $candidate->id === $onlyCandidateId)
                && $candidate->isRunning()
                ? collect([$candidate])
                : collect();
        }

        return $set->filter(fn (MockupCandidate $candidate) => $candidate->isRunning())
            ->sortKeys()
            ->values();
    }

    private function buildCandidate(Project $project, MockupCandidate $candidate, int $tokensBefore): int
    {
        $candidate->update([
            'folder' => $this->workspace->relativeFolder($candidate),
            'failure_reason' => null,
        ]);

        $candidateDir = $this->workspace->candidatePath($candidate);
        $workDir = $this->state->kerjaDir($project, $candidate);

        try {
            $this->updateProgress($candidate, 10, 'Menyiapkan brief dan aset kandidat');
            [$brief, $inputImages, $clientAssets, $referencePath] = $this->prepareInput($project, $candidate, $workDir, $candidateDir);

            $this->state->write($project, [
                'tahap' => 'GPT membuat desain, gambar, dan HTML kandidat ' . $candidate->candidate_number,
                'token' => $tokensBefore,
            ]);
            $this->updateProgress($candidate, 20, 'Mengirim brief ke OpenAI');

            $result = $this->client->generate($brief, $inputImages);
            $tokens = $result['usage']['total_tokens'];
            $this->state->write($project, [
                'tahap' => 'Memeriksa berkas dan gambar kandidat ' . $candidate->candidate_number,
                'token' => $tokensBefore + $tokens,
            ]);
            $this->updateProgress($candidate, 65, 'Memeriksa berkas dan gambar');

            $output = $this->parseOutput($result['output_text']);
            $this->writeFiles($output['files'], $project, $candidate, $candidateDir, $referencePath);
            $this->saveImages($output['generated_images'], $result['images'], $candidateDir, $referencePath === null);
            $this->copyClientAssets($clientAssets, $candidateDir);
            $this->writeAssetManifests($output['generated_images'], $clientAssets, $workDir, $candidateDir, $referencePath);
            $this->validateCandidate($project, $candidateDir, $output['generated_images'], $clientAssets);

            $index = $candidateDir . '/index.html';
            $this->updateProgress($candidate, 88, 'Membuat pratinjau');
            $this->state->write($project, ['tahap' => 'Membuat pratinjau kandidat ' . $candidate->candidate_number]);
            $this->snapshot->captureFile($index, $candidateDir . '/preview.png');

            $candidate->update([
                'idea_sentence' => $this->readIdea($candidateDir),
                'png_path' => $candidate->folder . '/preview.png',
                'status' => MockupCandidate::STATUS_AWAITING_REVIEW,
                'failure_reason' => null,
                'critique_rounds' => 0,
            ]);
            $this->updateProgress($candidate, 100, 'Selesai');

            Log::info('mockup:openai kandidat selesai', [
                'candidate_id' => $candidate->id,
                'response_id' => $result['response_id'],
                'model' => config('services.mockup.openai.model'),
                'generated_images' => count($result['images']),
                'total_tokens' => $tokens,
            ]);

            return $tokens;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * @return array{
     *     0: string,
     *     1: list<array{label: string, path: string, mime: string}>,
     *     2: list<array{label: string, path: string, extension: string, category: string}>,
     *     3: string|null
     * }
     */
    private function prepareInput(Project $project, MockupCandidate $candidate, string $workDir, string $candidateDir): array
    {
        $clientAssets = [];
        $images = [];

        foreach ($this->assets->letakan($project, $workDir) as $asset) {
            $extension = strtolower((string) pathinfo($asset['jalur'], PATHINFO_EXTENSION));
            $mime = $this->mimeForExtension($extension);

            if ($mime === null) {
                throw new RuntimeException('Aset klien ' . $asset['asal'] . ' memakai format yang belum didukung jalur API. Gunakan PNG, JPG, atau WebP.');
            }

            $label = (string) pathinfo($asset['jalur'], PATHINFO_FILENAME);
            $path = $workDir . '/' . $asset['jalur'];
            $clientAssets[] = [
                'label' => $label,
                'path' => $path,
                'extension' => $extension,
                'category' => $asset['kategori'],
            ];
            $images[] = ['label' => $label, 'path' => $path, 'mime' => $mime];
        }

        $referencePath = $this->reference->letakan($project, $workDir);

        if ($referencePath !== null) {
            $referenceFile = $workDir . '/' . $referencePath;
            $extension = strtolower((string) pathinfo($referenceFile, PATHINFO_EXTENSION));
            $mime = $this->mimeForExtension($extension);

            if ($mime === null) {
                throw new RuntimeException('Format gambar referensi tidak didukung. Gunakan PNG, JPG, atau WebP.');
            }

            $images[] = ['label' => 'design-reference', 'path' => $referenceFile, 'mime' => $mime];
        }

        $others = $this->gate->currentSet($project)
            ->reject(fn (MockupCandidate $row) => $row->id === $candidate->id)
            ->pluck('idea_sentence')
            ->filter(fn ($idea) => is_string($idea) && trim($idea) !== '')
            ->take(3)
            ->values()
            ->all();

        $brief = MockupCodexBrief::build($project, $candidate, 3, $others, $referencePath);
        $brief .= $this->apiOutputInstructions($clientAssets, $referencePath !== null);

        if (file_put_contents($workDir . '/brief.txt', $brief) === false
            || file_put_contents($candidateDir . '/brief.txt', $brief) === false) {
            throw new RuntimeException('Brief kandidat tidak bisa disimpan.');
        }

        return [$brief, $images, $clientAssets, $referencePath];
    }

    /**
     * @param  list<array{label: string, path: string, extension: string, category: string}>  $clientAssets
     */
    private function apiOutputInstructions(array $clientAssets, bool $hasReference): string
    {
        $lines = [
            '',
            '## Kontrak eksekusi OpenAI API',
            'Brief di atas adalah sumber utama untuk arah desain, data bisnis, struktur halaman, dan mutu. Instruksi yang meminta menjalankan shell, mencari path di mesin lokal, menulis langsung ke folder kerja, atau mengambil gambar dari cache Codex tidak berlaku pada API. Aplikasi akan menyimpan keluaran terstruktur ini.',
            'Jawab hanya dengan JSON sesuai skema API. Isi `files` dengan berkas teks lengkap: `index.html`, `rencana.json`, `design-tokens.json`, `ide.txt`, serta tepat semua `pages/<slug>.html` yang diminta brief. Jangan masukkan gambar biner atau blok Markdown ke `files`.',
            'Gunakan token `__EXITO_IMAGE:<slot>` pada setiap `src` atau `url(...)` gambar. Jangan memakai data URI, URL gambar luar, path lokal, atau nama berkas yang tidak ada di manifest.',
            'Aset klien yang disertakan sebagai gambar input memiliki nama token berikut:',
        ];

        if ($clientAssets === []) {
            $lines[] = '- Tidak ada aset gambar klien.';
        } else {
            foreach ($clientAssets as $asset) {
                $lines[] = '- `' . $asset['label'] . '` (' . $asset['category'] . '): gunakan token `__EXITO_IMAGE:' . $asset['label'] . '` bila aset ini dipakai. Logo harus dipakai apa adanya dan bukan dibuat ulang.';
            }
        }

        if ($hasReference) {
            $lines[] = 'Gambar input bernama `design-reference` adalah acuan desain. Ikuti komposisi dan gaya visualnya; jangan pasang gambar acuan tersebut sebagai foto situs.';
            $lines[] = 'Dalam `generated_images`, tulis daftar slot hanya untuk gambar situs yang dibuat alat gambar, sesuai urutan pemanggilan alat.';
        } else {
            $lines[] = 'Tidak ada acuan dari klien. Gunakan pemanggilan alat gambar pertama hanya untuk membuat gambar mockup referensi 16:9. Simpan hasil pertamanya oleh aplikasi sebagai acuan, jangan gunakan sebagai foto situs.';
            $lines[] = 'Setelah gambar referensi, minta gambar situs melalui alat gambar satu per satu sesuai urutan daftar `generated_images` yang akan kamu kembalikan. Urutan daftar itu harus sama persis dengan urutan hasil gambar alat.';
        }

        $lines[] = 'Buat maksimal 6 gambar untuk dipakai di website, di luar gambar referensi. Slot `hero` wajib ada dan harus dipakai di HTML. Nama slot hanya huruf kecil, angka, dan tanda hubung. Setiap slot gambar situs harus muncul pada HTML dan mempunyai tepat satu hasil gambar alat, kecuali slot aset klien yang menggunakan token asetnya.';
        $lines[] = 'Pastikan tidak ada gambar yang dibuat tetapi tidak dicatat dalam `generated_images`, dan tidak ada slot dalam daftar tersebut yang tidak memiliki hasil gambar alat. Tulis semua isi file sebagai UTF-8.';

        return "\n" . implode("\n", $lines) . "\n";
    }

    /**
     * @return array{files: list<array{path: string, content: string}>, generated_images: list<string>}
     */
    private function parseOutput(string $text): array
    {
        $output = json_decode($text, true);

        if (!is_array($output) || !is_array($output['files'] ?? null) || !is_array($output['generated_images'] ?? null)) {
            throw new RuntimeException('Keluaran OpenAI bukan JSON kandidat yang lengkap.');
        }

        $files = [];

        foreach ($output['files'] as $file) {
            if (!is_array($file) || !is_string($file['path'] ?? null) || !is_string($file['content'] ?? null)) {
                throw new RuntimeException('Keluaran OpenAI memuat berkas dengan format yang salah.');
            }

            $path = str_replace('\\', '/', $file['path']);

            if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
                throw new RuntimeException('Keluaran OpenAI memuat path berkas yang tidak aman.');
            }

            if (isset($files[$path])) {
                throw new RuntimeException('Keluaran OpenAI memuat path berkas duplikat: ' . $path . '.');
            }

            $files[$path] = $file['content'];
        }

        $slots = [];

        foreach ($output['generated_images'] as $slot) {
            if (!is_string($slot) || preg_match('/^[a-z0-9][a-z0-9-]*$/', $slot) !== 1) {
                throw new RuntimeException('Manifest gambar OpenAI memuat nama slot yang tidak valid.');
            }

            if (isset($slots[$slot])) {
                throw new RuntimeException('Manifest gambar OpenAI memuat slot duplikat: ' . $slot . '.');
            }

            $slots[$slot] = true;
        }

        return ['files' => array_map(
            fn (string $path, string $content) => ['path' => $path, 'content' => $content],
            array_keys($files),
            array_values($files),
        ), 'generated_images' => array_keys($slots)];
    }

    /**
     * @param  list<array{path: string, content: string}>  $files
     */
    private function writeFiles(array $files, Project $project, MockupCandidate $candidate, string $dir, ?string $inputReference): void
    {
        $requiredPages = MockupBrief::halamanWajib($project);
        $allowedPaths = [
            'index.html',
            'rencana.json',
            'design-tokens.json',
            'ide.txt',
        ];

        foreach (array_keys($requiredPages) as $slug) {
            $allowedPaths[] = 'pages/' . $slug . '.html';
        }

        $written = [];

        foreach ($files as $file) {
            if (!in_array($file['path'], $allowedPaths, true)) {
                throw new RuntimeException('OpenAI mengembalikan berkas yang tidak diminta: ' . $file['path'] . '.');
            }

            $path = $dir . '/' . $file['path'];
            File::ensureDirectoryExists(dirname($path));

            if (file_put_contents($path, $file['content']) === false) {
                throw new RuntimeException('Berkas hasil OpenAI tidak bisa disimpan: ' . $file['path'] . '.');
            }

            MockupCodexText::baca($path);
            $written[$file['path']] = true;
        }

        foreach ($allowedPaths as $path) {
            if (!isset($written[$path])) {
                throw new RuntimeException('OpenAI tidak mengembalikan berkas wajib: ' . $path . '.');
            }
        }

        foreach (['rencana.json', 'design-tokens.json'] as $jsonFile) {
            $json = json_decode((string) file_get_contents($dir . '/' . $jsonFile), true);

            if (!is_array($json)) {
                throw new RuntimeException('Berkas ' . $jsonFile . ' bukan JSON valid.');
            }
        }

        if (trim(MockupCodexText::baca($dir . '/ide.txt')) === '') {
            throw new RuntimeException('Berkas ide.txt kosong.');
        }

        $referenceNote = $inputReference !== null
            ? 'design: design-reference/' . basename($inputReference)
            : 'design: design-reference/generated-reference.png';

        if (file_put_contents($dir . '/desain-referensi.txt', $referenceNote . "\n") === false) {
            throw new RuntimeException('Catatan gambar referensi tidak bisa disimpan.');
        }

        $candidate->update(['folder' => $this->workspace->relativeFolder($candidate)]);
    }

    /**
     * @param  list<string>  $slots
     * @param  list<array{base64: string, revised_prompt: string}>  $images
     */
    private function saveImages(array $slots, array $images, string $candidateDir, bool $generateReference): void
    {
        if (!in_array('hero', $slots, true)) {
            throw new RuntimeException('Manifest gambar OpenAI tidak menyertakan slot hero.');
        }

        if (count($slots) > 6) {
            throw new RuntimeException('OpenAI menghasilkan lebih dari 6 gambar untuk website.');
        }

        $offset = $generateReference ? 1 : 0;

        if ($generateReference && $images === []) {
            throw new RuntimeException('OpenAI tidak menghasilkan gambar mockup referensi.');
        }

        if (count($images) !== count($slots) + $offset) {
            throw new RuntimeException('Jumlah gambar dari alat OpenAI tidak cocok dengan manifest. Gambar: ' . count($images) . ', slot: ' . count($slots) . '.');
        }

        if ($generateReference) {
            $this->saveImage($images[0]['base64'], $candidateDir . '/design-reference/generated-reference');
        }

        foreach ($slots as $index => $slot) {
            $this->saveImage(
                $images[$index + $offset]['base64'],
                $candidateDir . '/' . $slot,
            );
        }
    }

    private function saveImage(string $base64, string $pathWithoutExtension): void
    {
        $bytes = base64_decode($base64, true);

        if (!is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Data gambar dari OpenAI bukan Base64 yang valid.');
        }

        $info = @getimagesizefromstring($bytes);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => null,
        };

        if ($extension === null) {
            throw new RuntimeException('OpenAI mengembalikan gambar dengan format yang tidak didukung.');
        }

        $maxBytes = max(1024, (int) config('services.mockup.openai.max_generated_image_bytes', 20000000));

        if (strlen($bytes) > $maxBytes) {
            throw new RuntimeException('Gambar dari OpenAI melebihi batas ukuran yang diizinkan.');
        }

        File::ensureDirectoryExists(dirname($pathWithoutExtension));

        if (file_put_contents($pathWithoutExtension . '.' . $extension, $bytes) === false) {
            throw new RuntimeException('Gambar dari OpenAI tidak bisa disimpan.');
        }
    }

    /**
     * @param  list<array{label: string, path: string, extension: string, category: string}>  $assets
     */
    private function copyClientAssets(array $assets, string $candidateDir): void
    {
        foreach ($assets as $asset) {
            $target = $candidateDir . '/' . $asset['label'] . '.' . $asset['extension'];

            if (is_file($target)) {
                throw new RuntimeException('Nama aset klien bertabrakan dengan gambar hasil OpenAI: ' . $asset['label'] . '.');
            }

            if (!copy($asset['path'], $target)) {
                throw new RuntimeException('Aset klien gagal disalin ke folder kandidat: ' . $asset['label'] . '.');
            }
        }
    }

    /**
     * @param  list<string>  $generatedSlots
     * @param  list<array{label: string, path: string, extension: string, category: string}>  $clientAssets
     */
    private function writeAssetManifests(
        array $generatedSlots,
        array $clientAssets,
        string $workDir,
        string $candidateDir,
        ?string $inputReference,
    ): void
    {
        $lines = [];

        foreach ($generatedSlots as $slot) {
            $matches = glob($candidateDir . '/' . $slot . '.{png,jpg,webp}', GLOB_BRACE) ?: [];

            if (count($matches) !== 1) {
                throw new RuntimeException('Gambar hasil OpenAI untuk slot ' . $slot . ' tidak ditemukan atau ganda.');
            }

            $lines[] = $slot . ': ' . basename($matches[0]);
        }

        foreach ($clientAssets as $asset) {
            $lines[] = $asset['label'] . ': ' . basename($asset['label'] . '.' . $asset['extension']);
        }

        if (file_put_contents($candidateDir . '/foto-slot.txt', implode("\n", $lines) . "\n") === false) {
            throw new RuntimeException('Manifest gambar kandidat tidak bisa disimpan.');
        }

        if ($inputReference !== null) {
            $referenceTarget = $candidateDir . '/design-reference/' . basename($inputReference);
            $referenceSource = $workDir . '/' . $inputReference;
            File::ensureDirectoryExists(dirname($referenceTarget));

            if (!copy($referenceSource, $referenceTarget)) {
                throw new RuntimeException('Gambar referensi klien tidak bisa disalin ke arsip kandidat.');
            }
        }
    }

    /**
     * @param  list<string>  $generatedSlots
     * @param  list<array{label: string, path: string, extension: string, category: string}>  $clientAssets
     */
    private function validateCandidate(Project $project, string $dir, array $generatedSlots, array $clientAssets): void
    {
        $pages = array_merge(glob($dir . '/*.html') ?: [], glob($dir . '/pages/*.html') ?: []);

        if (!is_file($dir . '/index.html') || $pages === []) {
            throw new RuntimeException('OpenAI berhenti tanpa beranda atau halaman HTML.');
        }

        foreach (array_keys(MockupBrief::halamanWajib($project)) as $slug) {
            if (!is_file($dir . '/pages/' . $slug . '.html')) {
                throw new RuntimeException('Halaman wajib tidak ditemukan: pages/' . $slug . '.html.');
            }
        }

        $markup = '';

        foreach ($pages as $page) {
            $markup .= MockupCodexText::baca($page);
        }

        preg_match_all('/__EXITO_IMAGE:([A-Za-z0-9][A-Za-z0-9_-]*)/', $markup, $matches);
        $referencedSlots = array_values(array_unique($matches[1] ?? []));
        $availableSlots = array_merge($generatedSlots, array_column($clientAssets, 'label'));
        $missing = array_values(array_diff($referencedSlots, $availableSlots));

        if ($missing !== []) {
            throw new RuntimeException('HTML memakai slot gambar tanpa aset yang cocok: ' . implode(', ', $missing) . '.');
        }

        foreach ($generatedSlots as $slot) {
            if (!in_array($slot, $referencedSlots, true)) {
                throw new RuntimeException('Gambar hasil OpenAI tidak dipakai di HTML: ' . $slot . '.');
            }

            if (count(glob($dir . '/' . $slot . '.{png,jpg,webp}', GLOB_BRACE) ?: []) !== 1) {
                throw new RuntimeException('Berkas gambar tidak tersedia untuk slot ' . $slot . '.');
            }
        }

        foreach ($clientAssets as $asset) {
            if (in_array($asset['label'], $referencedSlots, true) && !is_file($dir . '/' . $asset['label'] . '.' . $asset['extension'])) {
                throw new RuntimeException('Aset klien tidak tersedia untuk slot ' . $asset['label'] . '.');
            }
        }

        if (!in_array('hero', $referencedSlots, true)) {
            throw new RuntimeException('HTML tidak memasang gambar hero.');
        }
    }

    private function mimeForExtension(string $extension): ?string
    {
        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };
    }

    private function readIdea(string $dir): string
    {
        $idea = trim(strtok(MockupCodexText::baca($dir . '/ide.txt'), "\n") ?: '');

        if ($idea !== '') {
            return mb_substr($idea, 0, 300);
        }

        $html = MockupCodexText::baca($dir . '/index.html');

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match) === 1) {
            return mb_substr(trim(strip_tags($match[1])), 0, 300);
        }

        return '';
    }

    private function recordFailure(MockupCandidate $candidate, string $reason): void
    {
        $message = mb_substr($reason, 0, 500);
        $candidate->update(['failure_reason' => $message]);
        $this->updateProgress($candidate, 100, 'Gagal: ' . $message);

        Log::warning('mockup:openai kandidat gagal', [
            'candidate_id' => $candidate->id,
            'candidate_number' => $candidate->candidate_number,
            'reason' => $message,
        ]);
    }

    private function updateProgress(MockupCandidate $candidate, int $percent, string $message): void
    {
        Cache::put(
            'mockup_candidate_' . $candidate->id . '_progress',
            ['percent' => $percent, 'message' => $message],
            now()->addMinutes(30),
        );
    }
}
