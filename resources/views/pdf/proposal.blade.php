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
            font-size: 16px;
            line-height: 1.7;
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
            font-size: 28px;
            font-weight: bold;
            color: #2c4a6b;
            letter-spacing: 1px;
        }

        .cover .title-block .subtitle {
            font-size: 22px;
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
            background-image: url('{{ public_path('images/header-bg.jpg') }}');
            background-size: 100% 100%;
            background-position: center top;
            background-repeat: no-repeat;
            padding: 20px 45px 40px;
            page-break-after: always;
            position: relative;
            min-height: 1056px;
            box-sizing: border-box;
        }

        .header-band {
            background: transparent;
            height: 85px;
            margin: -20px -45px 25px -45px;
            padding: 18px 45px 0;
        }

        .header-band .logo {
            width: 155px;
        }

        .header-band .date {
            float: right;
            color: #fff;
            font-size: 15px;
            margin-top: 4px;
        }

        p {
            margin: 0 0 12px;
            text-align: justify;
        }

        .signature-logo {
            width: 190px;
            margin: 14px 0 6px;
        }

        .agency-signoff {
            font-weight: bold;
            font-size: 16px;
        }

        h2.section-title {
            background: #f1f5f9;
            padding: 12px 16px;
            font-size: 19px;
            text-align: center;
            margin: 0 0 20px;
        }

        .mockup-img {
            display: block;
            margin: 0 auto;
            max-width: 420px;
            width: 100%;
        }

        .mockup-section-img {
            display: block;
            margin: 0 auto 14px;
            max-width: 480px;
            width: 100%;
            page-break-inside: avoid;
            border: 1px solid #e2e8f0;
        }

        .mockup-option-img { display: block; margin: 0 auto; max-width: 100%; max-height: 640px; width: auto; height: auto; border: 1px solid #d6c7b8; }
        .mockup-option-label { text-align: center; margin: 10px 0 0; color: #6f4328; font-size: 13px; font-weight: bold; }

        .website-preview {
            border: 1px solid #cbd5e1;
            background: #fff;
            overflow: hidden;
        }

        .website-preview-nav {
            padding: 9px 14px;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
        }

        .website-preview-logo {
            max-height: 20px;
            max-width: 100px;
            vertical-align: middle;
            margin-right: 7px;
            background: #fff;
            padding: 2px;
        }

        .website-preview-hero {
            padding: 25px 20px;
            color: #fff;
        }

        .website-preview-hero h3 {
            margin: 0 0 8px;
            font-size: 20px;
            line-height: 1.2;
        }

        .website-preview-hero p { color: #fff; font-size: 11px; }

        .website-preview-cta {
            display: inline-block;
            padding: 7px 11px;
            background: #fff;
            font-size: 9px;
            font-weight: bold;
        }

        .website-preview-section {
            padding: 12px 16px;
            border-top: 1px solid #e2e8f0;
        }

        .website-preview-section h4 { margin: 0 0 4px; font-size: 12px; }
        .website-preview-section p { margin: 0; font-size: 10px; line-height: 1.45; }

        .website-preview-cards { width: 100%; border-collapse: separate; border-spacing: 6px; margin-top: 6px; }
        .website-preview-card { width: 33.33%; padding: 8px; vertical-align: top; background: #f8fafc; border: 1px solid #e2e8f0; }
        .website-preview-card strong { display: block; font-size: 9px; margin-bottom: 3px; }
        .website-preview-card span { font-size: 8px; color: #64748b; line-height: 1.3; }

        .website-preview-visual { border: 1px solid #d6c7b8; background: #fffdf8; }
        .visual-nav { padding: 11px 18px; color: #fff; font-size: 9px; font-weight: bold; }
        .visual-nav-links { float: right; font-size: 8px; font-weight: normal; }
        .visual-hero { padding: 30px 24px 28px; color: #fff; }
        .visual-hero h3 { margin: 0 0 8px; font-size: 22px; line-height: 1.15; }
        .visual-hero p { margin: 0; max-width: 450px; color: #fff; font-size: 10px; line-height: 1.5; }
        .visual-button { display: inline-block; margin-top: 14px; padding: 7px 12px; background: #fff; font-size: 9px; font-weight: bold; }
        .visual-content { padding: 16px 18px; }
        .visual-heading { margin: 0 0 4px; color: #263746; font-size: 14px; }
        .visual-copy { margin: 0; color: #64748b; font-size: 9px; line-height: 1.45; }
        .visual-grid { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 7px -6px 0; }
        .visual-card { width: 33.33%; padding: 10px; vertical-align: top; background: #fff; border: 1px solid #eadfce; }
        .visual-card strong { display: block; color: #263746; font-size: 9px; margin-bottom: 4px; }
        .visual-card span { color: #64748b; font-size: 8px; line-height: 1.35; }
        .visual-card-price { display: block; margin-top: 6px; color: #a65d32; font-size: 9px; font-weight: bold; }
        .visual-newsletter { margin: 0 18px 16px; padding: 15px 18px; background: #f1e5d6; }
        .visual-newsletter strong { color: #263746; font-size: 11px; }
        .visual-newsletter span { display: block; margin-top: 3px; color: #64748b; font-size: 8px; }
        .visual-footer { padding: 13px 18px; background: #263746; color: #fff; font-size: 8px; }

        .blueprint-page {
            margin-top: 12px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            page-break-inside: avoid;
        }

        .blueprint-page h3 { margin: 0 0 6px; font-size: 13px; color: #1e3a5f; }
        .blueprint-section { margin: 5px 0; font-size: 10px; }

        table.cost-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            table-layout: fixed;
        }

        table.cost-table th,
        table.cost-table td {
            border: 1px solid #94a3b8;
            padding: 9px 11px;
            text-align: left;
            font-size: 15px;
            line-height: 1.55;
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
            margin-bottom: 4px;
        }

        .terms {
            margin-top: 6px;
        }

        .terms li {
            margin-bottom: 10px;
            font-size: 15px;
        }

        .clients-block {
            text-align: center;
            margin-top: 20px;
        }

        .clients-block img {
            max-width: 100%;
            width: 360px;
            height: auto;
        }

        .clients-block .label {
            font-size: 15px;
            color: #64748b;
            margin-bottom: 10px;
        }

        .portfolio-block {
            text-align: center;
            margin-top: 30px;
        }

        .portfolio-block .label {
            font-size: 15px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .portfolio-block a {
            color: #111827;
            font-size: 18px;
            font-weight: bold;
            text-decoration: none;
        }

        .thank-you {
            text-align: center;
            font-size: 30px;
            font-weight: bold;
            color: #1e293b;
            margin-top: 40px;
        }

        .page-number {
            position: absolute;
            right: 72px;
            bottom: 28px;
            color: #fff;
            font-size: 15px;
        }
    </style>
</head>

<body>

    {{-- ===== PAGE 1: COVER ===== --}}
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

    {{-- ===== PAGE 2: GREETING ===== --}}
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
        <span class="page-number">1</span>
    </div>

    {{-- ===== PAGE(S): DESIGN MOCK UP =====
         Each candidate gets its OWN full page rather than being squeezed
         3-across into one narrow table row — the tall full-page mockup
         PNG (see AiServices::generateMockupImage) was getting scaled down
         to a 33%-width column and cut off at the page boundary, which
         looked like a broken/cropped image even though the source PNG
         itself was complete. One full-width image per page shows the
         whole design clearly. --}}
    @php
        $design = $mockup['design'] ?? [];
        $home = collect($mockup['pages'] ?? [])->first(fn ($page) => strtolower($page['name'] ?? '') === 'home');
        $homeSections = $home['sections'] ?? [];
        $hero = collect($homeSections)->first(fn ($section) => strtolower($section['name'] ?? '') === 'hero') ?? ($homeSections[0] ?? []);
        $mockupCandidates = $mockupCandidates ?? [$mockup];
        $mockupPageCount = count($mockupCandidates) > 1 ? count($mockupCandidates) : 1;
    @endphp

    @if (count($mockupCandidates) > 1)
        @foreach ($mockupCandidates as $candidate)
            <div class="content-page">
                <div class="header-band">
                    <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
                    <span class="date">{{ now()->format('n/j/Y') }}</span>
                </div>

                <h2 class="section-title">Design Mock Up — Option {{ $candidate['candidate_number'] ?? $loop->iteration }}</h2>

                <p style="font-size:11px; text-align:center; color:#64748b;">
                    {{ $candidate['candidate_label'] ?? ($candidate['website_concept'] ?? 'Website mockup blueprint generated from the client analysis.') }}
                </p>

                @if (!empty($candidate['screenshot_path']) && file_exists(storage_path('app/public/' . $candidate['screenshot_path'])))
                    <img src="{{ storage_path('app/public/' . $candidate['screenshot_path']) }}" class="mockup-option-img" alt="Mockup option {{ $candidate['candidate_number'] ?? $loop->iteration }} for {{ $project->name }}">
                @endif

                <p style="margin-top:12px; font-size:10px; text-align:center; color:#64748b;">
                    Style: {{ $candidate['design']['style'] ?? '-' }} &nbsp;|&nbsp; Fonts: {{ $candidate['design']['font_heading'] ?? '-' }} / {{ $candidate['design']['font_body'] ?? '-' }}
                </p>

                <span class="page-number">{{ 1 + $loop->iteration }}</span>
            </div>
        @endforeach
    @else
        <div class="content-page">
            <div class="header-band">
                <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
                <span class="date">{{ now()->format('n/j/Y') }}</span>
            </div>

            <h2 class="section-title">Design Mock Up</h2>

            <p style="font-size:11px; text-align:center; color:#64748b;">
                {{ $mockup['website_concept'] ?? 'Website mockup blueprint generated from the client analysis.' }}
            </p>

            @if (!empty($mockup['screenshot_path']) && file_exists(storage_path('app/public/' . $mockup['screenshot_path'])))
                <img src="{{ storage_path('app/public/' . $mockup['screenshot_path']) }}" class="mockup-section-img" style="max-width:100%;" alt="Website mockup {{ $project->name }}">
            @else

            <div class="website-preview-visual">
                <div class="visual-nav" style="background:{{ $design['primary_color'] ?? '#6f4328' }};">
                    @if (!empty($mockup['client_logo_path']) && file_exists($mockup['client_logo_path']))
                        <img src="{{ $mockup['client_logo_path'] }}" class="website-preview-logo" alt="{{ $project->client_name }} logo">
                    @else
                        {{ strtoupper($project->name) }}
                    @endif
                    <span class="visual-nav-links">HOME &nbsp;&nbsp; ABOUT &nbsp;&nbsp; SERVICES &nbsp;&nbsp; CONTACT</span>
                </div>
                <div class="visual-hero" style="background:{{ $design['primary_color'] ?? '#6f4328' }};">
                    <h3>{{ $hero['headline'] ?? $project->name }}</h3>
                    <p>{{ $hero['description'] ?? '' }}</p>
                    @if (!empty($hero['cta'] ?? $mockup['global_cta'] ?? null))
                        <span class="visual-button" style="color:{{ $design['primary_color'] ?? '#6f4328' }};">{{ $hero['cta'] ?? $mockup['global_cta'] }}</span>
                    @endif
                </div>
                @foreach (array_slice($homeSections, 1, 4) as $section)
                    @php $sectionType = strtolower((string) ($section['type'] ?? $section['name'] ?? '')); @endphp
                    <div class="visual-content">
                        <h4 class="visual-heading">{{ $section['headline'] ?? $section['name'] ?? 'Website Section' }}</h4>
                        <p class="visual-copy">{{ $section['description'] ?? '' }}</p>
                        @if (!empty($section['items']) && is_array($section['items']))
                            <table class="visual-grid"><tr>
                                @foreach (array_slice($section['items'], 0, 3) as $item)
                                    <td class="visual-card">
                                        <strong>{{ is_array($item) ? ($item['title'] ?? $item['name'] ?? 'Item') : $item }}</strong>
                                        @if (is_array($item) && !empty($item['description']))<span>{{ $item['description'] }}</span>@endif
                                        @if (is_array($item) && !empty($item['price']))<span class="visual-card-price">{{ $item['price'] }}</span>@endif
                                    </td>
                                @endforeach
                            </tr></table>
                        @endif
                    </div>
                @endforeach
                @php
                    $newsletter = collect($homeSections)->first(fn ($section) => str_contains(strtolower((string) ($section['name'] ?? $section['type'] ?? '')), 'newsletter'));
                @endphp
                @if ($newsletter)
                    <div class="visual-newsletter"><strong>{{ $newsletter['headline'] ?? $newsletter['name'] }}</strong><span>{{ $newsletter['description'] ?? '' }}</span></div>
                @endif
                <div class="visual-footer">{{ $mockup['footer']['text'] ?? 'Kopi Nusa · Produk · Berlangganan · Kontak' }}</div>
            </div>
            @endif

            <p style="margin-top:12px; font-size:10px; text-align:center; color:#64748b;">
                Style: {{ $design['style'] ?? '-' }} &nbsp;|&nbsp; Fonts: {{ $design['font_heading'] ?? '-' }} / {{ $design['font_body'] ?? '-' }}
            </p>
            @if (!empty($mockup['design_reference_type']) && $mockup['design_reference_type'] !== 'none')
                <p style="font-size:9px; text-align:center; color:#64748b;">
                    Design reference supplied by client: {{ ucfirst($mockup['design_reference_type']) }}
                    @if (!empty($mockup['design_reference_url'])) — {{ $mockup['design_reference_url'] }} @endif
                </p>
            @endif
            <span class="page-number">2</span>
        </div>
    @endif

    {{-- ===== PAGE: WEBSITE BLUEPRINT ===== --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">{{ now()->format('n/j/Y') }}</span>
        </div>

        <h2 class="section-title">Website Blueprint</h2>
        @foreach ($mockup['pages'] ?? [] as $page)
            <div class="blueprint-page">
                <h3>{{ $page['name'] ?? 'Page' }}</h3>
                @foreach ($page['sections'] ?? [] as $section)
                    <div class="blueprint-section">
                        <strong>{{ $section['name'] ?? 'Section' }}</strong>
                        @if (!empty($section['headline'])) — {{ $section['headline'] }} @endif
                        @if (!empty($section['cta'])) <span style="color:#2563eb;"> · CTA: {{ $section['cta'] }}</span> @endif
                    </div>
                @endforeach
            </div>
        @endforeach
        <span class="page-number">{{ 1 + $mockupPageCount + 1 }}</span>
    </div>

    @include('pdf.proposal-pages')

    @if (false)
    {{-- ===== PAGE: COST TABLE + OTHER SERVICES + TERMS + CLIENTS + CLOSING ===== --}}
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
                    <td>{{ $project->value ? 'IDR ' . number_format($project->value, 0, ',', '.') : 'Contact us for a quote' }}
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
                    <td>IDR 3.000.000 (30 Days) + 20% Fee Management</td>
                    <td>
                        <ul class="feature-list">
                            <li>Full campaign setup in Google Ads</li>
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
                            <li>Campaign setup in Meta Ads Manager</li>
                            <li>Audience targeting (location, interests, demographics)</li>
                            <li>Copywriting caption + CTA</li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td>SEO Optimization</td>
                    <td>IDR 4.000.000 / Month</td>
                    <td>
                        <ul class="feature-list">
                            <li>10 keywords (main + derivative)</li>
                            <li>Website audit &amp; technical SEO fixing</li>
                            <li>On Page SEO + internal linking</li>
                            <li>SEO articles (10/month, max 1000 words)</li>
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
    @endif

</body>

</html>
