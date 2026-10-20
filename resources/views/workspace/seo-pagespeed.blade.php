<x-card class="mb-6">
    <div class="flex items-center justify-between mb-1">
        <h2 class="font-semibold text-slate-800">Website Performance (PageSpeed)</h2>
        @if ($pagespeed)
            <span
                class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-md bg-emerald-100 text-emerald-700">
                <i class='bx bx-check'></i> Analyzed
            </span>
        @endif
    </div>
    <p class="text-xs text-slate-600 mb-4">Speed & Core Web Vitals (Lighthouse).</p>

    @if (!$resolvedUrl)
        <div class="text-sm text-amber-700 bg-amber-50 border-l-4 border-l-amber-700 rounded-md p-4">
            The client's website URL has not been filled in. Fill it in first on the
            <a href="{{ route('pages.projects.edit', $project) }}"
                class="underline font-semibold">Edit
                Project page</a>.
        </div>
    @else
        <div id="pagespeedBox">
            <button type="button" id="btnAnalyzePagespeed"
                class="min-h-11 inline-flex items-center gap-2 bg-brand-600 text-white text-xs font-semibold px-4 py-2 rounded-md">
                <i class='bx bx-tachometer'></i>
                {{ $pagespeed ? 'Re-run Analysis' : 'Analyze Performance' }}
            </button>
            <p class="text-xs text-slate-600 mt-1">Takes ~30-60 seconds.</p>

            <div id="pagespeedProgress" class="hidden mt-3">
                <div class="w-full bg-slate-100 rounded-full h-2 mb-2 overflow-hidden">
                    <div id="pagespeedProgressBar"
                        class="bg-brand-600 h-2 rounded-full transition-all" style="width:0%"></div>
                </div>
                <p id="pagespeedProgressMessage" class="text-xs text-slate-500"></p>
            </div>

            <div id="pagespeedError"
                class="hidden mt-3 text-sm text-red-700 bg-red-50 border-l-4 border-l-red-700 rounded-md p-4">
            </div>
        </div>

        @if ($pagespeed)
            <div class="border-t border-slate-100 mt-4 pt-4">
                @foreach (['mobile' => 'Mobile', 'desktop' => 'Desktop'] as $key => $label)
                    @php $d = $pagespeed[$key] ?? null; @endphp
                    @if ($d)
                        <div class="mb-4">
                            <p class="text-xs font-semibold text-slate-600 mb-2">{{ $label }}
                            </p>
                            <div class="flex justify-center gap-2 mb-3">
                                @include('pdf.partials.gauge', [
                                    'score' => $d['scores']['performance'] ?? null,
                                    'label' => 'Performance',
                                ])
                                @include('pdf.partials.gauge', [
                                    'score' => $d['scores']['accessibility'] ?? null,
                                    'label' => 'Accessibility',
                                ])
                                @include('pdf.partials.gauge', [
                                    'score' => $d['scores']['best_practices'] ?? null,
                                    'label' => 'Best Practices',
                                ])
                                @include('pdf.partials.gauge', [
                                    'score' => $d['scores']['seo'] ?? null,
                                    'label' => 'SEO',
                                ])
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr
                                            class="text-left text-xs text-slate-600 border-b border-slate-100">
                                            <th class="pb-1 pr-4">Metric</th>
                                            <th class="pb-1 pr-4">Value</th>
                                            <th class="pb-1 pr-4">Status</th>
                                            <th class="pb-1">Source</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (['lcp' => 'Largest Contentful Paint (LCP)', 'cls' => 'Cumulative Layout Shift (CLS)', 'inp' => 'Interaction to Next Paint (INP)', 'fcp' => 'First Contentful Paint (FCP)', 'speed_index' => 'Speed Index'] as $metricKey => $metricLabel)
                                            @php $m = $d['metrics'][$metricKey] ?? null; @endphp
                                            <tr class="border-b border-slate-50">
                                                <td class="py-2 pr-4 text-slate-700">
                                                    {{ $metricLabel }}</td>
                                                <td class="py-2 pr-4 text-slate-600">
                                                    {{ $m['value'] ?? '-' }}
                                                    {{ $m['unit'] ?? '' }}
                                                </td>
                                                <td class="py-2 pr-4">
                                                    @php
                                                        $statusMap = [
                                                            'good' => [
                                                                'Good',
                                                                'bg-emerald-50 text-emerald-700',
                                                            ],
                                                            'needs_improvement' => [
                                                                'Needs Improvement',
                                                                'bg-amber-50 text-amber-700',
                                                            ],
                                                            'poor' => [
                                                                'Poor',
                                                                'bg-red-50 text-red-700',
                                                            ],
                                                        ];
                                                        [$statusLabel, $statusColor] = $statusMap[
                                                            $m['status'] ?? ''
                                                        ] ?? ['-', 'bg-slate-100 text-slate-700'];
                                                    @endphp
                                                    <span
                                                        class="text-xs px-2 py-1 rounded-full {{ $statusColor }}">{{ $statusLabel }}</span>
                                                </td>
                                                <td class="py-2 text-xs text-slate-600">
                                                    {{ ($m['source'] ?? null) === 'field' ? 'Real user data' : (($m['source'] ?? null) === 'lab' ? 'Simulated' : '-') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endforeach
                <p class="text-xs text-slate-600">Last analyzed:
                    {{ $pagespeed['analyzed_at'] ?? '-' }}</p>

                <div class="border-t border-slate-100 mt-4 pt-4">
                    <p class="text-xs font-semibold text-slate-600 mb-1">Semrush Overview Screenshot
                        (for Proposal)</p>
                    <a href="https://www.semrush.com/analytics/overview/?q={{ urlencode(parse_url($resolvedUrl, PHP_URL_HOST)) }}&searchType=domain"
                        target="_blank" rel="noopener"
                        class="inline-flex items-center px-1 min-h-11 text-xs text-brand-600 hover:underline">
                        Open Semrush for this domain &rarr;
                    </a>

                    @if (!empty($seo['manual_screenshots']['own_semrush']))
                        <div class="mt-2">
                            <img src="{{ Storage::url($seo['manual_screenshots']['own_semrush']) }}"
                                class="max-w-xs border rounded-lg">
                            <form
                                action="{{ route('pages.projects.manual-screenshot.destroy', $project) }}"
                                method="POST" class="mt-1">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="target" value="own_semrush">
                                <input type="hidden" name="return_tab" value="performa">
                                <button type="submit"
                                    class="inline-flex items-center px-1 min-h-11 text-xs text-red-700 hover:underline">Delete
                                    & re-upload</button>
                            </form>
                        </div>
                    @else
                        <form action="{{ route('pages.projects.manual-screenshot.store', $project) }}"
                            method="POST" enctype="multipart/form-data"
                            class="mt-2 flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="target" value="own_semrush">
                            <input type="hidden" name="return_tab" value="performa">
                            <input type="file" name="screenshot" accept="image/*" required
                                class="text-xs">
                            <button type="submit"
                                class="min-h-11 text-xs bg-brand-600 text-white px-3 py-2 rounded-md">Upload</button>
                        </form>
                    @endif
                </div>

                <div class="border-t border-slate-100 mt-4 pt-4">
                    <p class="text-xs font-semibold text-slate-600 mb-1">PageSpeed Report Screenshot
                        (for Proposal)</p>
                    <a href="https://pagespeed.web.dev/report?url={{ urlencode($resolvedUrl) }}"
                        target="_blank" rel="noopener"
                        class="inline-flex items-center px-1 min-h-11 text-xs text-brand-600 hover:underline">Open
                        PageSpeed for this site
                        &rarr;</a>

                    @if (!empty($seo['manual_screenshots']['own_pagespeed']))
                        <div class="mt-2">
                            <img src="{{ Storage::url($seo['manual_screenshots']['own_pagespeed']) }}"
                                class="max-w-xs border rounded-lg">
                            <form
                                action="{{ route('pages.projects.manual-screenshot.destroy', $project) }}"
                                method="POST" class="mt-1">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="target" value="own_pagespeed">
                                <input type="hidden" name="return_tab" value="performa">
                                <button type="submit"
                                    class="inline-flex items-center px-1 min-h-11 text-xs text-red-700 hover:underline">Delete
                                    & re-upload</button>
                            </form>
                        </div>
                    @else
                        <form action="{{ route('pages.projects.manual-screenshot.store', $project) }}"
                            method="POST" enctype="multipart/form-data"
                            class="mt-2 flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="target" value="own_pagespeed">
                            <input type="hidden" name="return_tab" value="performa">
                            <input type="file" name="screenshot" accept="image/*" required
                                class="text-xs">
                            <button type="submit"
                                class="min-h-11 text-xs bg-brand-600 text-white px-3 py-2 rounded-md">Upload</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <script>
            const PAGESPEED_ANALYZE_URL = "{{ route('pages.projects.pagespeed.analyze', $project) }}";
            const PAGESPEED_STATUS_URL = "{{ route('pages.projects.pagespeed.status', $project) }}";

            (function() {
                const btn = document.getElementById('btnAnalyzePagespeed');
                const progress = document.getElementById('pagespeedProgress');
                const progressBar = document.getElementById('pagespeedProgressBar');
                const progressMessage = document.getElementById('pagespeedProgressMessage');
                const errorBox = document.getElementById('pagespeedError');
                let pollTimer = null;

                function checkStatus() {
                    fetch(PAGESPEED_STATUS_URL, {
                            headers: {
                                Accept: 'application/json'
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'queued' || data.status === 'running') {
                                progressBar.style.width = (data.progress || 0) + '%';
                                progressMessage.textContent = data.message || '';
                            } else if (data.status === 'done') {
                                clearInterval(pollTimer);
                                window.location.reload();
                            } else if (data.status === 'failed') {
                                clearInterval(pollTimer);
                                progress.classList.add('hidden');
                                errorBox.textContent = data.message || 'Analysis failed.';
                                errorBox.classList.remove('hidden');
                            }
                        })
                        .catch(() => {});
                }

                btn?.addEventListener('click', function() {
                    errorBox.classList.add('hidden');
                    fetch(PAGESPEED_ANALYZE_URL, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                                    '',
                            },
                        })
                        .then(res => res.json())
                        .then(() => {
                            progress.classList.remove('hidden');
                            clearInterval(pollTimer);
                            pollTimer = setInterval(checkStatus, 3000);
                            checkStatus();
                        })
                        .catch(() => {
                            errorBox.textContent = 'Failed to start analysis.';
                            errorBox.classList.remove('hidden');
                        });
                });
            })();
        </script>
    @endif
</x-card>
