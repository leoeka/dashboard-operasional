@php
    /** @var \App\Models\Project $project */
    $statusLabel = ['good' => 'Baik', 'needs_improvement' => 'Perlu perbaikan', 'poor' => 'Kurang', 'unknown' => 'Belum dianalisis'];
    $statusColor = ['good' => '#16794a', 'needs_improvement' => '#b0740e', 'poor' => '#b3261e', 'unknown' => '#6b7280'];
    $layerBlurb = [
        'seo' => 'Ditemukan di hasil Google — teknis & on-page.',
        'sxo' => 'Pengunjung dari pencarian puas & menyelesaikan tujuannya.',
        'geo' => 'Siap dibaca & dikutip mesin jawaban AI.',
    ];
    $fmtPct = fn ($v) => $v === null ? '-' : round($v * 100, 1) . '%';
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Laporan SEO / SXO / GEO - {{ $project->client_name }}</title>
<style>
    @page { margin: 32px 34px 40px; }
    body { font-family: Helvetica, Arial, sans-serif; color: #2f3945; font-size: 11px; line-height: 1.6; margin: 0; }
    h1 { font-size: 19px; color: #1f2937; margin: 0 0 2px; }
    h2 { font-size: 13px; color: #1f2937; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #d7dbe2; }
    h3 { font-size: 11px; color: #374151; margin: 14px 0 4px; text-transform: uppercase; letter-spacing: .04em; }
    p { margin: 0 0 6px; }
    .muted { color: #6b7280; }
    .head-meta { color: #6b7280; font-size: 10px; margin-top: 2px; }
    table { border-collapse: collapse; width: 100%; }
    .scorecards td { width: 33.33%; vertical-align: top; padding: 0 6px; }
    .scorecards td:first-child { padding-left: 0; }
    .scorecards td:last-child { padding-right: 0; }
    .card { border: 1px solid #d7dbe2; border-radius: 6px; padding: 12px 14px; }
    .card .layer { font-size: 12px; font-weight: bold; letter-spacing: .06em; }
    .card .num { font-size: 30px; font-weight: bold; line-height: 1.1; }
    .card .pill { display: inline-block; font-size: 9px; font-weight: bold; color: #fff; border-radius: 3px; padding: 2px 6px; }
    .card .blurb { color: #6b7280; font-size: 9.5px; margin-top: 6px; }
    .data-table th { text-align: left; background: #f1f3f6; color: #55606f; font-size: 9.5px; text-transform: uppercase; letter-spacing: .04em; padding: 5px 8px; border-bottom: 1px solid #cfd4dc; }
    .data-table td { padding: 5px 8px; border-bottom: 1px solid #e6e9ee; vertical-align: top; }
    .data-table td.num, .data-table th.num { text-align: right; }
    .actions td { padding: 6px 8px; border-bottom: 1px solid #e6e9ee; vertical-align: top; }
    .actions .rank { width: 22px; color: #9aa3af; font-weight: bold; }
    .tag { display: inline-block; font-size: 8.5px; font-weight: bold; color: #fff; background: #55606f; border-radius: 3px; padding: 1px 5px; }
    .note { color: #8a5a12; }
    .empty { color: #6b7280; font-style: italic; }
    .foot { margin-top: 24px; padding-top: 8px; border-top: 1px solid #d7dbe2; color: #9aa3af; font-size: 9px; }
</style>
</head>
<body>

<h1>Laporan SEO / SXO / GEO</h1>
<div class="head-meta">
    {{ $project->client_name }} &middot; Proyek {{ $project->code }}
    @php $url = $project->seo_requirements['target_url'] ?? $project->backlink_requirements['target_url'] ?? null; @endphp
    @if ($url) &middot; {{ $url }} @endif
    &middot; Dibuat {{ $generatedAt }}
</div>

@if ($analysedNothing)
    <p class="empty" style="margin-top:18px">Belum ada data analisis. Jalankan minimal PageSpeed, cek akses AI crawler, dan structured data dari halaman Workspace, lalu unduh laporan ini lagi.</p>
@endif

<table class="scorecards" style="margin-top:16px">
    <tr>
        @foreach (['seo' => 'SEO', 'sxo' => 'SXO', 'geo' => 'GEO'] as $key => $name)
            @php $s = $scores[$key]; $st = $s['status']; @endphp
            <td>
                <div class="card">
                    <div class="layer" style="color:{{ $statusColor[$st] }}">{{ $name }}</div>
                    <div class="num" style="color:{{ $statusColor[$st] }}">{{ $s['value'] ?? '--' }}<span style="font-size:12px;color:#9aa3af">{{ $s['value'] === null ? '' : '/100' }}</span></div>
                    <span class="pill" style="background:{{ $statusColor[$st] }}">{{ $statusLabel[$st] }}</span>
                    <div class="blurb">{{ $layerBlurb[$key] }}</div>
                </div>
            </td>
        @endforeach
    </tr>
</table>

<h2>Prioritas perbaikan</h2>
@if (empty($actions))
    <p class="empty">Tidak ada temuan yang bisa ditindaklanjuti dari data yang tersedia.</p>
@else
    <table class="actions">
        @foreach ($actions as $i => $a)
            <tr>
                <td class="rank">{{ $i + 1 }}</td>
                <td style="width:46px"><span class="tag">{{ $a['layer'] }}</span></td>
                <td>{{ $a['text'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- ---------- SEO ---------- --}}
<h2>SEO</h2>
@include('pdf.partials.seo-sxo-geo-parts', ['parts' => $scores['seo']['parts']])

@if ($onpage)
    <h3>Audit on-page ({{ $onpage['summary']['pages_checked'] }} halaman &middot; skor rata-rata {{ $onpage['summary']['avg_score'] ?? '-' }})</h3>
    <p class="muted">{{ $onpage['recommendation'] }}</p>
    <table class="data-table" style="margin-top:6px">
        <tr><th>Halaman</th><th class="num">Skor</th><th>Isu</th></tr>
        @foreach ($onpage['pages'] as $p)
            <tr>
                <td>{{ $p['url'] }}</td>
                <td class="num">{{ $p['score'] ?? '-' }}</td>
                <td class="note">{{ $p['fetch_status'] === 'ok' ? (implode(' · ', $p['issues']) ?: '—') : 'gagal diambil' }}</td>
            </tr>
        @endforeach
    </table>
@else
    <p class="empty">Audit on-page belum dijalankan.</p>
@endif

@if ($technical)
    <h3>SEO teknis situs (skor {{ $technical['score'] ?? '-' }})</h3>
    <p class="muted">{{ $technical['recommendation'] }}</p>
    <table class="data-table" style="margin-top:6px">
        <tr><th>Cek</th><th>Status</th><th>Detail</th></tr>
        @foreach ($technical['checks'] as $c)
            <tr>
                <td>{{ $c['label'] }}</td>
                <td style="color:{{ $c['status'] === 'fail' ? '#b3261e' : ($c['status'] === 'warn' ? '#b0740e' : '#16794a') }}">
                    {{ ['pass' => 'OK', 'warn' => 'perhatikan', 'fail' => 'MASALAH'][$c['status']] ?? $c['status'] }}
                </td>
                <td>{{ $c['detail'] }}</td>
            </tr>
        @endforeach
    </table>
@else
    <p class="empty">Cek SEO teknis belum dijalankan.</p>
@endif

@if ($structured)
    <h3>Structured data ({{ $structured['summary']['pages_checked'] }} halaman)</h3>
    <p class="muted">{{ $structured['recommendation'] }}</p>
    <table class="data-table" style="margin-top:6px">
        <tr><th>Halaman</th><th>Jenis</th><th>Schema ditemukan</th><th>Kurang</th></tr>
        @foreach ($structured['pages'] as $p)
            <tr>
                <td>{{ $p['url'] }}</td>
                <td>{{ $p['page_type_guess'] }}</td>
                <td>{{ $p['fetch_status'] === 'ok' ? (implode(', ', $p['types_found']) ?: '—') : 'gagal diambil' }}</td>
                <td class="note">{{ implode(', ', $p['missing']) ?: '—' }}</td>
            </tr>
        @endforeach
    </table>
@else
    <p class="empty">Structured data belum dianalisis.</p>
@endif

{{-- ---------- SXO ---------- --}}
<h2>SXO</h2>
@include('pdf.partials.seo-sxo-geo-parts', ['parts' => $scores['sxo']['parts']])

@if ($ctr)
    <h3>CTR di bawah normal ({{ $ctr['summary']['opportunity_count'] }} query &middot; ~{{ $ctr['summary']['estimated_missed_clicks'] }} klik/bln hilang)</h3>
    <p class="muted">{{ $ctr['recommendation'] }}</p>
    @if (!empty($ctr['opportunities']))
        <table class="data-table" style="margin-top:6px">
            <tr><th>Query</th><th class="num">Posisi</th><th class="num">Impresi</th><th class="num">CTR</th><th class="num">Seharusnya</th><th class="num">Klik hilang</th></tr>
            @foreach (array_slice($ctr['opportunities'], 0, 10) as $o)
                <tr>
                    <td>{{ $o['keyword'] }}</td>
                    <td class="num">{{ $o['position'] }}</td>
                    <td class="num">{{ number_format($o['impressions']) }}</td>
                    <td class="num">{{ $fmtPct($o['actual_ctr']) }}</td>
                    <td class="num">{{ $fmtPct($o['expected_ctr']) }}</td>
                    <td class="num">{{ $o['missed_clicks'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@else
    <p class="empty">CTR gap belum dianalisis (butuh data Search Console).</p>
@endif

@if ($scorecard)
    <h3>Engagement landing page organik (rata-rata {{ $fmtPct($scorecard['summary']['avg_engagement_rate']) }}, {{ $scorecard['summary']['avg_engagement_time'] }} dtk)</h3>
    <p class="muted">{{ $scorecard['recommendation'] }}</p>
    @if (!empty($scorecard['weak_pages']))
        <table class="data-table" style="margin-top:6px">
            <tr><th>Halaman lemah</th><th class="num">Sesi</th><th class="num">Engagement</th><th class="num">Waktu</th><th>Catatan</th></tr>
            @foreach ($scorecard['weak_pages'] as $p)
                <tr>
                    <td>{{ $p['landing_page'] }}</td>
                    <td class="num">{{ number_format($p['sessions']) }}</td>
                    <td class="num">{{ $fmtPct($p['engagement_rate']) }}</td>
                    <td class="num">{{ $p['avg_engagement_time'] }} dtk</td>
                    <td class="note">{{ $p['reason'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@else
    <p class="empty">Engagement scorecard belum tersedia (butuh analisis GA4).</p>
@endif

{{-- ---------- GEO ---------- --}}
<h2>GEO</h2>
@include('pdf.partials.seo-sxo-geo-parts', ['parts' => $scores['geo']['parts']])

@if ($crawler)
    <h3>Akses crawler AI ({{ $crawler['robots_status'] === 'found' ? 'robots.txt ditemukan' : 'tidak ada robots.txt' }}, llms.txt: {{ $crawler['llms_txt'] }})</h3>
    <p class="muted">{{ $crawler['recommendation'] }}</p>
    <table class="data-table" style="margin-top:6px">
        <tr><th>Crawler</th><th>Vendor</th><th>Fungsi</th><th>Akses</th></tr>
        @foreach ($crawler['crawlers'] as $c)
            <tr>
                <td>{{ $c['bot'] }}</td>
                <td>{{ $c['vendor'] }}</td>
                <td>{{ $c['purpose'] === 'answers' ? 'menjawab' : 'melatih' }}</td>
                <td style="color:{{ $c['access'] === 'blocked' ? '#b3261e' : ($c['access'] === 'partial' ? '#b0740e' : '#16794a') }}">
                    {{ ['allowed' => 'diizinkan', 'blocked' => 'DIBLOKIR', 'partial' => 'sebagian', 'unknown' => '-'][$c['access']] ?? $c['access'] }}
                </td>
            </tr>
        @endforeach
    </table>
@else
    <p class="empty">Akses AI crawler belum dicek.</p>
@endif

@if ($extract)
    <h3>Konten mudah dikutip AI (skor rata-rata {{ $extract['summary']['avg_score'] ?? '-' }})</h3>
    <p class="muted">{{ $extract['recommendation'] }}</p>
    <table class="data-table" style="margin-top:6px">
        <tr><th>Halaman</th><th class="num">Skor</th><th>Kriteria terpenuhi</th><th>Saran utama</th></tr>
        @foreach ($extract['pages'] as $p)
            @php
                $critLabels = ['direct_answers' => 'jawaban ringkas', 'tldr' => 'TL;DR', 'stats_sourced' => 'statistik bersumber', 'faq' => 'FAQ', 'quotable' => 'kalimat mandiri'];
                $met = $p['status'] === 'ok' ? array_keys(array_filter($p['criteria'])) : [];
            @endphp
            <tr>
                <td>{{ $p['url'] }}</td>
                <td class="num">{{ $p['score'] ?? '-' }}</td>
                <td>{{ $p['status'] === 'ok' ? (implode(', ', array_map(fn ($k) => $critLabels[$k] ?? $k, $met)) ?: '—') : 'gagal dinilai' }}</td>
                <td class="note">{{ $p['suggestions'][0] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>
@else
    <p class="empty">Penilaian extractability konten (AI) belum dijalankan.</p>
@endif

<div class="foot">
    Skor tiap lapis = rata-rata tertimbang dari sinyal yang datanya tersedia (bobot di config/seo_scoring.php).
    Sinyal yang belum dianalisis tidak dihitung. Laporan ini membaca data tersimpan — jalankan tiap analisis dari halaman Workspace untuk melengkapinya.
</div>

</body>
</html>
