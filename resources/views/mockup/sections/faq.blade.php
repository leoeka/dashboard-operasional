@php $stack = $section['plan']['heading_align'] === 'center'; @endphp
<div class="faq {{ $stack ? 'faq--stack' : '' }}">
    @include('mockup.partials.head')
    <div class="faq-list">
        @foreach ($section['items'] as $item)
            {{-- The PNG is a still image, so every answer is shown open there. --}}
            <details class="faq-item" @if ($site['fixed'] || $loop->first) open @endif>
                <summary>{{ $item['title'] }}</summary>
                @if ($item['text'])<p>{{ $item['text'] }}</p>@endif
            </details>
        @endforeach
    </div>
</div>
