<?php

use App\Exceptions\ProviderException;
use App\Models\Project;
use App\Services\OpenAiWordPressBuilderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

function gptBuilderProject(): Project
{
    return new Project([
        'name' => 'Bali Explore Tour',
        'client_name' => 'Bali Explore Tour',
        'code' => 'BET-100',
    ]);
}

function gptBuilderStream(string $output, bool $complete = true): string
{
    $events = "event: response.output_text.delta\ndata: ".json_encode([
        'type' => 'response.output_text.delta',
        'delta' => $output,
    ], JSON_UNESCAPED_SLASHES)."\n\n";

    if ($complete) {
        $events .= "event: response.completed\ndata: {\"type\":\"response.completed\"}\n\n";
    }

    return $events;
}

beforeEach(function () {
    Storage::fake('public');
    config([
        'services.openai.key' => 'test-openai-key',
        'services.openai.wordpress_builder_model' => 'gpt-5.6-test',
        'services.openai.wordpress_build_timeout' => 30,
        'services.openai.wordpress_max_output_tokens' => 4096,
    ]);
});

it('requires the OpenAI key and reports the GPT provider', function () {
    config(['services.openai.key' => null]);

    try {
        app(OpenAiWordPressBuilderService::class)->build(gptBuilderProject(), []);
        $this->fail('Expected a ProviderException.');
    } catch (ProviderException $e) {
        expect($e->provider)->toBe('openai')
            ->and($e->errorCode)->toBe(ProviderException::MISSING_API_KEY)
            ->and($e->getMessage())->toContain('OpenAI');
    }
});

it('streams a GPT theme manifest and attaches the approved mockup image', function () {
    Storage::disk('public')->put(
        'mockups/approved.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=')
    );
    $manifest = [
        'files' => [
            'exito-client-theme/style.css' => '/* theme */',
            'exito-client-theme/index.php' => '<?php get_header(); the_content(); get_footer();',
            'exito-client-theme/header.php' => '<?php echo esc_html("header");',
            '../outside.php' => '<?php echo "unsafe";',
        ],
    ];
    Http::fake([
        'api.openai.com/*' => Http::response(
            gptBuilderStream(json_encode($manifest, JSON_UNESCAPED_SLASHES)),
            200,
            ['Content-Type' => 'text/event-stream']
        ),
    ]);

    $result = app(OpenAiWordPressBuilderService::class)->build(gptBuilderProject(), [
        'mockup' => ['screenshot_path' => 'mockups/approved.png', 'design' => []],
        'assets' => [],
    ]);

    expect($result['files'])->toHaveKeys(['exito-client-theme/style.css', 'exito-client-theme/index.php', 'exito-client-theme/header.php'])
        ->and($result['files'])->not->toHaveKey('../outside.php');

    Http::assertSentCount(1);
    $request = Http::recorded()->first()[0];
    $body = $request->data();
    $content = $body['input'][1]['content'];
    $image = collect($content)->firstWhere('type', 'input_image');

    expect($request->url())->toEndWith('/v1/responses')
        ->and($body['model'])->toBe('gpt-5.6-test')
        ->and($body['max_output_tokens'])->toBe(4096)
        ->and($image['image_url'] ?? '')->toStartWith('data:image/');
});

it('rejects a manifest without the required WordPress theme entry point', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(
            gptBuilderStream(json_encode(['files' => ['exito-client-theme/style.css' => '/* theme */']])),
            200,
            ['Content-Type' => 'text/event-stream']
        ),
    ]);

    expect(fn () => app(OpenAiWordPressBuilderService::class)->build(gptBuilderProject(), []))
        ->toThrow(ProviderException::class);
});

it('rejects a theme when its required PHP entry point fails syntax validation', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(
            gptBuilderStream(json_encode(['files' => [
                'exito-client-theme/style.css' => "/*\nTheme Name: Test\n*/",
                'exito-client-theme/index.php' => '<?php function invalid( {',
            ]])),
            200,
            ['Content-Type' => 'text/event-stream']
        ),
    ]);

    expect(fn () => app(OpenAiWordPressBuilderService::class)->build(gptBuilderProject(), []))
        ->toThrow(ProviderException::class);
});

it('stops the build when PHP lint cannot validate the required entry point', function () {
    Process::fake(fn () => Process::result('', 'php command unavailable', 127));
    Http::fake([
        'api.openai.com/*' => Http::response(
            gptBuilderStream(json_encode(['files' => [
                'exito-client-theme/style.css' => "/*\nTheme Name: Test\n*/",
                'exito-client-theme/index.php' => '<?php get_header();',
            ]])),
            200,
            ['Content-Type' => 'text/event-stream']
        ),
    ]);

    expect(fn () => app(OpenAiWordPressBuilderService::class)->build(gptBuilderProject(), []))
        ->toThrow(ProviderException::class);
});

it('classifies GPT quota errors and rejects incomplete or invalid streams', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'error' => ['message' => 'Your credit balance is too low'],
        ], 400),
    ]);

    try {
        app(OpenAiWordPressBuilderService::class)->build(gptBuilderProject(), []);
        $this->fail('Expected a ProviderException.');
    } catch (ProviderException $e) {
        expect($e->provider)->toBe('openai')
            ->and($e->errorCode)->toBe(ProviderException::QUOTA_EXHAUSTED);
    }

    Http::fake([
        'api.openai.com/*' => Http::response(
            gptBuilderStream('{"files":', false),
            200,
            ['Content-Type' => 'text/event-stream']
        ),
    ]);

    expect(fn () => app(OpenAiWordPressBuilderService::class)->build(gptBuilderProject(), []))
        ->toThrow(ProviderException::class);
});
