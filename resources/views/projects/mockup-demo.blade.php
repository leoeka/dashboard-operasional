{{--
    Device preview around the live demo. The demo itself is an iframe, so each
    width below is a real viewport and the site's own media queries respond to
    it exactly as they would on a phone or tablet.
--}}
@php
    $previewUrl = route('pages.projects.mockup.preview', [$project, $candidate]);
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Demo Opsi {{ $candidate + 1 }} — {{ $project->name }}</title>
<style>
*{box-sizing:border-box}
html,body{margin:0;height:100%}
body{display:flex;flex-direction:column;background:#0f172a;color:#e2e8f0;font:14px/1.4 system-ui,-apple-system,'Segoe UI',Roboto,sans-serif}
.bar{display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;padding:10px 16px;background:#020617;border-bottom:1px solid #1e293b}
.bar a{color:inherit}
.back{color:#94a3b8;text-decoration:none;white-space:nowrap}
.title{font-weight:700;white-space:nowrap}
.title small{font-weight:400;color:#94a3b8}
.group{display:flex;gap:4px;background:#1e293b;border-radius:10px;padding:3px}
.group button,.group a{border:0;background:transparent;color:#cbd5e1;padding:7px 12px;border-radius:8px;font:inherit;cursor:pointer;text-decoration:none;white-space:nowrap}
.group button[aria-pressed="true"],.group a[aria-current="true"]{background:#f8fafc;color:#0f172a;font-weight:600}
.group button:focus-visible,.group a:focus-visible,select:focus-visible{outline:2px solid #60a5fa;outline-offset:2px}
select{background:#1e293b;color:#e2e8f0;border:0;border-radius:8px;padding:8px 10px;font:inherit}
.spacer{flex:1}
.badge{padding:3px 8px;border-radius:999px;background:#14532d;color:#bbf7d0;font-size:12px}
.stage{flex:1;overflow:auto;display:flex;justify-content:center;padding:20px}
.frame{width:100%;max-width:1440px;height:100%;min-height:600px;border:0;background:#fff;border-radius:6px;box-shadow:0 20px 60px rgba(0,0,0,.45);transition:max-width .25s ease}
.stage[data-device="tablet"] .frame{max-width:768px}
.stage[data-device="mobile"] .frame{max-width:390px}
.size{color:#64748b;font-size:12px;white-space:nowrap}
@media (max-width:700px){.stage{padding:8px}.title small,.size{display:none}}
</style>
</head>
<body>
<div class="bar">
    <a class="back" href="{{ route('pages.project-workspace', ['project' => $project->id]) }}">&larr; Workspace</a>
    <span class="title">{{ $project->name }} <small>Opsi {{ $candidate + 1 }}@if ($label) · {{ $label }}@endif</small></span>
    @if ($candidateCount > 1)
        <nav class="group" aria-label="Opsi desain">
            @for ($i = 0; $i < $candidateCount; $i++)
                <a href="{{ route('pages.projects.mockup.demo', [$project, $i]) }}" @if ($i === $candidate) aria-current="true" @endif>Opsi {{ $i + 1 }}</a>
            @endfor
        </nav>
    @endif

    @if (count($pages) > 1)
        <label>
            <span class="size">Halaman</span>
            <select id="page-select" aria-label="Halaman">
                @foreach ($pages as $page)
                    <option value="{{ $page['slug'] }}">{{ $page['name'] }}</option>
                @endforeach
            </select>
        </label>
    @endif

    <span class="spacer"></span>

    <div class="group" role="group" aria-label="Ukuran perangkat">
        <button type="button" data-device="desktop" aria-pressed="true">Desktop</button>
        <button type="button" data-device="tablet" aria-pressed="false">Tablet</button>
        <button type="button" data-device="mobile" aria-pressed="false">Mobile</button>
        <a id="fullscreen" href="{{ $previewUrl }}" target="_blank" rel="noopener">Fullscreen &#8599;</a>
    </div>
    <span class="size" id="size">1440px</span>
</div>

<main class="stage" id="stage" data-device="desktop">
    <iframe class="frame" id="frame" src="{{ $previewUrl }}" title="Live preview opsi {{ $candidate + 1 }}"></iframe>
</main>

<script>
(function () {
    var stage = document.getElementById('stage');
    var frame = document.getElementById('frame');
    var size = document.getElementById('size');
    var fullscreen = document.getElementById('fullscreen');
    var select = document.getElementById('page-select');
    var base = @json($previewUrl);
    var widths = { desktop: '1440px', tablet: '768px', mobile: '390px' };

    document.querySelectorAll('[data-device]').forEach(function (button) {
        if (button.tagName !== 'BUTTON') return;
        button.addEventListener('click', function () {
            var device = button.getAttribute('data-device');
            stage.setAttribute('data-device', device);
            size.textContent = widths[device];
            document.querySelectorAll('button[data-device]').forEach(function (b) {
                b.setAttribute('aria-pressed', b === button ? 'true' : 'false');
            });
        });
    });

    function pageUrl(slug) {
        return base + (slug && slug !== 'home' ? '?page=' + encodeURIComponent(slug) : '');
    }

    if (select) {
        select.addEventListener('change', function () {
            frame.src = pageUrl(select.value);
            fullscreen.href = pageUrl(select.value);
        });
    }

    // Keep the page picker and the fullscreen link in step with navigation
    // clicked inside the demo itself (same origin, so the URL is readable).
    frame.addEventListener('load', function () {
        try {
            var slug = new URL(frame.contentWindow.location.href).searchParams.get('page') || 'home';
            if (select) select.value = slug;
            fullscreen.href = pageUrl(slug);
        } catch (e) { /* cross-origin never happens here; ignore */ }
    });
})();
</script>
</body>
</html>
