<?php

use App\Jobs\BuildWpThemeJob;
use App\Models\Client;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Models\WpBuild;
use App\Services\WpThemeBuilder;
use App\Services\WpThemeInspector;
use App\Support\MockupWorkspace;

function wpTestJpegBytes(): string
{
    // JPEG 1x1 sah: pemeriksaan meja periksa menolak byte gambar palsu.
    return (string) base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q==');
}

function wpTestPngBytes(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
}

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
    file_put_contents($candidateDir . '/preview.png', wpTestPngBytes());

    // Dummy slot image
    file_put_contents($candidateDir . '/slot-hero.jpg', wpTestJpegBytes());

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
        ->and(is_file($themeDir . '/page.php'))->toBeTrue()
        ->and(is_file($themeDir . '/index.php'))->toBeTrue()
        ->and(is_file($themeDir . '/screenshot.png'))->toBeTrue()
        ->and(is_file($themeDir . '/images/slot-hero.jpg'))->toBeTrue()
        ->and(is_file($themeDir . '/content/beranda.html'))->toBeTrue()
        ->and(is_file($themeDir . '/content/beranda.json'))->toBeTrue()
        ->and(is_file($themeDir . '/front-page.php'))->toBeFalse();
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
    $frontContent = file_get_contents($themeDir . '/content/beranda.html');

    // WhatsApp di header -> format wa.me/62...
    expect($headerContent)->toContain('https://wa.me/6281234567890');

    // Telepon dan email di halaman beranda
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

    // Slot kedua sengaja berberkas .png walau penandanya tanpa ekstensi
    file_put_contents((new MockupWorkspace())->candidatePath($candidate) . '/slot-gallery.png', wpTestPngBytes());

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $workspace = new MockupWorkspace();
    $themeDir = $workspace->buildPath($build);

    $headerContent = file_get_contents($themeDir . '/header.php');
    $frontContent = file_get_contents($themeDir . '/content/beranda.html');

    // Halaman 'layanan' tidak dibuat di tema: tautannya dibuang, teksnya tetap ada
    expect($headerContent)->toContain('Layanan')
        ->and($headerContent)->not->toContain('<a')
        ->and($headerContent)->not->toContain('href="#"')
        ->and($headerContent)->not->toContain('home_url')
        ->and($frontContent)->toContain('[[siteflow:gambar:slot-hero.jpg]]')
        ->and($frontContent)->toContain('[[siteflow:gambar:slot-gallery.png]]');
});

it('keeps an in-page contact anchor when the target section exists', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>body { color: #333; }</style></head>
<body>
    <header><a href="__EXITO_PAGE_URL:kontak">Kontak</a></header>
    <main id="kontak">Alamat kami</main>
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

    $themeDir = (new MockupWorkspace())->buildPath($build);

    expect(file_get_contents($themeDir . '/header.php'))->toContain('href="#kontak"');
});

it('drops a contact anchor when no real section carries that id', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>body { color: #333; }</style></head>
<body>
    <header><a href="__EXITO_PAGE_URL:kontak">Kontak</a></header>
    <main data-id="kontak">Isi</main>
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

    $themeDir = (new MockupWorkspace())->buildPath($build);
    $headerContent = file_get_contents($themeDir . '/header.php');

    expect($headerContent)->toContain('Kontak')
        ->and($headerContent)->not->toContain('<a')
        ->and($headerContent)->not->toContain('href="#"');
});

it('unwraps internal links whose slugs are prefixes of each other', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>body { color: #333; }</style></head>
<body>
    <header><a href="__EXITO_PAGE_URL:layanan">Layanan</a></header>
    <main>
        <a href="__EXITO_PAGE_URL:layanan-detail">Detail layanan</a>
        <p>Isi</p>
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

    $themeDir = (new MockupWorkspace())->buildPath($build);

    $headerContent = file_get_contents($themeDir . '/header.php');
    $frontContent = file_get_contents($themeDir . '/content/beranda.html');

    expect($headerContent)->toContain('Layanan')
        ->and($headerContent)->not->toContain('<a')
        ->and($frontContent)->toContain('Detail layanan')
        ->and($frontContent)->not->toContain('<a')
        ->and($frontContent)->not->toContain('__EXITO_');
});

it('builds content pages, menu and media payload from candidate pages', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>
    :root { --brand: #1d4ed8; }
    body { color: #1f2937; background: #ffffff; }
</style></head>
<body>
    <header class="site-header">
        <a class="logo" href="__EXITO_PAGE_URL:tentang">Toko Bunga</a>
        <nav>
            <ul class="nav-links">
                <li><a href="__EXITO_PAGE_URL:tentang">Tentang Kami</a></li>
                <li><a href="__EXITO_PAGE_URL:layanan">Layanan</a></li>
            </ul>
        </nav>
        <a class="btn-wa" href="__EXITO_PAGE_URL:whatsapp">Pesan</a>
    </header>
    <main>
        <h1>Bunga Segar</h1>
        <img src="__EXITO_IMAGE:slot-hero" alt="Hero">
    </main>
    <footer><p>Footer</p></footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);
    $candidateDir = (new MockupWorkspace())->candidatePath($candidate);
    mkdir($candidateDir . '/pages', 0775, true);

    file_put_contents($candidateDir . '/pages/tentang.html', <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Tentang Toko Bunga</title>
    <style>.tentang-hero { background-image: url('__EXITO_IMAGE:slot-hero'); }</style>
</head>
<body>
    <header><nav><ul><li><a href="__EXITO_PAGE_URL:layanan">Layanan</a></li></ul></nav></header>
    <main>
        <section class="tentang-hero">
            <h1>Tentang Kami</h1>
            <img src="__EXITO_IMAGE:slot-hero" alt="Toko">
            <a href="__EXITO_PAGE_URL:layanan">Lihat layanan</a>
            <a href="__EXITO_PAGE_URL:telepon">Telepon kami</a>
        </section>
    </main>
    <footer><p>Footer global</p></footer>
</body>
</html>
HTML);

    file_put_contents($candidateDir . '/pages/layanan.html', <<<HTML
<!DOCTYPE html>
<html>
<head><title>Layanan Kami</title><style>.layanan-grid { display: grid; }</style></head>
<body>
    <header><nav>Navigasi</nav></header>
    <main><section class="layanan-grid"><h1>Layanan</h1><p>Rangkaian bunga.</p></section></main>
    <footer><p>Footer global</p></footer>
</body>
</html>
HTML);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);

    expect(is_file($themeDir . '/page.php'))->toBeTrue()
        ->and(is_file($themeDir . '/README.txt'))->toBeTrue()
        ->and(is_file($themeDir . '/content/beranda.html'))->toBeTrue()
        ->and(is_file($themeDir . '/content/beranda.json'))->toBeTrue()
        ->and(is_file($themeDir . '/content/tentang.html'))->toBeTrue()
        ->and(is_file($themeDir . '/content/tentang.json'))->toBeTrue()
        ->and(is_file($themeDir . '/content/layanan.html'))->toBeTrue()
        ->and(is_file($themeDir . '/content/layanan.json'))->toBeTrue()
        ->and(is_file($themeDir . '/content/halaman.json'))->toBeTrue();

    $headerContent = file_get_contents($themeDir . '/header.php');
    $tentangContent = file_get_contents($themeDir . '/content/tentang.html');
    $styleContent = file_get_contents($themeDir . '/style.css');
    $functionsContent = file_get_contents($themeDir . '/functions.php');

    // Menu WordPress sungguhan menggantikan daftar tautan statis di header.
    expect($headerContent)->toContain("siteflow_nav_menu('nav-links')")
        ->and($headerContent)->toContain('https://wa.me/6281234567890')
        ->and($headerContent)->not->toContain('__EXITO_');

    // Halaman isi: chrome global dibuang, tautan dan gambar jadi token.
    expect($tentangContent)->not->toContain('<header')
        ->and($tentangContent)->not->toContain('<footer')
        ->and($tentangContent)->toContain('[[siteflow:halaman:layanan]]')
        ->and($tentangContent)->toContain('[[siteflow:gambar:slot-hero.jpg]]')
        ->and($tentangContent)->toContain('tel:081234567890');

    // CSS halaman isi ikut ke satu stylesheet tema, diberi lingkup halaman,
    // lalu disalin ke lingkup Elementor supaya gaya mockup tidak kalah.
    expect($styleContent)->toContain('.tentang-hero')
        ->and($styleContent)->toContain('.layanan-grid')
        ->and($styleContent)->toContain('images/slot-hero.jpg')
        ->and($styleContent)->toContain('.siteflow-page-tentang .tentang-hero')
        ->and($styleContent)->toContain('.siteflow-page.siteflow-page .siteflow-page-tentang .tentang-hero')
        ->and($styleContent)->toContain('.elementor-edit-area.elementor-edit-area .siteflow-page-tentang .tentang-hero')
        ->and($styleContent)->not->toContain('__EXITO_');

    expect($functionsContent)->toContain('function siteflow_import_pages(')
        ->and($functionsContent)->toContain('function siteflow_import_menu(')
        ->and($functionsContent)->toContain('function siteflow_import_media(')
        ->and($functionsContent)->toContain('function siteflow_run_import(')
        ->and($functionsContent)->toContain('function siteflow_sync_elementor_kit(')
        ->and($functionsContent)->toContain('function siteflow_theme_palette(')
        ->and($functionsContent)->toContain('siteflow_sync_elementor_kit();')
        ->and($functionsContent)->toContain('system_colors')
        ->and($functionsContent)->toContain("'#6EC1E4'")
        ->and($functionsContent)->toContain("current_user_can('manage_options')")
        ->and($functionsContent)->toContain("get_option('siteflow_nav_item_ids'")
        ->and($functionsContent)->toContain("add_option('siteflow_import_lock'");

    $meta = json_decode((string) file_get_contents($themeDir . '/content/halaman.json'), true);

    $slugs = array_column($meta['halaman'], 'slug');
    sort($slugs);

    expect($slugs)->toBe(['beranda', 'layanan', 'tentang'])
        ->and($meta['nav'])->toBe([
            ['slug' => 'tentang', 'label' => 'Tentang Kami'],
            ['slug' => 'layanan', 'label' => 'Layanan'],
        ])
        ->and($meta['gambar'])->toBe(['slot-hero.jpg'])
        ->and(array_key_exists('slot-hero.jpg', $meta['gambar_alt'] ?? []))->toBeTrue()
        ->and($meta['versi'])->not->toBeEmpty()
        ->and($meta['kunci'])->toContain((string) $project->id);

    // Isi halaman tersimpan sebagai elemen Elementor, bukan satu widget HTML.
    $payload = json_decode((string) file_get_contents($themeDir . '/content/tentang.json'), true);
    $widgetTypes = [];

    $collect = function (array $elements) use (&$collect, &$widgetTypes): void {
        foreach ($elements as $element) {
            $widgetTypes[] = $element['widgetType'] ?? $element['elType'] ?? '';
            $collect($element['elements'] ?? []);
        }
    };
    $collect($payload['elementor']);

    expect($payload['versi'])->not->toBeEmpty()
        ->and($widgetTypes)->toContain('heading')
        ->and($widgetTypes)->toContain('text-editor')
        ->and($widgetTypes)->toContain('image')
        ->and($widgetTypes)->not->toContain('html');
});

it('frames each photo and isolates content-page CSS from the front page', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>
    :root { --ink: #26312d; --white: #fff; }
    body { color: #26312d; }
    .hero { display: grid; grid-template-columns: .86fr 1.14fr; }
    .hero-media { height: 520px; }
    .hero-media img { height: 510px; object-fit: cover; }
    .photo-caption { position: absolute; bottom: 16px; left: 16px; background: var(--white); }
</style></head>
<body>
    <header><nav><ul class="nav-links"><li><a href="pages/tentang.html">Tentang</a></li></ul></nav></header>
    <main>
        <section class="hero"><h1>Bambu</h1><div class="hero-media"><img src="__EXITO_IMAGE:hero" alt="Pengerjaan bambu"><span class="photo-caption">Serat bambu setelah diserut</span></div></section>
    </main>
    <footer><p>Footer</p></footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);
    $candidateDir = (new MockupWorkspace())->candidatePath($candidate);
    file_put_contents($candidateDir . '/hero.png', wpTestPngBytes());
    file_put_contents($candidateDir . '/galeri-1.png', wpTestPngBytes());
    mkdir($candidateDir . '/pages', 0775, true);

    file_put_contents($candidateDir . '/pages/tentang.html', <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Tentang</title>
    <style>
        .tentang-hero { padding: 60px; }
        .hero { grid-template-columns: 1fr 1fr; }
        .story { height: 440px; border-radius: 4px 46% 4px 4px; }
        .story img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body>
    <header><nav>Tentang</nav></header>
    <main><section class="tentang-hero"><h1>Tentang</h1><div class="story"><img src="__EXITO_IMAGE:galeri-1" alt="Detail bambu"></div></section></main>
    <footer><p>Footer</p></footer>
</body>
</html>
HTML);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    $frontContent = file_get_contents($themeDir . '/content/beranda.html');
    $tentangContent = file_get_contents($themeDir . '/content/tentang.html');
    $styleContent = file_get_contents($themeDir . '/style.css');

    // Foto hero mendapat bingkai peran + slot pada kontainernya.
    expect($frontContent)->toContain('class="hero-media siteflow-photo siteflow-photo-hero"')
        ->and($tentangContent)->toContain('class="story siteflow-photo siteflow-photo-galeri-1"');

    // Halaman isi dibungkus kelas lingkup; beranda tidak.
    expect($tentangContent)->toStartWith('<div class="siteflow-page-scope siteflow-page-tentang">')
        ->and($frontContent)->not->toContain('siteflow-page-scope')
        ->and(file_get_contents($themeDir . '/content/tentang.json'))->toContain('siteflow-page-scope siteflow-page-tentang');

    // CSS halaman isi diberi awalan halaman itu; aturan tanpa awalan hilang.
    expect($styleContent)->toContain('.siteflow-page-tentang .tentang-hero')
        ->and($styleContent)->toContain('.siteflow-page.siteflow-page .siteflow-page-tentang .tentang-hero')
        ->and($styleContent)->not->toContain("\n.tentang-hero{")
        ->and($styleContent)->not->toContain("\n.story{");

    // Rasio, titik fokus, keterangan di bawah foto, dan bingkai dekoratif mati.
    expect($styleContent)->toContain('width:100%;height:auto;min-height:0;max-height:none;max-width:100%;aspect-ratio:4 / 3')
        ->and($styleContent)->toContain('width:100%;height:auto;min-height:0;max-height:none;max-width:100%;aspect-ratio:1 / 1')
        ->and($styleContent)->toContain('object-position:var(--siteflow-foto-fokus,50% 40%)')
        ->and($styleContent)->toContain('.siteflow-photo.siteflow-photo-hero .photo-caption')
        ->and($styleContent)->toContain('position:static')
        ->and($styleContent)->toContain('.siteflow-photo.siteflow-photo-galeri-1::after')
        ->and($styleContent)->toContain('content:none');
});

it('reads menu and links written as plain relative pages from the codex path', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>
    :root { --brand: #1d4ed8; }
    body { color: #1f2937; background: #ffffff; }
</style></head>
<body>
    <header>
        <a class="brand" href="index.html">Toko Bunga</a>
        <nav aria-label="Navigasi utama">
            <ul class="nav-links">
                <li><a href="pages/tentang.html">Tentang</a></li>
                <li><a href="pages/produk.html">Produk</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h1>Bunga Segar</h1>
        <img src="__EXITO_IMAGE:slot-hero" alt="Hero">
    </main>
    <footer><p>Footer</p></footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);
    $candidateDir = (new MockupWorkspace())->candidatePath($candidate);
    mkdir($candidateDir . '/pages', 0775, true);

    file_put_contents($candidateDir . '/pages/tentang.html', <<<HTML
<!DOCTYPE html>
<html>
<head><title>Tentang Toko</title></head>
<body>
    <header><nav><ul><li><a href="produk.html">Produk</a></li></ul></nav></header>
    <main>
        <h1>Tentang Kami</h1>
        <a href="../index.html">Kembali ke beranda</a>
        <a href="produk.html">Lihat produk</a>
    </main>
    <footer><p>Footer global</p></footer>
</body>
</html>
HTML);

    file_put_contents($candidateDir . '/pages/produk.html', <<<HTML
<!DOCTYPE html>
<html>
<head><title>Produk</title></head>
<body>
    <header><nav><ul><li><a href="tentang.html">Tentang</a></li></ul></nav></header>
    <main><h1>Produk</h1><p>Rangkaian bunga.</p></main>
    <footer><p>Footer global</p></footer>
</body>
</html>
HTML);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    $headerContent = file_get_contents($themeDir . '/header.php');
    $tentangContent = file_get_contents($themeDir . '/content/tentang.html');
    $menu = (new WpThemeInspector())->checkMenuPayload($themeDir);

    expect($headerContent)->toContain("siteflow_nav_menu('nav-links')")
        ->and($headerContent)->toContain("home_url('/')")
        ->and($headerContent)->not->toContain('pages/tentang.html')
        ->and($tentangContent)->toContain('[[siteflow:halaman:produk]]')
        ->and($tentangContent)->toContain('[[siteflow:halaman:beranda]]')
        ->and($tentangContent)->not->toContain('siteflow:halaman:home')
        ->and($tentangContent)->toContain('Kembali ke beranda')
        ->and($menu['passed'])->toBeTrue();

    $meta = json_decode((string) file_get_contents($themeDir . '/content/halaman.json'), true);

    expect($meta['nav'])->toBe([
        ['slug' => 'tentang', 'label' => 'Tentang'],
        ['slug' => 'produk', 'label' => 'Produk'],
    ])
        ->and(array_column($meta['halaman'], 'slug'))->toEqualCanonicalizing(['beranda', 'tentang', 'produk']);
});

it('keeps the header link list when it contains contact buttons', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>body { color: #1f2937; background: #ffffff; }</style></head>
<body>
    <header>
        <nav><ul class="nav-links">
            <li><a href="__EXITO_PAGE_URL:tentang">Tentang</a></li>
            <li><a href="__EXITO_PAGE_URL:whatsapp">Pesan</a></li>
        </ul></nav>
    </header>
    <main>Isi</main>
    <footer>Footer</footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);
    $candidateDir = (new MockupWorkspace())->candidatePath($candidate);
    mkdir($candidateDir . '/pages', 0775, true);
    file_put_contents($candidateDir . '/pages/tentang.html', '<html><body><main><h1>Tentang</h1></main></body></html>');

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    (new WpThemeBuilder())->build($build);

    $headerContent = file_get_contents((new MockupWorkspace())->buildPath($build) . '/header.php');

    // Tombol kontak bagian desain: daftar tetap statis, tidak diganti menu.
    expect($headerContent)->not->toContain('siteflow_nav_menu(')
        ->and($headerContent)->toContain('https://wa.me/6281234567890')
        ->and($headerContent)->toContain("siteflow_page_url('tentang')");
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
    $frontContent = file_get_contents($themeDir . '/content/beranda.html');

    // Kontak kosong = kontrolnya dibuang (K-7), teksnya tetap terbaca
    expect($headerContent)->not->toContain('href="#"')
        ->and($headerContent)->not->toContain('<a')
        ->and($headerContent)->toContain('WA')
        ->and($frontContent)->not->toContain('href="#"')
        ->and($frontContent)->not->toContain('<a')
        ->and($frontContent)->toContain('Tel')
        ->and($frontContent)->toContain('Mail')
        ->and($headerContent)->not->toContain('__EXITO_')
        ->and($frontContent)->not->toContain('__EXITO_');
});

it('fails the build when an image marker has no downloaded file', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>body { color: #333; }</style></head>
<body>
    <header><nav>Navigasi</nav></header>
    <main><img src="__EXITO_IMAGE:hilang" alt="Hilang"></main>
    <footer>Footer</footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    expect(fn () => (new WpThemeBuilder())->build($build))
        ->toThrow(RuntimeException::class, "Slot gambar 'hilang'");
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
<head><style>body { color: #1f2937; background: #ffffff; }</style></head>
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

it('clears stale page files and images when the same build is rebuilt', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><style>body { color: #1f2937; background: #ffffff; }</style></head>
<body>
    <header><nav><ul class="nav-links"><li><a href="__EXITO_PAGE_URL:tentang">Tentang</a></li></ul></nav></header>
    <main><h1>Bunga</h1><img src="__EXITO_IMAGE:slot-hero" alt="Hero"></main>
    <footer>Footer</footer>
</body>
</html>
HTML;

    $candidate = wpTestCandidate($project, $html);
    $candidateDir = (new MockupWorkspace())->candidatePath($candidate);
    mkdir($candidateDir . '/pages', 0775, true);
    file_put_contents($candidateDir . '/pages/tentang.html', '<html><body><main>Halaman tentang</main></body></html>');
    file_put_contents($candidateDir . '/pages/layanan.html', '<html><body><main>Halaman layanan</main></body></html>');
    file_put_contents($candidateDir . '/slot-extra.jpg', 'dummy-jpg-content');

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    $builder = new WpThemeBuilder();
    $builder->build($build);

    $themeDir = (new MockupWorkspace())->buildPath($build);
    expect(is_file($themeDir . '/content/layanan.html'))->toBeTrue()
        ->and(is_file($themeDir . '/content/layanan.json'))->toBeTrue()
        ->and(is_file($themeDir . '/images/slot-extra.jpg'))->toBeTrue();

    // Kandidat berubah: halaman layanan dan foto tambahan dihapus, lalu build ulang.
    unlink($candidateDir . '/pages/layanan.html');
    unlink($candidateDir . '/slot-extra.jpg');
    $builder->build($build->fresh());

    $meta = json_decode((string) file_get_contents($themeDir . '/content/halaman.json'), true);

    expect(is_file($themeDir . '/content/layanan.html'))->toBeFalse()
        ->and(is_file($themeDir . '/content/layanan.json'))->toBeFalse()
        ->and(is_file($themeDir . '/images/slot-extra.jpg'))->toBeFalse()
        ->and(array_column($meta['halaman'], 'slug'))->toBe(['beranda', 'tentang'])
        ->and($meta['gambar'])->toBe(['slot-hero.jpg']);
});

it('rejects an svg image from the candidate with a clear message', function () {
    $client = wpTestClient();
    $project = wpTestProject($client);

    $candidate = wpTestCandidate($project, '<html><body><main>Halo</main></body></html>');
    $candidateDir = (new MockupWorkspace())->candidatePath($candidate);
    file_put_contents($candidateDir . '/slot-icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    expect(fn () => (new WpThemeBuilder())->build($build))
        ->toThrow(RuntimeException::class, 'berformat .svg');
});
