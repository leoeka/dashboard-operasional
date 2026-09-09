<?php

use App\Services\CtrGapAnalyzer;

it('returns no opportunities for an empty query set', function () {
    $r = (new CtrGapAnalyzer())->analyze([]);

    expect($r['opportunities'])->toBe([])
        ->and($r['queries_analyzed'])->toBe(0)
        ->and($r['summary']['opportunity_count'])->toBe(0);
});

it('flags a well-ranked query whose CTR is far below the expected curve', function () {
    $r = (new CtrGapAnalyzer())->analyze([
        ['keyword' => 'jasa desain interior jakarta', 'impressions' => 4000, 'ctr' => 0.05, 'position' => 1.4],
    ]);

    expect($r['summary']['opportunity_count'])->toBe(1);
    $o = $r['opportunities'][0];
    expect($o['keyword'])->toBe('jasa desain interior jakarta')
        ->and($o['missed_clicks'])->toBeGreaterThan(100)
        ->and($o['expected_ctr'])->toBeGreaterThan($o['actual_ctr']);
});

it('ignores low-impression queries and healthy-CTR queries', function () {
    $r = (new CtrGapAnalyzer())->analyze([
        ['keyword' => 'sepi', 'impressions' => 10, 'ctr' => 0.0, 'position' => 1],       // too few impressions
        ['keyword' => 'sehat', 'impressions' => 2000, 'ctr' => 0.30, 'position' => 1],   // CTR above expected
        ['keyword' => 'halaman-2', 'impressions' => 2000, 'ctr' => 0.0, 'position' => 14], // not page 1
    ]);

    expect($r['queries_analyzed'])->toBe(1) // only "sehat" passes the impression + position filter
        ->and($r['opportunities'])->toBe([]);
});

it('sorts opportunities by estimated missed clicks, descending', function () {
    $r = (new CtrGapAnalyzer())->analyze([
        ['keyword' => 'small', 'impressions' => 300, 'ctr' => 0.01, 'position' => 3],
        ['keyword' => 'big', 'impressions' => 9000, 'ctr' => 0.01, 'position' => 2],
    ]);

    expect(array_column($r['opportunities'], 'keyword'))->toBe(['big', 'small'])
        ->and($r['opportunities'][0]['missed_clicks'])->toBeGreaterThan($r['opportunities'][1]['missed_clicks']);
});

it('interpolates the expected CTR between whole positions', function () {
    $a = new CtrGapAnalyzer();

    expect($a->expectedCtr(1))->toBe(0.281)
        ->and($a->expectedCtr(2))->toBe(0.152)
        ->and($a->expectedCtr(1.5))->toBeGreaterThan(0.152)
        ->and($a->expectedCtr(1.5))->toBeLessThan(0.281)
        ->and($a->expectedCtr(20))->toBe(0.017); // clamped to position 10
});
