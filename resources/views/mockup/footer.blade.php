@php($layout = $site['global']['footer_layout'])
@php($chrome = $site['chrome'])
@if ($layout === 'minimal')
<footer class="footer footer--minimal">
    <a class="footer-brand" href="{!! $chrome['home'] !!}">{{ $site['brand'] }}</a>
    @if ($chrome['footer_menu'])
    {!! $chrome['footer_menu'] !!}
    @else
    <ul class="footer-links">
        @foreach ($site['nav_primary'] as $item)
            <li><a href="{{ $item['href'] }}">{{ $item['name'] }}</a></li>
        @endforeach
    </ul>
    @endif
</footer>
<div class="footer-bottom footer-bottom--split">
    <span>&copy; {!! $chrome['year'] !!} {{ $site['brand'] }}</span>
    <span>{{ $site['lang'] === 'en' ? 'All rights reserved.' : 'Hak cipta dilindungi.' }}</span>
</div>
@elseif ($layout === 'asymmetric')
<footer class="footer footer--asymmetric">
    <div class="footer-lead">
        <span class="footer-brand">{{ $site['brand'] }}</span>
        <p>{{ $site['concept'] }}</p>
        <a class="button" href="{!! $chrome['cta_href'] !!}">{{ $site['cta'] }}</a>
    </div>
    <div class="footer-cols">
        <div>
            <h4>{{ $site['lang'] === 'en' ? 'Navigation' : 'Navigasi' }}</h4>
            @if ($chrome['footer_menu'])
            {!! $chrome['footer_menu'] !!}
            @else
            <ul>
                @foreach ($site['nav_primary'] as $item)
                    <li><a href="{{ $item['href'] }}">{{ $item['name'] }}</a></li>
                @endforeach
            </ul>
            @endif
        </div>
        <div>
            <h4>{{ $site['lang'] === 'en' ? 'Contact' : 'Kontak' }}</h4>
            <p>{{ $site['lang'] === 'en' ? 'Get in touch for details and bookings.' : 'Hubungi kami untuk informasi dan pemesanan.' }}</p>
        </div>
    </div>
</footer>
<div class="footer-bottom">&copy; {!! $chrome['year'] !!} {{ $site['brand'] }}. {{ $site['lang'] === 'en' ? 'All rights reserved.' : 'Hak cipta dilindungi.' }}</div>
@else
<footer class="footer footer--columns">
    <div>
        <h4>{{ $site['brand'] }}</h4>
        <p>{{ $site['concept'] }}</p>
    </div>
    <div>
        <h4>{{ $site['lang'] === 'en' ? 'Navigation' : 'Navigasi' }}</h4>
        @if ($chrome['footer_menu'])
        {!! $chrome['footer_menu'] !!}
        @else
        <ul>
            @foreach ($site['nav_primary'] as $item)
                <li><a href="{{ $item['href'] }}">{{ $item['name'] }}</a></li>
            @endforeach
        </ul>
        @endif
    </div>
    <div>
        <h4>{{ $site['lang'] === 'en' ? 'Contact' : 'Kontak' }}</h4>
        <p>{{ $site['lang'] === 'en' ? 'Get in touch for details and bookings.' : 'Hubungi kami untuk informasi dan pemesanan.' }}</p>
    </div>
</footer>
<div class="footer-bottom">&copy; {!! $chrome['year'] !!} {{ $site['brand'] }}. {{ $site['lang'] === 'en' ? 'All rights reserved.' : 'Hak cipta dilindungi.' }}</div>
@endif
