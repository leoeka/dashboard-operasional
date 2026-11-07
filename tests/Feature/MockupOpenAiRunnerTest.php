<?php

use App\Models\MockupCandidate;
use App\Models\Project;
use App\Services\MockupOpenAiRunner;
use App\Support\MockupCodexState;
use App\Support\MockupWorkspace;
use App\Support\PageSnapshot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->openAiBase = sys_get_temp_dir() . '/siteflow-openai-' . uniqid();

    config([
        'services.openai.key' => 'test-openai-key',
        'services.mockup.base' => $this->openAiBase,
        'services.mockup.work' => $this->openAiBase . '/work',
        'services.mockup.openai.model' => 'gpt-6-astra',
        'services.mockup.openai.image_model' => 'gpt-image-2.5-sunburst',
        'services.mockup.openai.timeout' => 60,
        'services.mockup.openai.max_output_tokens' => 20000,
        'services.mockup.openai.max_input_image_bytes' => 12000000,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->openAiBase);

    if (isset($this->openAiCandidate)) {
        File::deleteDirectory(app(MockupWorkspace::class)->candidatePath($this->openAiCandidate));
    }
});

function openAiMockupProject(): Project
{
    static $sequence = 0;

    return Project::create([
        'code' => 'OAI-' . str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT),
        'name' => 'Studio Uji API',
        'client_name' => 'Klien Uji',
        'type' => 'Company',
        'status' => 'request',
        'website_name' => 'Studio Uji',
        'target_market' => 'Pemilik usaha lokal',
        'description' => 'Studio desain interior.',
    ]);
}

function openAiMockupCandidate(Project $project, int $number): MockupCandidate
{
    $candidate = MockupCandidate::create([
        'project_id' => $project->id,
        'candidate_number' => $number,
    ]);
    $candidate->update(['folder' => app(MockupWorkspace::class)->relativeFolder($candidate)]);

    return $candidate;
}

function openAiResponsePayload(array $overrides = []): array
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true);
    $files = [
        ['path' => 'index.html', 'content' => '<!doctype html><html><head><title>Studio</title></head><body><img src="__EXITO_IMAGE:hero" alt="Interior"></body></html>'],
        ['path' => 'rencana.json', 'content' => '{"nama_proyek":"Studio Uji","kalimat_ide":"Ruang tenang untuk rumah."}'],
        ['path' => 'design-tokens.json', 'content' => '{"colors":{"background":"#fff"}}'],
        ['path' => 'ide.txt', 'content' => "Ruang tenang untuk rumah.\n"],
        ['path' => 'pages/tentang.html', 'content' => '<!doctype html><html><body><h1>Tentang</h1></body></html>'],
        ['path' => 'pages/layanan.html', 'content' => '<!doctype html><html><body><h1>Layanan</h1></body></html>'],
        ['path' => 'pages/kontak.html', 'content' => '<!doctype html><html><body><h1>Kontak</h1></body></html>'],
    ];

    return array_replace_recursive([
        'id' => 'resp_test',
        'status' => 'completed',
        'output_text' => json_encode([
            'files' => $files,
            'generated_images' => ['hero'],
        ], JSON_UNESCAPED_SLASHES),
        'output' => [
            [
                'type' => 'image_generation_call',
                'status' => 'completed',
                'result' => base64_encode((string) $png),
                'revised_prompt' => 'Design reference.',
            ],
            [
                'type' => 'image_generation_call',
                'status' => 'completed',
                'result' => base64_encode((string) $png),
                'revised_prompt' => 'Website hero image.',
            ],
        ],
        'usage' => ['input_tokens' => 100, 'output_tokens' => 200, 'total_tokens' => 300],
    ], $overrides);
}

it('makes one Responses API request and only marks a validated candidate ready for review', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response(openAiResponsePayload(), 200),
    ]);
    Http::preventStrayRequests();
    $this->mock(PageSnapshot::class)
        ->shouldReceive('captureFile')
        ->once()
        ->andReturnUsing(function (string $html, string $png): string {
            file_put_contents($png, 'preview');

            return $png;
        });

    $project = openAiMockupProject();
    $candidate = openAiMockupCandidate($project, 1);
    $this->openAiCandidate = $candidate;

    expect(app(MockupOpenAiRunner::class)->run($project))->toBe(0);

    $candidate->refresh();
    $directory = app(MockupWorkspace::class)->candidatePath($candidate);

    expect($candidate->status)->toBe(MockupCandidate::STATUS_AWAITING_REVIEW)
        ->and($candidate->idea_sentence)->toBe('Ruang tenang untuk rumah.')
        ->and(is_file($directory . '/index.html'))->toBeTrue()
        ->and(is_file($directory . '/hero.png'))->toBeTrue()
        ->and(is_file($directory . '/design-reference/generated-reference.png'))->toBeTrue()
        ->and(is_file($directory . '/preview.png'))->toBeTrue()
        ->and(file_get_contents($directory . '/foto-slot.txt'))->toContain('hero: hero.png');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses'
        && $request->hasHeader('Authorization', 'Bearer test-openai-key')
        && $request['model'] === 'gpt-6-astra'
        && $request['tools'][0]['type'] === 'image_generation'
        && $request['tools'][0]['model'] === 'gpt-image-2.5-sunburst');
});

it('leaves a candidate failed when the generated image count does not match the manifest', function () {
    $response = openAiResponsePayload();
    $response['output'] = [$response['output'][0]];
    Http::fake(['api.openai.com/v1/responses' => Http::response($response, 200)]);
    Http::preventStrayRequests();
    $this->mock(PageSnapshot::class)->shouldNotReceive('captureFile');

    $project = openAiMockupProject();
    $candidate = openAiMockupCandidate($project, 1);
    $this->openAiCandidate = $candidate;

    expect(app(MockupOpenAiRunner::class)->run($project))->toBe(1);

    $candidate->refresh();

    expect($candidate->isRunning())->toBeFalse()
        ->and($candidate->failure_reason)->toContain('Jumlah gambar dari alat OpenAI tidak cocok')
        ->and($candidate->status)->toBe(MockupCandidate::STATUS_DRAFT);
});
