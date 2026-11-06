<?php

use App\Support\PageSnapshot;
use Illuminate\Support\Facades\Artisan;

it('reports cPanel blockers without exposing configured API credentials', function () {
    config([
        'services.openai.key' => 'sk-test-not-a-real-key',
    ]);

    $this->mock(PageSnapshot::class)
        ->shouldReceive('captureFile')
        ->once()
        ->andReturnUsing(function (string $htmlPath, string $imagePath): string {
            file_put_contents($imagePath, 'test image');

            return $imagePath;
        });

    expect(Artisan::call('deployment:check-cpanel'))->toBe(1)
        ->and(Artisan::output())
        ->toContain('APP_ENV production')
        ->toContain('Queue database')
        ->toContain('Node.js dan Chromium bisa merender')
        ->not->toContain('sk-test-not-a-real-key');
});
