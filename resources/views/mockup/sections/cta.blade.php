@php $centered = $section['plan']['heading_align'] === 'center'; @endphp
<div class="cta {{ $centered ? 'cta--center' : '' }}">
    <div>
        @if ($section['eyebrow'])<span class="eyebrow">{{ $section['eyebrow'] }}</span>@endif
        <h2>{{ $section['headline'] }}</h2>
        @if ($section['description'])<p>{{ $section['description'] }}</p>@endif
    </div>
    @if ($section['cta'])<a class="btn" href="#">{{ $section['cta'] }}</a>@endif
</div>
