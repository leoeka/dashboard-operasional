{{--
    One design blueprint rendered as a website. The approved PNG
    (pdf/mockup-render) and the live demo (projects/mockup-live) both render
    this file from App\Support\MockupSite::build(), so what the client compares
    in the demo is what they approve and what WordPress is built from.
--}}
<!doctype html>
<html lang="{{ $site['lang'] }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $site['page']['name'] }} — {{ $site['brand'] }}</title>
@if ($site['fonts_url'])
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="{{ $site['fonts_url'] }}">
@endif
@include('mockup.styles')
</head>
<body class="{{ $site['fixed'] ? 'is-fixed' : 'is-fluid' }}"><div class="site {{ $site['full_page'] ? 'full-page' : 'legacy-page' }}">

@include('mockup.header')

<main>
@foreach ($site['page']['sections'] as $section)
    @if ($section['renderer'] === 'hero')
        @include('mockup.hero', ['section' => $section])
    @else
        @include('mockup.section', ['section' => $section])
    @endif
@endforeach
</main>

@include('mockup.footer')

</div></body></html>
