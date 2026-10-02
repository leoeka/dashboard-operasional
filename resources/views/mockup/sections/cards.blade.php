@php
    $items = $section['items'];
    $count = count($items);
    $columns = max(1, min($sc['columns'], $count ?: 1));
    $listing = !empty($sc['listing']);
    $featureFirst = !$listing && !empty($sc['feature_first']) && $count >= 3;
    $gridClass = $featureFirst ? 'grid--feature-first' : '';
    $gridInline = $featureFirst
        ? 'grid-template-columns:' . ($count >= 5 ? '1.4fr 1fr 1fr' : '1.4fr 1fr') . ';grid-auto-rows:1fr'
        : "grid-template-columns:repeat({$columns},1fr)" . ($listing ? ';--card-ratio:' . str_replace(':', '/', $sc['image_ratio']) : '');
    $cardClass = match ($sc['card_treatment']) {
        'shadowed' => 'shadow',
        'plain' => 'card--plain',
        'flush' => 'card--flush',
        default => '',
    };
    $ratio = str_replace(':', '/', $sc['image_ratio']);
@endphp
@if ($site['full_page'])
    @include('mockup.partials.head')
@else
    <div class="section-head">
        <h2>{{ $section['headline'] }}</h2>
        @if ($section['description'])<p>{{ $section['description'] }}</p>@endif
    </div>
@endif
<div class="{{ trim('grid ' . $gridClass . ($listing ? ' grid--listing' : '')) }}" style="{{ $gridInline }}">
    @foreach ($items as $itemIndex => $item)
        @php $photo = $section['photos'][$itemIndex] ?? null; @endphp
        @if ($listing)
            @include('mockup.partials.listing-card', ['item' => $item, 'photo' => $photo])
            @continue
        @endif
        <article class="card {{ $cardClass }}" style="--card-ratio:{{ $ratio }};border-radius:{{ $sc['radius_px'] }}px;text-align:{{ $section['plan']['body_align'] }}">
            @if ($photo)<img src="{{ $photo }}" alt="{{ $item['title'] }}">@endif
            <div class="card-body">
                <h3>{{ $item['title'] }}</h3>
                @if ($item['text'])<p>{{ $item['text'] }}</p>@endif
                @if ($item['price'])<span class="price">{{ $item['price'] }}</span>@endif
            </div>
        </article>
    @endforeach
</div>
@if ($site['full_page'] && $section['cta'])<a class="btn" href="#">{{ $section['cta'] }}</a>@endif
