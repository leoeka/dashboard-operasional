@php
    $count = count($section['items']);
    $featured = \App\Support\SectionContent::featuredIndex($section['items']);
@endphp
@include('mockup.partials.head')
<div class="plans" style="--cols:{{ max(1, min(4, $count ?: 1)) }}">
    @foreach ($section['items'] as $item)
        <div class="plan {{ $loop->index === $featured ? 'plan--featured' : '' }}">
            <h3>{{ $item['title'] }}</h3>
            @if ($item['price'])<div class="price">{{ $item['price'] }}</div>@endif
            @if ($item['text'])<p>{{ $item['text'] }}</p>@endif
            @if ($item['features'])
                <ul>@foreach ($item['features'] as $feature)<li>{{ $feature }}</li>@endforeach</ul>
            @endif
            <a class="btn" href="#">{{ $section['cta'] ?: $site['cta'] }}</a>
        </div>
    @endforeach
</div>
