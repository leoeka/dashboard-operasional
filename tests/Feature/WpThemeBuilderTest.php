<?php

use App\Jobs\BuildWpThemeJob;
use App\Models\Client;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Models\WpBuild;
use App\Services\WpThemeBuilder;
use App\Support\MockupWorkspace;

function wpTestProject(?Client $client = null): Project
{
    static $seq = 0;
    $seq++;

    return Project::create([
        'client_id' => $client?->id,
        'code' => 'THM-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
        'name' => 'Website Toko Bunga',
        'client_name' => $client?->contact_name ?? 'Budi Santoso',
        'type' => 'Company Profile',
        'status' => 'in_progress',
    ]);
}

function wpTestClient(): Client
{
    static $seq = 0;
    $seq++;

    return Client::create([
        'company_name' => 'PT Toko Bunga ' . $seq,
        'contact_name' => 'Budi Santoso',
        'email' => 'budi' . $seq . '@tokobunga.com',
        'phone' => '0812-3456-7890',
        'whatsapp' => '0812-3456-7890',
    ]);
}

function wpTestCandidate(Project $project, string $htmlContent): MockupCandidate
{
    $candidate = MockupCandidate::create([
        'project_id' => $project->id,
        'candidate_number' => 1,
        'status' => MockupCandidate::STATUS_CHOSEN,
        'idea_sentence' => 'Desain floris modern dengan layout asimetris',
    ]);

    $workspace = new MockupWorkspace();
    $candidateDir = $workspace->candidatePath($candidate);
    $candidate->update(['folder' => $workspace->relativeFolder($candidate)]);

    file_put_contents($candidateDir . '/index.html', $htmlContent);

    // Dummy preview image
    file_put_contents($candidateDir . '/preview.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

    // Dummy slot image
    file_put_contents($candidateDir . '/slot-hero.jpg', 'dummy-jpg-content');

    return $candidate;
}

afterEach(function () {
    $workspace = new MockupWorkspace();
    foreach (Project::where('code', 'like', 'THM-%')->get() as $p) {
        $workspace->deleteProject($p->id);
    }
});

it('builds complete wordpress theme structure from chosen candidate', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        :root { --brand-color: #2f5bc4; }
        body { font-family: sans-serif; }
    </style>
</head>
<body>
    <header class="site-header">
        <a href="__EXITO_PAGE_URL:tentang">Tentang Kami</a>
        <a href="__EXITO_PAGE_URL:whatsapp" class="btn-wa">Chat WhatsApp</a>
    </header>
    <main class="site-main">
        <h1>Bunga Segar Berkualitas</h1>
        <img src="__EXITO_IMAGE:slot-hero" alt="Hero">
        <a href="__EXITO_PAGE_URL:telepon">Hubungi Kami</a>
        <a href="__EXITO_PAGE_URL:email">Kirim Email</a>
    </main>
    <footer class="site-footer">
        <p>&copy; 2026 PT Toko Bunga</p>
    </footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
        'status' => WpBuild::STATUS_PENDING,
    ]);

    $builder = new WpThemeBuilder();
    $result = $builder->build($build);

    expect($result->status)->toBe(WpBuild::STATUS_INSPECTION)
        ->and($result->folder)->toBe('mockup-work/' . $project->id . '/build-' . $build->id);

    $workspace = new MockupWorkspace();
    $themeDir = $workspace->buildPath($build);

    // Pastikan seluruh berkas wajib tema WordPress tercipta
    expect(is_file($themeDir . '/style.css'))->toBeTrue()
        ->and(is_file($themeDir . '/functions.php'))->toBeTrue()
        ->and(is_file($themeDir . '/header.php'))->toBeTrue()
        ->and(is_file($themeDir . '/footer.php'))->toBeTrue()
        ->and(is_file($themeDir . '/front-page.php'))->toBeTrue()
        ->and(is_file($themeDir . '/index.php'))->toBeTrue()
        ->and(is_file($themeDir . '/screenshot.png'))->toBeTrue()
        ->and(is_file($themeDir . '/images/slot-hero.jpg'))->toBeTrue();
});

it('replaces all contact cta markers with real client data', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>body { color: #333; }</style></head>
<body>
    <header><nav><a href="__EXITO_PAGE_URL:whatsapp">WA</a></nav></header>
    <main>
        <a href="__EXITO_PAGE_URL:telepon">Telepon</a>
        <a href="__EXITO_PAGE_URL:email">Email</a>
    </main>
    <footer>Footer</footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $workspace = new MockupWorkspace();
    $themeDir = $workspace->buildPath($build);

    $headerContent = file_get_contents($themeDir . '/header.php');
    $frontContent = file_get_contents($themeDir . '/front-page.php');

    // WhatsApp di header -> format wa.me/62...
    expect($headerContent)->toContain('https://wa.me/6281234567890');

    // Telepon dan email di front-page
    expect($frontContent)->toContain('tel:081234567890')
        ->and($frontContent)->toContain('mailto:' . $client->email);
});

it('replaces image markers and internal link markers properly', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>body { color: #333; }</style></head>
<body>
    <header><a href="__EXITO_PAGE_URL:layanan">Layanan</a></header>
    <main>
        <img src="__EXITO_IMAGE:slot-hero.jpg" alt="Hero">
        <img src="__EXITO_IMAGE:slot-gallery" alt="Gallery">
    </main>
    <footer>Footer</footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $workspace = new MockupWorkspace();
    $themeDir = $workspace->buildPath($build);

    $headerContent = file_get_contents($themeDir . '/header.php');
    $frontContent = file_get_contents($themeDir . '/front-page.php');

    expect($headerContent)->toContain("home_url('/layanan/')")
        ->and($frontContent)->toContain("get_template_directory_uri() . '/images/slot-hero.jpg'")
        ->and($frontContent)->toContain("get_template_directory_uri() . '/images/slot-gallery.jpg'");
});

it('leaves zero __EXITO_ markers in any theme template file', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>body { color: #333; }</style></head>
<body>
    <header>
        <a href="__EXITO_PAGE_URL:whatsapp">WA</a>
        <a href="__EXITO_PAGE_URL:kontak">Kontak</a>
    </header>
    <main>
        <img src="__EXITO_IMAGE:slot-hero" alt="Hero">
        <a href="__EXITO_PAGE_URL:telepon">Tel</a>
        <a href="__EXITO_PAGE_URL:email">Mail</a>
        <a href="__EXITO_PAGE_URL:tentang">About</a>
    </main>
    <footer><p>Footer</p></footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $workspace = new MockupWorkspace();
    $themeDir = $workspace->buildPath($build);

    $files = glob($themeDir . '/*.php');
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $content = file_get_contents($file);
        expect($content)->not->toContain('__EXITO_');
    }
});

it('handles client with missing contact information gracefully without breaking', function () {
    // Project tanpa Client CRM
    $project = wpTestProject(null);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>body { color: #111; }</style></head>
<body>
    <header><a href="__EXITO_PAGE_URL:whatsapp">WA</a></header>
    <main>
        <a href="__EXITO_PAGE_URL:telepon">Tel</a>
        <a href="__EXITO_PAGE_URL:email">Mail</a>
    </main>
    <footer>Footer</footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $workspace = new MockupWorkspace();
    $themeDir = $workspace->buildPath($build);

    $headerContent = file_get_contents($themeDir . '/header.php');
    $frontContent = file_get_contents($themeDir . '/front-page.php');

    // Karena tidak ada nomor telepon/email, tautan jatuh aman ke '#'
    expect($headerContent)->toContain('href="#"')
        ->and($frontContent)->toContain('href="#"')
        ->and($headerContent)->not->toContain('__EXITO_')
        ->and($frontContent)->not->toContain('__EXITO_');
});

it('writes a valid wordpress theme stylesheet header in style.css', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        .hero { background-image: url('__EXITO_IMAGE:slot-hero'); }
    </style>
</head>
<body>
    <header>Header</header>
    <main>Main</main>
    <footer>Footer</footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $workspace = new MockupWorkspace();
    $themeDir = $workspace->buildPath($build);

    $styleContent = file_get_contents($themeDir . '/style.css');

    expect($styleContent)->toContain('Theme Name: ' . $project->name)
        ->and($styleContent)->toContain('Author: SiteFlow')
        ->and($styleContent)->toContain('Version: 1.0.0')
        ->and($styleContent)->toContain('Text Domain: website-toko-bunga')
        ->and($styleContent)->toContain("images/slot-hero.jpg")
        ->and($styleContent)->not->toContain('<?php');
});

it('executes theme build via BuildWpThemeJob in queue', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>body { color: #000; }</style></head>
<body>
    <header><a href="__EXITO_PAGE_URL:whatsapp">WA</a></header>
    <main>Konten</main>
    <footer>Footer</footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
        'status' => WpBuild::STATUS_PENDING,
    ]);

    // Jalankan sync lewat job antrean (di Tahap 9, job langsung menjalankan meja periksa dan menghasilkan tema ready jika valid)
    (new BuildWpThemeJob($build))->handle();

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_READY)
        ->and($fresh->zip_path)->not->toBeNull();
});

it('records failure in failed status when candidate index.html is missing', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $candidate = MockupCandidate::create([
        'project_id' => $project->id,
        'candidate_number' => 1,
        'status' => MockupCandidate::STATUS_CHOSEN,
        'folder' => 'mockup-work/' . $project->id . '/non-existent-candidate',
    ]);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
        'status' => WpBuild::STATUS_PENDING,
    ]);

    (new BuildWpThemeJob($build))->handle();

    $fresh = $build->fresh();
    expect($fresh->status)->toBe(WpBuild::STATUS_INSPECTION_FAILED)
        ->and($fresh->inspection_results)->toBeArray()
        ->and($fresh->inspection_results['error'])->toContain('tidak ditemukan');
});
