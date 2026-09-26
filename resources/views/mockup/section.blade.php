@php
    $sc = $section['c'];
    // Legacy pages keep the exact band the approved PNG showed: the card grid
    // on the neutral band, everything else on white, fixed section padding.
    $bg = $site['full_page'] ? $section['background'] : ($section['renderer'] === 'cards' ? 'band' : 'none');
    $shellStyle = $site['full_page']
        ? "padding-top:{$sc['spacing_top']}px;padding-bottom:{$sc['spacing_bottom']}px"
        : '';
    $wrapStyle = '--cw:' . ($sc['container_width'] ? $sc['container_width'] . 'px' : 'none') . ";--h:{$sc['heading_px']}px";
@endphp
<section id="section-{{ $section['index'] }}" class="section section--{{ $section['renderer'] }} {{ $bg !== 'none' ? 'bg-' . $bg : '' }} {{ !$site['full_page'] && $bg === 'band' ? 'alt' : '' }}" style="{{ $shellStyle }}">
    <div class="wrap" style="{{ $wrapStyle }}">
        @include('mockup.sections.' . $section['renderer'], ['section' => $section, 'sc' => $sc])
    </div>
</section>
