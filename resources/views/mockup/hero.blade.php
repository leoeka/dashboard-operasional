@php
    $heroC = $section['c'];
    $heroPhoto = $section['photo'];

    $ratio = str_replace(':', '/', $heroC['image_ratio']);

    $objectPosition = match ($heroC['focal_point'] ?? 'center') {
        'left' => '25% 50%',
        'right' => '75% 50%',
        'top' => '50% 25%',
        'bottom' => '50% 75%',
        default => '50% 50%',
    };

    // --pt lets a full page add the floating navbar's height on top of the
    // composition's own spacing (styles.blade.php), without a second number.
    $heroInline = "--pt:{$heroC['spacing_top']}px;padding-top:{$heroC['spacing_top']}px;padding-bottom:{$heroC['spacing_bottom']}px;text-align:{$heroC['text_align']}";

    $heroCopyInline = $heroC['family'] === 'split'
        ? "flex:0 0 {$heroC['content_width']}%;max-width:{$heroC['content_width']}%"
        : ($heroC['container_width']
            ? "max-width:{$heroC['container_width']}px"
            : '');

    $heroPhotoInline = $heroC['family'] === 'split'
        ? "flex:0 0 {$heroC['image_width']}%;max-width:{$heroC['image_width']}%"
        : '';

    $heroImgInline = "aspect-ratio:{$ratio};border-radius:{$heroC['radius_px']}px;object-position:{$objectPosition}";

    $showHeroPhoto = $heroPhoto
        && in_array(
            $heroC['image_position'],
            ['left', 'right', 'above', 'below'],
            true
        );
@endphp
<section
    class="hero hero--{{ $heroC['family'] }} hero--c-{{ $heroC['composition'] }} hero--img-{{ $heroC['image_position'] }}"
    style="{{ $heroInline }}">
  @if ($heroC['image_position'] === 'background' && $heroPhoto)
    <img
        class="hero-bg-photo"
        src="{{ $heroPhoto }}"
        style="object-position:{{ $objectPosition }}"
        alt=""
    >
    <div class="hero-scrim"></div>
@endif
    <div class="hero-copy" style="{{ $heroCopyInline }}">
        <h1 style="--h:{{ $heroC['heading_px'] }}px">{{ $section['headline'] ?: $site['brand'] }}</h1>
        @if ($section['description'])
            <p>{{ $section['description'] }}</p>
        @endif
        <a class="button" href="#">{{ $section['cta'] ?: $site['cta'] }}</a>
    </div>
    @if ($showHeroPhoto)
    <div class="hero-photo" style="{{ $heroPhotoInline }}">
        <img
            src="{{ $heroPhoto }}"
            style="{{ $heroImgInline }}"
            alt="{{ $section['headline'] }}"
        >
    </div>
@endif
</section>
