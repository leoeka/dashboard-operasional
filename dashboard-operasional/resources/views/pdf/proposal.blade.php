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
            line-height: 1.5;
            margin: 0;
        }

        .cover {
            background-image: url('{{ public_path('images/cover-bg.jpg') }}');
            background-size: cover;
            width: 100%;
            height: 100%;
            padding: 60px 50px;
            page-break-after: always;
        }

        .cover .logo {
            width: 160px;
        }

        .cover .title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            color: #1e3a5f;
            margin-top: 180px;
        }

        .cover .agency {
            position: absolute;
            bottom: 60px;
            left: 50px;
            color: #fff;
            font-weight: bold;
        }

        .cover .date {
            position: absolute;
            bottom: 40px;
            left: 50px;
            color: #fff;
            font-size: 11px;
        }

        .content-page {
            padding: 30px 50px;
            page-break-after: always;
            position: relative;
        }

        .header-band {
            background-image: url('{{ public_path('images/header-bg.jpg') }}');
            background-size: cover;
            height: 90px;
            margin: -30px -50px 20px -50px;
            padding: 20px 50px 0;
        }

        .header-band .logo {
            width: 110px;
        }

        .header-band .date {
            float: right;
            color: #fff;
            font-size: 11px;
        }

        h2 {
            font-size: 15px;
            color: #1e40af;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
            margin-top: 10px;
        }

        h3 {
            font-size: 13px;
            color: #334155;
            margin-bottom: 5px;
        }

        p {
            margin: 7px 0;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .info-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-table td:first-child {
            width: 30%;
            font-weight: bold;
            color: #475569;
        }

        .box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 12px;
            margin: 10px 0;
        }

        .blue-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 12px;
            margin: 10px 0;
        }

        .feature {
            display: inline-block;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 5px 8px;
            margin: 3px;
            font-size: 10px;
        }

        .page-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
            margin-bottom: 7px;
        }

        .mockup-img {
            display: block;
            margin: 12px auto;
            max-width: 450px;
            max-height: 500px;
            border: 1px solid #e2e8f0;
        }

        .mockup-placeholder {
            border: 2px dashed #cbd5e1;
            padding: 50px 20px;
            text-align: center;
            color: #64748b;
            margin-top: 15px;
        }

        .footer-note {
            margin-top: 25px;
            color: #64748b;
            font-size: 10px;
        }
    </style>
</head>

<body>


    {{-- ========================================================= --}}
    {{-- HALAMAN 1 : COVER --}}
    {{-- ========================================================= --}}

    <div class="cover">

        <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">

        <div class="title">

            {{ strtoupper($project->client_name) }}

            <br>

            WEB PROPOSAL DEVELOPMENT

        </div>

        <div class="agency">
            PT. Exito Bali Digital
        </div>

        <div class="date">
            {{ now()->translatedFormat('d/m/Y') }}
        </div>

    </div>



    {{-- ========================================================= --}}
    {{-- HALAMAN 2 : GREETING --}}
    {{-- ========================================================= --}}

    <div class="content-page">

        <div class="header-band">

            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">

            <span class="date">
                {{ now()->translatedFormat('d/m/Y') }}
            </span>

        </div>


        <p>
            Warmest Greeting from PT. Exito Bali Digital,
        </p>

        <p>
            Kami adalah agency yang menyediakan layanan Web Design &
            Web Development, pemrograman web, maintenance, promosi online,
            Search Engine Optimization, dan Digital Marketing skala lokal
            maupun internasional.
        </p>

        <p>
            Melalui dokumen ini, kami mengajukan penawaran untuk layanan
            pengembangan website Anda.
        </p>

        <p>
            Berikut adalah hasil analisis kebutuhan dan konsep awal
            pengembangan website berdasarkan informasi yang diberikan.
        </p>

        <br>

        <p>
            Terima kasih atas perhatiannya.
        </p>

        <p>
            <strong>
                PT. Exito Bali Digital
            </strong>
        </p>

    </div>



    {{-- ========================================================= --}}
    {{-- HALAMAN 3 : PROJECT INFORMATION --}}
    {{-- ========================================================= --}}

    <div class="content-page">

        <div class="header-band">

            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">

            <span class="date">
                {{ now()->translatedFormat('d/m/Y') }}
            </span>

        </div>


        <h2>
            Project Information
        </h2>


        <table class="info-table">

            <tr>
                <td>
                    Client
                </td>

                <td>
                    {{ $project->client_name }}
                </td>
            </tr>

            <tr>
                <td>
                    Project
                </td>

                <td>
                    {{ $project->name }}
                </td>
            </tr>

            <tr>
                <td>
                    Website Type
                </td>

                <td>
                    {{ $project->type ?? 'Company Profile' }}
                </td>
            </tr>

            <tr>
                <td>
                    Project Code
                </td>

                <td>
                    {{ $project->code }}
                </td>
            </tr>

        </table>


        {{-- BUSINESS OVERVIEW --}}

        <h2>
            Business Overview
        </h2>

        <div class="box">

            {{ $analysis['business_overview'] ?? '-' }}

        </div>


        {{-- TARGET MARKET --}}

        <h2>
            Target Market
        </h2>

        <div class="box">

            {{ $analysis['target_market'] ?? '-' }}

        </div>


        {{-- WEBSITE GOAL --}}

        <h2>
            Website Goal
        </h2>

        <div class="box">

            {{ $analysis['website_goal'] ?? '-' }}

        </div>

    </div>



    {{-- ========================================================= --}}
    {{-- HALAMAN 4 : WEBSITE ANALYSIS --}}
    {{-- ========================================================= --}}

    <div class="content-page">

        <div class="header-band">

            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">

            <span class="date">
                {{ now()->translatedFormat('d/m/Y') }}
            </span>

        </div>


        <h2>
            Website Analysis & Recommendation
        </h2>


        <h3>
            Recommended Website Structure
        </h3>


        @if (!empty($analysis['recommended_structure']))

            @foreach ($analysis['recommended_structure'] as $index => $page)
                <div class="page-item">

                    <strong>
                        {{ $index + 1 }}.
                        {{ $page }}
                    </strong>

                </div>
            @endforeach

        @endif



        <h3>
            Recommended Features
        </h3>


        @if (!empty($analysis['recommended_features']))

            @foreach ($analysis['recommended_features'] as $feature)
                <span class="feature">
                    {{ $feature }}
                </span>
            @endforeach

        @endif



        <h3 style="margin-top:20px;">
            SEO Strategy
        </h3>

        <div class="blue-box">

            {{ $analysis['seo_strategy'] ?? '-' }}

        </div>



        <h3>
            Design Direction
        </h3>

        <div class="box">

            {{ $analysis['design_direction'] ?? '-' }}

        </div>



        <h3>
            Recommended CTA
        </h3>

        <div class="blue-box">

            {{ $analysis['recommended_cta'] ?? '-' }}

        </div>

    </div>



    {{-- ========================================================= --}}
    {{-- HALAMAN 5 : DESIGN MOCKUP --}}
    {{-- ========================================================= --}}

    <div class="content-page">

        <div class="header-band">

            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">

            <span class="date">
                {{ now()->translatedFormat('d/m/Y') }}
            </span>

        </div>


        <h2>
            Design Mock Up
        </h2>


        @if ($project->mockupTemplate)

            {{-- Jika nanti template BeTheme sudah tersedia --}}

            @if ($project->mockupTemplate->previewUrl())
                <img src="{{ $project->mockupTemplate->previewUrl() }}" class="mockup-img">
            @endif


            <p style="text-align:center;">

                <strong>
                    {{ $project->mockupTemplate->name }}
                </strong>

            </p>
        @elseif (!empty($mockup))
            {{-- Dummy Mockup Sementara --}}

            <div class="blue-box">

                <strong>
                    {{ $mockup['template_name'] ?? 'AI Website Mockup' }}
                </strong>

                <br>

                Style:
                {{ $mockup['layout_style'] ?? '-' }}

            </div>


            <h3>
                Hero Section
            </h3>

            <div class="box">

                <strong>
                    {{ $mockup['hero']['headline'] ?? '-' }}
                </strong>

                <br><br>

                {{ $mockup['hero']['subheadline'] ?? '-' }}

                <br><br>

                <strong>
                    CTA:
                </strong>

                {{ $mockup['hero']['cta'] ?? '-' }}

            </div>


            <h3>
                Website Layout
            </h3>


            @foreach ($mockup['sections'] ?? [] as $index => $section)
                <div class="page-item">

                    <strong>
                        {{ $index + 1 }}.
                        {{ $section['name'] }}
                    </strong>

                    <br>

                    <span style="color:#64748b;">
                        {{ $section['description'] }}
                    </span>

                </div>
            @endforeach


            <div class="mockup-placeholder">

                <strong>
                    WEBSITE MOCKUP PREVIEW
                </strong>

                <br><br>

                Visual mockup template akan ditampilkan
                setelah template website perusahaan
                terhubung ke sistem.

            </div>
        @else
            <div class="mockup-placeholder">

                Mockup belum tersedia.

            </div>

        @endif

    </div>



    {{-- ========================================================= --}}
    {{-- HALAMAN 6 : CLOSING --}}
    {{-- ========================================================= --}}

    <div class="content-page">

        <div class="header-band">

            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">

            <span class="date">
                {{ now()->translatedFormat('d/m/Y') }}
            </span>

        </div>


        <h2>
            Project Summary
        </h2>


        <div class="box">

            {{ $mockup['content_notes'] ?? 'Konsep website dibuat berdasarkan kebutuhan dan hasil analisis project.' }}

        </div>


        <p style="margin-top:30px;">

            Demikian proposal dan konsep awal pengembangan website
            yang kami ajukan.

        </p>

        <p>

            Detail implementasi website akan disesuaikan kembali
            dengan kebutuhan project pada tahap development.

        </p>


        <br><br>


        <p>
            Hormat kami,
        </p>

        <p>
            <strong>
                PT. Exito Bali Digital
            </strong>
        </p>


        <div class="footer-note">

            Dokumen ini merupakan proposal dan konsep awal
            pengembangan website.

        </div>

    </div>


</body>

</html>
