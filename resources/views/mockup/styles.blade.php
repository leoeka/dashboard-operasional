{{--
    Shared stylesheet for the approved PNG and the live demo.

    Every measurement comes from MockupDesignSpec (via $site['tokens']) or from
    the section's resolved composition (inline custom properties), never a
    literal — see tests/Unit/MockupDesignSpecTest. The base rules describe the
    fixed desktop canvas the PNG is captured at; the responsive block at the
    bottom only applies to the live demo (.is-fluid), so the PNG is unchanged
    by it and the demo can be checked at tablet and phone widths.
--}}
@php
    $s = $site['tokens'];
    $c = $site['colors'];
    $g = $site['global'];
    $fh = $site['fonts']['heading'];
    $fb = $site['fonts']['body'];
    $btnRadius = match ($g['button_treatment']) {
        'pill' => 999,
        default => $s['button_radius'],
    };
@endphp
<style>
:root{--primary:{{ $c['primary'] }};--secondary:{{ $c['secondary'] }};--accent:{{ $c['accent'] }};--on-primary:{{ $c['on_primary'] }};--on-accent:{{ $c['on_accent'] }};--ink:{{ $c['ink'] }};--muted:#5c554f;--line:{{ $s['card_border_color'] }};--band:{{ $s['section_band_color'] }};--r:{{ $g['radius_px'] }}px;--gap:{{ $s['grid_gap'] }}px;--g:{{ $s['gutter'] }}px}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--secondary);color:var(--ink);font-family:'{{ $fb }}',Arial,sans-serif;line-height:1.55}
img{max-width:100%;display:block}
a{color:inherit}
.site{width:{{ $site['fixed'] ? $s['container_width'] . 'px' : '100%' }};background:#fff;overflow-x:hidden}
h1,h2,h3,h4{font-family:'{{ $fh }}',Georgia,serif;line-height:1.15;margin:0}
.wrap{max-width:var(--cw,none);margin:0 auto}

/* ---- header ---- */
.nav{min-height:{{ $s['nav_height'] }}px;padding:0 {{ $s['gutter'] }}px;display:flex;align-items:center;justify-content:space-between;gap:24px;background:#fff;border-bottom:1px solid {{ $s['nav_border'] }};position:relative;z-index:20}
.brand{display:flex;align-items:center;gap:12px;font-size:{{ $s['brand_font_size'] }}px;font-weight:700;letter-spacing:.3px;font-family:'{{ $fh }}',Georgia,serif;text-decoration:none}
.brand img{height:40px;width:auto;display:block}
.links{display:flex;gap:{{ $s['nav_link_gap'] }}px;font-size:{{ $s['nav_link_font_size'] }}px}
.links a{color:var(--ink);text-decoration:none;opacity:.8}
.links a.is-active{opacity:1;font-weight:700;box-shadow:inset 0 -2px 0 var(--accent)}
.nav .button{background:var(--accent);color:var(--on-accent);padding:{{ $s['nav_button_padding_y'] }}px {{ $s['nav_button_padding_x'] }}px;border-radius:{{ $btnRadius }}px;font-weight:700;font-size:{{ $s['nav_button_font_size'] }}px;white-space:nowrap;text-decoration:none}
.nav-toggle,.nav-burger,.links-cta{display:none}

/* ---- buttons ---- */
.btn{display:inline-block;margin-top:24px;padding:15px 26px;border-radius:{{ $btnRadius }}px;font-weight:700;font-size:15px;text-decoration:none;background:var(--accent);color:var(--on-accent)}
.btn--outline{background:transparent;color:inherit;box-shadow:inset 0 0 0 2px currentColor}
.btn--link{background:none;padding:0;color:inherit;border-bottom:2px solid var(--accent);border-radius:0}

/* ---- hero: every composition shares this base, then its family and its own
       modifier adjust it. Widths, spacing, heading size and ratio arrive inline
       from CompositionSpec so there is exactly one source for them. ---- */
.hero{min-height:{{ $s['hero_min_height'] }}px;padding:{{ $s['hero_padding_y'] }}px {{ $s['gutter'] }}px;background:var(--primary);color:var(--on-primary);position:relative;overflow:hidden}
.hero:after{content:'';position:absolute;right:-120px;top:-160px;width:620px;height:620px;border-radius:50%;background:var(--accent);opacity:.22}
.hero-copy{position:relative;z-index:1;min-width:0}
.hero-copy h1{margin:0 0 20px;line-height:{{ $s['h1_line_height'] }};font-size:clamp(calc(var(--h) * .58),8vw,var(--h))}
.hero-copy p{max-width:{{ $s['hero_copy_max_width'] }}px;font-size:{{ $s['hero_copy_font_size'] }}px;line-height:1.6;opacity:.92;margin:0}
.hero-copy .button{display:inline-block;margin-top:24px;background:#fff;color:var(--primary);padding:15px 26px;border-radius:{{ $btnRadius }}px;font-weight:700;font-size:15px;text-decoration:none}
.hero-photo{position:relative;z-index:1;min-width:0}
.hero-photo img{width:100%;object-fit:cover;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.hero--split{display:flex;align-items:center;gap:{{ $s['hero_gap'] }}px}
.hero--split .hero-photo img{height:{{ $s['hero_image_height'] }}px}
.hero--img-left{flex-direction:row-reverse}
.hero--img-left:after{left:-120px;right:auto}
.hero--stacked_media{display:flex;flex-direction:column;gap:44px}
.hero--img-above{flex-direction:column-reverse}
.hero--stacked_media .hero-copy,.hero--centered .hero-copy{margin:0 auto;width:100%}
.hero--stacked_media .hero-photo img{height:auto}
.hero--centered{display:block}
.hero--centered .hero-copy p{margin-left:auto;margin-right:auto}
.hero--overlay{display:block;padding-left:0;padding-right:0;min-height:{{ $s['hero_overlay_min_height'] }}px}
.hero--overlay:after{content:none}
.hero--overlay .hero-bg-photo{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;opacity:.55}
.hero--overlay .hero-scrim{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.35),var(--primary) 92%);z-index:1}
.hero--overlay .hero-copy{position:relative;z-index:2;margin:0 auto;padding:{{ $s['hero_overlay_padding_top'] }}px {{ $s['gutter'] }}px {{ $s['hero_overlay_padding_bottom'] }}px}
.hero--overlay .hero-copy p{margin:0 auto}
.hero--overlay .hero-copy .button{background:var(--accent);color:var(--on-accent)}
.hero--c-asymmetric_split .hero-photo{margin-top:56px}
.hero--c-overlapping .hero-photo{margin-bottom:-110px;z-index:3}
.hero--c-overlapping{overflow:visible}
.hero--c-contained{margin:32px {{ $s['gutter'] }}px;border-radius:28px;min-height:auto}
.hero--c-fullscreen_image{padding-left:0;padding-right:0}
.hero--c-fullscreen_image .hero-copy{padding:0 {{ $s['gutter'] }}px 72px}
.hero--c-fullscreen_image .hero-photo img{border-radius:0;box-shadow:none}
.hero--c-editorial{background:var(--secondary);color:var(--ink)}
.hero--c-editorial:after{content:none}
.hero--c-editorial .hero-copy .button{background:var(--accent);color:var(--on-accent)}
.hero--c-editorial .hero-copy p{opacity:1;color:var(--muted)}
.hero--c-centered_minimal .hero-photo{display:none}

/* ---- section shell ---- */
.section{padding:{{ $s['section_padding_y'] }}px {{ $s['gutter'] }}px}
.section.alt,.section.bg-band{background:{{ $s['section_band_color'] }}}
.section.bg-primary{background:var(--primary);color:var(--on-primary)}
.section.bg-accent{background:var(--accent);color:var(--on-accent)}
.section-head{max-width:{{ $s['section_head_max_width'] }}px;margin:0 auto 34px;text-align:center}
.section-head h2{margin:0 0 10px;font-size:{{ $s['h2_size'] }}px;color:var(--primary)}
.section-head p{margin:0;color:var(--muted);font-size:{{ $s['section_head_font_size'] }}px;line-height:1.6}
.head{max-width:{{ $s['section_head_max_width'] }}px;margin:0 0 40px}
.head--center{margin-left:auto;margin-right:auto;text-align:center}
.head--right{margin-left:auto;text-align:right}
.head h2{font-size:clamp(min(var(--h),26px),4vw,var(--h));color:var(--primary);margin:0 0 12px}
.bg-primary .head h2,.bg-accent .head h2{color:inherit}
.head p{margin:0;color:var(--muted);font-size:{{ $s['section_head_font_size'] }}px;line-height:1.65}
.bg-primary .head p,.bg-accent .head p{color:inherit;opacity:.85}
.eyebrow{display:inline-block;margin-bottom:12px;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--accent)}
.bg-primary .eyebrow,.bg-accent .eyebrow{color:inherit;opacity:.8}

/* ---- legacy icon row (approved pre-V2 PNGs) ---- */
.icon-row{display:flex;justify-content:center;gap:32px;flex-wrap:wrap}
.icon-item{flex:1 1 260px;max-width:280px;text-align:center}
.icon-badge{width:52px;height:52px;border-radius:50%;background:var(--accent);color:var(--on-accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;margin:0 auto 14px;font-family:'{{ $fh }}',Georgia,serif}
.icon-item h3{margin:0 0 6px;font-size:{{ $s['icon_title_size'] }}px}
.icon-item p{margin:0;color:#6b6459;font-size:{{ $s['body_text_size'] }}px;line-height:1.5}
.icon-row.minimal{display:block;max-width:820px;margin:0 auto}
.icon-row.minimal .icon-item{display:flex;align-items:flex-start;gap:18px;text-align:left;max-width:none;padding:18px 0;border-bottom:1px solid #ece7de}
.icon-row.minimal .icon-item:last-child{border-bottom:none}
.icon-row.minimal .icon-badge{border-radius:6px;width:36px;height:36px;flex:none;margin:0;font-size:14px}
.icon-row.chips .icon-item{background:#fff;border-radius:20px;padding:28px 20px;box-shadow:0 10px 30px rgba(0,0,0,.05)}
.icon-row.chips .icon-badge{border-radius:14px}

/* ---- features (V2): numbered, ruled, no boxes ---- */
.features{display:grid;grid-template-columns:repeat(var(--cols,3),minmax(0,1fr));gap:36px var(--gap)}
.feature{border-top:2px solid var(--primary);padding-top:20px}
.feature-num{font-family:'{{ $fh }}',Georgia,serif;font-size:14px;font-weight:700;color:var(--accent);letter-spacing:1px;margin-bottom:14px}
.feature h3{font-size:20px;margin-bottom:8px}
.feature p{margin:0;color:var(--muted);font-size:15px}
.split-head{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,7fr);gap:64px;align-items:start}
.split-head .head{margin:0;position:sticky;top:24px}
.split-head .features{--cols:2}

/* ---- cards ---- */
.grid{display:grid;gap:{{ $s['grid_gap'] }}px}
.card{border:{{ $s['card_border_width'] }}px solid {{ $s['card_border_color'] }};border-radius:{{ $s['card_radius'] }}px;overflow:hidden;background:#fff}
.card img{width:100%;height:{{ $s['card_image_height'] }}px;object-fit:cover;display:block}
.card img{aspect-ratio:var(--card-ratio,4/3);height:auto}
.card-body{padding:{{ $s['card_padding'] }}px}
.card h3{margin:0 0 6px;font-size:{{ $s['card_title_size'] }}px;color:var(--ink)}
.card p{margin:0;color:#6b6459;font-size:{{ $s['body_text_size'] }}px;line-height:1.5}
.card .price{display:block;margin-top:10px;font-weight:700;color:var(--accent)}
.card.shadow{border:none;border-radius:{{ $s['card_radius_soft'] }}px;box-shadow:0 16px 40px rgba(0,0,0,.09)}
.card.shadow img{height:{{ $s['card_image_height_soft'] }}px}
.card--plain{border:none;background:transparent}
.card--plain .card-body{padding:{{ $s['card_padding'] }}px 0 0}
.card--flush{border:none;border-radius:0;background:transparent}
.card--flush .card-body{padding:10px 0 0}
.grid--feature-first .card:first-child{grid-row:span 2}
.grid--feature-first .card:first-child{display:flex;flex-direction:column}
.grid--feature-first .card:first-child img{aspect-ratio:auto;flex:1 1 auto;height:auto;min-height:320px}
.full-page .card h3{font-size:18px}
.full-page .card p{font-size:14px}

/* ---- editorial text + image ---- */
.editorial{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:72px;align-items:center}
.editorial--img-left .editorial-media{order:-1}
.editorial-media{position:relative}
.editorial-media:before{content:'';position:absolute;inset:32px -24px -24px 32px;background:var(--accent);opacity:.14;border-radius:var(--r)}
.editorial--img-left .editorial-media:before{inset:32px 32px -24px -24px}
.editorial-media img{position:relative;width:100%;object-fit:cover;border-radius:var(--r)}
.editorial .head{margin-bottom:24px}
.editorial-list{list-style:none;margin:28px 0 0;padding:0;counter-reset:ed}
.editorial-list li{counter-increment:ed;display:grid;grid-template-columns:44px 1fr;gap:4px 12px;padding:16px 0;border-top:1px solid var(--line)}
.editorial-list li:before{content:counter(ed,decimal-leading-zero);font-family:'{{ $fh }}',Georgia,serif;font-weight:700;color:var(--accent);grid-row:span 2}
.editorial-list strong{font-size:17px}
.editorial-list span{color:var(--muted);font-size:15px}
.editorial--text{grid-template-columns:minmax(0,5fr) minmax(0,7fr);align-items:start}
.editorial--text .lead{font-size:20px;line-height:1.7;margin:0;color:var(--ink)}

/* ---- alternating media rows ---- */
.alt-rows{display:grid;gap:72px}
.alt-row{display:grid;grid-template-columns:minmax(0,6fr) minmax(0,5fr);gap:64px;align-items:center}
.alt-row:nth-child(even){grid-template-columns:minmax(0,5fr) minmax(0,6fr)}
.alt-row:nth-child(even) .alt-media{order:2}
.alt-media img,.alt-media .panel{width:100%;object-fit:cover;border-radius:var(--r)}
.panel{display:flex;align-items:flex-end;padding:28px;background:var(--primary);color:var(--on-primary);font-family:'{{ $fh }}',Georgia,serif;font-size:72px;font-weight:700;line-height:1}
.alt-row:nth-child(even) .panel{background:var(--accent);color:var(--on-accent)}
.alt-copy .num{font-family:'{{ $fh }}',Georgia,serif;color:var(--accent);font-weight:700;letter-spacing:1px}
.alt-copy h3{font-size:clamp(22px,2.6vw,30px);margin:10px 0 12px}
.alt-copy p{margin:0;color:var(--muted);font-size:16px}

/* ---- stats ---- */
.stats{display:grid;grid-template-columns:minmax(0,4fr) minmax(0,8fr);gap:56px;align-items:center}
.stats--solo{display:block}
.stats-grid{display:grid;grid-template-columns:repeat(var(--cols,4),minmax(0,1fr));gap:32px}
.stat{border-left:1px solid currentColor;padding-left:20px}
.stats-grid .stat{border-color:rgba(127,127,127,.45)}
.stat-value{font-family:'{{ $fh }}',Georgia,serif;font-size:clamp(34px,4.4vw,56px);font-weight:700;line-height:1}
.stat-label{margin-top:10px;font-size:14px;opacity:.85}

/* ---- testimonials: one led quote, the rest supporting ---- */
.quotes{display:grid;grid-template-columns:minmax(0,7fr) minmax(0,5fr);gap:56px;align-items:start}
.quotes--solo{grid-template-columns:1fr;max-width:860px}
.quote-lead{margin:0}
.quote-lead p{font-family:'{{ $fh }}',Georgia,serif;font-size:clamp(22px,2.4vw,32px);line-height:1.35;margin:0}
.quote-lead p:before{content:'\201C';display:block;font-size:84px;line-height:.6;color:var(--accent);margin-bottom:18px}
.quote-side{display:grid;gap:28px}
.quote-small{margin:0;padding-left:20px;border-left:3px solid var(--accent)}
.quote-small p{margin:0 0 12px;font-size:16px}
.cite{display:flex;align-items:center;gap:12px;margin-top:22px;font-style:normal}
.quote-small .cite{margin-top:0}
.avatar{width:44px;height:44px;border-radius:50%;background:var(--primary);color:var(--on-primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex:none}
.cite strong{display:block;font-size:15px}
.cite span{display:block;font-size:13px;color:var(--muted)}

/* ---- gallery: a composed mosaic, not a uniform grid ---- */
.mosaic{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));grid-auto-rows:200px;gap:14px;grid-auto-flow:dense}
.tile{position:relative;overflow:hidden;border-radius:var(--r);background:var(--band);margin:0}
.tile--0{grid-column:span 2;grid-row:span 2}
.tile--3{grid-column:span 2}
.tile img{width:100%;height:100%;object-fit:cover}
.tile figcaption{position:absolute;left:0;right:0;bottom:0;padding:40px 18px 16px;background:linear-gradient(180deg,transparent,rgba(0,0,0,.6));color:#fff;font-weight:700;font-size:15px}
.tile--empty{display:flex;align-items:flex-end;background:var(--primary)}
.tile--empty:nth-child(3n+2){background:var(--accent)}
.tile--empty:nth-child(3n){background:var(--band)}
.tile--empty figcaption{background:none;color:var(--on-primary)}
.tile--empty:nth-child(3n+2) figcaption{color:var(--on-accent)}
.tile--empty:nth-child(3n) figcaption{color:var(--ink)}

/* ---- logos ---- */
.logos{display:flex;flex-wrap:wrap;justify-content:center;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.logo{flex:1 1 160px;padding:26px 18px;text-align:center;font-family:'{{ $fh }}',Georgia,serif;font-weight:700;font-size:18px;letter-spacing:.5px;color:var(--muted)}
.logos--compact .head{margin-bottom:24px}

/* ---- faq ---- */
.faq{display:grid;grid-template-columns:minmax(0,4fr) minmax(0,7fr);gap:72px;align-items:start}
.faq .head{margin:0}
.faq--stack{grid-template-columns:1fr;gap:32px;max-width:860px;margin:0 auto}
.faq-list{border-top:1px solid var(--line)}
.faq-item{border-bottom:1px solid var(--line)}
.faq-item summary{list-style:none;cursor:pointer;display:flex;justify-content:space-between;gap:24px;padding:22px 0;font-weight:700;font-size:17px}
.faq-item summary::-webkit-details-marker{display:none}
.faq-item summary:after{content:'+';color:var(--accent);font-size:22px;line-height:1}
.faq-item[open] summary:after{content:'\2212'}
.faq-item p{margin:0 0 22px;color:var(--muted);font-size:15px;max-width:640px}

/* ---- cta ---- */
.cta{display:flex;align-items:center;justify-content:space-between;gap:48px}
.cta--center{flex-direction:column;text-align:center;justify-content:center;gap:0}
.cta h2{font-size:clamp(min(var(--h),28px),4.2vw,calc(var(--h) + 8px));max-width:760px}
.cta p{margin:14px 0 0;opacity:.9;max-width:620px;font-size:17px}
.cta--center p{margin-left:auto;margin-right:auto}
.cta .btn{background:var(--on-accent);color:var(--accent);margin-top:0;flex:none}
.cta--center .btn{margin-top:28px}

/* ---- team ---- */
.team{display:grid;grid-template-columns:repeat(var(--cols,4),minmax(0,1fr));gap:32px var(--gap)}
.member img,.member .monogram{width:100%;aspect-ratio:var(--ratio,1/1);object-fit:cover;border-radius:var(--r)}
.monogram{display:flex;align-items:center;justify-content:center;background:var(--band);color:var(--primary);font-family:'{{ $fh }}',Georgia,serif;font-size:42px;font-weight:700}
.member h3{font-size:18px;margin:16px 0 4px}
.member .role{font-size:13px;color:var(--accent);font-weight:700;letter-spacing:.5px;text-transform:uppercase}
.member p{margin:8px 0 0;color:var(--muted);font-size:14px}

/* ---- pricing ---- */
.plans{display:grid;grid-template-columns:repeat(var(--cols,3),minmax(0,1fr));gap:var(--gap);align-items:stretch}
.plan{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:32px 28px;display:flex;flex-direction:column;text-align:left}
.plan--featured{background:var(--primary);color:var(--on-primary);border-color:var(--primary);transform:translateY(-12px)}
.plan h3{font-size:20px}
.plan .price{font-family:'{{ $fh }}',Georgia,serif;font-size:clamp(28px,3vw,40px);font-weight:700;margin:14px 0 6px}
.plan p{margin:0;font-size:14px;opacity:.85}
.plan ul{list-style:none;margin:20px 0 0;padding:0;font-size:14px}
.plan li{padding:9px 0;border-top:1px solid rgba(127,127,127,.25)}
.plan .btn{margin-top:auto;text-align:center}
.plan ul + .btn,.plan p + .btn{margin-top:24px}
.plan--featured .btn{background:var(--accent);color:var(--on-accent)}

/* ---- footer ---- */
.footer{margin-top:0;padding:{{ $s['footer_padding_top'] }}px {{ $s['gutter'] }}px {{ $s['footer_padding_bottom'] }}px;background:{{ $s['footer_bg'] }};color:#fff;display:grid;grid-template-columns:{{ $s['footer_columns'] }};gap:{{ $s['footer_gap'] }}px}
.footer h4{margin:0 0 14px;font-size:{{ $s['footer_heading_size'] }}px;text-transform:uppercase;letter-spacing:1px;color:{{ $s['footer_heading_color'] }};font-family:'{{ $fb }}',Arial,sans-serif}
.footer p,.footer li{color:{{ $s['footer_text_color'] }};line-height:1.7;font-size:{{ $s['footer_text_size'] }}px}
.footer ul{list-style:none;margin:0;padding:0}
.footer a{text-decoration:none}
.footer-bottom{padding:{{ $s['footer_bottom_padding_y'] }}px {{ $s['gutter'] }}px;background:{{ $s['footer_bottom_bg'] }};color:{{ $s['footer_bottom_text_color'] }};font-size:{{ $s['footer_bottom_font_size'] }}px;text-align:center}

@if (!$site['fixed'])
/* ================= responsive — live demo only ================= */
@media (max-width:1200px){
  :root{--g:48px}
  .nav,.section,.footer,.footer-bottom{padding-left:var(--g);padding-right:var(--g)}
  .hero{padding-left:var(--g);padding-right:var(--g)}
  .hero--overlay,.hero--c-fullscreen_image{padding-left:0;padding-right:0}
  .hero--overlay .hero-copy,.hero--c-fullscreen_image .hero-copy{padding-left:var(--g);padding-right:var(--g)}
  .hero--c-contained{margin-left:var(--g);margin-right:var(--g)}
  .editorial,.faq,.split-head{gap:48px}
}
@media (max-width:1024px){
  .links{gap:20px}
  .features{--cols:2 !important}
  .team,.stats-grid{--cols:2 !important}
  .mosaic{grid-template-columns:repeat(3,minmax(0,1fr));grid-auto-rows:170px}
  .stats{grid-template-columns:1fr;gap:32px}
  .quotes{grid-template-columns:1fr;gap:40px}
  .plans{grid-template-columns:repeat(2,minmax(0,1fr))}
  .plan--featured{transform:none}
  .grid[style*="repeat(4"],.grid[style*="repeat(5"],.grid[style*="repeat(6"]{grid-template-columns:repeat(2,minmax(0,1fr)) !important}
}
@media (max-width:860px){
  .nav{min-height:68px}
  .links,.nav>.button{display:none}
  .nav-burger{display:flex;flex-direction:column;justify-content:center;gap:5px;width:44px;height:44px;cursor:pointer;margin-left:auto}
  .nav-burger span{display:block;height:2px;width:24px;background:var(--ink);margin:0 auto}
  .nav-toggle:checked ~ .links{display:flex;position:absolute;top:100%;left:0;right:0;flex-direction:column;gap:0;background:#fff;border-bottom:1px solid var(--line);padding:8px var(--g) 16px}
  .nav-toggle:checked ~ .links a{padding:12px 0;border-bottom:1px solid var(--line)}
  .nav-toggle:checked ~ .links .links-cta{display:block;margin-top:12px;text-align:center;background:var(--accent);color:var(--on-accent);border-radius:{{ $btnRadius }}px;border:none;font-weight:700}

  .hero{min-height:0;padding-top:56px !important;padding-bottom:56px !important}
  .hero--split{flex-direction:column;align-items:stretch;gap:32px}
  .hero--split .hero-copy,.hero--split .hero-photo{flex:1 1 auto !important;max-width:100% !important}
  .hero--split .hero-photo img{height:auto;max-height:420px}
  .hero--c-asymmetric_split .hero-photo{margin-top:0}
  .hero--c-overlapping .hero-photo{margin-bottom:0}
  .hero--overlay{min-height:0}
  .hero--overlay .hero-copy{padding-top:120px;padding-bottom:56px}
  .hero--c-contained{margin:16px var(--g);border-radius:20px}
  .hero:after{width:360px;height:360px;right:-160px;top:-180px}
  .hero-copy p{font-size:16px}

  .section{padding-top:clamp(44px,9vw,64px) !important;padding-bottom:clamp(44px,9vw,64px) !important}
  .editorial,.editorial--text,.faq,.split-head{grid-template-columns:1fr;gap:32px}
  .split-head .head{position:static}
  .editorial-media{order:-1}
  .editorial-media:before{inset:16px -10px -10px 16px}
  .alt-rows{gap:48px}
  .alt-row,.alt-row:nth-child(even){grid-template-columns:1fr;gap:20px}
  .alt-row:nth-child(even) .alt-media{order:0}
  .cta{flex-direction:column;align-items:flex-start;gap:24px}
  .cta--center{align-items:center}
  .footer{grid-template-columns:1fr 1fr;gap:28px}
  .footer>div:first-child{grid-column:1 / -1}
}
@media (max-width:560px){
  :root{--g:20px;--gap:16px}
  .brand{font-size:18px}
  .brand img{height:32px}
  .features,.split-head .features{--cols:1 !important}
  .stats-grid{--cols:2 !important;gap:24px 16px}
  .team{--cols:2 !important;gap:24px 14px}
  .mosaic{grid-template-columns:repeat(2,minmax(0,1fr));grid-auto-rows:150px;gap:10px}
  .tile--0{grid-column:span 2;grid-row:span 2}
  .tile--3{grid-column:span 2}
  .plans{grid-template-columns:1fr}
  .grid{grid-template-columns:1fr !important}
  .grid--feature-first .card:first-child{grid-row:auto}
  .grid--feature-first .card:first-child img{min-height:0;aspect-ratio:var(--card-ratio,4/3)}
  .icon-row{gap:20px}
  .faq-item summary{font-size:16px;padding:18px 0}
  .quote-lead p:before{font-size:64px}
  .footer{grid-template-columns:1fr}
  .btn,.hero-copy .button{display:block;text-align:center}
  .cta .btn{align-self:stretch}
}
@endif
</style>
