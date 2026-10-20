@php($chrome = $site['chrome'])
<header class="nav nav--{{ $site['global']['header_layout'] }}">
    <a class="brand" href="{!! $chrome['home'] !!}">
        @if ($chrome['logo'])<img src="{!! $chrome['logo'] !!}" alt="{{ $site['brand'] }}">@endif
        {{ $site['brand'] }}
    </a>
    {{-- CSS-only menu toggle: the demo stays a plain page with no script. --}}
    <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
    <label for="nav-toggle" class="nav-burger" aria-label="Menu"><span></span><span></span><span></span></label>
    @if ($chrome['menu'])
    {!! $chrome['menu'] !!}
    @else
    {{-- The list WordPress will replace with its own menu: same <ul>/<li> shape,
         so one stylesheet styles both. --}}
    <ul class="links">
        @foreach ($site['nav_primary'] as $item)
            <li><a href="{{ $item['href'] }}" @class(['is-active' => $item['active']])>{{ $item['name'] }}</a></li>
        @endforeach
        <li class="links-cta-item"><a class="links-cta" href="{!! $chrome['cta_href'] !!}">{{ $site['cta'] }}</a></li>
    </ul>
    @endif
    <a class="button" href="{!! $chrome['cta_href'] !!}">{{ $site['cta'] }}</a>
</header>
