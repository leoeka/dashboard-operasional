@php
    $fmtNumber = fn ($value, $decimals = 0) => $value === null ? '—' : number_format((float) $value, $decimals, ',', '.');
    $fmtPercent = fn ($value) => $value === null ? '—' : number_format((float) $value * 100, 2, ',', '.') . '%';
    $gaOverall = $ga4['overall'] ?? [];
    $gaChannels = array_slice($ga4['channels'] ?? [], 0, 8);
    $gscTotals = $gsc['totals'] ?? [];
    $semrushMetrics = $semrush['metrics'] ?? [];
    $trendRows = $ga4['daily_trend'] ?? [];
    $gscTrendRows = $gsc['daily_trend'] ?? [];
    $trendSample = count($trendRows) > 24 ? array_values(array_filter($trendRows, fn ($row, $i) => $i % max(1, (int) ceil(count($trendRows) / 24)) === 0, ARRAY_FILTER_USE_BOTH)) : $trendRows;
    $gscTrendSample = count($gscTrendRows) > 24 ? array_values(array_filter($gscTrendRows, fn ($row, $i) => $i % max(1, (int) ceil(count($gscTrendRows) / 24)) === 0, ARRAY_FILTER_USE_BOTH)) : $gscTrendRows;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Analysis SEO Page — {{ $project->client_name }}</title>
<style>
    @page { margin: 34px 38px 42px; }
    body { font-family: Helvetica, Arial, sans-serif; color: #1f2937; font-size: 10px; line-height: 1.5; }
    h1 { text-align: center; font-size: 21px; color: #17365d; margin: 0 0 5px; }
    h2 { color: #17365d; font-size: 15px; margin: 22px 0 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
    h3 { color: #17365d; font-size: 11px; margin: 16px 0 6px; }
    p { margin: 4px 0 8px; }
    .meta { text-align: center; color: #64748b; margin-bottom: 18px; }
    .subhead { color: #17365d; font-size: 12px; font-weight: bold; margin: 14px 0 7px; }
    table { width: 100%; border-collapse: collapse; margin: 6px 0 12px; }
    th { color: #fff; background: #203864; padding: 6px; text-align: left; }
    td { padding: 5px 6px; border: 1px solid #cbd5e1; vertical-align: top; }
    tbody tr:nth-child(even) { background: #f1f5f9; }
    .numeric { text-align: right; white-space: nowrap; }
    .muted { color: #64748b; }
    .finding { margin: 8px 0 10px; page-break-inside: avoid; }
    .finding strong { color: #17365d; }
    .screenshot { text-align: center; margin: 8px 0 14px; page-break-inside: avoid; }
    .screenshot img { max-width: 100%; max-height: 650px; }
    .empty { color: #64748b; font-style: italic; }
    .section { page-break-inside: auto; }
    .callout { background: #f1f5f9; border-left: 3px solid #203864; padding: 8px 10px; margin: 8px 0; }
    .foot { border-top: 1px solid #cbd5e1; color: #64748b; font-size: 8px; padding-top: 6px; margin-top: 18px; }
</style>
</head>
<body>
<h1>Analysis SEO Page</h1>
<div class="meta">{{ $project->client_name }} · {{ $project->name }} · {{ $targetUrl ?? 'URL belum diisi' }}<br>Periode GA4: {{ $ga4['period']['start'] ?? '—' }} s.d. {{ $ga4['period']['end'] ?? '—' }} · GSC: {{ $gsc['period']['start'] ?? '—' }} s.d. {{ $gsc['period']['end'] ?? '—' }}<br>Dibuat {{ $generatedAt }}</div>

@if (!$hasAnyData)
    <p class="empty">Belum ada data laporan. Jalankan analisis GA4 dan Search Console, lalu unggah screenshot serta isi metrik Semrush.</p>
@endif

<div class="section">
<h2>2. Google Analytics 4 — Traffic &amp; Engagement</h2>
<div class="subhead">2.1 Ringkasan Keseluruhan</div>
@if ($ga4)
<table>
    <thead><tr><th>Metrik</th><th>Nilai</th></tr></thead>
    <tbody>
        <tr><td>Total Sesi</td><td>{{ $fmtNumber($gaOverall['sessions'] ?? null) }}</td></tr>
        <tr><td>Sesi Engagement</td><td>{{ $fmtNumber($gaOverall['engaged_sessions'] ?? null) }}</td></tr>
        <tr><td>Rasio Engagement</td><td>{{ $fmtPercent($gaOverall['engagement_rate'] ?? null) }}</td></tr>
        <tr><td>Rata-rata Waktu Interaksi per Sesi</td><td>{{ $fmtNumber($gaOverall['avg_engagement_time'] ?? null, 1) }} detik</td></tr>
        <tr><td>Total Peristiwa (event)</td><td>{{ $fmtNumber($gaOverall['event_count'] ?? null) }} ({{ $fmtNumber($gaOverall['events_per_session'] ?? null, 2) }}/sesi)</td></tr>
        <tr><td>Peristiwa Utama (Konversi)</td><td>{{ $fmtNumber($gaOverall['conversions'] ?? $ga4['totals']['conversions'] ?? null, 2) }} ({{ $fmtPercent($gaOverall['conversion_rate'] ?? null) }} dari sesi)</td></tr>
    </tbody>
</table>
<div class="subhead">2.2 Kinerja per Saluran (Top 8)</div>
@if ($gaChannels)
<table>
    <thead><tr><th>Saluran</th><th class="numeric">Sesi (%)</th><th class="numeric">Sesi Engagement</th><th class="numeric">Rasio Engagement</th><th class="numeric">Waktu Interaksi</th><th class="numeric">Peristiwa/Sesi</th><th class="numeric">Peristiwa (%)</th></tr></thead>
    <tbody>@foreach ($gaChannels as $channel)
        <tr><td>{{ $channel['channel'] ?? '—' }}</td><td class="numeric">{{ $fmtNumber($channel['sessions'] ?? null) }} ({{ $fmtPercent($channel['session_share'] ?? null) }})</td><td class="numeric">{{ $fmtNumber($channel['engaged_sessions'] ?? null) }}</td><td class="numeric">{{ $fmtPercent($channel['engagement_rate'] ?? null) }}</td><td class="numeric">{{ $fmtNumber($channel['avg_engagement_time'] ?? null, 1) }} dtk</td><td class="numeric">{{ $fmtNumber($channel['events_per_session'] ?? null, 2) }}</td><td class="numeric">{{ $fmtPercent($channel['event_share'] ?? null) }}</td></tr>
    @endforeach</tbody>
</table>
@else <p class="empty">Data per saluran belum tersedia pada hasil GA4 tersimpan.</p> @endif
<h3>2.3 Temuan Utama</h3>
@foreach ($ga4Findings as $finding)<p class="finding">{{ $finding }}</p>@endforeach
<h3>2.4 Tren Sesi Harian (sampel)</h3>
@if ($trendSample)
<table><thead><tr><th>Tanggal</th><th class="numeric">Sesi</th><th class="numeric">Konversi</th></tr></thead><tbody>@foreach ($trendSample as $row)<tr><td>{{ $row['date'] ?? '—' }}</td><td class="numeric">{{ $fmtNumber($row['sessions'] ?? null) }}</td><td class="numeric">{{ $fmtNumber($row['conversions'] ?? null, 2) }}</td></tr>@endforeach</tbody></table>
@else <p class="empty">Data tren harian tidak tersedia.</p> @endif
@else <p class="empty">Data GA4 belum dianalisis.</p> @endif
</div>

<div class="section">
<h2>3. Google Search Console — Performance Overview</h2>
@if ($gsc)
<p>Periode: {{ $gsc['period']['start'] ?? '—' }} sampai {{ $gsc['period']['end'] ?? '—' }}. Data tanggal terbaru dapat belum lengkap.</p>
<table><thead><tr><th>Metrik</th><th>Nilai</th></tr></thead><tbody>
    <tr><td>Total Clicks</td><td>{{ $fmtNumber($gscTotals['clicks'] ?? null) }}</td></tr>
    <tr><td>Total Impressions</td><td>{{ $fmtNumber($gscTotals['impressions'] ?? null) }}</td></tr>
    <tr><td>Average CTR</td><td>{{ $fmtPercent($gscTotals['ctr'] ?? null) }}</td></tr>
    <tr><td>Average Position</td><td>{{ isset($gscTotals['position']) ? $fmtNumber($gscTotals['position'], 1) : '—' }}</td></tr>
</tbody></table>
<h3>3.1 Temuan Utama</h3>
@foreach ($gscFindings as $finding)<p class="finding">{{ $finding }}</p>@endforeach
<div class="subhead">3.2 Top Queries</div>
@if (!empty($gsc['top_queries']))
<table><thead><tr><th>Query</th><th class="numeric">Clicks</th><th class="numeric">Impressions</th><th class="numeric">CTR</th><th class="numeric">Position</th></tr></thead><tbody>@foreach ($gsc['top_queries'] as $query)<tr><td>{{ $query['keys'][0] ?? '—' }}</td><td class="numeric">{{ $fmtNumber($query['clicks'] ?? null) }}</td><td class="numeric">{{ $fmtNumber($query['impressions'] ?? null) }}</td><td class="numeric">{{ $fmtPercent($query['ctr'] ?? null) }}</td><td class="numeric">{{ isset($query['position']) ? $fmtNumber($query['position'], 1) : '—' }}</td></tr>@endforeach</tbody></table>
@else <p class="empty">Query belum tersedia.</p> @endif
<div class="subhead">3.3 Top Pages</div>
@if (!empty($gsc['top_pages']))
<table><thead><tr><th>Halaman</th><th class="numeric">Clicks</th><th class="numeric">Impressions</th><th class="numeric">CTR</th><th class="numeric">Position</th></tr></thead><tbody>@foreach ($gsc['top_pages'] as $page)<tr><td>{{ $page['keys'][0] ?? '—' }}</td><td class="numeric">{{ $fmtNumber($page['clicks'] ?? null) }}</td><td class="numeric">{{ $fmtNumber($page['impressions'] ?? null) }}</td><td class="numeric">{{ $fmtPercent($page['ctr'] ?? null) }}</td><td class="numeric">{{ isset($page['position']) ? $fmtNumber($page['position'], 1) : '—' }}</td></tr>@endforeach</tbody></table>
@else <p class="empty">Halaman belum tersedia.</p> @endif
<h3>3.4 Tren Klik Harian (sampel)</h3>
@if ($gscTrendSample)
<table><thead><tr><th>Tanggal</th><th class="numeric">Clicks</th><th class="numeric">Impressions</th><th class="numeric">CTR</th><th class="numeric">Position</th></tr></thead><tbody>@foreach ($gscTrendSample as $row)<tr><td>{{ $row['keys'][0] ?? '—' }}</td><td class="numeric">{{ $fmtNumber($row['clicks'] ?? null) }}</td><td class="numeric">{{ $fmtNumber($row['impressions'] ?? null) }}</td><td class="numeric">{{ $fmtPercent($row['ctr'] ?? null) }}</td><td class="numeric">{{ isset($row['position']) ? $fmtNumber($row['position'], 1) : '—' }}</td></tr>@endforeach</tbody></table>
@else <p class="empty">Data tren harian tidak tersedia.</p> @endif
@else <p class="empty">Data Google Search Console belum dianalisis.</p> @endif
</div>

<div class="section">
<h2>4. Analisis Semrush</h2>
@if ($semrushScreenshot && \Illuminate\Support\Facades\Storage::disk('public')->exists($semrushScreenshot))
<div class="screenshot"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($semrushScreenshot) }}" alt="Screenshot overview Semrush"><p class="muted">Screenshot Semrush yang diunggah manual · Snapshot {{ $semrush['as_of'] ?? 'tanggal tidak dicatat' }}</p></div>
@else <p class="empty">Screenshot Semrush belum diunggah.</p> @endif
@if ($semrushMetrics)
<table><thead><tr><th>Metrik</th><th>Nilai</th></tr></thead><tbody>
    @foreach ([
        'authority_score' => 'Authority Score', 'ai_visibility_score' => 'AI Visibility Score', 'organic_traffic' => 'Organic Traffic', 'organic_keywords' => 'Organic Keywords',
        'paid_traffic' => 'Paid Traffic', 'paid_keywords' => 'Paid Keywords', 'referring_domains' => 'Referring Domains', 'backlinks' => 'Backlinks', 'traffic_share' => 'Traffic Share (%)',
    ] as $key => $label)
        @if (array_key_exists($key, $semrushMetrics))<tr><td>{{ $label }}</td><td>{{ $fmtNumber($semrushMetrics[$key], $key === 'authority_score' ? 1 : 0) }}{{ $key === 'traffic_share' ? '%' : '' }}</td></tr>@endif
    @endforeach
</tbody></table>
@else <p class="empty">Metrik Semrush belum dimasukkan. Nilai dari screenshot harus dicatat manual di Workspace.</p> @endif
@if (!empty($semrush['ai_platforms']))
<h3>Visibilitas AI Search</h3>
<table><thead><tr><th>Platform</th><th class="numeric">Mentions</th><th class="numeric">Cited Pages</th></tr></thead><tbody>@foreach ($semrush['ai_platforms'] as $row)<tr><td>{{ $row['label'] }}</td><td class="numeric">{{ $fmtNumber($row['first']) }}</td><td class="numeric">{{ $fmtNumber($row['second']) }}</td></tr>@endforeach</tbody></table>
@endif
@if (!empty($semrush['countries']))
<h3>Distribusi Traffic berdasarkan Negara</h3>
<table><thead><tr><th>Negara</th><th class="numeric">Traffic</th><th class="numeric">Keywords</th></tr></thead><tbody>@foreach ($semrush['countries'] as $row)<tr><td>{{ $row['label'] }}</td><td class="numeric">{{ $fmtNumber($row['first']) }}</td><td class="numeric">{{ $fmtNumber($row['second']) }}</td></tr>@endforeach</tbody></table>
@endif
<h3>Temuan Semrush</h3>
@foreach ($semrushFindings as $finding)<p class="finding">{{ $finding }}</p>@endforeach
</div>

<div class="section">
<h2>5. Kesimpulan &amp; Saran</h2>
<div class="callout">{{ $conclusion }}</div>
<ul>@foreach ($recommendations as $recommendation)<li>{{ $recommendation }}</li>@endforeach</ul>
</div>

<div class="foot">GA4 dan Search Console berasal dari properti akun yang terhubung serta periode yang tercantum pada laporan. Metrik Semrush dan analisis screenshot dimasukkan operator secara manual; angka Semrush merupakan estimasi pihak ketiga. Tren menunjukkan korelasi waktu dan tidak menetapkan penyebab.</div>
</body>
</html>
