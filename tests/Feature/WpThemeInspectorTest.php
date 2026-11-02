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
    file_put_contents($candidateDir . '/preview.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    file_put_contents($candidateDir . '/slot-rendang.jpg', 'dummy-jpg-data');

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
        ->and($result->inspection_results['checks']['file_size'])->toBeTrue();

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
    file_put_contents($themeDir . '/front-page.php', "<?php parse error syntax !!!");

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

    // Tambahkan iframe terlarang (K-9) pada index.html kandidat di dalam body
    $candidateDir = (new MockupWorkspace())->candidatePath($candidate);
    $html = file_get_contents($candidateDir . '/index.html');
    file_put_contents($candidateDir . '/index.html', str_replace('</main>', '<iframe src="https://evil.com"></iframe></main>', $html));

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
