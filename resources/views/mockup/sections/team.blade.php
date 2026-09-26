@php $cols = max(1, min($sc['columns'], count($section['items']) ?: 1)); @endphp
@include('mockup.partials.head')
<div class="team" style="--cols:{{ $cols }};--ratio:{{ str_replace(':', '/', $sc['image_ratio']) }}">
    @foreach ($section['items'] as $itemIndex => $item)
        @php $photo = $section['photos'][$itemIndex] ?? null; @endphp
        <div class="member">
            @if ($photo)
                <img src="{{ $photo }}" alt="{{ $item['title'] }}">
            @else
                <div class="monogram">{{ \App\Support\SectionContent::initials($item['title']) }}</div>
            @endif
            <h3>{{ $item['title'] }}</h3>
            @if ($item['role'])<div class="role">{{ $item['role'] }}</div>@endif
            @if ($item['text'])<p>{{ $item['text'] }}</p>@endif
        </div>
    @endforeach
</div>
