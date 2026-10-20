@php($withChrome = $site['chrome']['render'])
@if ($withChrome)
<div class="site {{ $site['full_page'] ? 'full-page' : 'legacy-page' }}">
@include('mockup.header')
@endif
<main>
@foreach ($site['page']['sections'] as $section)
    @if ($section['renderer'] === 'hero')
        @include('mockup.hero', ['section' => $section])
    @else
        @include('mockup.section', ['section' => $section])
    @endif
@endforeach
</main>
@if ($withChrome)
@include('mockup.footer')
</div>
@endif
