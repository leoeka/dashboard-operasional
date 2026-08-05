<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 12px;
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
            top: 400px;
            width: 100%;
            text-align: center;
        }

        .cover .title-block .client-name {
            font-size: 20px;
            font-weight: bold;
            color: #2c4a6b;
            letter-spacing: 1px;
        }

        .cover .title-block .subtitle {
            font-size: 16px;
            font-weight: bold;
            color: #2c4a6b;
            letter-spacing: 1px;
            margin-top: 4px;
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

        .content-page {
            padding: 20px 45px 40px;
            page-break-after: always;
            position: relative;
        }

        .header-band {
            background-image: url('{{ public_path('images/header-bg.jpg') }}');
            background-size: cover;
            height: 85px;
            margin: -20px -45px 25px -45px;
            padding: 18px 45px 0;
        }

        .header-band .logo {
            width: 105px;
        }

        .header-band .date {
            float: right;
            color: #fff;
            font-size: 11px;
            margin-top: 4px;
        }

        p {
            margin: 0 0 12px;
            text-align: justify;
        }

        .signature-logo {
            width: 130px;
            margin: 10px 0 4px;
        }

        .agency-signoff {
            font-weight: bold;
            font-size: 12px;
        }

        h2.section-title {
            background: #f1f5f9;
            padding: 10px 16px;
            font-size: 14px;
            text-align: center;
            margin: 0 0 20px;
        }

        .mockup-img {
            display: block;
            margin: 0 auto;
            max-width: 420px;
            width: 100%;
        }

        table.cost-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table.cost-table th,
        table.cost-table td {
            border: 1px solid #94a3b8;
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            vertical-align: top;
        }

        table.cost-table th {
            background: #f1f5f9;
            font-weight: bold;
        }

        .feature-list {
            margin: 0;
            padding-left: 16px;
        }

        .feature-list li {
            margin-bottom: 3px;
        }

        .terms {
            margin-top: 6px;
        }

        .terms li {
            margin-bottom: 8px;
            font-size: 11px;
        }

        .clients-block {
            text-align: center;
            margin-top: 20px;
        }

        .clients-block .label {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 10px;
        }

        .portfolio-block {
            text-align: center;
            margin-top: 30px;
        }

        .portfolio-block .label {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .portfolio-block a {
            color: #2563eb;
            font-size: 11px;
        }

        .thank-you {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            color: #1e293b;
            margin-top: 40px;
        }
    </style>
</head>

<body>

    {{-- ===== HALAMAN 1: COVER ===== --}}
    <div class="cover">
        <div class="title-block">
            <div class="client-name">{{ strtoupper($project->client_name) }}</div>
            <div class="subtitle">WEB PROPOSAL DEVELOPMENT</div>
        </div>
        <div class="agency-block">
            <div class="agency-name">PT. Exito Bali Digital</div>
            <div class="agency-date">{{ now()->format('n/j/Y') }}</div>
        </div>
    </div>

    {{-- ===== HALAMAN 2: GREETING ===== --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">{{ now()->format('n/j/Y') }}</span>
        </div>

        <p>Warmest Greeting from PT. Exito Bali Digital,</p>
        <p>
            EXITO BALI is company providing Web Design &amp; Web Development services, Web programming,
            Web maintenance, Online Promotion, Search Engine Optimization, Digital Marketing Local and
            International coverage.
        </p>
        <p>Through by this letter, we are offering proposal to Website Development Services.</p>
        <p>Thank you for your kind attention.</p>
        <p style="margin-bottom:0;">Best regards,</p>

        <img src="{{ public_path('images/logo-transparent.png') }}" class="signature-logo">
        <p class="agency-signoff">PT. EXITO BALI DIGITAL</p>
    </div>

    {{-- ===== HALAMAN: RINGKASAN STRATEGI (AI ANALYSIS, DIGABUNG) ===== --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">{{ now()->format('n/j/Y') }}</span>
        </div>

        <h2 class="section-title">Business &amp; Website Strategy Summary</h2>

        <div style="font-size: 11px;">
            @if(!empty($analysis['business_analysis']))
                <p><strong>Business Overview:</strong> {{ $analysis['business_analysis'] }}</p>
            @endif

            @if(!empty($analysis['target_market']))
                <p><strong>Target Market:</strong> {{ $analysis['target_market'] }}</p>
            @endif

            @if(!empty($analysis['website_objective']))
                <p><strong>Website Objective:</strong> {{ $analysis['website_objective'] }}</p>
            @endif

            @if(!empty($analysis['sitemap']))
                <p><strong>Sitemap:</strong> {{ $analysis['sitemap'] }}</p>
            @endif

            @if(!empty($analysis['content_strategy']))
                <p><strong>Content &amp; CTA Strategy:</strong> {{ $analysis['content_strategy'] }}</p>
            @endif
        </div>
    </div>

    {{-- ===== HALAMAN: DESIGN MOCK UP ===== --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">{{ now()->format('n/j/Y') }}</span>
        </div>

        <h2 class="section-title">Design Mock Up</h2>

        @if (!empty($mockup['image_path']))
            <img src="{{ storage_path('app/public/' . \Illuminate\Support\Str::after($mockup['image_path'], 'storage/')) }}"
                class="mockup-img">
        @else
            <p style="text-align:center; color:#94a3b8;">Mockup belum tersedia.</p>
        @endif
    </div>

    {{-- ===== HALAMAN: COST TABLE + OTHER SERVICES + SYARAT + KLIEN + PENUTUP ===== --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">{{ now()->format('n/j/Y') }}</span>
        </div>

        <h2 class="section-title">Website Development Services Cost</h2>

        <table class="cost-table">
            <thead>
                <tr>
                    <th style="width:28%">SERVICES</th>
                    <th style="width:20%">COST</th>
                    <th>DESCRIPTION</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Website Development<br>{{ $project->type ?? 'Business Package' }}</td>
                    <td>{{ $project->value ? 'IDR ' . number_format($project->value, 0, ',', '.') : 'Hubungi kami untuk penawaran' }}
                    </td>
                    <td>
                        <ul class="feature-list">
                            <li>Mobile friendly site</li>
                            <li>Premium SEO onpage</li>
                            <li>SSL Certificate (HTTPS security)</li>
                            <li>WhatsApp Chat Integration</li>
                            <li>Free domain (.com/.id) for 1 year</li>
                            <li>Google Search Console &amp; Analytics Setup</li>
                            <li>Email Accounts (business email)</li>
                            <li>Backup System (weekly backup)</li>
                            <li>SEO Friendly</li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="cost-table">
            <thead>
                <tr>
                    <th style="width:28%">OTHER SERVICES</th>
                    <th style="width:20%">COST</th>
                    <th>DESCRIPTION</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Setup Google Ads + Management Ads</td>
                    <td>IDR 3.000.000 (30 Hari) + 20% Fee Management</td>
                    <td>
                        <ul class="feature-list">
                            <li>Full campaign setup di Google Ads</li>
                            <li>Advanced keyword research</li>
                            <li>High-converting ad copy</li>
                            <li>Smart bidding &amp; budget optimization</li>
                            <li>Conversion tracking (Leads, WhatsApp, calls)</li>
                            <li>Monthly report + strategic insights</li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td>Setup Meta Ads (Facebook &amp; Instagram)</td>
                    <td>IDR 750.000 (One Time)</td>
                    <td>
                        <ul class="feature-list">
                            <li>1 Video Ads + 1 Carousel Ads</li>
                            <li>Setup campaign di Meta Ads Manager</li>
                            <li>Targeting audience (lokasi, minat, demografi)</li>
                            <li>Copywriting caption + CTA</li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td>Optimasi SEO</td>
                    <td>IDR 4.000.000 / Bulan</td>
                    <td>
                        <ul class="feature-list">
                            <li>10 keywords (main + derivative)</li>
                            <li>Website audit &amp; technical SEO fixing</li>
                            <li>On Page SEO + internal linking</li>
                            <li>SEO articles (10/bulan, max 1000 kata)</li>
                            <li>Link building &amp; monthly report</li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>

        <ul class="terms">
            <li><strong>Agreement Contract must be done</strong> before we do any work related to website development.
            </li>
            <li>Deposit (Down Payment) <strong>must be paid minimum 50%</strong> of total invoice.</li>
            <li>Due to our full booked schedule, please consult to us about development time.</li>
            <li>Price stated in this quotation might be subject to changes according to client's requests of website's
                system and featured.</li>
        </ul>

        <div class="clients-block">
            <div class="label">Our Client</div>
            <img src="{{ public_path('images/clients/logos-strip.png') }}" style="max-width:100%; width:480px;">
        </div>

        <div class="portfolio-block">
            <div class="label">Our Portfolio :</div>
            <a href="https://www.exitobali.com/our-client-portfolio">https://www.exitobali.com/our-client-portfolio</a>
        </div>

        <div class="thank-you">Thank you for your interest</div>
    </div>

</body>

</html>