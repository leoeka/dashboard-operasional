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

        .mockup-img {
            display: block;
            margin: 12px auto;
            max-width: 340px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>

<body>

    {{-- HALAMAN SAMPUL --}}
    <div class="cover">
        <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
        <div class="title">
            {{ strtoupper($project->client_name) }}<br>
            WEB PROPOSAL DEVELOPMENT
        </div>
        <div class="agency">PT. Exito Bali Digital</div>
        <div class="date">{{ now()->translatedFormat('d/m/Y') }}</div>
    </div>

    {{-- HALAMAN ISI: GREETING --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">{{ now()->translatedFormat('d/m/Y') }}</span>
        </div>

        <p>Warmest Greeting from PT. Exito Bali Digital,</p>
        <p>
            Kami adalah agency yang menyediakan layanan Web Design &amp; Web Development, pemrograman web,
            maintenance, promosi online, Search Engine Optimization, dan Digital Marketing skala lokal maupun
            internasional.
        </p>
        <p>Melalui dokumen ini, kami mengajukan penawaran untuk layanan pengembangan website Anda.</p>
        <p>Terima kasih atas perhatiannya.</p>
        <p><strong>PT. Exito Bali Digital</strong></p>
    </div>

    {{-- HALAMAN ISI: MOCKUP --}}
    @if ($project->mockupTemplate)
        <div class="content-page">
            <div class="header-band">
                <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
                <span class="date">{{ now()->translatedFormat('d/m/Y') }}</span>
            </div>

            <h2>Design Mock Up</h2>
            @if ($project->mockupTemplate->previewUrl())
                <img src="{{ $project->mockupTemplate->previewUrl() }}" class="mockup-img">
            @endif
            <p style="text-align:center; color:#64748b;">{{ $project->mockupTemplate->name }}</p>
        </div>
    @endif

</body>

</html>