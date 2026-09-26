<?php

use App\Support\MockupSite;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * The live demo must be a real responsive site: no horizontal scrolling and no
 * blank page at phone, tablet, laptop and desktop widths. Rendered by a real
 * headless Chromium (tests/Browser/responsive-smoke.cjs), because only a
 * browser can measure layout.
 *
 * Skipped — not failed — where Node or Puppeteer is not installed, so the PHP
 * suite still runs on a machine without a browser.
 */
function responsivePages(): array
{
    $v2 = require base_path('tests/Fixtures/v2-blueprint.php');
    $photo = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600"><rect width="800" height="600" fill="#7a9e9f"/></svg>');
    $images = ['home' => ['hero' => $photo, 'sections' => [2 => [$photo, $photo, $photo], 3 => [$photo], 6 => [$photo, $photo]]]];

    $legacy = [
        'global_cta' => 'Pesan',
        'design' => ['primary_color' => '#1F3A5F', 'accent_color' => '#C87941', 'layout_variant' => 'split-left'],
        'pages' => [['name' => 'Home', 'sections' => [
            ['name' => 'Hero', 'headline' => 'Kopi Nusantara Pilihan dari Petani Lokal'],
            ['name' => 'Kenapa', 'headline' => 'Kenapa Kami', 'items' => [['title' => 'Segar'], ['title' => 'Adil'], ['title' => 'Cepat']]],
            ['name' => 'Menu', 'headline' => 'Menu', 'items' => [['title' => 'Gayo'], ['title' => 'Toraja'], ['title' => 'Kintamani'], ['title' => 'Flores']]],
        ]]],
    ];

    $render = fn (array $mockup, string $page, array $images = []) => view('mockup.site', ['site' => MockupSite::build($mockup, [
        'brand' => 'Nusa Trails', 'page' => $page, 'fixed' => false, 'images' => $images,
    ])])->render();

    return [
        'v2-home' => $render($v2, 'home', $images),
        'v2-tentang' => $render($v2, 'tentang'),
        'v2-paket' => $render($v2, 'paket'),
        'legacy-home' => $render($legacy, 'home', ['home' => ['hero' => $photo, 'items' => [$photo, $photo, $photo, $photo]]]),
    ];
}

it('never scrolls horizontally and never renders blank, from phone to desktop', function () {
    $node = (new Process(['node', '--version']))->setTimeout(10);
    $node->run();
    if (!$node->isSuccessful() || !is_dir(base_path('node_modules/puppeteer'))) {
        $this->markTestSkipped('Node or Puppeteer is not installed; browser smoke test skipped.');
    }

    $dir = storage_path('framework/testing/responsive-' . uniqid());
    File::ensureDirectoryExists($dir);

    try {
        foreach (responsivePages() as $name => $html) {
            File::put("{$dir}/{$name}.html", $html);
        }

        $process = new Process(['node', base_path('tests/Browser/responsive-smoke.cjs'), $dir], base_path());
        $process->setTimeout(300)->run();

        expect($process->isSuccessful())->toBeTrue('Browser run failed: ' . $process->getErrorOutput());

        $results = array_map(fn (string $line) => json_decode($line, true), array_filter(explode("\n", trim($process->getOutput()))));

        // 4 pages × 4 widths, every one measured.
        expect($results)->toHaveCount(16);

        foreach ($results as $r) {
            $where = "{$r['file']} @ {$r['width']}px";

            expect($r['scrollWidth'])->toBeLessThanOrEqual($r['innerWidth'], "{$where} scrolls horizontally")
                ->and($r['overflowing'])->toBe([], "{$where} paints past the right edge: " . implode(', ', $r['overflowing']))
                ->and($r['blank'])->toBeFalse("{$where} is blank at the viewport centre")
                ->and($r['sections'])->toBeGreaterThan(0, "{$where} rendered no sections")
                ->and($r['textLength'])->toBeGreaterThan(20, "{$where} rendered no text");
        }
    } finally {
        File::deleteDirectory($dir);
    }
})->group('browser');
