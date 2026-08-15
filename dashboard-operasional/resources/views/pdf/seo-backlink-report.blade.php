<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan SEO & Backlink - {{ $project->client_name }}</title>
    <style>
        /*
         * FIX (revisi ke-3, TOTAL rombak): dua pendekatan sebelumnya
         * (@page background-image, lalu <img>+position:fixed) sama-sama
         * tidak stabil di dompdf versi ini. Sekarang ikut PERSIS pola
         * yang sudah TERBUKTI jalan di pdf/proposal.blade.php: tiap
         * halaman = <div> terpisah manual, background-image langsung di
         * div biasa (bukan @page), header (logo+tanggal) ditulis ulang
         * di tiap div — bukan elemen yang "menempel otomatis". Nomor
         * halaman juga tidak pakai canvas dompdf lagi (itu yang bikin
         * berantakan) — diganti hitungan manual sederhana di Blade.
         */
        @page {
            margin: 0;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #334155;
            font-size: 11px;
            line-height: 1.6;
            margin: 0;
        }

        /* ===================== COVER ===================== */
        .cover {
            background-image: url('{{ public_path('images/cover-bg.jpg') }}');
            background-size: cover;
            width: 100%;
            height: 100%;
            position: relative;
            page-break-after: always;
        }
        .cover .title-block {
            position: absolute;
            top: 380px;
            width: 100%;
            text-align: center;
            padding: 0 50px;
        }
        .cover .title-block .report-title {
            font-size: 20px;
            font-weight: bold;
            color: #2c4a6b;
            letter-spacing: 1px;
        }
        .cover .title-block .cover-url {
            font-size: 13px;
            color: #2E86C1;
            margin-top: 10px;
        }
        .cover .agency-block {
            position: absolute;
            bottom: 55px;
            left: 40px;
            color: #fff;
        }
        .cover .agency-block .agency-name {
            font-size: 15px;
            font-weight: bold;
        }
        .cover .agency-block .agency-date {
            font-size: 11px;
            margin-top: 4px;
        }

        /* ===================== HALAMAN ISI ===================== */
        .content-page {
            padding: 20px 45px 40px;
            page-break-after: always;
            position: relative;
        }
        .content-page:last-child {
            page-break-after: auto;
        }

        .header-band {
            background-image: url('{{ public_path('images/header-bg.jpg') }}');
            background-size: cover;
            height: 85px;
            margin: -20px -45px 20px -45px;
            padding: 18px 45px 0;
        }
        .header-band .logo {
            width: 105px;
        }
        .header-band .date {
            float: right;
            color: #fff;
            font-size: 10px;
            margin-top: 4px;
        }

        h2.section-title {
            font-size: 14px;
            margin: 0 0 10px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid #2E86C1;
            color: #1B4F72;
        }
        h2.section-title .roman {
            color: #2E86C1;
        }
        p.body-text {
            font-size: 10.5px;
            line-height: 1.5;
            text-align: justify;
            color: #475569;
            margin: 6px 0 12px 0;
        }
        .subtitle {
            font-size: 10px;
            color: #64748b;
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
            background: #eaf2fb;
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
            color: #1B4F72;
            border-bottom: 1px solid #cfe0f0;
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
        .chip {
            display: inline-block;
            background: #eaf2fb;
            color: #1B4F72;
            font-size: 9px;
            padding: 2px 8px;
            border-radius: 10px;
            margin: 0 4px 4px 0;
        }
        ul.simple-list {
            margin: 6px 0 14px 0;
            padding-left: 18px;
        }
        ul.simple-list li {
            font-size: 10.5px;
            color: #475569;
            margin-bottom: 3px;
        }
        .footer-note {
            margin-top: 24px;
            font-size: 8.5px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- ===== HALAMAN 1: COVER ===== --}}
    <div class="cover">
        <div class="title-block">
            <div class="report-title">LAPORAN SEO &amp; BACKLINK<br>{{ strtoupper($project->client_name) }}</div>
            <div class="cover-url">{{ $seo['target_url'] ?? $backlink['target_url'] ?? '-' }}</div>
        </div>
        <div class="agency-block">
            <div class="agency-name">PT. Exito Bali Digital</div>
            <div class="agency-date">{{ $generatedAt }}</div>
        </div>
    </div>

    @php
        // Nomor halaman dihitung manual sederhana di Blade (BUKAN pakai
        // canvas dompdf lagi — itu penyebab berantakannya) — cover
        // dianggap halaman 1, tiap .content-page berikutnya +1.
        $pageCounter = 2;
        $totalPages = 6;
    @endphp

    {{-- ===== HALAMAN 2: I, II, III ===== --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">Halaman {{ $pageCounter++ }} / {{ $totalPages }} &middot; {{ $generatedAt }}</span>
        </div>

        <h2 class="section-title"><span class="roman">I.</span> Peran SEO untuk Website</h2>
        <p class="body-text">
            SEO (Search Engine Optimization) berperan penting dalam membangun visibilitas website di mesin
            pencari. Melalui SEO, kami mempelajari kata kunci yang relevan dengan bisnis Anda, memahami posisi
            website dibanding kompetitor, dan menemukan peluang perbaikan agar website lebih mudah ditemukan
            oleh calon pelanggan yang memang mencari produk atau layanan seperti yang Anda tawarkan.
        </p>
        <p class="body-text">
            Pendekatan ini membantu menghemat waktu dan sumber daya — alih-alih menebak-nebak strategi, kami
            mengandalkan data nyata dari performa pencarian, kecepatan website, dan perilaku pengunjung untuk
            menentukan langkah optimasi yang paling berdampak.
        </p>

        <h2 class="section-title"><span class="roman">II.</span> Fungsi SEO pada Website</h2>
        <p class="body-text">
            SEO bekerja dengan mengoptimalkan kata kunci yang digunakan agar mudah diindeks oleh mesin
            pencari, sehingga halaman website mendapatkan peringkat yang lebih baik dan tampil di halaman
            utama hasil pencarian sesuai relevansi kata kunci yang dicari pengguna. Laporan ini merangkum
            hasil analisis dan progres optimasi yang sudah dan sedang berjalan untuk website Anda.
        </p>

        <h2 class="section-title"><span class="roman">III.</span> Kebutuhan SEO &amp; Backlink</h2>
        <table class="meta-table">
            <tr><td class="label">Client</td><td>{{ $project->client_name }}</td></tr>
            <tr><td class="label">Project</td><td>{{ $project->name }}</td></tr>
            <tr><td class="label">URL Website</td><td>{{ $seo['target_url'] ?? $backlink['target_url'] ?? '-' }}</td></tr>
            @if ($project->wants_seo)
                <tr><td class="label">Lokasi Target</td><td>{{ $seo['location'] ?? '-' }}</td></tr>
                <tr><td class="label">Platform</td><td>{{ $seo['cms_platform'] ? ucfirst($seo['cms_platform']) : '-' }}</td></tr>
            @endif
            @if ($project->wants_backlink)
                <tr><td class="label">Jumlah Backlink</td><td>{{ $backlink['quantity'] ?? '-' }}</td></tr>
                <tr><td class="label">Prioritas</td><td>{{ ($backlink['priority'] ?? '') === 'quality' ? 'Kualitas' : ((($backlink['priority'] ?? '') === 'quantity') ? 'Kuantitas' : '-') }}</td></tr>
                <tr><td class="label">Niche</td><td>{{ $backlink['niche'] ?? '-' }}</td></tr>
            @endif
        </table>
    </div>

    {{-- ===== HALAMAN 3: IV. Analisis Keyword ===== --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">Halaman {{ $pageCounter++ }} / {{ $totalPages }} &middot; {{ $generatedAt }}</span>
        </div>

        <h2 class="section-title"><span class="roman">IV.</span> Analisis Keyword (AI)</h2>
        @if ($aiRecommendations)
            @if (!empty($aiTopics['core_topics']))
                <p class="body-text" style="margin-bottom:4px;"><strong>Topik inti terdeteksi:</strong></p>
                <p style="margin: 0 0 12px 0;">
                    @foreach ($aiTopics['core_topics'] as $topic)
                        <span class="chip">{{ $topic }}</span>
                    @endforeach
                </p>
            @endif

            <table class="data">
                <thead>
                    <tr><th>Keyword</th><th>Volume/bulan</th><th>Persaingan</th></tr>
                </thead>
                <tbody>
                    @forelse (($aiRecommendations['main_keywords'] ?? []) as $kw)
                        <tr>
                            <td>{{ $kw['keyword'] ?? '-' }}</td>
                            <td>{{ $kw['avg_monthly_searches'] ?? '-' }}</td>
                            <td>{{ $kw['competition'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-note">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if (!empty($aiRecommendations['related_keywords']))
                <p class="body-text" style="margin-bottom:4px;"><strong>Related Keywords:</strong></p>
                <ul class="simple-list">
                    @foreach (array_slice($aiRecommendations['related_keywords'], 0, 15) as $rk)
                        <li>{{ $rk }}</li>
                    @endforeach
                </ul>
            @endif

            @if (!empty($aiRecommendations['summary']))
                <p class="body-text"><strong>Ringkasan strategi:</strong> {{ $aiRecommendations['summary'] }}</p>
            @endif
        @else
            <p class="empty-note">Belum ada data analisis keyword untuk project ini.</p>
        @endif
    </div>

    {{-- ===== HALAMAN 4: V, VI ===== --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">Halaman {{ $pageCounter++ }} / {{ $totalPages }} &middot; {{ $generatedAt }}</span>
        </div>

        <h2 class="section-title"><span class="roman">V.</span> Analisis Kompetitor</h2>
        @if ($discoveredCompetitors->isNotEmpty())
            <p class="body-text">
                Berikut kompetitor yang ditemukan secara otomatis oleh AI berdasarkan topik dan lokasi bisnis
                Anda. Data traffic dan backlink kompetitor belum tersedia di laporan ini (memerlukan akses tool
                riset kompetitor berbayar) — daftar berikut menampilkan kompetitor yang teridentifikasi sebagai
                referensi awal.
            </p>
            <ul class="simple-list">
                @foreach ($discoveredCompetitors as $url)
                    <li>{{ $url }}</li>
                @endforeach
            </ul>
        @else
            <p class="empty-note">Belum ada kompetitor yang ditemukan untuk project ini.</p>
        @endif

        <h2 class="section-title"><span class="roman">VI.</span> Performa Website (PageSpeed)</h2>
        @if ($pagespeed)
            @foreach (['mobile' => 'Mobile', 'desktop' => 'Desktop'] as $key => $label)
                @php $d = $pagespeed[$key] ?? null; @endphp
                @if ($d)
                    <p class="body-text" style="margin-bottom:4px;"><strong>{{ $label }}</strong></p>
                    @if (!empty($d['screenshot']))
                        <img src="{{ $d['screenshot'] }}" style="max-width: {{ $key === 'mobile' ? '160px' : '320px' }}; height: auto; border: 1px solid #e2e8f0; margin-bottom: 8px; {{ $key === 'mobile' ? 'float: right; margin-left: 10px;' : '' }}">
                    @endif
                    <table class="data" style="{{ $key === 'mobile' ? 'width: calc(100% - 175px);' : '' }}">
                        <thead>
                            <tr><th>Performance</th><th>Accessibility</th><th>Best Practices</th><th>SEO</th></tr>
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
    </div>

    {{-- ===== HALAMAN 5: VII. Search Console ===== --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">Halaman {{ $pageCounter++ }} / {{ $totalPages }} &middot; {{ $generatedAt }}</span>
        </div>

        <h2 class="section-title"><span class="roman">VII.</span> Laporan Search Console</h2>
        @if ($searchConsole)
            @php $gscTotals = $searchConsole['totals'] ?? []; @endphp
            <table class="meta-table">
                <tr><td class="label">Total Klik</td><td>{{ $gscTotals['clicks'] ?? 0 }}</td></tr>
                <tr><td class="label">Impressions</td><td>{{ $gscTotals['impressions'] ?? 0 }}</td></tr>
                <tr><td class="label">CTR</td><td>{{ isset($gscTotals['ctr']) ? round($gscTotals['ctr'] * 100, 1) . '%' : '-' }}</td></tr>
                <tr><td class="label">Posisi Rata-rata</td><td>{{ isset($gscTotals['position']) ? round($gscTotals['position'], 1) : '-' }}</td></tr>
            </table>

            @if (!empty($searchConsole['top_queries']))
                <table class="data">
                    <thead>
                        <tr><th>Query</th><th>Klik</th><th>Tayang</th><th>CTR</th><th>Posisi</th></tr>
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
            <p class="subtitle">Periode: {{ $searchConsole['period']['start'] ?? '-' }} s/d {{ $searchConsole['period']['end'] ?? '-' }}</p>
        @else
            <p class="empty-note">Belum ada data Search Console untuk project ini.</p>
        @endif
    </div>

    {{-- ===== HALAMAN 6: VIII. GA4 + footer ===== --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">Halaman {{ $pageCounter++ }} / {{ $totalPages }} &middot; {{ $generatedAt }}</span>
        </div>

        <h2 class="section-title"><span class="roman">VIII.</span> Laporan Google Analytics (GA4)</h2>
        @if ($ga4)
            @php
                $ga4Totals = $ga4['totals'] ?? [];
                $nvr = $ga4['new_vs_returning'] ?? [];
            @endphp
            <table class="meta-table">
                <tr><td class="label">Organic Sessions</td><td>{{ $ga4Totals['organic_sessions'] ?? 0 }}</td></tr>
                <tr><td class="label">Total Users</td><td>{{ $ga4Totals['total_users'] ?? 0 }}</td></tr>
                <tr><td class="label">Conversions</td><td>{{ $ga4Totals['conversions'] ?? 0 }}</td></tr>
                <tr><td class="label">New Users</td><td>{{ $nvr['new'] ?? 0 }}</td></tr>
                <tr><td class="label">Returning Users</td><td>{{ $nvr['returning'] ?? 0 }}</td></tr>
            </table>

            @if (!empty($ga4['by_landing_page']))
                <table class="data">
                    <thead>
                        <tr><th>Halaman</th><th>Sessions</th><th>Engagement Rate</th><th>Conversions</th></tr>
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
    </div>

</body>
</html>