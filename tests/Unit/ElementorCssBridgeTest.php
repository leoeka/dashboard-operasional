<?php

use App\Services\ElementorCssBridge;

it('keeps a button rule on the Elementor anchor', function () {
    $css = '.button{background:#173F35}.hero .button{color:#fff}';

    $output = (new ElementorCssBridge())->build($css, ['button']);

    expect($output)->toContain('.elementor-edit-area.elementor-edit-area .button .elementor-button{background:#173F35}')
        ->and($output)->toContain('.elementor-edit-area.elementor-edit-area .hero .button .elementor-button{color:#fff}')
        ->and($output)->not->toContain('.elementor-widget-button.elementor-button');
});

it('keeps two button classes apart so the later one cannot recolour both', function () {
    // Kelas sumber tinggal pada pembungkus widget, jadi .button dan
    // .button-light punya sasarannya masing-masing.
    $css = '.button{background:#ff6a00}.button-light{background:#f6f1e6}';

    $bridge = new ElementorCssBridge();
    $output = $bridge->resetRules(['button', 'button-light'])
        . "\n" . $bridge->build($css, ['button', 'button-light'])
        . "\n" . $bridge->build('.cta .button{color:#fff}', ['button', 'button-light']);

    expect($output)->toContain('.elementor-edit-area.elementor-edit-area .button .elementor-button{background:#ff6a00}')
        ->and($output)->toContain('.elementor-edit-area.elementor-edit-area .button-light .elementor-button{background:#f6f1e6}')
        // Penyetel hanya ditulis satu kali, di depan seluruh salinan, sehingga
        // aturan turunan menang saat bobotnya sama — termasuk atas salinan
        // CSS halaman isi yang digabung sesudah salinan beranda.
        ->and(substr_count($output, '.elementor-widget-button .elementor-button{'))->toBe(2)
        ->and(strpos($output, '.elementor-widget-button .elementor-button{'))->toBeLessThan(
            strpos($output, '.button .elementor-button{')
        )
        ->and(strpos($output, '.elementor-widget-button .elementor-button{'))->toBeLessThan(
            strpos($output, '.cta .button .elementor-button{')
        );
});

it('restores band colour on headings and text the kit would otherwise own', function () {
    $css = '.hero{background:#123d35;color:#fff}.eyebrow{color:#ff6a00}.button{background:#ff6a00;color:#fff}h2{font-size:32px}.split .photo-wrap{padding:8px}';

    $output = (new ElementorCssBridge())->colourInheritanceRules($css, ['button']);

    // Satu aturan, lingkup satu kelas: cukup untuk mengalahkan CSS kit
    // (bobot dua kelas) tapi masih di bawah salinan jembatan.
    expect($output)->toContain(
        '.siteflow-page .hero .elementor-heading-title,.siteflow-page .hero .elementor-widget-text-editor,'
            . '.elementor-edit-area .hero .elementor-heading-title,.elementor-edit-area .hero .elementor-widget-text-editor{color:inherit}'
    )
        // Kelas tanpa latar bukan pita; kelas tombol tidak memuat judul.
        ->and($output)->not->toContain('.eyebrow')
        ->and($output)->not->toContain('.button')
        ->and($output)->not->toContain('.photo-wrap')
        ->and($output)->not->toContain('h2');
});

it('stretches the widget wrapper of a full-height svg layer', function () {
    $css = '.route-art{height:440px}.route-art svg{width:100%;height:100%;position:absolute;inset:0}';

    $output = (new ElementorCssBridge())->build($css);

    expect($output)->toContain(
        '.elementor-edit-area.elementor-edit-area .route-art>.elementor-widget>.elementor-widget-container{width:100%;height:100%;position:absolute;inset:0}'
    );
});

it('gives the image widget wrapper the height of its theme container', function () {
    $css = '.story-art{height:440px}.story-art img{width:100%;height:100%;object-fit:cover}';

    $output = (new ElementorCssBridge())->build($css);

    expect($output)->toContain('.siteflow-page.siteflow-page .story-art>.elementor-widget,')
        ->and($output)->toContain('.story-art>.elementor-widget>.elementor-widget-container{width:100%;height:100%;object-fit:cover}');
});

it('scopes every rule twice so theme styles beat Elementor defaults', function () {
    $output = (new ElementorCssBridge())->build('h1{color:#202923}');

    expect($output)->toContain('.siteflow-page.siteflow-page h1')
        ->and($output)->toContain('.elementor-edit-area.elementor-edit-area h1');
});

it('scopes content page rules to their page wrapper and keeps root rules global', function () {
    $css = ':root{--ink:#111}body{font-family:sans-serif}h1{font-size:40px}.now{color:#b00}@media(max-width:600px){.now{font-size:12px}}';

    $result = (new ElementorCssBridge())->scopeCss($css, '.siteflow-page-about');

    expect($result['global'])->toContain(':root{--ink:#111}')
        ->and($result['global'])->toContain('body{font-family:sans-serif}')
        ->and($result['global'])->not->toContain('.now')
        // Setelan tanpa kelas tidak diberi awalan lingkup: salinan berlingkup
        // situs sudah ada dan berlaku di halaman ini juga.
        ->and($result['scoped'])->not->toContain('h1')
        ->and($result['scoped'])->toContain('.siteflow-page-about .now{color:#b00}')
        ->and($result['scoped'])->toContain('@media(max-width:600px){.siteflow-page-about .now{font-size:12px}}');
});

it('does not let a page-scoped element reset outrank a class rule on the same page', function () {
    // Di mockup .button{color:white} menang atas a{color:inherit}. Diberi
    // awalan lingkup, a ikut membawa kelas lingkup dan posisinya terbalik,
    // jadi tautan tombol di halaman isi jadi abu-abu.
    $css = 'a{color:inherit;text-decoration:none}.button{background:#ff6a00;color:white}';
    $bridge = new ElementorCssBridge();

    $scoped = $bridge->scopeCss($css, '.siteflow-page-cakupan');
    $output = $bridge->build($scoped['scoped']);

    expect($output)->not->toContain('.siteflow-page-cakupan a')
        ->and($output)->toContain('.siteflow-page.siteflow-page .siteflow-page-cakupan .button,.elementor-edit-area.elementor-edit-area .siteflow-page-cakupan .button{background:#ff6a00;color:white}');
});

it('keeps the other selectors of a rule when one class is missing from the page', function () {
    // Satu aturan bisa dipakai kepala situs dan tombol di dalam halaman.
    // Memangkas per aturan membuang keduanya; .nav-cta hanya ada di kepala.
    $css = '.nav-cta, .button{background:#ff6a00;color:white}.navlinks{display:flex}';

    $output = (new ElementorCssBridge())->pruneUnusedClasses($css, ['button']);

    expect($output)->toContain('.button{background:#ff6a00;color:white}')
        ->and($output)->not->toContain('.nav-cta')
        ->and($output)->not->toContain('.navlinks');
});

it('drops rules whose classes do not exist in the page markup', function () {
    $css = '.navlinks{display:flex}.hero{padding:80px}.nav .brand{gap:8px}h2{font-size:32px}';

    $output = (new ElementorCssBridge())->pruneUnusedClasses($css, ['hero']);

    expect($output)->toContain('.hero{padding:80px}')
        ->and($output)->toContain('h2{font-size:32px}')
        ->and($output)->not->toContain('.navlinks')
        ->and($output)->not->toContain('.brand');
});

it('keeps surviving rules inside a media query and drops the query when it empties', function () {
    $css = '@media(max-width:600px){.hero{padding:24px}.footer-col{gap:12px}}@media(min-width:900px){.navlinks{display:flex}}';

    $output = (new ElementorCssBridge())->pruneUnusedClasses($css, ['hero']);

    expect($output)->toContain('@media(max-width:600px){.hero{padding:24px}}')
        ->and($output)->not->toContain('.footer-col')
        ->and($output)->not->toContain('min-width:900px');
});

it('keeps global at-rules that carry no class', function () {
    $css = '@font-face{font-family:X;src:url(a.woff2)}@import url("https://fonts.example/x.css");.hero{color:red}';

    $output = (new ElementorCssBridge())->pruneUnusedClasses($css, ['hero']);

    expect($output)->toContain('@font-face{font-family:X;src:url(a.woff2)}')
        ->and($output)->toContain('@import url("https://fonts.example/x.css");')
        ->and($output)->toContain('.hero{color:red}');
});

it('pairs every page scope with every selector when pages share one rule', function () {
    $css = '.container{width:min(1280px,100% - 160px)}.cards{display:grid}';

    $result = (new ElementorCssBridge())->scopeCss(
        $css,
        '.siteflow-page-layanan, .siteflow-page-cakupan'
    );

    expect($result['scoped'])->toContain(
        '.siteflow-page-layanan .container,.siteflow-page-cakupan .container{width:min(1280px,100% - 160px)}'
    )
        ->and($result['scoped'])->toContain(
            '.siteflow-page-layanan .cards,.siteflow-page-cakupan .cards{display:grid}'
        )
        // Satu aturan dibagi dua halaman, bukan disalin dua kali.
        ->and(substr_count($result['scoped'], 'display:grid'))->toBe(1);
});
