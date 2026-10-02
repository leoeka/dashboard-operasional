{{-- A bookable tour, room or product. Every fact (location, duration, rating,
     price) is drawn only when the content carries it; ContentIntegrityService
     has already removed any figure the client never gave. Mirrored by
     ElementorPageBuilderService::gbListingCard(). --}}
@php $labels = \App\Support\SectionContent::listingLabels($site['lang']); @endphp
<article class="listing">
    @if ($photo)
        <div class="listing-media"><img src="{{ $photo }}" alt="{{ $item['title'] }}"></div>
    @endif
    <div class="listing-body">
        @if ($item['location'] || $item['duration'] || $item['rating'])
            <div class="listing-meta">
                <span class="listing-facts">
                    @if ($item['location'])<span class="listing-fact listing-fact--place">{{ $item['location'] }}</span>@endif
                    @if ($item['duration'])<span class="listing-fact listing-fact--time">{{ $item['duration'] }}</span>@endif
                </span>
                @if ($item['rating'])
                    <span class="listing-rating" aria-label="{{ $labels['rating'] }} {{ $item['rating'] }}"><span class="listing-star" aria-hidden="true">★</span>{{ $item['rating'] }}@if ($item['reviews'])<small>({{ $item['reviews'] }})</small>@endif</span>
                @endif
            </div>
        @endif
        <h3>{{ $item['title'] }}</h3>
        @if ($item['text'])<p class="listing-text">{{ $item['text'] }}</p>@endif
        <div class="listing-foot">
            @if ($item['price'])
                <p class="listing-price"><strong>{{ $item['price'] }}</strong>@if ($item['price_unit'])<span>{{ $item['price_unit'] }}</span>@endif</p>
            @endif
            <a class="listing-cta" href="#">{{ $labels['details'] }}</a>
        </div>
    </div>
</article>
