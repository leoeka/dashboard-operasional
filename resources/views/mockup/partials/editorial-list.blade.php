@if ($section['items'])
    <ol class="editorial-list">
        @foreach ($section['items'] as $item)
            <li><strong>{{ $item['title'] }}</strong>@if ($item['text'])<span>{{ $item['text'] }}</span>@endif</li>
        @endforeach
    </ol>
@endif
