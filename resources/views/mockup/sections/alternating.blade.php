@php $ratio = str_replace(':', '/', $sc['image_ratio']); @endphp
@include('mockup.partials.head')
<div class="alt-rows">
    @foreach ($section['items'] as $itemIndex => $item)
        @php $photo = $section['photos'][$itemIndex] ?? null; @endphp
        <div class="alt-row">
            <div class="alt-media">
                @if ($photo)
                    <img src="{{ $photo }}" style="aspect-ratio:{{ $ratio }}" alt="{{ $item['title'] }}">
                @else
                    <div class="panel" style="aspect-ratio:{{ $ratio }}">{{ sprintf('%02d', $loop->iteration) }}</div>
                @endif
            </div>
            <div class="alt-copy">
                <span class="num">{{ sprintf('%02d', $loop->iteration) }}</span>
                <h3>{{ $item['title'] }}</h3>
                @if ($item['text'])<p>{{ $item['text'] }}</p>@endif
            </div>
        </div>
    @endforeach
</div>
@if ($section['cta'])<a class="btn" href="#">{{ $section['cta'] }}</a>@endif
