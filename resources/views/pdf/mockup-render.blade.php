{{--
    The mockup PNG the client approves: the shared site renderer
    (resources/views/mockup/site.blade.php) on the fixed desktop canvas (MockupDesignSpec container_width), with the
    candidate's persisted photographs inlined as data URLs. The live demo
    (projects/mockup-live) renders the very same partials, so the picture in the
    proposal and the demo cannot show two different designs.

    GenerateMockupGptService passes a prepared $site. Callers written before the
    shared renderer existed pass the old variables (hero / iconSection /
    photoSection / homeSections); those are folded into the same view-model.
--}}
@php
    if (!isset($site)) {
        $sourcePages = array_values($pages ?? ($mockup['pages'] ?? []));
        $homeIndex = \App\Support\SitemapPages::homeIndex($sourcePages) ?? 0;
        $sourceSections = !empty($homeSections)
            ? array_values($homeSections)
            : array_values(array_filter([$hero ?? null, $iconSection ?? null, $photoSection ?? null]));

        if ($sourceSections) {
            $sourcePages[$homeIndex] = array_merge(
                is_array($sourcePages[$homeIndex] ?? null) ? $sourcePages[$homeIndex] : ['name' => 'Home'],
                ['sections' => $sourceSections]
            );
        }

        $site = \App\Support\MockupSite::build(
            array_merge($mockup ?? [], ['design' => $design ?? ($mockup['design'] ?? []), 'pages' => $sourcePages]),
            [
                'brand' => $project->client?->company_name ?? $project->name,
                'logo' => $logoDataUrl ?? null,
                'fixed' => true,
                'images' => ['home' => ['hero' => $heroPhoto ?? null, 'items' => $itemPhotos ?? []]],
            ]
        );
    }
@endphp
@include('mockup.site', ['site' => $site])
