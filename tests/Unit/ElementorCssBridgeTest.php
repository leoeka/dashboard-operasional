<?php

use App\Services\ElementorCssBridge;

it('moves a button class onto the button anchor without fusing the selector', function () {
    $css = '.button{background:#173F35}.hero .button{color:#fff}';

    $output = (new ElementorCssBridge())->build($css, ['button']);

    expect($output)->toContain('.elementor-edit-area.elementor-edit-area .elementor-button{background:#173F35}')
        ->and($output)->toContain('.elementor-edit-area.elementor-edit-area .hero .elementor-button{color:#fff}')
        ->and($output)->not->toContain('.elementor-widget-button.elementor-button');
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
        ->and($result['scoped'])->toStartWith('.siteflow-page-about h1{font-size:40px}')
        ->and($result['scoped'])->toContain('.siteflow-page-about .now{color:#b00}')
        ->and($result['scoped'])->toContain('@media(max-width:600px){.siteflow-page-about .now{font-size:12px}}');
});
