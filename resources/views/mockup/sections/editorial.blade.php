@php
    $photo = $section['photo'];
    $ratio = str_replace(':', '/', $sc['image_ratio']);
@endphp
@if ($photo)
    <div class="editorial {{ $sc['image_position'] === 'left' ? 'editorial--img-left' : '' }}">
        <div class="editorial-copy">
            @include('mockup.partials.head', ['align' => 'left'])
            @include('mockup.partials.editorial-list')
            @if ($section['cta'])<a class="btn" href="#">{{ $section['cta'] }}</a>@endif
        </div>
        <div class="editorial-media"><img src="{{ $photo }}" style="aspect-ratio:{{ $ratio }}" alt="{{ $section['headline'] }}"></div>
    </div>
@else
    {{-- No photograph: an editorial type layout — heading in one column, the
         story in a wider one — rather than an empty image slot. --}}
    <div class="editorial editorial--text">
        <div class="head" style="margin:0">
            @if ($section['eyebrow'])<span class="eyebrow">{{ $section['eyebrow'] }}</span>@endif
            <h2>{{ $section['headline'] }}</h2>
        </div>
        <div>
            @if ($section['description'])<p class="lead">{{ $section['description'] }}</p>@endif
            @include('mockup.partials.editorial-list')
            @if ($section['cta'])<a class="btn" href="#">{{ $section['cta'] }}</a>@endif
        </div>
    </div>
@endif
