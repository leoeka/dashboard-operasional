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

    // Real layout variety, not just color — three genuinely different
    // structural arrangements, assigned deterministically per candidate
    // (see generateMockupCandidates()) rather than left to GPT to pick, so
    // every render is guaranteed to actually match one of these three
    // known-good layouts instead of an unpredictable combination.
    // - split-right : copy left / photo right, centered icon badges, even card grid (safe default)
    // - overlay-bg  : full-bleed photo hero with dark overlay, minimal left-aligned feature list, one featured card + smaller grid
    // - split-left  : photo left / copy right (mirrored), soft rounded icon chips, shadowed rounded cards
    $layoutVariant = $design['layout_variant'] ?? 'split-right';

    // Every measurement below comes from the shared spec, so the PNG the
    // client approves, the Gutenberg page body, and the chrome brief Claude
    // builds header/footer/style.css from all read the same numbers.
    $s = \App\Support\MockupDesignSpec::tokens();

    // Resolved by CompositionSpec, the same resolver the Gutenberg page body
    // reads — so a composition cannot mean one arrangement here and another in
    // WordPress. Legacy blueprints (layout_variant only) resolve to exactly the
    // arrangement they always rendered.
    $heroC = $heroComposition ?? \App\Support\CompositionSpec::resolve($hero ?? [], $design, 'hero');
    $ratio = fn (string $r) => str_replace(':', '/', $r);
@endphp
<style>
*{box-sizing:border-box}
body{margin:0;background:{{ $secondary }};color:#211d1a;font-family:'{{ $fontBody }}',Arial,sans-serif}
.site{width:{{ $s['container_width'] }}px;background:#fff}
.nav{min-height:{{ $s['nav_height'] }}px;padding:0 {{ $s['gutter'] }}px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid {{ $s['nav_border'] }}}
.brand{display:flex;align-items:center;gap:12px;font-size:{{ $s['brand_font_size'] }}px;font-weight:700;letter-spacing:.3px;font-family:'{{ $fontHeading }}',Georgia,serif}
.brand img{height:40px;width:auto;display:block}
.links{display:flex;gap:{{ $s['nav_link_gap'] }}px;font-size:{{ $s['nav_link_font_size'] }}px}
.links span{color:#211d1a}
.nav .button{background:{{ $accent }};color:#fff;padding:{{ $s['nav_button_padding_y'] }}px {{ $s['nav_button_padding_x'] }}px;border-radius:{{ $s['button_radius'] }}px;font-weight:700;font-size:{{ $s['nav_button_font_size'] }}px;white-space:nowrap}

/* ---- Hero base — every composition shares this, then its family and its own
       modifier adjust it. Widths/spacing/heading size/ratio arrive inline from
       CompositionSpec so there is exactly one source for them. ---- */
.hero{min-height:{{ $s['hero_min_height'] }}px;padding:{{ $s['hero_padding_y'] }}px {{ $s['gutter'] }}px;background:{{ $primary }};color:#fff;position:relative;overflow:hidden}
.hero:after{content:'';position:absolute;right:-120px;top:-160px;width:620px;height:620px;border-radius:50%;background:{{ $accent }};opacity:.22}
.hero-copy{position:relative;z-index:1;min-width:0}
.hero-copy h1{margin:0 0 20px;font-family:'{{ $fontHeading }}',Georgia,serif;line-height:{{ $s['h1_line_height'] }}}
.hero-copy p{max-width:{{ $s['hero_copy_max_width'] }}px;font-size:{{ $s['hero_copy_font_size'] }}px;line-height:1.6;opacity:.92}
.hero-copy .button{display:inline-block;margin-top:24px;background:#fff;color:{{ $primary }};padding:15px 26px;border-radius:{{ $s['button_radius'] }}px;font-weight:700;font-size:15px}
.hero-photo{position:relative;z-index:1;min-width:0}
.hero-photo img{width:100%;object-fit:cover;box-shadow:0 20px 60px rgba(0,0,0,.25)}

/* family: copy and photo side by side */
.hero--split{display:flex;align-items:center;gap:{{ $s['hero_gap'] }}px}
.hero--split .hero-photo img{height:{{ $s['hero_image_height'] }}px}
.hero--img-left{flex-direction:row-reverse}
.hero--img-left:after{left:-120px;right:auto}

/* family: photo and copy stacked */
.hero--stacked_media{display:flex;flex-direction:column;gap:44px}
.hero--img-above{flex-direction:column-reverse}
.hero--stacked_media .hero-copy,.hero--centered .hero-copy{margin:0 auto;width:100%}
.hero--stacked_media .hero-photo img{height:auto}

/* family: copy centred, no photo */
.hero--centered{display:block}
.hero--centered .hero-copy p{margin-left:auto;margin-right:auto}

/* family: full-bleed photo behind the copy */
.hero--overlay{display:block;padding-left:0;padding-right:0}
.hero--overlay:after{content:none}
.hero--overlay .hero-bg-photo{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;opacity:.55}
.hero--overlay .hero-scrim{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.35),{{ $primary }} 92%);z-index:1}
.hero--overlay .hero-copy{position:relative;z-index:2;margin:0 auto;padding:{{ $s['hero_overlay_padding_top'] }}px {{ $s['gutter'] }}px {{ $s['hero_overlay_padding_bottom'] }}px}
.hero--overlay .hero-copy p{margin:0 auto}
.hero--overlay .hero-copy .button{background:{{ $accent }};color:#fff}

/* composition modifiers — what makes three options read as three designs */
.hero--c-asymmetric_split .hero-photo{margin-top:56px}
.hero--c-overlapping .hero-photo{margin-bottom:-110px;z-index:3}
.hero--c-overlapping{overflow:visible}
.hero--c-contained{margin:32px {{ $s['gutter'] }}px;border-radius:28px;min-height:auto}
.hero--c-fullscreen_image{padding-left:0;padding-right:0}
.hero--c-fullscreen_image .hero-copy{padding:0 {{ $s['gutter'] }}px 72px}
.hero--c-fullscreen_image .hero-photo img{border-radius:0;box-shadow:none}
.hero--c-editorial{background:{{ $secondary }};color:#211d1a}
.hero--c-editorial:after{content:none}
.hero--c-editorial .hero-copy .button{background:{{ $accent }};color:#fff}
.hero--c-editorial .hero-copy p{opacity:1;color:#5c554f}
.hero--c-centered_minimal .hero-photo{display:none}

.section{padding:{{ $s['section_padding_y'] }}px {{ $s['gutter'] }}px}
.section.alt{background:{{ $s['section_band_color'] }}}
.section-head{max-width:{{ $s['section_head_max_width'] }}px;margin:0 auto 34px;text-align:center}
.section-head h2{margin:0 0 10px;font-family:'{{ $fontHeading }}',Georgia,serif;font-size:{{ $s['h2_size'] }}px;color:{{ $primary }}}
.section-head p{margin:0;color:#5c554f;font-size:{{ $s['section_head_font_size'] }}px;line-height:1.6}

/* ---- Icon row: centered badges (default) ---- */
.icon-row{display:flex;justify-content:center;gap:32px;flex-wrap:wrap}
.icon-item{flex:1 1 260px;max-width:280px;text-align:center}
.icon-badge{width:52px;height:52px;border-radius:50%;background:{{ $accent }};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;margin:0 auto 14px;font-family:'{{ $fontHeading }}',Georgia,serif}
.icon-item h3{margin:0 0 6px;font-size:{{ $s['icon_title_size'] }}px}
.icon-item p{margin:0;color:#6b6459;font-size:{{ $s['body_text_size'] }}px;line-height:1.5}

/* ---- Icon row: minimal (overlay-bg pairing) — left-aligned list, no circles ---- */
.icon-row.minimal{display:block;max-width:820px;margin:0 auto}
.icon-row.minimal .icon-item{display:flex;align-items:flex-start;gap:18px;text-align:left;max-width:none;padding:18px 0;border-bottom:1px solid #ece7de}
.icon-row.minimal .icon-item:last-child{border-bottom:none}
.icon-row.minimal .icon-badge{border-radius:6px;width:36px;height:36px;flex:none;margin:0;font-size:14px}

/* ---- Icon row: soft chips (split-left pairing) ---- */
.icon-row.chips .icon-item{background:#fff;border-radius:20px;padding:28px 20px;box-shadow:0 10px 30px rgba(0,0,0,.05)}
.icon-row.chips .icon-badge{border-radius:14px}

.grid{display:grid;gap:{{ $s['grid_gap'] }}px}
.card img{aspect-ratio:var(--card-ratio,4/3);height:auto}
.card--plain{border:none;background:transparent}
.card--plain .card-body{padding:{{ $s['card_padding'] }}px 0 0}
.card--flush{border:none;border-radius:0;background:transparent}
.card--flush .card-body{padding:10px 0 0}
.grid--feature-first .card:first-child{grid-row:span 2}
.grid--feature-first .card:first-child img{aspect-ratio:auto;height:100%;min-height:320px}

/* ---- Card: bordered (default) ---- */
.card{border:{{ $s['card_border_width'] }}px solid {{ $s['card_border_color'] }};border-radius:{{ $s['card_radius'] }}px;overflow:hidden;background:#fff}
.card img{width:100%;height:{{ $s['card_image_height'] }}px;object-fit:cover;display:block}
.card-body{padding:{{ $s['card_padding'] }}px}
.card h3{margin:0 0 6px;font-size:{{ $s['card_title_size'] }}px;color:#211d1a}
.card p{margin:0;color:#6b6459;font-size:{{ $s['body_text_size'] }}px;line-height:1.5}

/* ---- Card: shadow/rounded (split-left pairing) ---- */
.card.shadow{border:none;border-radius:{{ $s['card_radius_soft'] }}px;box-shadow:0 16px 40px rgba(0,0,0,.09)}
.card.shadow img{height:{{ $s['card_image_height_soft'] }}px}

.footer{margin-top:0;padding:{{ $s['footer_padding_top'] }}px {{ $s['gutter'] }}px {{ $s['footer_padding_bottom'] }}px;background:{{ $s['footer_bg'] }};color:#fff;display:grid;grid-template-columns:{{ $s['footer_columns'] }};gap:{{ $s['footer_gap'] }}px}
.footer h4{margin:0 0 14px;font-size:{{ $s['footer_heading_size'] }}px;text-transform:uppercase;letter-spacing:1px;color:{{ $s['footer_heading_color'] }}}
.footer p,.footer li{color:{{ $s['footer_text_color'] }};line-height:1.7;font-size:{{ $s['footer_text_size'] }}px}
.footer ul{list-style:none;margin:0;padding:0}
.footer-bottom{padding:{{ $s['footer_bottom_padding_y'] }}px {{ $s['gutter'] }}px;background:{{ $s['footer_bottom_bg'] }};color:{{ $s['footer_bottom_text_color'] }};font-size:{{ $s['footer_bottom_font_size'] }}px;text-align:center}
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

@php
    $heroInline = "padding-top:{$heroC['spacing_top']}px;padding-bottom:{$heroC['spacing_bottom']}px;text-align:{$heroC['text_align']}";
    $heroCopyInline = $heroC['family'] === 'split'
        ? "flex:0 0 {$heroC['content_width']}%;max-width:{$heroC['content_width']}%"
        : ($heroC['container_width'] ? "max-width:{$heroC['container_width']}px" : '');
    $heroPhotoInline = $heroC['family'] === 'split'
        ? "flex:0 0 {$heroC['image_width']}%;max-width:{$heroC['image_width']}%"
        : '';
    $heroImgInline = "aspect-ratio:{$ratio($heroC['image_ratio'])};border-radius:{$heroC['radius_px']}px";
    $showHeroPhoto = $heroPhoto && in_array($heroC['image_position'], ['left', 'right', 'above', 'below'], true);
@endphp
<section class="hero hero--{{ $heroC['family'] }} hero--c-{{ $heroC['composition'] }} hero--img-{{ $heroC['image_position'] }}" style="{{ $heroInline }}">
    @if ($heroC['image_position'] === 'background' && $heroPhoto)
        <img class="hero-bg-photo" src="{{ $heroPhoto }}" alt="">
        <div class="hero-scrim"></div>
    @endif
    <div class="hero-copy" style="{{ $heroCopyInline }}">
        <h1 style="font-size:{{ $heroC['heading_px'] }}px">{{ $hero['headline'] ?? $project->name }}</h1>
        <p>{{ $hero['description'] ?? '' }}</p>
        <span class="button">{{ $hero['cta'] ?? $globalCta }}</span>
    </div>
    @if ($showHeroPhoto)
        <div class="hero-photo" style="{{ $heroPhotoInline }}"><img src="{{ $heroPhoto }}" style="{{ $heroImgInline }}" alt=""></div>
    @endif
</section>

{{-- Compact feature row — icon badge + short text, no AI photo needed
     (mirrors a typical "why choose us" band: short, no scrolling weight).
     Style follows the hero's layout variant so the whole page reads as
     one deliberate design, not mismatched sections. --}}
@if ($iconSection)
    <section class="section">
        <div class="section-head">
            <h2>{{ $iconSection['headline'] ?? $iconSection['name'] ?? '' }}</h2>
            @if (!empty($iconSection['description']))<p>{{ $iconSection['description'] }}</p>@endif
        </div>
        <div class="icon-row {{ $layoutVariant === 'overlay-bg' ? 'minimal' : ($layoutVariant === 'split-left' ? 'chips' : '') }}" style="text-align:{{ $iconComposition['text_align'] ?? 'center' }}">
            @foreach (array_slice($iconSection['items'] ?? [], 0, 3) as $index => $item)
                @php
                    $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? '') : $item;
                    $desc = is_array($item) ? ($item['description'] ?? '') : '';
                @endphp
                <div class="icon-item">
                    <div class="icon-badge">{{ $index + 1 }}</div>
                    <div>
                        <h3>{{ $title }}</h3>
                        @if ($desc)<p>{{ $desc }}</p>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

{{-- The one section that gets real photos — products/menu/services, the
     part of the page a client actually wants to see illustrated. --}}
@if ($photoSection)
    @php
        $photoItems = array_slice($photoSection['items'] ?? [], 0, 4);
        $pc = $photoComposition ?? \App\Support\CompositionSpec::resolve($photoSection, $design, 'card_grid');
        $columns = max(1, min($pc['columns'], count($photoItems) ?: 1));
        $gridClass = !empty($pc['feature_first']) && count($photoItems) >= 3 ? 'grid--feature-first' : '';
        $gridInline = !empty($pc['feature_first']) && count($photoItems) >= 3
            ? 'grid-template-columns:1.4fr 1fr;grid-auto-rows:1fr'
            : "grid-template-columns:repeat({$columns},1fr)";
        $cardClass = match ($pc['card_treatment']) {
            'shadowed' => 'shadow',
            'plain' => 'card--plain',
            'flush' => 'card--flush',
            default => '',
        };
    @endphp
    <section class="section alt">
        <div class="section-head">
            <h2>{{ $photoSection['headline'] ?? $photoSection['name'] ?? '' }}</h2>
            @if (!empty($photoSection['description']))<p>{{ $photoSection['description'] }}</p>@endif
        </div>
        <div class="grid {{ $gridClass }}" style="{{ $gridInline }}">
            @foreach ($photoItems as $itemIndex => $item)
                @php
                    $title = is_array($item) ? ($item['title'] ?? $item['name'] ?? '') : $item;
                    $desc = is_array($item) ? ($item['description'] ?? '') : '';
                    $photo = $itemPhotos[$itemIndex] ?? null;
                @endphp
                <article class="card {{ $cardClass }}" style="--card-ratio:{{ $ratio($pc['image_ratio']) }};border-radius:{{ $pc['radius_px'] }}px">
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
