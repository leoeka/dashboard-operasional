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

        .mockup-option-img {
            display: block;
            margin: 0 auto;
            max-width: 100%;
            max-height: 640px;
            width: auto;
            height: auto;
            border: 1px solid #d6c7b8;
        }

        .mockup-option-label {
            text-align: center;
            margin: 10px 0 0;
            color: #6f4328;
            font-size: 13px;
            font-weight: bold;
        }

        {{-- Kotak centang pilihan klien: digambar sebagai kotak CSS (bukan glyph)
             supaya tercetak rapi baik di kertas maupun di layar, dan jelas
             ke klien mana yang harus dicentang. --}}
        .client-choice {
            margin-top: 18px;
            text-align: center;
        }

        .client-choice .choice-box {
            display: inline-block;
            width: 22px;
            height: 22px;
            border: 2px solid #1e293b;
            vertical-align: middle;
        }

        .client-choice .choice-label {
            margin-left: 8px;
            font-size: 13px;
            font-weight: bold;
            color: #1e293b;
            vertical-align: middle;
        }

        .client-choice .choice-hint {
            margin-top: 4px;
            font-size: 10px;
            color: #64748b;
            text-align: center;
        }

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
            <div class="agency-date">{{ $date }}</div>
        </div>
    </div>

    {{-- ===== PAGE 2: GREETING ===== --}}
    <div class="content-page">
        <div class="header-band">
            <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
            <span class="date">{{ $date }}</span>
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

    {{-- ===== PAGE 3-5: SATU HALAMAN PER KANDIDAT =====
         PNG-nya file persis yang ditinjau tim di gerbang (mockup_candidates.png_path),
         jadi yang klien centang di PDF = yang tim lihat di dashboard. Kotak centang
         di tiap halaman adalah satu-satunya hal baru dibanding templat lama. --}}
    @foreach ($candidates as $candidate)
        <div class="content-page">
            <div class="header-band">
                <img src="{{ public_path('images/logo-transparent.png') }}" class="logo">
                <span class="date">{{ $date }}</span>
            </div>

            <h2 class="section-title">Design Mock Up — Option {{ $candidate->candidate_number }}</h2>

            @if ($candidate->idea_sentence)
                <p style="font-size:11px; text-align:center; color:#64748b;">{{ $candidate->idea_sentence }}</p>
            @endif

            @if ($candidate->png_path && file_exists(storage_path('app/'.$candidate->png_path)))
                <img src="{{ storage_path('app/'.$candidate->png_path) }}" class="mockup-option-img"
                    alt="Mockup option {{ $candidate->candidate_number }} for {{ $project->name }}">
            @endif

            <div class="client-choice">
                <span class="choice-box"></span>
                <span class="choice-label">Pilihan klien / Client's choice</span>
                <div class="choice-hint">
                    Centang satu kotak di halaman opsi yang Anda pilih, lalu kembalikan proposal ini ke tim.
                </div>
            </div>

            <span class="page-number">{{ 1 + $loop->iteration }}</span>
        </div>
    @endforeach

    @include('pdf.proposal-pages')

</body>

</html>
