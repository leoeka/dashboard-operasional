<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $doc['number'] }}</title>
    <style>
        @page { margin: 36px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #222222; }
    </style>
</head>
<body>
    @include('pdf.partials.invoice-document', [
        'doc'     => $doc,
        'logoSrc' => public_path('images/logo_exito_bali_ads.png'),
    ])
</body>
</html>