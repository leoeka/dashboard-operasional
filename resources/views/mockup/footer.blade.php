<footer class="footer">
    <div>
        <h4>{{ $site['brand'] }}</h4>
        <p>{{ $site['concept'] }}</p>
    </div>
    <div>
        <h4>{{ $site['lang'] === 'en' ? 'Navigation' : 'Navigasi' }}</h4>
        <ul>
            @foreach ($site['nav_primary'] as $item)
                <li><a href="{{ $item['href'] }}">{{ $item['name'] }}</a></li>
            @endforeach
        </ul>
    </div>
    <div>
        <h4>{{ $site['lang'] === 'en' ? 'Contact' : 'Kontak' }}</h4>
        <p>{{ $site['lang'] === 'en' ? 'Get in touch for details and bookings.' : 'Hubungi kami untuk informasi dan pemesanan.' }}</p>
    </div>
</footer>
<div class="footer-bottom">&copy; {{ date('Y') }} {{ $site['brand'] }}. All rights reserved.</div>
