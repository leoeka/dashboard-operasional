<div class="logos--compact">
    @include('mockup.partials.head')
</div>
<div class="logos">
    @foreach ($section['items'] as $item)
        <div class="logo">{{ $item['title'] }}</div>
    @endforeach
</div>
