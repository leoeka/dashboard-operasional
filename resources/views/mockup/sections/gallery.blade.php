@include('mockup.partials.head')
<div class="mosaic mosaic--{{ min(6, count($section['items'])) }}">
    @foreach ($section['items'] as $itemIndex => $item)
        @php $photo = $section['photos'][$itemIndex] ?? null; @endphp
        <figure class="tile tile--{{ $loop->index }} {{ $photo ? '' : 'tile--empty' }}">
            @if ($photo)<img src="{{ $photo }}" alt="{{ $item['title'] }}">@endif
            @if ($item['title'])<figcaption>{{ $item['title'] }}</figcaption>@endif
        </figure>
    @endforeach
</div>
