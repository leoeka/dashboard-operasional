@php $items = $section['items']; $align = $section['plan']['body_align']; @endphp
@if (!$site['full_page'])
    {{-- Legacy icon row, exactly as pre-V2 PNGs showed it; its style follows the
         hero's layout variant so the page reads as one design. --}}
    <div class="section-head">
        <h2>{{ $section['headline'] }}</h2>
        @if ($section['description'])<p>{{ $section['description'] }}</p>@endif
    </div>
    <div class="icon-row {{ $site['layout_variant'] === 'overlay-bg' ? 'minimal' : ($site['layout_variant'] === 'split-left' ? 'chips' : '') }}" style="text-align:{{ $align }}">
        @foreach ($items as $item)
            <div class="icon-item">
                <div class="icon-badge">{{ $loop->iteration }}</div>
                <div>
                    <h3>{{ $item['title'] }}</h3>
                    @if ($item['text'])<p>{{ $item['text'] }}</p>@endif
                </div>
            </div>
        @endforeach
    </div>
@else
    @php
        $count = count($items);
        // More than three features read better beside the heading than in one
        // long row — unless the designer asked for a centred band.
        $split = $count > 3 && $align !== 'center';
        $cols = max(1, min($sc['columns'], $count ?: 1));
    @endphp
    <div class="{{ $split ? 'split-head' : '' }}">
        @include('mockup.partials.head')
        <div class="features" style="{{ $split ? '' : "--cols:{$cols};" }}text-align:{{ $align }}">
            @foreach ($items as $item)
                <div class="feature">
                    <div class="feature-num">{{ sprintf('%02d', $loop->iteration) }}</div>
                    <h3>{{ $item['title'] }}</h3>
                    @if ($item['text'])<p>{{ $item['text'] }}</p>@endif
                </div>
            @endforeach
        </div>
    </div>
    @if ($section['cta'])<a class="btn" href="#">{{ $section['cta'] }}</a>@endif
@endif
