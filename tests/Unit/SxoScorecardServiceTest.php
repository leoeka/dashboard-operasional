<?php

use App\Services\SxoScorecardService;

it('returns an empty scorecard when there is no landing-page data', function () {
    $r = (new SxoScorecardService())->build([]);

    expect($r['summary']['pages'])->toBe(0)
        ->and($r['weak_pages'])->toBe([])
        ->and($r['recommendation'])->toContain('baik');
});

it('flags a high-traffic landing page with low engagement', function () {
    $r = (new SxoScorecardService())->build([
        ['landing_page' => '/promo', 'sessions' => 400, 'engagement_rate' => 0.31, 'avg_engagement_time' => 8, 'conversions' => 1],
        ['landing_page' => '/', 'sessions' => 900, 'engagement_rate' => 0.72, 'avg_engagement_time' => 55, 'conversions' => 20],
    ]);

    expect($r['summary']['pages'])->toBe(2)
        ->and($r['summary']['weak_pages'])->toBe(1);

    $weak = $r['weak_pages'][0];
    expect($weak['landing_page'])->toBe('/promo')
        ->and($weak['reason'])->toContain('engagement rate rendah')
        ->and($weak['reason'])->toContain('waktu kunjungan singkat');
});

it('does not flag a weak page that has too little traffic', function () {
    $r = (new SxoScorecardService())->build([
        ['landing_page' => '/obscure', 'sessions' => 5, 'engagement_rate' => 0.1, 'avg_engagement_time' => 2],
    ]);

    expect($r['weak_pages'])->toBe([]);
});

it('computes session-weighted averages', function () {
    $r = (new SxoScorecardService())->build([
        ['landing_page' => '/a', 'sessions' => 100, 'engagement_rate' => 0.4, 'avg_engagement_time' => 10],
        ['landing_page' => '/b', 'sessions' => 300, 'engagement_rate' => 0.8, 'avg_engagement_time' => 50],
    ]);

    // weighted rate = (100*0.4 + 300*0.8) / 400 = 0.7
    expect($r['summary']['avg_engagement_rate'])->toBe(0.7)
        ->and($r['summary']['avg_engagement_time'])->toBe(40.0);
});

it('sorts weak pages by wasted-traffic priority', function () {
    $r = (new SxoScorecardService())->build([
        ['landing_page' => '/low-traffic-bad', 'sessions' => 30, 'engagement_rate' => 0.2, 'avg_engagement_time' => 5],
        ['landing_page' => '/high-traffic-bad', 'sessions' => 800, 'engagement_rate' => 0.2, 'avg_engagement_time' => 5],
    ]);

    expect(array_column($r['weak_pages'], 'landing_page'))->toBe(['/high-traffic-bad', '/low-traffic-bad']);
});
