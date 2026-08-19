<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Proposal SEO - {{ $project->client_name }}</title>
    <style>
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

        .report-title {
            font-size: 20px;
            font-weight: bold;
            color: #2c4a6b;
            letter-spacing: 1px;
        }

        .cover-url {
            font-size: 13px;
            color: #2E86C1;
            margin-top: 10px;
        }

        .agency-block {
            position: absolute;
            bottom: 55px;
            left: 40px;
            color: #fff;
        }

        .agency-name {
            font-size: 15px;
            font-weight: bold;
        }

        .agency-date {
            font-size: 11px;
            margin-top: 4px;
        }

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
            color: #1B4F72;
            text-transform: uppercase;
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

        ul.simple-list {
            margin: 6px 0 14px 0;
            padding-left: 18px;
            list-style-type: none;
        }

        ul.simple-list li {
            font-size: 10.5px;
            color: #475569;
            margin-bottom: 3px;
        }

        ul.simple-list li .bullet-arrow {
            font-family: 'DejaVu Sans', sans-serif;
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

        /* Blok screenshot+gauge per kompetitor DIBUNGKUS pakai kelas
           ini supaya dompdf tidak memotongnya di tengah kalau sisa
           halaman tidak cukup — kalau tidak muat, dompdf lempar
           SELURUH blok ke halaman berikutnya sekaligus. */
        .pagespeed-block {
            page-break-inside: avoid;
            margin-bottom: 16px;
        }

        .competitor-item {
            page-break-inside: avoid;
            margin-bottom: 18px;
        }

        .price-offer-title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            color: #1a1a1a;
            margin: 10px 0 4px 0;
        }

        .price-offer-subtitle {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            color: #475569;
            margin-bottom: 18px;
        }

        .price-offer-package-name {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 18px;
        }

        .price-box {
            background-color: #4A90C2;
            color: #ffffff;
            text-align: center;
            padding: 22px 20px;
            margin: 0 auto 22px auto;
            width: 320px;
            border-radius: 2px;
        }

        .price-main {
            font-size: 24px;
            font-weight: bold;
        }

        .price-or {
            font-size: 11px;
            margin: 4px 0;
        }

        .price-alt {
            font-size: 20px;
            font-weight: bold;
        }

        ul.price-list {
            margin: 0 auto;
            padding-left: 20px;
            width: 90%;
            list-style-type: none;
        }

        ul.price-list li {
            font-size: 10.5px;
            color: #334155;
            font-weight: bold;
            margin-bottom: 6px;
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

    @php
        $targetUrl = $seo['target_url'] ?? $backlink['target_url'] ?? '-';
        $pageCounter = 2;
        $totalPages = 5;

        // Index kompetitor by host supaya gampang dicocokkan ke data
        // screenshot+scores dari $competitorPagespeed (yang cuma berisi
        // maks 2 kompetitor teratas, bukan semua $discoveredCompetitors).
        $competitorPagespeedByHost = collect($competitorPagespeed ?? [])
            ->keyBy(fn($c) => parse_url($c['url'], PHP_URL_HOST));
    @endphp

    <!-- HALAMAN 1 -->
    <div class="cover">
        <div class="title-block">
            <div class="report-title">
                PROPOSAL SEO WEB<br>
                OPTIMIZATION {{ strtoupper($project->client_name) }}
            </div>
            <div class="cover-url">{{ $targetUrl }}</div>
        </div>

        <div class="agency-block">
            <div class="agency-name">PT. Exito Bali Digital</div>
            <div class="agency-date">{{ $generatedAt }}</div>
        </div>
    </div>

    <!-- HALAMAN 2 -->
    <div class="content-page">
        <div class="header-band">
            <span class="date">Halaman {{ $pageCounter++ }} / {{ $totalPages }} &middot; {{ $generatedAt }}</span>
        </div>

        <h2 class="section-title"><span class="roman">I.</span> THE ROLE OF SEO FOR WEBSITES</h2>

        <p class="body-text">
            WHAT IS ITS ROLE? ITS ROLE IS TO KNOW MANY THINGS ABOUT WEBSITE
            COMPETITORS. STARTING FROM THE TACTICS THEY USE, THE KEYWORDS THEY TARGET,
            WHAT ARE THE WEAKNESSES AND STRENGTHS OF THEIR SEARCH ENGINE OPTIMIZATION,
            TO WHAT TACTICS THEY SUCCESSFULLY USE.
        </p>

        <p class="body-text">
            THIS PLAN IS REALLY VERY USEFUL FOR WEBSITES IN COMPETING WITH OTHER
            WEBSITES. BECAUSE WE DON'T NEED TO DO EXPERIMENTS FIRST WHICH HAVE AN
            UNSUCCESSFUL IMPACT. WHILE WITH THIS TECHNIQUE, YOU CAN SAVE TIME BY
            DIRECTLY RESEARCHING WHAT OUR COMPETITORS ARE ACTUALLY DOING.
        </p>

        <h2 class="section-title"><span class="roman">II.</span> SEO FUNCTION ON WEBSITE</h2>

        <p class="body-text">
            SEO ITSELF WORKS TO OPTIMIZE THE KEYWORDS USED TO BE EASILY INDEXED
            BY SEARCH ENGINES SO THAT THE PAGE GETS THE TOP RANKING AND IS DISPLAYED ON
            THE MAIN PAGE OF THE SEARCH ENGINE ACCORDING TO THE RELEVANCE OF THE
            KEYWORDS ENTERED BY THE USER IN THE SEARCH ENGINE.
        </p>

        <h2 class="section-title"><span class="roman">III.</span> ANALIYS SEO PAGE</h2>

        @if (!empty($seo['manual_screenshots']['own_pagespeed']))
            <div style="text-align:center; margin-bottom:12px;">
                <img src="{{ Storage::disk('public')->path($seo['manual_screenshots']['own_pagespeed']) }}"
                    style="max-width: 100%; border: 1px solid #e2e8f0;">
            </div>
        @else
            <p class="empty-note" style="margin-bottom:8px;">Screenshot laporan PageSpeed belum diunggah untuk proposal ini.
            </p>
        @endif

        @if ($pagespeed)
            <table class="data">
                <thead>
                    <tr>
                        <th>DEVICE</th>
                        <th>PERFORMANCE</th>
                        <th>ACCESSIBILITY</th>
                        <th>BEST PRACTICES</th>
                        <th>SEO</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (['mobile' => 'MOBILE', 'desktop' => 'DESKTOP'] as $key => $label)
                        @php $d = $pagespeed[$key] ?? null; @endphp
                        @if ($d)
                            <tr>
                                <td>{{ $label }}</td>
                                <td>{{ $d['scores']['performance'] ?? '-' }}</td>
                                <td>{{ $d['scores']['accessibility'] ?? '-' }}</td>
                                <td>{{ $d['scores']['best_practices'] ?? '-' }}</td>
                                <td>{{ $d['scores']['seo'] ?? '-' }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty-note">Belum ada data analisis SEO page.</p>
        @endif
    </div>

    <!-- HALAMAN 3 -->
    <div class="content-page">
        <div class="header-band">
            <span class="date">Halaman {{ $pageCounter++ }} / {{ $totalPages }} &middot; {{ $generatedAt }}</span>
        </div>

        <h2 class="section-title">
            <span class="roman">IV.</span> ANALISYS COMPETITOR {{ strtoupper($project->client_name) }}
        </h2>

        @php $competitorsToShow = $discoveredCompetitors->take(3); @endphp
        @if ($competitorsToShow->isNotEmpty())
            @foreach ($competitorsToShow as $index => $url)
                @php
                    $host = parse_url($url, PHP_URL_HOST);
                    $compData = $competitorPagespeedByHost->get($host);
                @endphp
                <div class="competitor-item">
                    <p class="body-text" style="margin-bottom: {{ $compData ? '10px' : '12px' }};">
                        <strong>{{ chr(65 + $index) }}. Recommendation Competitor {{ $index + 1 }}:</strong>
                        <br>
                        ({{ $url }})
                    </p>

                    @if ($compData)
                        <div class="pagespeed-block">
                            @php $compManualScreenshot = $seo['manual_screenshots']['competitor_pagespeed'][$host] ?? null; @endphp
                            @if (!empty($compManualScreenshot))
                                <div style="text-align:center;">
                                    <img src="{{ Storage::disk('public')->path($compManualScreenshot) }}"
                                        style="max-width: 100%; border: 1px solid #e2e8f0;">
                                </div>
                            @else
                                <p class="empty-note">Screenshot laporan PageSpeed kompetitor ini belum diunggah.</p>
                            @endif
                        </div>
                    @else
                        <p class="empty-note">Data performa untuk kompetitor ini belum dianalisis (dibatasi 2
                            kompetitor teratas per analisis).</p>
                    @endif
                </div>
            @endforeach
        @else
            <p class="empty-note">Belum ada kompetitor yang ditemukan untuk project ini.</p>
        @endif
    </div>

    <!-- HALAMAN 4 -->
    <div class="content-page">
        <div class="header-band">
            <span class="date">Halaman {{ $pageCounter++ }} / {{ $totalPages }} &middot; {{ $generatedAt }}</span>
        </div>

        <h2 class="section-title"><span class="roman">V.</span> RELATED SEARCHES</h2>

        @if (!empty($aiRecommendations['related_keywords']))
            <ul class="simple-list">
                @foreach (array_slice($aiRecommendations['related_keywords'], 0, 15) as $keyword)
                    <li><span class="bullet-arrow">&#10146;</span> {{ $keyword }}</li>
                @endforeach
            </ul>
        @else
            <p class="empty-note">Belum ada related searches.</p>
        @endif

        <h2 class="section-title"><span class="roman">VI.</span> TOP KEYWORD</h2>

        @if (!empty($aiRecommendations['main_keywords']))
            <ul class="simple-list">
                @foreach (array_slice($aiRecommendations['main_keywords'], 0, 11) as $keyword)
                    <li><span class="bullet-arrow">&#10146;</span> {{ $keyword['keyword'] ?? '-' }}</li>
                @endforeach
            </ul>
        @else
            <p class="empty-note">Belum ada top keyword.</p>
        @endif
    </div>

    <!-- HALAMAN 5 -->
    <div class="content-page">
        <div class="header-band">
            <span class="date">Halaman {{ $pageCounter++ }} / {{ $totalPages }} &middot; {{ $generatedAt }}</span>
        </div>

        <div class="price-offer-title">PRICE OFFER</div>
        <div class="price-offer-subtitle">Here is Our Best Price Offer</div>
        <div class="price-offer-package-name">SEO Package</div>

        <div class="price-box">
            <div class="price-main">IDR 4.000.000/MONTH</div>
            <div class="price-or">or</div>
            <div class="price-alt">$250/MONTH</div>
        </div>

        <ul class="price-list">
            <li><span class="bullet-arrow">&#10146;</span> Minimum 3 words/long tail keywords</li>
            <li><span class="bullet-arrow">&#10146;</span> 10 keywords (including Main Keywords and Derivative Keywords)
            </li>
            <li><span class="bullet-arrow">&#10146;</span> Keyword recommendations based on Keyword Research</li>
            <li><span class="bullet-arrow">&#10146;</span> Website Audit/Fixing SEO Technical</li>
            <li><span class="bullet-arrow">&#10146;</span> On Page SEO</li>
            <li><span class="bullet-arrow">&#10146;</span> Page Structure Optimization</li>
            <li><span class="bullet-arrow">&#10146;</span> UX Optimization</li>
            <li><span class="bullet-arrow">&#10146;</span> Internal Linking Building</li>
            <li><span class="bullet-arrow">&#10146;</span> SEO Articles (10 updated articles/month, 1000 words maximum)
            </li>
            <li><span class="bullet-arrow">&#10146;</span> Link Building Booster (Backlink)</li>
            <li><span class="bullet-arrow">&#10146;</span> Monthly Report</li>
            <li><span class="bullet-arrow">&#10146;</span> 100% White Hat SEO Technique</li>
            <li><span class="bullet-arrow">&#10146;</span> Secret SEO Method Indexer Technique</li>
            <li><span class="bullet-arrow">&#10146;</span> Content Creation &amp; Optimization (1000 Words)</li>
            <li><span class="bullet-arrow">&#10146;</span> Internal linking</li>
            <li><span class="bullet-arrow">&#10146;</span> Title, Description, Heading optimization</li>
            <li><span class="bullet-arrow">&#10146;</span> Plugin Optimization</li>
            <li><span class="bullet-arrow">&#10146;</span> Keyword Density</li>
            <li><span class="bullet-arrow">&#10146;</span> Multiple keywords optimization</li>
            <li><span class="bullet-arrow">&#10146;</span> Index in Google</li>
            <li><span class="bullet-arrow">&#10146;</span> Plugin SEO optimization (Wordpress only)</li>
            <li><span class="bullet-arrow">&#10146;</span> Security Optimization (Wordpress only)</li>
            <li><span class="bullet-arrow">&#10146;</span> Caching Optimization (Wordpress only)</li>
        </ul>

        <p class="footer-note">
            Proposal ini dibuat otomatis oleh sistem — data bersumber dari Google PageSpeed Insights, Google
            Places API (analisis kompetitor), dan analisis AI (Gemini). Angka volume pencarian keyword adalah
            estimasi AI kecuali ditandai sumber data Google Ads.
        </p>
    </div>

</body>

</html>