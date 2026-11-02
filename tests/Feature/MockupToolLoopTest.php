<?php

use App\Exceptions\ProviderException;
use App\Services\MockupOpenAiClient;
use App\Services\MockupToolBox;
use App\Services\MockupToolLoop;
use Illuminate\Support\Facades\Http;

function toolLoopFolder(): string
{
    $path = sys_get_temp_dir() . '/mockup-tools-' . bin2hex(random_bytes(4));
    mkdir($path, 0775, true);

    return $path;
}

function toolLoopChromiumAda(): bool
{
    $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;

    if ($home === null) {
        return false;
    }

    return is_dir(getcwd() . '/node_modules/puppeteer') && is_dir($home . '/.cache/puppeteer/chrome');
}

function toolLoopBukaKunciOpenAi(): void
{
    config([
        'services.openai.key' => 'kunci-uji',
        'services.openai.mockup_model' => 'model-uji',
    ]);
}

it('menjalankan panggilan alat sampai otak berhenti dan mengembalikan jawaban akhir', function () {
    toolLoopBukaKunciOpenAi();
    $requests = [];
    Http::preventStrayRequests();
    Http::fake(function ($request) use (&$requests) {
        $requests[] = json_decode($request->body(), true);

        return count($requests) === 1
            ? Http::response(['status' => 'completed', 'output' => [[
                'type' => 'function_call',
                'name' => 'tulis_berkas',
                'arguments' => json_encode(['nama' => 'index.html', 'isi' => '<h1>Halo</h1>']),
                'call_id' => 'call-1',
            ]]], 200)
            : Http::response(['status' => 'completed', 'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => 'selesai menulis']],
            ]]], 200);
    });

    $folder = toolLoopFolder();
    $loop = new MockupToolLoop(new MockupOpenAiClient(), new MockupToolBox($folder), 12);

    expect($loop->run('aturan uji', 'tulis satu berkas'))->toBe('selesai menulis');
    expect(file_get_contents($folder . '/index.html'))->toBe('<h1>Halo</h1>');

    expect($requests[0]['model'])->toBe('model-uji')
        ->and($requests[0]['store'])->toBeFalse()
        ->and($requests[0]['input'][0]['content'][0]['text'])->toBe('tulis satu berkas')
        ->and(collect($requests[0]['tools'])->pluck('name')->all())
        ->toBe(['cari_gambar', 'unduh_gambar', 'tulis_berkas', 'lihat_potret']);

    expect($requests[1]['input'][1]['type'])->toBe('function_call')
        ->and($requests[1]['input'][2]['type'])->toBe('function_call_output')
        ->and($requests[1]['input'][2]['output'][0]['type'])->toBe('input_text')
        ->and($requests[1]['input'][2]['output'][0]['text'])->toBe('OK: index.html');
});

it('berhenti di batas ronde bila otak terus memanggil alat', function () {
    toolLoopBukaKunciOpenAi();
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['status' => 'completed', 'output' => [[
        'type' => 'function_call',
        'name' => 'tulis_berkas',
        'arguments' => json_encode(['nama' => 'a.txt', 'isi' => 'x']),
        'call_id' => 'call-x',
    ]]], 200));

    $loop = new MockupToolLoop(new MockupOpenAiClient(), new MockupToolBox(toolLoopFolder()), 3);

    $loop->run('aturan', 'tugas');
})->throws(RuntimeException::class, 'batas ronde 3');

it('klien menolak jalan tanpa kunci OpenAI', function () {
    config(['services.openai.key' => null]);

    (new MockupOpenAiClient())->send('instruksi', [], []);
})->throws(ProviderException::class);

it('tulis_berkas menulis di dalam folder dan menolak yang keluar', function () {
    $folder = toolLoopFolder();
    $tools = new MockupToolBox($folder);

    $nested = $tools->execute('tulis_berkas', json_encode(['nama' => 'pages/tentang.html', 'isi' => 'halaman']));
    expect($nested[0]['text'])->toBe('OK: pages/tentang.html');
    expect(file_get_contents($folder . '/pages/tentang.html'))->toBe('halaman');

    foreach (['../../jahat.html', '/jahat.html', 'a/../../jahat.html'] as $namaJahat) {
        $hasil = $tools->execute('tulis_berkas', json_encode(['nama' => $namaJahat, 'isi' => 'x']));
        expect($hasil[0]['text'])->toStartWith('GAGAL');
    }

    mkdir($folder . '/folder-kota', 0775);
    $keFolder = $tools->execute('tulis_berkas', json_encode(['nama' => 'folder-kota', 'isi' => 'x']));
    expect($keFolder[0]['text'])->toStartWith('GAGAL');

    $garisMiring = $tools->execute('tulis_berkas', json_encode(['nama' => 'folder-kota/', 'isi' => 'x']));
    expect($garisMiring[0]['text'])->toStartWith('GAGAL');

    expect($tools->jumlahTulis())->toBe(1);

    expect(file_exists(dirname($folder) . '/jahat.html'))->toBeFalse();
});

it('unduh_gambar menyimpan gambar dan menolak bukan-gambar', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://cdn.contoh.test/hero.jpg' => Http::response('bytes-palsu', 200, ['Content-Type' => 'image/jpeg']),
        'https://cdn.contoh.test/bukan.txt' => Http::response('halo', 200, ['Content-Type' => 'text/plain']),
    ]);

    $folder = toolLoopFolder();
    $tools = new MockupToolBox($folder);

    $ok = $tools->execute('unduh_gambar', json_encode(['url' => 'https://cdn.contoh.test/hero.jpg', 'slot' => 'hero']));
    expect($ok[0]['text'])->toBe('slot: hero.jpg');
    expect(file_get_contents($folder . '/hero.jpg'))->toBe('bytes-palsu');

    $gagal = $tools->execute('unduh_gambar', json_encode(['url' => 'https://cdn.contoh.test/bukan.txt', 'slot' => 'teks']));
    expect($gagal[0]['text'])->toStartWith('GAGAL');
    expect(file_exists($folder . '/teks.txt'))->toBeFalse();
});

it('cari_gambar membawa kata kunci ke Google dan membungkus hasilnya', function () {
    config([
        'services.google_custom_search.api_key' => 'kunci-uji',
        'services.google_custom_search.engine_id' => 'mesin-uji',
    ]);
    $urls = [];
    Http::preventStrayRequests();
    Http::fake(function ($request) use (&$urls) {
        $urls[] = $request->url();

        return Http::response(['items' => [
            [
                'link' => 'https://cdn.contoh.test/vila.jpg',
                'title' => 'Vila tropis',
                'displayLink' => 'contoh.test',
                'image' => ['thumbnailLink' => 'https://cdn.contoh.test/vila-kecil.jpg'],
            ],
        ]], 200);
    });

    $hasil = (new MockupToolBox(toolLoopFolder()))->execute('cari_gambar', json_encode(['kata_kunci' => 'vila bali']));

    expect($urls[0])->toContain('customsearch/v1')
        ->and($urls[0])->toContain('q=vila%20bali')
        ->and($hasil[0]['text'])->toContain('https://cdn.contoh.test/vila.jpg')
        ->and($hasil[0]['text'])->toContain('Vila tropis');
});

it('cari_gambar melempar ProviderException bila kunci Google belum disetel', function () {
    config([
        'services.google_custom_search.api_key' => null,
        'services.google_custom_search.engine_id' => null,
    ]);

    (new MockupToolBox(toolLoopFolder()))->execute('cari_gambar', json_encode(['kata_kunci' => 'apa saja']));
})->throws(ProviderException::class);

it('lihat_potret minta index.html bila halaman belum ditulis', function () {
    $hasil = (new MockupToolBox(toolLoopFolder()))->execute('lihat_potret', '{}');

    expect($hasil[0]['text'])->toStartWith('GAGAL: index.html belum ada');
});

it('lihat_potret memotret halaman dan mengembalikan gambar untuk otak', function () {
    if (!toolLoopChromiumAda()) {
        $this->markTestSkipped('Chromium puppeteer belum terpasang di mesin ini.');
    }

    $folder = toolLoopFolder();
    file_put_contents($folder . '/index.html', '<!doctype html><title>uji</title><p>halaman uji</p>');

    $hasil = (new MockupToolBox($folder))->execute('lihat_potret', '{}');

    expect($hasil[0]['type'])->toBe('input_image')
        ->and($hasil[0]['image_url'])->toStartWith('data:image/png;base64,')
        ->and(file_exists($folder . '/preview.png'))->toBeTrue();
});
