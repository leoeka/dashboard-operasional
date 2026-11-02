<x-card class="mb-6">
    <div class="mb-1">
        <h2 class="font-semibold text-slate-800">{{ $seoTrackLabel }}</h2>
    </div>

    {{-- SEO --}}
    @if ($project->wants_seo)
        <div class="border-t border-slate-100 pt-4 mb-4">
            <div class="flex items-center gap-2 mb-2">
                <i class='bx bx-search text-brand-600'></i>
                <span class="text-sm font-semibold text-slate-700">SEO Requirements</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div class="flex gap-2">
                    <span class="text-slate-600 w-28 flex-shrink-0">Target URL</span>
                    <span class="text-slate-700 break-all">{{ ($seo['target_url'] ?? null) ?: '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="text-slate-600 w-28 flex-shrink-0">Location</span>
                    <span class="text-slate-700">{{ ($seo['location'] ?? null) ?: '-' }}</span>
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <span class="text-slate-600 w-28 flex-shrink-0">Keyword</span>
                    <span class="text-slate-700">
                        @forelse ($keywordList->take(4) as $kw)
                            <span
                                class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-1 rounded-full mr-1 mb-1">{{ $kw }}</span>
                        @empty
                            -
                        @endforelse
                        @if ($keywordList->count() > 4)
                            <span class="text-xs text-slate-600">+{{ $keywordList->count() - 4 }}
                                more</span>
                        @endif
                    </span>
                </div>
                <div class="flex gap-2">
                    <span class="text-slate-600 w-28 flex-shrink-0">Platform</span>
                    <span>
                        @if ($cmsPlatform)
                            <span
                                class="inline-block bg-brand-50 text-brand-700 text-xs px-2 py-1 rounded-full">{{ ucfirst($cmsPlatform === 'baru' ? 'New Website (from us)' : $cmsPlatform) }}</span>
                        @else
                            -
                        @endif
                    </span>
                </div>
            </div>
        </div>
    @endif

    {{-- BACKLINK --}}
    @if ($project->wants_backlink)
        <div class="border-t border-slate-100 pt-4 mb-4">
            <div class="flex items-center gap-2 mb-2">
                <i class='bx bx-link text-brand-600'></i>
                <span class="text-sm font-semibold text-slate-700">Backlink Requirements</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div class="flex gap-2">
                    <span class="text-slate-600 w-28 flex-shrink-0">Quantity</span>
                    <span class="text-slate-700">{{ $backlink['quantity'] ?? '-' }} backlink</span>
                </div>
                <div class="flex gap-2">
                    <span class="text-slate-600 w-28 flex-shrink-0">Priority</span>
                    <span
                        class="text-slate-700">{{ ($backlink['priority'] ?? null) === 'quality' ? 'Quality' : (($backlink['priority'] ?? null) === 'quantity' ? 'Quantity' : '-') }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="text-slate-600 w-28 flex-shrink-0">Niche</span>
                    <span class="text-slate-700">{{ ($backlink['niche'] ?? null) ?: '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="text-slate-600 w-28 flex-shrink-0">Anchor type</span>
                    <span class="text-slate-700">
                        @forelse (($backlink['anchor_type'] ?? []) as $type)
                            <span
                                class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-1 rounded-full mr-1">{{ str_replace('_', ' ', ucfirst($type)) }}</span>
                        @empty
                            -
                        @endforelse
                    </span>
                </div>
            </div>
        </div>
    @endif
</x-card>
