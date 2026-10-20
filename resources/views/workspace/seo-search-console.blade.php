<x-card class="mb-6">
    <div class="flex items-center justify-between mb-1">
        <h2 class="font-semibold text-slate-800">Search Console Report</h2>
        @if ($searchConsole)
            <span
                class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-md bg-emerald-100 text-emerald-700">
                <i class='bx bx-check'></i> Data available
            </span>
        @endif
    </div>
    <p class="text-xs text-slate-600 mb-4">Last 3 months.</p>

    @if (!$resolvedUrl)
        <div class="text-sm text-amber-700 bg-amber-50 border-l-4 border-l-amber-700 rounded-md p-4">
            The client's website URL has not been filled in.
        </div>
    @else
        <button type="button" id="btnAnalyzeGsc"
            class="min-h-11 inline-flex items-center gap-2 bg-brand-600 text-white text-xs font-semibold px-4 py-2 rounded-md">
            <i class='bx bx-refresh'></i>
            {{ $searchConsole ? 'Refresh Data' : 'Fetch Search Console Data' }}
        </button>
        <span id="gscSpinner" class="hidden text-xs text-slate-600 ml-2">Loading...</span>

        <div id="gscError"
            class="hidden mt-3 text-sm text-red-700 bg-red-50 border-l-4 border-l-red-700 rounded-md p-4">
        </div>

        @if ($searchConsole)
            @php
                $gscTotals = $searchConsole['totals'] ?? null;
                $gscTopQueries = $searchConsole['top_queries'] ?? [];
                $gscByDevice = $searchConsole['by_device'] ?? [];
            @endphp
            <div class="border-t border-slate-100 mt-4 pt-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                    <div class="rounded-md p-4 text-center bg-slate-50">
                        <div class="text-base font-bold text-slate-700">
                            {{ $gscTotals['clicks'] ?? 0 }}</div>
                        <div class="text-xs text-slate-600">Total Clicks</div>
                    </div>
                    <div class="rounded-md p-4 text-center bg-slate-50">
                        <div class="text-base font-bold text-slate-700">
                            {{ $gscTotals['impressions'] ?? 0 }}</div>
                        <div class="text-xs text-slate-600">Impressions</div>
                    </div>
                    <div class="rounded-md p-4 text-center bg-slate-50">
                        <div class="text-base font-bold text-slate-700">
                            {{ isset($gscTotals['ctr']) ? round($gscTotals['ctr'] * 100, 1) . '%' : '-' }}
                        </div>
                        <div class="text-xs text-slate-600">CTR</div>
                    </div>
                    <div class="rounded-md p-4 text-center bg-slate-50">
                        <div class="text-base font-bold text-slate-700">
                            {{ isset($gscTotals['position']) ? round($gscTotals['position'], 1) : '-' }}
                        </div>
                        <div class="text-xs text-slate-600">Average Position</div>
                    </div>
                </div>

                @if (!empty($gscTopQueries))
                    <p class="text-xs text-slate-600 mb-2">Top Search Queries</p>
                    <div class="overflow-x-auto mb-4">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-slate-600 border-b border-slate-100">
                                    <th class="pb-1 pr-4">Query</th>
                                    <th class="pb-1 pr-4">Clicks</th>
                                    <th class="pb-1 pr-4">Impressions</th>
                                    <th class="pb-1 pr-4">CTR</th>
                                    <th class="pb-1">Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($gscTopQueries as $q)
                                    <tr class="border-b border-slate-50">
                                        <td class="py-2 pr-4 text-slate-700">
                                            {{ $q['keys'][0] ?? '-' }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $q['clicks'] ?? 0 }}
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">
                                            {{ $q['impressions'] ?? 0 }}</td>
                                        <td class="py-2 pr-4 text-slate-600">
                                            {{ isset($q['ctr']) ? round($q['ctr'] * 100, 1) . '%' : '-' }}
                                        </td>
                                        <td class="py-2 text-slate-600">
                                            {{ isset($q['position']) ? round($q['position'], 1) : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (!empty($gscByDevice))
                    <p class="text-xs text-slate-600 mb-2">Clicks per Device</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-slate-600 border-b border-slate-100">
                                    <th class="pb-1 pr-4">Device</th>
                                    <th class="pb-1">Clicks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($gscByDevice as $d)
                                    <tr class="border-b border-slate-50">
                                        <td class="py-2 pr-4 text-slate-700 capitalize">
                                            {{ strtolower($d['keys'][0] ?? '-') }}
                                        </td>
                                        <td class="py-2 text-slate-600">{{ $d['clicks'] ?? 0 }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <p class="text-xs text-slate-600 mt-3">
                    Period: {{ $searchConsole['period']['start'] ?? '-' }} to
                    {{ $searchConsole['period']['end'] ?? '-' }}
                    &middot; Last fetched: {{ $searchConsole['analyzed_at'] ?? '-' }}
                </p>
            </div>
        @endif

        <script>
            const GSC_ANALYZE_URL = "{{ route('pages.projects.search-console.analyze', $project) }}";

            (function() {
                const btn = document.getElementById('btnAnalyzeGsc');
                const spinner = document.getElementById('gscSpinner');
                const errorBox = document.getElementById('gscError');

                btn?.addEventListener('click', function() {
                    errorBox.classList.add('hidden');
                    btn.disabled = true;
                    spinner.classList.remove('hidden');

                    fetch(GSC_ANALYZE_URL, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                                    '',
                            },
                        })
                        .then(res => res.json().then(data => ({
                            ok: res.ok,
                            data
                        })))
                        .then(({
                            ok,
                            data
                        }) => {
                            if (ok && data.success) {
                                window.location.reload();
                            } else {
                                btn.disabled = false;
                                spinner.classList.add('hidden');
                                errorBox.textContent = data.message || 'Failed to fetch Search Console data.';
                                errorBox.classList.remove('hidden');
                            }
                        })
                        .catch(() => {
                            btn.disabled = false;
                            spinner.classList.add('hidden');
                            errorBox.textContent = 'Failed to fetch data. Try again.';
                            errorBox.classList.remove('hidden');
                        });
                });
            })();
        </script>
    @endif
</x-card>
