<?php

use App\Models\MockupCandidate;
use App\Services\MockupCandidateRunner;
use App\Support\MockupWorkspace;
use Illuminate\Support\Facades\Http;

function runnerChromiumAda(): bool
{
    $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;

    if ($home === null) {
        return false;
    }

    return is_dir(getcwd() . '/node_modules/puppeteer') && is_dir($home . '/.cache/puppeteer/chrome');
}

function runnerBukaKunciOpenAi(): void
{
    config([
        'services.openai.key' => 'kunci-uji',
        'services.openai.mockup_model' => 'model-uji',
    ]);
}

function runnerRencana(string $kalimatIde): string
{
    return json_encode([
        'kalimat_ide' => $kalimatIde,
        'palet' => ['#0B3C5D', '#1D70A2', '#F6F1E7', '#C89B3C'],
        'pasangan_huruf' => 'Playfair Display + Inter',
        'konsep_tata_letak' => 'hero penuh lebar lalu kolom editorial',
        'elemen_tanda_tangan' => 'garis ombak tipis di bawah hero',
    ]);
}

function runnerHtml(): string
{
    return '<!doctype html><html><head><meta charset="utf-8"><title>Vila</title></head>'
        . '<body><img src="__EXITO_IMAGE:hero" alt="Vila">'
        . '<a href="__EXITO_PAGE_URL:telepon">Telepon kami</a></body></html>';
}

function runnerHtmlPerbaikan(): string
{
    return '<!doctype html><html><head><meta charset="utf-8"><title>Vila</title></head>'
        . '<body><img src="__EXITO_IMAGE:hero" alt="Vila">'
        . '<a href="__EXITO_PAGE_URL:telepon">Telepon kami</a>'
        . '<p>perbaikan kontras</p></body></html>';
}

/**
 * Http::response() membungkus jawaban dalam promise; pipeline fake yang
 * mengurainya, jadi jangan memberi tipe kembalian di sini.
 */
function runnerBalasan(string $teks)
{
    return Http::response(['status' => 'completed', 'output' => [[
        'type' => 'message',
        'content' => [['type' => 'output_text', 'text' => $teks]],
    ]]], 200);
}

function runnerPanggilan(string $nama, string $arguments)
{
    static $urut = 0;

    return Http::response(['status' => 'completed', 'output' => [[
        'type' => 'function_call',
        'name' => $nama,
        'arguments' => $arguments,
        'call_id' => 'call-' . $nama . '-' . ++$urut,
    ]]], 200);
}

/**
 * Otak palsu yang menjawab berdasarkan banyaknya pesan user di percakapan:
 * pesan 1 -> rencana draf, pesan 2 -> rencana final, pesan 3 -> tulis
 * index.html, pesan 4-5 -> ronde kritik. Mode ronde kritik:
 * 'perbaikan-lalu-bersih' = potret lalu perbaiki di ronde 1, bersih di 2;
 * 'langsung-bersih'       = ronde 1 langsung puas tanpa mengubah berkas;
 * 'terus-menulis'         = masih mengubah berkas di tiap ronde.
 */
function runnerOtak(array &$requests, string $mode, string $rencanaDraf, string $rencanaFinal): Closure
{
    return function ($request) use (&$requests, $mode, $rencanaDraf, $rencanaFinal) {
        $requests[] = json_decode($request->body(), true);
        $input = end($requests)['input'];

        $jumlahUser = count(array_filter(
            $input,
            fn ($item) => ($item['type'] ?? null) === 'message' && ($item['role'] ?? null) === 'user'
        ));
        $posUserTerakhir = -1;

        foreach ($input as $i => $item) {
            if (($item['type'] ?? null) === 'message' && ($item['role'] ?? null) === 'user') {
                $posUserTerakhir = $i;
            }
        }

        $panggilan = [];

        foreach ($input as $i => $item) {
            if ($i > $posUserTerakhir && ($item['type'] ?? null) === 'function_call') {
                $panggilan[] = $item['name'];
            }
        }

        $terakhir = $panggilan === [] ? null : end($panggilan);

        if ($jumlahUser >= 4) {
            return runnerBalasanRondeKritik($mode, $jumlahUser, $terakhir);
        }

        if ($jumlahUser >= 3) {
            if ($terakhir === null) {
                return runnerPanggilan('tulis_berkas', json_encode(['nama' => 'index.html', 'isi' => runnerHtml()]));
            }

            return runnerBalasan('selesai');
        }

        return runnerBalasan($jumlahUser === 2 ? $rencanaFinal : $rencanaDraf);
    };
}

function runnerBalasanRondeKritik(string $mode, int $jumlahUser, ?string $terakhir)
{
    if ($mode === 'langsung-bersih') {
        return runnerBalasan('selesai');
    }

    if ($mode === 'terus-menulis') {
        if ($terakhir === null) {
            return runnerPanggilan('tulis_berkas', json_encode(['nama' => 'index.html', 'isi' => runnerHtmlPerbaikan()]));
        }

        return runnerBalasan('selesai');
    }

    // perbaikan-lalu-bersih: ronde 1 potret lalu tulis ulang, ronde 2 puas.
    if ($jumlahUser === 4 && $terakhir === null) {
        return runnerPanggilan('lihat_potret', '{}');
    }

    if ($jumlahUser === 4 && $terakhir === 'lihat_potret') {
        return runnerPanggilan('tulis_berkas', json_encode(['nama' => 'index.html', 'isi' => runnerHtmlPerbaikan()]));
    }

    return runnerBalasan('selesai');
}

it('menjalankan empat babak untuk tiga kandidat dan meninggalkan artefak sah di tiap folder', function () {
    if (!runnerChromiumAda()) {
        $this->markTestSkipped('Chromium puppeteer belum terpasang di mesin ini.');
    }

    runnerBukaKunciOpenAi();
    $requests = [];
    Http::preventStrayRequests();
    Http::fake(runnerOtak($requests, 'perbaikan-lalu-bersih', runnerRencana('Vila biru draf'), runnerRencana('Vila biru yang tenang')));

    $project = mockupProject();
    $workspace = new MockupWorkspace();

    try {
        $folders = [];

        foreach ([1, 2, 3] as $number) {
            $candidate = mockupCandidate($project, $number);
            MockupCandidateRunner::make($candidate)->run($project, $candidate);

            $folder = $candidate->fresh()->folder;
            $folders[$number] = $folder;

            expect($folder)->toBe($workspace->relativeFolder($candidate));
            expect($candidate->fresh()->idea_sentence)->toBe('Vila biru yang tenang');
            expect($candidate->fresh()->png_path)->toBe($folder . '/preview.png');
            expect($candidate->fresh()->critique_rounds)->toBe(2);
            expect($candidate->fresh()->status)->toBe(MockupCandidate::STATUS_AWAITING_REVIEW);
            expect(file_get_contents($workspace->resolve($folder) . '/index.html'))
                ->toContain('__EXITO_IMAGE:hero')
                ->toContain('__EXITO_PAGE_URL:telepon')
                ->toContain('perbaikan');
            expect(is_file($workspace->resolve($folder) . '/preview.png'))->toBeTrue();
        }

        expect(count(array_unique($folders)))->toBe(3);
        expect(count($requests))->toBe(24);

        // Ronde markah harus membawa rencana FINAL (bukan draf) — kalau tidak,
        // index.html bisa lahir dari rencana yang berbeda dari kalimat ide tersimpan.
        $pesanMarkah = $requests[2]['input'][2]['content'][0]['text'];
        expect($pesanMarkah)->toContain('Vila biru yang tenang');
    } finally {
        $workspace->deleteProject($project->id);
    }
});

it('cukup satu ronde kritik kalau hasilnya sudah bersih', function () {
    if (!runnerChromiumAda()) {
        $this->markTestSkipped('Chromium puppeteer belum terpasang di mesin ini.');
    }

    runnerBukaKunciOpenAi();
    $requests = [];
    Http::preventStrayRequests();
    Http::fake(runnerOtak($requests, 'langsung-bersih', runnerRencana('Draf'), runnerRencana('Bersih sejak awal')));

    $project = mockupProject();
    $workspace = new MockupWorkspace();

    try {
        $candidate = mockupCandidate($project);
        MockupCandidateRunner::make($candidate)->run($project, $candidate);

        expect($candidate->fresh()->critique_rounds)->toBe(1);
        expect($candidate->fresh()->status)->toBe(MockupCandidate::STATUS_AWAITING_REVIEW);
        expect(count($requests))->toBe(5);
    } finally {
        $workspace->deleteProject($project->id);
    }
});

it('berhenti di ronde kedua meski otak masih ingin mengubah berkas', function () {
    if (!runnerChromiumAda()) {
        $this->markTestSkipped('Chromium puppeteer belum terpasang di mesin ini.');
    }

    runnerBukaKunciOpenAi();
    $requests = [];
    Http::preventStrayRequests();
    Http::fake(runnerOtak($requests, 'terus-menulis', runnerRencana('Draf'), runnerRencana('Belum puas')));

    $project = mockupProject();
    $workspace = new MockupWorkspace();

    try {
        $candidate = mockupCandidate($project);
        MockupCandidateRunner::make($candidate)->run($project, $candidate);

        expect($candidate->fresh()->critique_rounds)->toBe(2);
        expect($candidate->fresh()->status)->toBe(MockupCandidate::STATUS_AWAITING_REVIEW);
        expect(count($requests))->toBe(8);
    } finally {
        $workspace->deleteProject($project->id);
    }
});

it('melempar rencana tidak sah saat otak menjawab bukan json', function () {
    runnerBukaKunciOpenAi();
    $requests = [];
    Http::preventStrayRequests();
    Http::fake(function ($request) use (&$requests) {
        $requests[] = json_decode($request->body(), true);

        return runnerBalasan('maaf, saya bingung');
    });

    $project = mockupProject();
    $workspace = new MockupWorkspace();

    try {
        $candidate = mockupCandidate($project);
        MockupCandidateRunner::make($candidate)->run($project, $candidate);
    } finally {
        $workspace->deleteProject($project->id);
    }
})->throws(RuntimeException::class, 'Rencana desain tidak sah: bukan JSON.');

it('melempar rencana tidak sah saat kalimat ide kosong', function () {
    runnerBukaKunciOpenAi();
    Http::preventStrayRequests();
    Http::fake(fn () => runnerBalasan(runnerRencana('')));

    $project = mockupProject();
    $workspace = new MockupWorkspace();

    try {
        $candidate = mockupCandidate($project);
        MockupCandidateRunner::make($candidate)->run($project, $candidate);
    } finally {
        $workspace->deleteProject($project->id);
    }
})->throws(RuntimeException::class, 'Rencana desain tidak sah: kalimat_ide kosong.');

it('melempar rencana tidak sah saat palet bukan 4-6 warna hex', function () {
    runnerBukaKunciOpenAi();
    Http::preventStrayRequests();
    Http::fake(fn () => runnerBalasan(json_encode([
        'kalimat_ide' => 'Palet rusak',
        'palet' => ['biru', 'putih'],
        'pasangan_huruf' => 'Inter',
        'konsep_tata_letak' => 'kotak',
        'elemen_tanda_tangan' => 'garis',
    ])));

    $project = mockupProject();
    $workspace = new MockupWorkspace();

    try {
        $candidate = mockupCandidate($project);
        MockupCandidateRunner::make($candidate)->run($project, $candidate);
    } finally {
        $workspace->deleteProject($project->id);
    }
})->throws(RuntimeException::class, 'Rencana desain tidak sah: palet harus 4-6 warna #hex.');
