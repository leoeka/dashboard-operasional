<?php

use App\Services\BundleExporterService;
use App\Services\ElementorPageBuilderService;
use App\Support\MockupDesignSpec;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The Block Editor re-runs every block's save() and flags any difference from
 * the stored markup as invalid ("Attempt Block Recovery"). These are the four
 * differences a real WordPress 7.1 editor reported on a built V2 page — each
 * pinned to the exact markup core's save() produces, so they cannot return.
 */
function validityHtml(array $design = [], array $imageMap = []): string
{
    $fixture = require base_path('tests/Fixtures/v2-blueprint.php');

    return (new ElementorPageBuilderService)->buildPages(
        $fixture['pages'],
        array_merge($fixture['design'], $design),
        $imageMap ?: ['home' => ['hero' => 'home-hero.jpg', 'items' => [0 => 'home-item-0.jpg'], 'sections' => [2 => [0 => 'home-item-0.jpg'], 3 => [0 => 'home-section-3-item-0.jpg']]]],
        $fixture['global_cta']
    )['home']['html'];
}

it('writes button styles exactly as core/button saves them', function () {
    $html = validityHtml();

    expect($html)->toContain('style="color:#12343B;background-color:#ffffff"')
        ->not->toContain('text-decoration:none;display:inline-block');
});

it('writes card padding as the longhand core/group saves, with no extra declarations', function () {
    $t = MockupDesignSpec::tokens();
    $p = $t['card_padding'].'px';

    expect(validityHtml())
        ->toContain("style=\"border-color:{$t['card_border_color']};border-width:{$t['card_border_width']}px;border-radius:{$t['card_radius']}px;padding-top:{$p};padding-right:{$p};padding-bottom:{$p};padding-left:{$p}\"")
        ->not->toContain('overflow:hidden;padding:');
});

it('declares the image scale that produces the object-fit it writes', function () {
    expect(validityHtml())
        ->toContain('"aspectRatio":"3/4","scale":"cover"')
        ->toContain('style="aspect-ratio:3/4;object-fit:cover"');
});

it('puts the cover background image before the dim span, as core/cover saves it', function () {
    $html = validityHtml(['layout_variant' => 'overlay-bg']);
    $cover = substr($html, strpos($html, '<!-- wp:cover'));

    expect(strpos($cover, 'wp-block-cover__image-background'))
        ->toBeLessThan(strpos($cover, 'wp-block-cover__background'));
});

function validityChrome(): array
{
    return [
        'wrapper' => '<div class="site full-page">',
        'header' => '<header class="nav nav--split"><a class="brand" href="__EXITO_THEME_ASSET__">Logo</a><?php exito_client_nav_menu(\'links\'); ?></header>',
        'footer' => '<footer class="footer footer--columns"><?php exito_client_nav_menu(\'footer-links\'); ?></footer>',
    ];
}

it('replaces image tokens outside the photo markers at import time', function () {
    $dir = sys_get_temp_dir().'/exito-validity-'.uniqid();
    $theme = 'exito-client-theme';
    (new BundleExporterService)->export([
        'theme' => ['name' => $theme],
        'mockup_rendering' => ['css' => '.approved{color:#123456}', 'pages' => [], 'chrome' => validityChrome()],
        'elementor_pages' => ['home' => ['title' => 'Home', 'html' => '<p>x</p>', 'elements' => []]],
    ], $dir);

    $zip = new ZipArchive;
    $zip->open($dir.'/theme-install.zip');
    $functions = $zip->getFromName("{$theme}/functions.php");
    $zip->close();

    // The marker pass must feed the second, marker-free pass — a cover's
    // `url` attribute otherwise keeps the raw token and the block is invalid.
    expect($functions)->toMatch('/\$html = preg_replace_callback\(\s+\'\/<!--EXITO_IMG_START:/')
        ->toContain("'/__EXITO_IMAGE:(.*?)__/'");
});

it('exports the approved mockup markup and stylesheet as a self-loading theme', function () {
    $dir = sys_get_temp_dir().'/exito-mockup-parity-'.uniqid();
    $theme = 'exito-client-theme';
    (new BundleExporterService)->export([
        'theme' => ['name' => $theme],
        'brand' => ['company_name' => 'Nusa Trails'],
        'mockup_rendering' => [
            'pages' => ['home' => ['html' => '<!-- wp:html --><main class="approved"><a href="__EXITO_PAGE_URL:about__">Approved</a></main><!-- /wp:html -->']],
            'css' => '.approved{color:#123456}',
            'fonts_url' => 'https://fonts.googleapis.com/css2?family=Inter',
            'chrome' => validityChrome(),
            'nav' => [['label' => 'Home', 'slug' => 'home'], ['label' => 'About', 'slug' => 'about']],
            'cta' => 'Hubungi Kami',
        ],
        'elementor_pages' => ['home' => ['title' => 'Home', 'html' => '<p>fallback content</p>', 'elements' => []]],
    ], $dir);

    $zip = new ZipArchive;
    $zip->open($dir.'/theme-install.zip');
    $style = $zip->getFromName("{$theme}/style.css");
    $header = $zip->getFromName("{$theme}/header.php");
    $page = $zip->getFromName("{$theme}/page.php");
    $functions = $zip->getFromName("{$theme}/functions.php");
    $zip->close();

    // WordPress never loads style.css by itself, and the pages carry the
    // approved markup rather than theme templates, so an unenqueued
    // stylesheet means every installed site renders unstyled.
    expect($style)->toContain('Theme Name: Nusa Trails — Exito Client Theme')
        ->toContain('.approved{color:#123456}')
        ->and($functions)->toContain("wp_enqueue_style('exito-client-theme', get_stylesheet_uri()")
        ->toContain('add_editor_style')
        ->toContain('Approved')
        ->toContain('__EXITO_PAGE_URL:')
        ->toContain("delete_post_meta(\$page_id, '_elementor_data')")
        ->and($header)->toContain('wp_head()')->toContain('fonts.googleapis.com')
        ->and($page)->toContain('the_content()');
});

it('refuses to ship a theme whose approved stylesheet is missing', function () {
    $dir = sys_get_temp_dir().'/exito-no-stylesheet-'.uniqid();

    // An empty style.css installs cleanly and renders an unstyled site, so
    // the failure has to surface at build time, not on the client's server.
    expect(fn () => (new BundleExporterService)->export([
        'theme' => ['name' => 'exito-client-theme'],
        'elementor_pages' => ['home' => ['title' => 'Home', 'html' => '<p>x</p>', 'elements' => []]],
    ], $dir))->toThrow(RuntimeException::class, 'Stylesheet mockup');
});

it('keeps existing WordPress pages intact when an imported mockup slug collides', function () {
    $dir = sys_get_temp_dir().'/exito-page-collision-'.uniqid();
    $theme = 'exito-client-theme';
    (new BundleExporterService)->export([
        'project_id' => 42,
        'theme' => ['name' => $theme],
        'mockup_rendering' => [
            'css' => '.approved{color:#123456}',
            'chrome' => validityChrome(),
            'nav' => [['label' => 'Home', 'slug' => 'home'], ['label' => 'About', 'slug' => 'about']],
            'pages' => [
                'home' => ['html' => '<a href="__EXITO_PAGE_URL:about__">About</a>'],
                'about' => ['html' => '<p>About</p>'],
            ],
        ],
        'elementor_pages' => [
            'home' => ['title' => 'Home', 'html' => '<p>Home</p>', 'elements' => []],
            'about' => ['title' => 'About', 'html' => '<p>About</p>', 'elements' => []],
        ],
    ], $dir);

    $zip = new ZipArchive;
    $zip->open($dir.'/theme-install.zip');
    $functions = $zip->getFromName("{$theme}/functions.php");
    $zip->close();

    $phpFile = tempnam(sys_get_temp_dir(), 'exito-importer-lint-');
    file_put_contents($phpFile, $functions);
    $lint = Process::run(['php', '-l', $phpFile]);
    @unlink($phpFile);

    expect($functions)
        ->toContain("'meta_key' => '_exito_client_page_key'")
        ->toContain('wp_unique_post_slug($slug, 0, \'publish\', \'page\', 0)')
        ->toContain('$page_ids[$slug] = (int) $page_id')
        ->toContain('function ($matches) use ($page_ids)')
        ->not->toContain('$existing = get_page_by_path($slug)')
        ->and($lint->successful())->toBeTrue();
});

it('ships the approved navigation as a real WordPress menu', function () {
    $dir = sys_get_temp_dir().'/exito-nav-menu-'.uniqid();
    $theme = 'exito-client-theme';
    (new BundleExporterService)->export([
        'project_id' => 7,
        'theme' => ['name' => $theme],
        'assets' => ['logo' => ['filename' => 'logo.png', 'bytes' => 'PNGBYTES']],
        'mockup_rendering' => [
            'css' => '.approved{color:#123456}',
            'cta' => 'Hubungi Kami',
            'chrome' => validityChrome(),
            'nav' => [['label' => 'Home', 'slug' => 'home'], ['label' => 'About', 'slug' => 'about']],
            'pages' => [
                'home' => ['html' => '<p>Home</p>'],
                'about' => ['html' => '<p>About</p>'],
            ],
        ],
        'elementor_pages' => [
            'home' => ['title' => 'Home', 'html' => '<p>Home</p>', 'elements' => []],
            'about' => ['title' => 'About', 'html' => '<p>About</p>', 'elements' => []],
        ],
    ], $dir);

    $zip = new ZipArchive;
    $zip->open($dir.'/theme-install.zip');
    $files = [
        'functions' => $zip->getFromName("{$theme}/functions.php"),
        'header' => $zip->getFromName("{$theme}/header.php"),
        'footer' => $zip->getFromName("{$theme}/footer.php"),
    ];
    $zip->close();

    $lints = [];
    foreach ($files as $name => $contents) {
        $phpFile = tempnam(sys_get_temp_dir(), "exito-chrome-lint-{$name}-");
        file_put_contents($phpFile, $contents);
        $lints[$name] = Process::run(['php', '-l', $phpFile]);
        @unlink($phpFile);
    }

    // The navigation bar and the footer are theme chrome, not page content: a
    // page the client creates later gets them too, and the links come from one
    // editable menu rather than from the HTML of every page.
    expect($files['functions'])
        ->toContain("register_nav_menus(['primary' => 'Menu utama']);")
        ->toContain('function exito_client_nav_menu(')
        ->toContain("add_filter('wp_nav_menu_items'")
        ->toContain('function exito_client_import_menu(array $page_ids): void {')
        ->toContain('wp_update_nav_menu_item($menu_id, 0, [')
        ->toContain("set_theme_mod('nav_menu_locations', \$locations);")
        ->toContain('exito_client_import_menu($page_ids);')
        ->and($files['header'])
            ->toContain('<div class="site full-page">')
            ->toContain("<?php exito_client_nav_menu('links'); ?>")
            ->toContain("get_stylesheet_directory_uri().'/assets/logo.png'")
            ->not->toContain('__EXITO_THEME_ASSET__')
        ->and($files['footer'])
            ->toContain("<?php exito_client_nav_menu('footer-links'); ?>")
            ->toContain("</div>\n<?php wp_footer(); ?>")
        ->and($lints['functions']->successful())->toBeTrue()
        ->and($lints['header']->successful())->toBeTrue()
        ->and($lints['footer']->successful())->toBeTrue();
});
