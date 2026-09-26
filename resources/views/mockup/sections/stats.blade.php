@php
    $hasHead = $section['headline'] || $section['description'];
    $centered = $section['plan']['heading_align'] === 'center';
    $cols = max(1, min(4, count($section['items']) ?: 1));
@endphp
<div class="stats {{ !$hasHead || $centered ? 'stats--solo' : '' }}">
    @if ($hasHead)
        @include('mockup.partials.head')
    @endif
    <div class="stats-grid" style="--cols:{{ $cols }}">
        @foreach ($section['items'] as $item)
            <div class="stat">
                <div class="stat-value">{{ $item['value'] }}</div>
                @if ($item['label'] && $item['label'] !== $item['value'])<div class="stat-label">{{ $item['label'] }}</div>@endif
            </div>
        @endforeach
    </div>
</div>
