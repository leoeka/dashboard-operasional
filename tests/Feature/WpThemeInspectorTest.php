<?php

use App\Models\Client;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Models\User;
use App\Models\WpBuild;
use App\Services\WpThemeBuilder;
use App\Services\WpThemeInspector;
use App\Support\MockupWorkspace;

function inspUser(): User
{
    return User::factory()->create();
}

function inspProject(?Client $client = null): Project
{
    static $seq = 0;
    $seq++;

    return Project::create([
        'client_id' => $client?->id,
        'code' => 'INSP-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
        'name' => 'Website Restoran Padang',
        'client_name' => $client?->contact_name ?? 'Sutan Rajo',
        'type' => 'Restoran',
        'status' => 'in_progress',
    ]);
}

function inspClient(): Client
{
    static $seq = 0;
    $seq++;

    return Client::create([
        'company_name' => 'Restoran Padang Salero ' . $seq,
        'contact_name' => 'Sutan Rajo',
        'email' => 'salero' . $seq . '@restoran.com',
        'phone' => '0813-9876-5432',
        'whatsapp' => '0813-9876-5432',
    ]);
}

function inspCandidate(Project $project): MockupCandidate
{
    $candidate = MockupCandidate::create([
        'project_id' => $project->id,
        'candidate_number' => 1,
        'status' => MockupCandidate::STATUS_CHOSEN,
        'idea_sentence' => 'Desain kuliner Minang dengan tipografi hangat dan hero masakan',
    ]);

    $workspace = new MockupWorkspace();
    $candidateDir = $workspace->candidatePath($candidate);
    $candidate->update(['folder' => $workspace->relativeFolder($candidate)]);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        :root {
            --brand-color: #b91c1c;
            --text-color: #1f2937;
            --bg-color: #ffffff;
        }
        body { font-family: sans-serif; color: var(--text-color); background: var(--bg-color); }
    </style>
</head>
<body>
    <header class="site-header">
        <a href="__EXITO_PAGE_URL:tentang">Tentang Kami</a>
        <a href="__EXITO_PAGE_URL:whatsapp" class="btn-wa">Pesan Meja WA</a>
    </header>
    <main class="site-main">
        <h1>Rendang Daging Istimewa</h1>
        <img src="__EXITO_IMAGE:slot-rendang" alt="Rendang">
        <a href="__EXITO_PAGE_URL:telepon">Hubungi Kami</a>
        <a href="__EXITO_PAGE_URL:email">Reservasi Email</a>
    </main>
    <footer class="site-footer">
        <p>&copy; 2026 Restoran Salero</p>
    </footer>
</body>
</html>
HTML;

    file_put_contents($candidateDir . '/index.html', $html);
    file_put_contents($candidateDir . '/preview.png', (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    file_put_contents($candidateDir . '/slot-rendang.jpg', (string) base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q=='));

    return $candidate;
}

/**
 * Kandidat yang sudah punya dua halaman isi dan navigasi header berbentuk
 * daftar: bentuk yang dipakai tema hasil tahap halaman isi.
 */
function inspCandidateWithPages(Project $project): MockupCandidate
{
    $candidate = inspCandidate($project);
    $candidateDir = (new MockupWorkspace())->candidatePath($candidate);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        :root {
            --brand-color: #b91c1c;
            --text-color: #1f2937;
            --bg-color: #ffffff;
        }
        body { font-family: sans-serif; color: var(--text-color); background: var(--bg-color); }
    </style>
</head>
<body>
    <header class="site-header">
        <nav>
            <ul class="nav-links">
                <li><a href="__EXITO_PAGE_URL:tentang">Tentang</a></li>
                <li><a href="__EXITO_PAGE_URL:layanan">Layanan</a></li>
            </ul>
        </nav>
        <a href="__EXITO_PAGE_URL:whatsapp" class="btn-wa">Pesan Meja WA</a>
    </header>
    <main class="site-main">
        <h1>Rendang Daging Istimewa</h1>
        <img src="__EXITO_IMAGE:slot-rendang" alt="Rendang">
        <a href="__EXITO_PAGE_URL:telepon">Hubungi Kami</a>
        <a href="__EXITO_PAGE_URL:email">Reservasi Email</a>
    </main>
    <footer class="site-footer">
        <p>&copy; 2026 Restoran Salero</p>
    </footer>
</body>
</html>
HTML;

    file_put_contents($candidateDir . '/index.html', $html);

    if (!is_dir($candidateDir . '/pages')) {
        mkdir($candidateDir . '/pages', 0775, true);
    }

    file_put_contents($candidateDir . '/pages/tentang.html', <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Tentang Kami</title>
    <style>.tentang-hero { color: #1f2937; background: #ffffff; }</style>
</head>
<body>
    <header><a href="__EXITO_PAGE_URL:layanan">Layanan</a></header>
    <main class="tentang-hero">
        <h1>Tentang Restoran Salero</h1>
        <p>Kami memasak rendang sejak 1998.</p>
        <img src="__EXITO_IMAGE:slot-rendang" alt="Rendang">
        <a href="__EXITO_PAGE_URL:layanan">Lihat layanan</a>
    </main>
    <footer><p>Footer halaman</p></footer>
</body>
</html>
HTML);

    file_put_contents($candidateDir . '/pages/layanan.html', <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Layanan</title>
    <style>.layanan-grid { color: #1f2937; background: #ffffff; }</style>
</head>
<body>
    <header><a href="__EXITO_PAGE_URL:tentang">Tentang</a></header>
    <main class="layanan-grid">
        <h1>Layanan Kami</h1>
        <p>Katering, reservasi meja, dan paket acara.</p>
        <a href="__EXITO_PAGE_URL:tentang">Kenali kami</a>
    </main>
    <footer><p>Footer halaman</p></footer>
</body>
</html>
HTML);

    return $candidate;
}

afterEach(function () {
    $workspace = new MockupWorkspace();
    foreach (Project::where('code', 'like', 'INSP-%')->get() as $p) {
        $workspace->deleteProject($p->id);
    }
});

it('passes inspection and packages theme into ready zip file when all rules are satisfied', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
        'status' => WpBuild::STATUS_PENDING,
    ]);

    // Bangun berkas tema WordPress
    (new WpThemeBuilder())->build($build);

    // Jalankan Meja Periksa & Pengemasan
    $inspector = new WpThemeInspector();
    $result = $inspector->inspectAndPackage($build);

    expect($result->status)->toBe(WpBuild::STATUS_READY)
        ->and($result->zip_path)->toBe('mockup-work/' . $project->id . '/theme-install.zip')
        ->and($result->inspection_results['passed'])->toBeTrue()
        ->and($result->inspection_results['checks']['required_files'])->toBeTrue()
        ->and($result->inspection_results['checks']['style_header'])->toBeTrue()
        ->and($result->inspection_results['checks']['syntax_lint'])->toBeTrue()
        ->and($result->inspection_results['checks']['zero_markers'])->toBeTrue()
        ->and($result->inspection_results['checks']['menu_locations'])->toBeTrue()
        ->and($result->inspection_results['checks']['no_external_embeds'])->toBeTrue()
        ->and($result->inspection_results['checks']['file_size'])->toBeTrue()
        ->and($result->inspection_results['checks']['color_contrast'])->toBeTrue()
        ->and($result->inspection_results['checks']['elementor_payload'])->toBeTrue()
        ->and($result->inspection_results['checks']['front_page'])->toBeTrue()
        ->and($result->inspection_results['elementor_pages_count'])->toBe(1);

    // Verifikasi fisik berkas ZIP
    $zipPath = storage_path('app/' . $result->zip_path);
    expect(is_file($zipPath))->toBeTrue();

    $zip = new ZipArchive();
    expect($zip->open($zipPath))->toBeTrue();

    // Pastikan berkas di dalam ZIP berada dalam folder tema (standar instalasi WordPress)
    $hasStyle = false;
    $hasFunctions = false;
    $hasScreenshot = false;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_ends_with($name, 'style.css')) $hasStyle = true;
        if (str_ends_with($name, 'functions.php')) $hasFunctions = true;
        if (str_ends_with($name, 'screenshot.png')) $hasScreenshot = true;
    }

    $zip->close();

    expect($hasStyle)->toBeTrue()
        ->and($hasFunctions)->toBeTrue()
        ->and($hasScreenshot)->toBeTrue();
});

it('fails inspection when a required theme file is missing', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    // Hapus salah satu berkas wajib
    $themeDir = (new MockupWorkspace())->buildPath($build);
    unlink($themeDir . '/functions.php');

    $inspector = new WpThemeInspector();

    expect(fn () => $inspector->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['passed'])->toBeFalse()
        ->and($fresh->inspection_results['checks']['required_files'])->toBeFalse()
        ->and($fresh->inspection_results['errors'][0])->toContain('functions.php');
});

it('fails inspection when style.css header is missing Theme Name', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents($themeDir . '/style.css', "/* CSS tanpa header yang sah */\nbody { color: red; }");

    $inspector = new WpThemeInspector();

    expect(fn () => $inspector->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['style_header'])->toBeFalse();
});

it('fails inspection when a php syntax error is detected in a template', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    // Masukkan sintaks PHP rusak
    file_put_contents($themeDir . '/page.php', "<?php parse error syntax !!!");

    $inspector = new WpThemeInspector();

    expect(fn () => $inspector->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['syntax_lint'])->toBeFalse();
});

it('fails inspection when a raw __EXITO_ marker remains in theme files', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    // Sisipkan penanda bocor di footer.php
    file_put_contents($themeDir . '/footer.php', "<!-- __EXITO_LEFTOVER__ -->\n<?php wp_footer(); ?>");

    $inspector = new WpThemeInspector();

    expect(fn () => $inspector->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['zero_markers'])->toBeFalse();
});

it('fails inspection K-9 when prohibited external script or iframe is present', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    // Sisipkan tag script eksternal protocol-relative
    file_put_contents($themeDir . '/header.php', "<script src = \"//foreign-tracker.com/spy.js\"></script>");

    $inspector = new WpThemeInspector();

    expect(fn () => $inspector->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['no_external_embeds'])->toBeFalse();
});

it('fails inspection M-11 when file size exceeds limit', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    // Buat file style.css melebihi 60KB
    file_put_contents($themeDir . '/style.css', str_repeat('a', 65 * 1024));

    $inspector = new WpThemeInspector();

    expect(fn () => $inspector->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['file_size'])->toBeFalse();
});

it('fails inspection K-9 when style.css imports a font from an external address', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents(
        $themeDir . '/style.css',
        "@import url(\"https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap\");\n",
        FILE_APPEND
    );

    $inspector = new WpThemeInspector();

    expect(fn () => $inspector->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['no_external_embeds'])->toBeFalse()
        ->and(implode(' ', $fresh->inspection_results['errors']))->toContain('fonts.googleapis.com');
});

it('fails inspection M-1 when a text and background pair is below the contrast threshold', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents($themeDir . '/style.css', ".muted { color: #aaaaaa; background: #ffffff; }\n", FILE_APPEND);

    $inspector = new WpThemeInspector();

    expect(fn () => $inspector->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['color_contrast'])->toBeFalse()
        ->and(implode(' ', $fresh->inspection_results['errors']))->toContain('M-1');
});

it('fails inspection M-2 when text over a brand color is not the highest-ratio candidate', function () {

    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents(
        $themeDir . '/style.css',
        ":root { --brand-bg: #767676; }\n.hero { color: #ffffff; background: var(--brand-bg); }\n",
        FILE_APPEND
    );

    $inspector = new WpThemeInspector();

    expect(fn () => $inspector->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['color_contrast'])->toBeFalse()
        ->and(implode(' ', $fresh->inspection_results['errors']))->toContain('M-2');
});

it('ignores composite backgrounds it cannot reduce to one flat color', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents(
        $themeDir . '/style.css',
        ".card { color: #333333; background: rgba(0, 0, 0, 0.5); }\n"
        . ".hero { color: #ffffff; background: linear-gradient(#9ca3af, #111827); }\n",
        FILE_APPEND
    );

    $result = (new WpThemeInspector())->inspectAndPackage($build);

    expect($result->status)->toBe(WpBuild::STATUS_READY)
        ->and($result->inspection_results['checks']['color_contrast'])->toBeTrue();
});

it('allows downloading the theme zip file when build status is ready', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);
    $user = inspUser();

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
        'status' => WpBuild::STATUS_PENDING,
    ]);

    (new WpThemeBuilder())->build($build);
    (new WpThemeInspector())->inspectAndPackage($build);

    $this->actingAs($user)
        ->get(route('pages.projects.builds.download', [$project, $build]))
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');
});

it('refuses download when build is not ready or zip missing', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);
    $user = inspUser();

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
        'status' => WpBuild::STATUS_BUILDING,
    ]);

    $this->actingAs($user)
        ->get(route('pages.projects.builds.download', [$project, $build]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('refuses download across projects or for non-website projects', function () {
    $projectA = inspProject();
    $projectB = inspProject();
    $candidateA = inspCandidate($projectA);
    $user = inspUser();

    $buildA = WpBuild::create([
        'project_id' => $projectA->id,
        'mockup_candidate_id' => $candidateA->id,
        'status' => WpBuild::STATUS_READY,
        'zip_path' => 'mockup-work/' . $projectA->id . '/theme-install.zip',
    ]);

    // Download lintas project -> 404
    $this->actingAs($user)
        ->get(route('pages.projects.builds.download', [$projectB, $buildA]))
        ->assertNotFound();
});

it('preserves structured inspection results when build job fails in queue', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    // Selipkan @import alamat luar ke CSS kandidat: pembangun lolos, meja
    // periksa yang menolak (K-9), jadi hasil terstruktur tetap tercatat.
    $candidateDir = (new MockupWorkspace())->candidatePath($candidate);
    $html = file_get_contents($candidateDir . '/index.html');
    file_put_contents(
        $candidateDir . '/index.html',
        str_replace('</style>', '@import url("https://evil.com/track.css");</style>', $html)
    );

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
        'status' => WpBuild::STATUS_PENDING,
    ]);

    (new \App\Jobs\BuildWpThemeJob($build))->handle();

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results)->toBeArray()
        ->and($fresh->inspection_results['passed'])->toBeFalse()
        ->and($fresh->inspection_results['checks']['no_external_embeds'])->toBeFalse()
        ->and($fresh->inspection_results['error'])->toContain('Meja periksa gagal')
        ->and($fresh->inspection_results['failed_at'])->not->toBeNull();
});

it('passes inspection with content pages, menu and media payload present', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidateWithPages($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
        'status' => WpBuild::STATUS_PENDING,
    ]);

    (new WpThemeBuilder())->build($build);

    $result = (new WpThemeInspector())->inspectAndPackage($build);

    expect($result->status)->toBe(WpBuild::STATUS_READY)
        ->and($result->inspection_results['checks']['page_payloads'])->toBeTrue()
        ->and($result->inspection_results['checks']['menu_filled'])->toBeTrue()
        ->and($result->inspection_results['checks']['media_payload'])->toBeTrue()
        ->and($result->inspection_results['checks']['elementor_payload'])->toBeTrue()
        ->and($result->inspection_results['checks']['front_page'])->toBeTrue()
        ->and($result->inspection_results['pages_count'])->toBe(3);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    $meta = json_decode((string) file_get_contents($themeDir . '/content/halaman.json'), true);

    expect(array_column($meta['halaman'], 'slug'))->toEqualCanonicalizing(['beranda', 'tentang', 'layanan'])
        ->and(array_column($meta['nav'], 'slug'))->toBe(['tentang', 'layanan'])
        ->and($meta['gambar'])->toBe(['slot-rendang.jpg']);
});

it('fails page payload check when a content page points at an image missing from the theme', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidateWithPages($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents(
        $themeDir . '/content/tentang.html',
        "\n<img src=\"[[siteflow:gambar:hantu.jpg]]\" alt=\"Hantu\">\n",
        FILE_APPEND
    );

    expect(fn () => (new WpThemeInspector())->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['page_payloads'])->toBeFalse()
        ->and(implode(' ', $fresh->inspection_results['errors']))->toContain("gambar 'hantu.jpg' yang tidak terdaftar");
});

it('fails menu payload check when a menu item has no page behind it', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidateWithPages($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    $metaPath = $themeDir . '/content/halaman.json';
    $meta = json_decode((string) file_get_contents($metaPath), true);
    $meta['nav'][] = ['slug' => 'promo', 'label' => 'Promo'];
    file_put_contents($metaPath, (string) json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    expect(fn () => (new WpThemeInspector())->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['menu_filled'])->toBeFalse()
        ->and(implode(' ', $fresh->inspection_results['errors']))->toContain("Item menu 'promo' tidak punya halaman isi.");
});

it('fails media payload check when an extra unlisted image sits in the theme', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidateWithPages($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    copy($themeDir . '/images/slot-rendang.jpg', $themeDir . '/images/ekstra.jpg');

    expect(fn () => (new WpThemeInspector())->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['media_payload'])->toBeFalse()
        ->and(implode(' ', $fresh->inspection_results['errors']))->toContain('images/ekstra.jpg tidak terdaftar');
});

it('fails K-9 when a content page carries a script tag', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidateWithPages($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents($themeDir . '/content/layanan.html', "<script>alert(1)</script>\n", FILE_APPEND);

    expect(fn () => (new WpThemeInspector())->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['no_external_embeds'])->toBeFalse();
});

it('fails page payload check when a theme template links to a page that was never built', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidateWithPages($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents(
        $themeDir . '/page.php',
        "\n<?php echo esc_url(siteflow_page_url('promo')); ?>\n",
        FILE_APPEND
    );

    expect(fn () => (new WpThemeInspector())->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['page_payloads'])->toBeFalse()
        ->and(implode(' ', $fresh->inspection_results['errors']))->toContain("menaut ke halaman 'promo' yang tidak dibuat");
});

it('fails media payload check when a listed image is not a real image', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidateWithPages($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents($themeDir . '/images/slot-rendang.jpg', 'bukan-gambar');

    expect(fn () => (new WpThemeInspector())->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['media_payload'])->toBeFalse()
        ->and(implode(' ', $fresh->inspection_results['errors']))->toContain('bukan gambar yang sah');
});

it('fails K-9 when a content page carries a javascript: link or inline handler', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidateWithPages($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents(
        $themeDir . '/content/tentang.html',
        "\n<a href=\"javascript:alert(1)\">Klik</a>\n<img src=\"x.jpg\" onerror=\"alert(1)\">\n",
        FILE_APPEND
    );

    expect(fn () => (new WpThemeInspector())->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    $errors = implode(' ', $fresh->inspection_results['errors']);

    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['no_external_embeds'])->toBeFalse()
        ->and($errors)->toContain('javascript:')
        ->and($errors)->toContain('on...=');
});

it('fails elementor payload check when a page has no element data', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidateWithPages($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents(
        $themeDir . '/content/layanan.json',
        (string) json_encode(['versi' => 'x', 'elementor' => []])
    );

    expect(fn () => (new WpThemeInspector())->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['elementor_payload'])->toBeFalse()
        ->and(implode(' ', $fresh->inspection_results['errors']))->toContain('content/layanan.json tidak memuat elemen Elementor');
});

it('fails elementor payload check when a page uses a raw html widget', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidateWithPages($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    $path = $themeDir . '/content/tentang.json';
    $data = json_decode((string) file_get_contents($path), true);
    $data['elementor'] = [[
        'id' => 'abc1234',
        'elType' => 'widget',
        'widgetType' => 'html',
        'settings' => ['html' => '<p>HTML mentah</p>'],
        'elements' => [],
    ]];
    file_put_contents($path, (string) json_encode($data));

    expect(fn () => (new WpThemeInspector())->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['elementor_payload'])->toBeFalse()
        ->and(implode(' ', $fresh->inspection_results['errors']))->toContain("widget 'html' tidak dipakai");
});

it('fails front page check when a stale front-page.php is present', function () {
    $client = inspClient();
    $project = inspProject($client);
    $candidate = inspCandidate($project);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    file_put_contents($themeDir . '/front-page.php', "<?php // sisa build lama\n");

    expect(fn () => (new WpThemeInspector())->inspectAndPackage($build))
        ->toThrow(RuntimeException::class, 'Meja periksa gagal');

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results['checks']['front_page'])->toBeFalse()
        ->and(implode(' ', $fresh->inspection_results['errors']))->toContain('front-page.php masih ada');
});
