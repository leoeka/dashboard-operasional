<div class="site {{ $site['full_page'] ? 'full-page' : 'legacy-page' }}">
@include('mockup.header')
<main>
@foreach ($site['page']['sections'] as $section)
    @if ($section['renderer'] === 'hero')
        @include('mockup.hero', ['section' => $section])
    @else
        @include('mockup.section', ['section' => $section])
    @endif
@endforeach
</main>
@include('mockup.footer')
</div>
