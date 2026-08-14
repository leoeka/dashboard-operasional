<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Laporan SEO & Backlink - {{ $project->client_name }}</title>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
        }

        h1 {
            font-size: 20px;
            margin-bottom: 4px;
            color: #1e293b;
        }

        h2 {
            font-size: 14px;
            margin-top: 24px;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid #3b6fe0;
            color: #1e293b;
        }

        .subtitle {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 20px;
        }

        .meta-table {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 3px 0;
            font-size: 11px;
            vertical-align: top;
        }

        .meta-table td.label {
            color: #64748b;
            width: 150px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        table.data th {
            background: #f1f5f9;
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }

        table.data td {
            padding: 6px 8px;
            font-size: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .empty-note {
            font-size: 10px;
            color: #94a3b8;
            font-style: italic;
        }

        .footer-note {
            margin-top: 30px;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }

        .chip {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            font-size: 9px;
            padding: 2px 8px;
            border-radius: 10px;
            margin: 0 4px 4px 0;
        }
    </style>
</head>

<body>

    <h1>Laporan SEO &amp; Backlink</h1>
    <p class="subtitle">Dibuat otomatis pada {{ $generatedAt }}</p>

    <table class="meta-table">
        <tr>
            <td class="label">Client</td>
            <td>{{ $project->client_name }}</td>
        </tr>
        <tr>
            <td class="label">Project</td>
            <td>{{ $project->name }}</td>
        </tr>
        <tr>
            <td class="label">Kode Project</td>
            <td>{{ $project->code }}</td>
        </tr>
        <tr>
            <td class="label">URL Website</td>
            <td>{{ $seo['target_url'] ?? $backlink['target_url'] ?? '-' }}</td>
        </tr>
    </table>

    {{-- ================= KEBUTUHAN SEO & BACKLINK ================= --}}
    <h2>1. Kebutuhan SEO &amp; Backlink</h2>
    <table class="meta-table">
        @if ($project->wants_seo)
            <tr>
                <td class="label">Lokasi Target</td>
                <td>{{ $seo['location'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Platform</td>
                <td>{{ $seo['cms_platform'] ? ucfirst($seo['cms_platform']) : '-' }}</td>
            </tr>
        @endif
        @if ($project->wants_backlink)
            <tr>
                <td class="label">Jumlah Backlink</td>
                <td>{{ $backlink['quantity'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Prioritas</td>
                <td>{{ ($backlink['priority'] ?? '') === 'quality' ? 'Kualitas' : ((($backlink['priority'] ?? '') === 'quantity') ? 'Kuantitas' : '-') }}
                </td>
            </tr>
            <tr>
                <td class="label">Niche</td>
                <td>{{ $backlink['niche'] ?? '-' }}</td>
            </tr>
        @endif
    </table>

    {{-- ================= KEYWORD AI ================= --}}
    <h2>2. Analisis Keyword (AI)</h2>
    @if ($aiRecommendations)
        @if (!empty($aiTopics['core_topics']))
            <p>
                <strong>Topik inti:</strong>
                @foreach ($aiTopics['core_topics'] as $topic)
                    <span class="chip">{{ $topic }}</span>
                @endforeach
            </p>
        @endif

        <table class="data">
            <thead>
                <tr>
                    <th>Keyword</th>
                    <th>Volume/bulan</th>
                    <th>Persaingan</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($aiRecommendations['main_keywords'] ?? []) as $kw)
                    <tr>
                        <td>{{ $kw['keyword'] ?? '-' }}</td>
                        <td>{{ $kw['avg_monthly_searches'] ?? '-' }}</td>
                        <td>{{ $kw['competition'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="empty-note">Belum ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if (!empty($aiRecommendations['summary']))
            <p><strong>Ringkasan strategi:</strong> {{ $aiRecommendations['summary'] }}</p>
        @endif
    @else
        <p class="empty-note">Belum ada data analisis keyword untuk project ini.</p>
    @endif

    {{-- ================= PAGESPEED ================= --}}
    <h2>3. Performa Website (PageSpeed)</h2>
    @if ($pagespeed)
        @foreach (['mobile' => 'Mobile', 'desktop' => 'Desktop'] as $key => $label)
            @php $d = $pagespeed[$key] ?? null; @endphp
            @if ($d)
                <p><strong>{{ $label }}</strong></p>
                <table class="data">
                    <thead>
                        <tr>
                            <th>Performance</th>
                            <th>Accessibility</th>
                            <th>Best Practices</th>
                            <th>SEO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $d['scores']['performance'] ?? '-' }}</td>
                            <td>{{ $d['scores']['accessibility'] ?? '-' }}</td>
                            <td>{{ $d['scores']['best_practices'] ?? '-' }}</td>
                            <td>{{ $d['scores']['seo'] ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif
        @endforeach
    @else
        <p class="empty-note">Belum ada data performa website untuk project ini.</p>
    @endif

    {{-- ================= SEARCH CONSOLE ================= --}}
    <h2>4. Laporan Search Console</h2>
    @if ($searchConsole)
        @php $gscTotals = $searchConsole['totals'] ?? []; @endphp
        <table class="meta-table">
            <tr>
                <td class="label">Total Klik</td>
                <td>{{ $gscTotals['clicks'] ?? 0 }}</td>
            </tr>
            <tr>
                <td class="label">Impressions</td>
                <td>{{ $gscTotals['impressions'] ?? 0 }}</td>
            </tr>
            <tr>
                <td class="label">CTR</td>
                <td>{{ isset($gscTotals['ctr']) ? round($gscTotals['ctr'] * 100, 1) . '%' : '-' }}</td>
            </tr>
            <tr>
                <td class="label">Posisi Rata-rata</td>
                <td>{{ isset($gscTotals['position']) ? round($gscTotals['position'], 1) : '-' }}</td>
            </tr>
        </table>

        @if (!empty($searchConsole['top_queries']))
            <table class="data">
                <thead>
                    <tr>
                        <th>Query</th>
                        <th>Klik</th>
                        <th>Tayang</th>
                        <th>CTR</th>
                        <th>Posisi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($searchConsole['top_queries'] as $q)
                        <tr>
                            <td>{{ $q['keys'][0] ?? '-' }}</td>
                            <td>{{ $q['clicks'] ?? 0 }}</td>
                            <td>{{ $q['impressions'] ?? 0 }}</td>
                            <td>{{ isset($q['ctr']) ? round($q['ctr'] * 100, 1) . '%' : '-' }}</td>
                            <td>{{ isset($q['position']) ? round($q['position'], 1) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p class="subtitle">Periode: {{ $searchConsole['period']['start'] ?? '-' }} s/d
            {{ $searchConsole['period']['end'] ?? '-' }}</p>
    @else
        <p class="empty-note">Belum ada data Search Console untuk project ini.</p>
    @endif

    {{-- ================= GA4 ================= --}}
    <h2>5. Laporan Google Analytics (GA4)</h2>
    @if ($ga4)
        @php
            $ga4Totals = $ga4['totals'] ?? [];
            $nvr = $ga4['new_vs_returning'] ?? [];
        @endphp
        <table class="meta-table">
            <tr>
                <td class="label">Organic Sessions</td>
                <td>{{ $ga4Totals['organic_sessions'] ?? 0 }}</td>
            </tr>
            <tr>
                <td class="label">Total Users</td>
                <td>{{ $ga4Totals['total_users'] ?? 0 }}</td>
            </tr>
            <tr>
                <td class="label">Conversions</td>
                <td>{{ $ga4Totals['conversions'] ?? 0 }}</td>
            </tr>
            <tr>
                <td class="label">New Users</td>
                <td>{{ $nvr['new'] ?? 0 }}</td>
            </tr>
            <tr>
                <td class="label">Returning Users</td>
                <td>{{ $nvr['returning'] ?? 0 }}</td>
            </tr>
        </table>

        @if (!empty($ga4['by_landing_page']))
            <table class="data">
                <thead>
                    <tr>
                        <th>Halaman</th>
                        <th>Sessions</th>
                        <th>Engagement Rate</th>
                        <th>Conversions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ga4['by_landing_page'] as $p)
                        <tr>
                            <td>{{ $p['landing_page'] ?? '-' }}</td>
                            <td>{{ $p['sessions'] ?? 0 }}</td>
                            <td>{{ isset($p['engagement_rate']) ? round($p['engagement_rate'] * 100, 1) . '%' : '-' }}</td>
                            <td>{{ $p['conversions'] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p class="subtitle">Periode: {{ $ga4['period']['start'] ?? '-' }} s/d {{ $ga4['period']['end'] ?? '-' }}</p>
    @else
        <p class="empty-note">Belum ada data Google Analytics untuk project ini.</p>
    @endif

    <p class="footer-note">
        Laporan ini dibuat otomatis oleh sistem — data bersumber dari Google Search Console, Google Analytics,
        Google PageSpeed Insights, dan analisis AI (Gemini). Angka volume pencarian keyword adalah estimasi
        AI kecuali ditandai sumber data Google Ads.
    </p>

</body>

</html>