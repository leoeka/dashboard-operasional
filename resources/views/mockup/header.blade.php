<header class="nav">
    <a class="brand" href="{{ $site['nav'][0]['href'] ?? '#' }}">
        @if ($site['logo'])<img src="{{ $site['logo'] }}" alt="{{ $site['brand'] }}">@endif
        {{ $site['brand'] }}
    </a>
    {{-- CSS-only menu toggle: the demo stays a plain page with no script. --}}
    <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
    <label for="nav-toggle" class="nav-burger" aria-label="Menu"><span></span><span></span><span></span></label>
    <nav class="links">
        @foreach ($site['nav_primary'] as $item)
            <a href="{{ $item['href'] }}" @class(['is-active' => $item['active']])>{{ $item['name'] }}</a>
        @endforeach
        <a class="links-cta" href="#">{{ $site['cta'] }}</a>
    </nav>
    <a class="button" href="#">{{ $site['cta'] }}</a>
</header>
