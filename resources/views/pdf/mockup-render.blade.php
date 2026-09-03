<!doctype html>
<html>
<head>
<meta charset="utf-8">
@php
    $primary = $design['primary_color'] ?? '#1F2937';
    $secondary = $design['secondary_color'] ?? '#F8FAFC';
    $accent = $design['accent_color'] ?? '#2563EB';
    $fontHeading = $design['font_heading'] ?? 'Georgia';
    $fontBody = $design['font_body'] ?? 'Arial';
    $pageNames = collect($pages ?? [])->pluck('name')->filter()->values();
    $globalCta = $mockup['global_cta'] ?? ($hero['cta'] ?? 'Get Started');
@endphp
<style>
*{box-sizing:border-box}
body{margin:0;background:{{ $secondary }};color:#211d1a;font-family:'{{ $fontBody }}',Arial,sans-serif}
.site{width:1440px;background:#fff}
.nav{min-height:94px;padding:0 74px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid rgba(0,0,0,.06)}
.brand{display:flex;align-items:center;gap:12px;font-size:22px;font-weight:700;letter-spacing:.3px;font-family:'{{ $fontHeading }}',Georgia,serif}
.brand img{height:40px;width:auto;display:block}
.links{display:flex;gap:32px;font-size:15px}
.links span{color:#211d1a}
.nav .button{background:{{ $accent }};color:#fff;padding:13px 22px;border-radius:8px;font-weight:700;font-size:14px;white-space:nowrap}
.hero{min-height:480px;padding:80px 74px;background:{{ $primary }};color:#fff;position:relative;overflow:hidden;display:flex;align-items:center;gap:60px}
.hero:after{content:'';position:absolute;right:-120px;top:-160px;width:620px;height:620px;border-radius:50%;background:{{ $accent }};opacity:.22}
.hero-copy{position:relative;z-index:1;flex:1 1 45%;min-width:0}
.hero-copy h1{margin:0 0 20px;font-family:'{{ $fontHeading }}',Georgia,serif;font-size:50px;line-height:1.12}
.hero-copy p{max-width:560px;font-size:18px;line-height:1.6;opacity:.92}
.hero-copy .button{display:inline-block;margin-top:24px;background:#fff;color:{{ $primary }};padding:15px 26px;border-radius:8px;font-weight:700;font-size:15px}
.hero-photo{position:relative;z-index:1;flex:1 1 45%;min-width:0}
.hero-photo img{width:100%;height:380px;object-fit:cover;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.section{padding:60px 74px}
.section.alt{background:#f6f4f0}
.section-head{max-width:680px;margin:0 auto 34px;text-align:center}
.section-head h2{margin:0 0 10px;font-family:'{{ $fontHeading }}',Georgia,serif;font-size:30px;color:{{ $primary }}}
.section-head p{margin:0;color:#5c554f;font-size:16px;line-height:1.6}
.icon-row{display:flex;justify-content:center;gap:32px;flex-wrap:wrap}
.icon-item{flex:1 1 260px;max-width:280px;text-align:center}
.icon-badge{width:52px;height:52px;border-radius:50%;background:{{ $accent }};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;margin:0 auto 14px;font-family:'{{ $fontHeading }}',Georgia,serif}
.icon-item h3{margin:0 0 6px;font-size:16px}
.icon-item p{margin:0;color:#6b6459;font-size:13px;line-height:1.5}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
.grid.cols-3{grid-template-columns:repeat(3,1fr)}
.grid.cols-2{grid-template-columns:repeat(2,1fr)}
.card{border:1px solid #eae5dd;border-radius:14px;overflow:hidden;background:#fff}
.card img{width:100%;height:150px;object-fit:cover;display:block}
.card-body{padding:16px}
.card h3{margin:0 0 6px;font-size:15px;color:#211d1a}
.card p{margin:0;color:#6b6459;font-size:13px;line-height:1.5}
.footer{margin-top:0;padding:44px 74px 36px;background:#1c1a17;color:#fff;display:grid;grid-template-columns:2fr 1fr 1fr;gap:36px}
.footer h4{margin:0 0 14px;font-size:14px;text-transform:uppercase;letter-spacing:1px;color:#c9c2b8}
.footer p,.footer li{color:#cfc8bd;line-height:1.7;font-size:14px}
.footer ul{list-style:none;margin:0;padding:0}
.footer-bottom{padding:20px 74px;background:#151310;color:#8f887d;font-size:13px;text-align:center}
</style>
</head>
<body><div class="site">

<header class="nav">
    <div class="brand">
        @if ($logoDataUrl)<img src="{{ $logoDataUrl }}" alt="{{ $project->name }}">@endif
        {{ $project->client?->company_name ?? $project->name }}
    </div>
    <nav class="links">
        @foreach ($pageNames->take(5) as $pageName)
            <span>{{ $pageName }}</span>
        @endforeach
    </nav>
    <span class="button">{{ $globalCta }}</span>
</header>

<section class="hero">
    <div class="hero-copy">
        <h1>{{ $hero['headline'] ?? $project->name }}</h1>
        <p>{{ $hero['description'] ?? '' }}</p>
        <span class="button">{{ $hero['cta'] ?? $globalCta }}</span>
    </div>
    @if ($heroPhoto)
        <div class="hero-photo"><img src="{{ $heroPhoto }}" alt=""></div>
    @endif
</section>

{{-- Compact feature row — icon badge + short text, no AI photo needed
     (mirrors a typical "why choose us" band: short, no scrolling weight). --}}
@if ($iconSection)
    <section class="section">
        <div class="section-head">
            <h2>{{ $iconSection['headline'] ?? $iconSection['name'] ?? '' }}</h2>
            @if (!empty($iconSection['description']))<p>{{ $iconSection['description'] }}</p>@endif
        </div>
        <div class="icon-row">
            @foreach (array_slice($iconSection['items'] ?? [], 0, 3) as $index => $item)
                @php
                    $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? '') : $item;
                    $desc = is_array($item) ? ($item['description'] ?? '') : '';
                @endphp
                <div class="icon-item">
                    <div class="icon-badge">{{ $index + 1 }}</div>
                    <h3>{{ $title }}</h3>
                    @if ($desc)<p>{{ $desc }}</p>@endif
                </div>
            @endforeach
        </div>
    </section>
@endif

{{-- The one section that gets real photos — products/menu/services, the
     part of the page a client actually wants to see illustrated. --}}
@if ($photoSection)
    @php $photoItems = array_slice($photoSection['items'] ?? [], 0, 4); @endphp
    <section class="section alt">
        <div class="section-head">
            <h2>{{ $photoSection['headline'] ?? $photoSection['name'] ?? '' }}</h2>
            @if (!empty($photoSection['description']))<p>{{ $photoSection['description'] }}</p>@endif
        </div>
        <div class="grid {{ count($photoItems) === 3 ? 'cols-3' : (count($photoItems) === 2 ? 'cols-2' : '') }}">
            @foreach ($photoItems as $itemIndex => $item)
                @php
                    $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? '') : $item;
                    $desc = is_array($item) ? ($item['description'] ?? '') : '';
                    $photo = $itemPhotos[$itemIndex] ?? null;
                @endphp
                <article class="card">
                    @if ($photo)<img src="{{ $photo }}" alt="">@endif
                    <div class="card-body">
                        <h3>{{ $title }}</h3>
                        @if ($desc)<p>{{ $desc }}</p>@endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif

<footer class="footer">
    <div>
        <h4>{{ $project->client?->company_name ?? $project->name }}</h4>
        <p>{{ $mockup['website_concept'] ?? '' }}</p>
    </div>
    <div>
        <h4>Navigasi</h4>
        <ul>
            @foreach ($pageNames->take(5) as $pageName)
                <li>{{ $pageName }}</li>
            @endforeach
        </ul>
    </div>
    <div>
        <h4>Kontak</h4>
        <p>Hubungi kami untuk informasi dan pemesanan.</p>
    </div>
</footer>
<div class="footer-bottom">&copy; {{ date('Y') }} {{ $project->client?->company_name ?? $project->name }}. All rights reserved.</div>

</div></body></html>
