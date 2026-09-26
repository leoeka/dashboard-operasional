{{-- Section heading block. $align comes from the section plan — the same value the Gutenberg builder aligns with. --}}
@php $align = $align ?? $section['plan']['heading_align']; @endphp
@if ($section['eyebrow'] || $section['headline'] || $section['description'])
<div class="head head--{{ $align }}" style="text-align:{{ $align }}">
    @if ($section['eyebrow'])<span class="eyebrow">{{ $section['eyebrow'] }}</span>@endif
    @if ($section['headline'])<h2>{{ $section['headline'] }}</h2>@endif
    @if ($section['description'])<p>{{ $section['description'] }}</p>@endif
</div>
@endif
