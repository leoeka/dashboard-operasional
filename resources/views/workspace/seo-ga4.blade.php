<x-card class="mb-6">
    <div class="flex items-center justify-between mb-1">
        <h2 class="font-semibold text-slate-800">Google Analytics (GA4) Report</h2>
        @if ($ga4)
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
        <button type="button" id="btnAnalyzeGa4"
            class="min-h-11 inline-flex items-center gap-2 bg-brand-600 text-white text-xs font-semibold px-4 py-2 rounded-md">
            <i class='bx bx-refresh'></i> {{ $ga4 ? 'Refresh Data' : 'Fetch GA4 Data' }}
        </button>
        <span id="ga4Spinner" class="hidden text-xs text-slate-600 ml-2">Loading...</span>

        <div id="ga4Error"
            class="hidden mt-3 text-sm text-red-700 bg-red-50 border-l-4 border-l-red-700 rounded-md p-4">
        </div>

        <div id="ga4SelectBox" class="hidden mt-3 p-3 bg-brand-50 border border-slate-300 rounded-lg">
            <p class="text-sm text-brand-700 mb-2">Found several matching GA4 Properties, choose one:
            </p>
            <select id="ga4PropertySelect"
                class="min-h-11 w-full bg-white border border-slate-500 rounded-md px-3 py-2 text-sm mb-2"></select>
            <button type="button" id="btnConfirmGa4Property"
                class="min-h-11 text-xs font-semibold bg-brand-600 text-white px-3 py-2 rounded-md">Use
                This
                Property</button>
        </div>

        <div id="ga4ManualBox"
            class="hidden mt-3 p-3 bg-amber-50 border-l-4 border-l-amber-700 rounded-lg">
            <p class="text-sm text-amber-700 mb-2">Could not find a GA4 Property automatically. Enter
                the Property ID
                manually (GA4 → Admin → Property Settings, numbers only, e.g. 123456789):</p>
            <input type="text" id="ga4ManualInput" placeholder="123456789"
                class="min-h-11 w-full border border-slate-500 rounded-md px-3 py-2 text-sm mb-2">
            <button type="button" id="btnConfirmGa4Manual"
                class="min-h-11 text-xs font-semibold bg-brand-600 text-white px-3 py-2 rounded-md hover:bg-brand-700">Save
                & Fetch
                Data</button>
        </div>

        @if ($ga4)
            @php
                $ga4Totals = $ga4['totals'] ?? null;
                $ga4Pages = $ga4['by_landing_page'] ?? [];
                $ga4NewVsReturning = $ga4['new_vs_returning'] ?? null;
            @endphp
            <div class="border-t border-slate-100 mt-4 pt-4">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                    <div class="rounded-md p-4 text-center bg-slate-50">
                        <div class="text-base font-bold text-slate-700">
                            {{ $ga4Totals['organic_sessions'] ?? 0 }}</div>
                        <div class="text-xs text-slate-600">Organic Sessions</div>
                    </div>
                    <div class="rounded-md p-4 text-center bg-slate-50">
                        <div class="text-base font-bold text-slate-700">
                            {{ $ga4Totals['total_users'] ?? 0 }}</div>
                        <div class="text-xs text-slate-600">Total Users</div>
                    </div>
                    <div class="rounded-md p-4 text-center bg-slate-50">
                        <div class="text-base font-bold text-slate-700">
                            {{ $ga4Totals['conversions'] ?? 0 }}</div>
                        <div class="text-xs text-slate-600">Conversions</div>
                    </div>
                </div>

                @if ($ga4NewVsReturning)
                    <p class="text-xs text-slate-600 mb-2">New vs Returning Users</p>
                    <div class="flex gap-3 mb-4">
                        <div class="rounded-md p-4 text-center bg-slate-50 flex-1">
                            <div class="text-base font-bold text-slate-700">
                                {{ $ga4NewVsReturning['new'] ?? 0 }}</div>
                            <div class="text-xs text-slate-600">New Users</div>
                        </div>
                        <div class="rounded-md p-4 text-center bg-slate-50 flex-1">
                            <div class="text-base font-bold text-slate-700">
                                {{ $ga4NewVsReturning['returning'] ?? 0 }}</div>
                            <div class="text-xs text-slate-600">Returning Users</div>
                        </div>
                    </div>
                @endif

                @if (!empty($ga4Pages))
                    <p class="text-xs text-slate-600 mb-2">Top Landing Pages</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-slate-600 border-b border-slate-100">
                                    <th class="pb-1 pr-4">Page</th>
                                    <th class="pb-1 pr-4">Sessions</th>
                                    <th class="pb-1 pr-4">Engagement Rate</th>
                                    <th class="pb-1 pr-4">Avg. Engagement</th>
                                    <th class="pb-1">Conversions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ga4Pages as $p)
                                    <tr class="border-b border-slate-50">
                                        <td class="py-2 pr-4 text-slate-700 break-all">
                                            {{ $p['landing_page'] ?? '-' }}</td>
                                        <td class="py-2 pr-4 text-slate-600">
                                            {{ $p['sessions'] ?? 0 }}</td>
                                        <td class="py-2 pr-4 text-slate-600">
                                            {{ isset($p['engagement_rate']) ? round($p['engagement_rate'] * 100, 1) . '%' : '-' }}
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">
                                            {{ isset($p['avg_engagement_time']) ? $p['avg_engagement_time'] . 's' : '-' }}
                                        </td>
                                        <td class="py-2 text-slate-600">{{ $p['conversions'] ?? 0 }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <p class="text-xs text-slate-600 mt-3">
                    Property ID: {{ $seo['ga4_property_id'] ?? '-' }}
                    &middot; Period: {{ $ga4['period']['start'] ?? '-' }} to
                    {{ $ga4['period']['end'] ?? '-' }}
                    &middot; Last fetched: {{ $ga4['analyzed_at'] ?? '-' }}
                </p>
            </div>
        @endif

        <script>
            const GA4_ANALYZE_URL = "{{ route('pages.projects.ga4.analyze', $project) }}";

            (function() {
                const btn = document.getElementById('btnAnalyzeGa4');
                const spinner = document.getElementById('ga4Spinner');
                const errorBox = document.getElementById('ga4Error');
                const selectBox = document.getElementById('ga4SelectBox');
                const propertySelect = document.getElementById('ga4PropertySelect');
                const btnConfirmSelect = document.getElementById('btnConfirmGa4Property');
                const manualBox = document.getElementById('ga4ManualBox');
                const manualInput = document.getElementById('ga4ManualInput');
                const btnConfirmManual = document.getElementById('btnConfirmGa4Manual');

                function hideAllBoxes() {
                    errorBox.classList.add('hidden');
                    selectBox.classList.add('hidden');
                    manualBox.classList.add('hidden');
                }

                function runAnalysis(propertyId) {
                    hideAllBoxes();
                    btn.disabled = true;
                    spinner.classList.remove('hidden');

                    fetch(GA4_ANALYZE_URL, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify(propertyId ? {
                                property_id: propertyId
                            } : {}),
                        })
                        .then(res => res.json().then(data => ({
                            ok: res.ok,
                            data
                        })))
                        .then(({
                            ok,
                            data
                        }) => {
                            spinner.classList.add('hidden');

                            if (ok && data.success) {
                                window.location.reload();
                            } else if (data.needs_selection) {
                                btn.disabled = false;
                                propertySelect.innerHTML = data.candidates.map(c =>
                                    `<option value="${c.property_id}">${c.name} (${c.property_id}) · ${c.url}</option>`
                                ).join('');
                                selectBox.classList.remove('hidden');
                            } else if (data.needs_manual_input) {
                                btn.disabled = false;
                                manualBox.classList.remove('hidden');
                            } else {
                                btn.disabled = false;
                                errorBox.textContent = data.message || 'Failed to fetch GA4 data.';
                                errorBox.classList.remove('hidden');
                            }
                        })
                        .catch(() => {
                            btn.disabled = false;
                            spinner.classList.add('hidden');
                            errorBox.textContent = 'Failed to fetch data. Try again.';
                            errorBox.classList.remove('hidden');
                        });
                }

                btn?.addEventListener('click', () => runAnalysis(null));
                btnConfirmSelect?.addEventListener('click', () => runAnalysis(propertySelect.value));
                btnConfirmManual?.addEventListener('click', () => {
                    const val = manualInput.value.trim();
                    if (val) runAnalysis(val);
                });
            })();
        </script>
    @endif
</x-card>
