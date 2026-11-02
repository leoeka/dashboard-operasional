{{-- KARTU ANALISIS AI — keyword & kompetitor otomatis --}}
<x-card class="mb-6">
    <div class="flex items-center justify-between mb-1">
        <h2 class="font-semibold text-slate-800">AI Analysis: Keyword & Competitor</h2>
        @if ($aiRecommendations)
            <span
                class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-md bg-emerald-100 text-emerald-700">
                <i class='bx bx-check'></i> Analyzed
            </span>
        @endif
    </div>
    <p class="text-xs text-slate-600 mb-4">Automatic keyword & competitor discovery via AI.</p>

    @if (!$resolvedUrl)
        <div class="text-sm text-amber-700 bg-amber-50 border-l-4 border-l-amber-700 rounded-md p-4">
            The client's website URL has not been filled in. Fill it in first on the
            <a href="{{ route('pages.projects.edit', $project) }}"
                class="underline font-semibold">Edit
                Project page</a>
            The analysis will run automatically once after the URL is saved, or it can be triggered
            manually here afterward.
        </div>
    @else
        <div id="aiAnalysisBox" data-project-id="{{ $project->id }}">
            <div id="aiIdleState" class="{{ $aiRecommendations ? 'hidden' : '' }}">
                <button type="button" id="btnStartAnalysis"
                    class="min-h-11 inline-flex items-center gap-2 bg-brand-600 text-white text-xs font-semibold px-4 py-2 rounded-md">
                    <i class='bx bx-magic-wand'></i> Start AI Analysis
                </button>
            </div>

            <div id="aiProgressState" class="hidden">
                <div class="w-full bg-slate-100 rounded-full h-2 mb-2 overflow-hidden">
                    <div id="aiProgressBar" class="bg-brand-600 h-2 rounded-full transition-all"
                        style="width:0%">
                    </div>
                </div>
                <p id="aiProgressMessage" class="text-xs text-slate-500"></p>
            </div>

            <div id="aiErrorState"
                class="hidden text-sm text-red-700 bg-red-50 border-l-4 border-l-red-700 rounded-md p-4">
                <p id="aiErrorMessage"></p>
                <button type="button" id="btnRetryAnalysis"
                    class="mt-2 text-xs font-semibold text-red-700 underline">Try again</button>
            </div>

            @if ($aiRecommendations)
                <button type="button" id="btnRerunAnalysis"
                    class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                    Re-run analysis
                </button>
            @endif
        </div>

        @if ($aiRecommendations)
            <div class="border-t border-slate-100 mt-4 pt-4 space-y-4">
                @if (!empty($aiTopics['core_topics']))
                    <div>
                        <p class="text-xs text-slate-600 mb-1">Core topics detected</p>
                        <div>
                            @foreach ($aiTopics['core_topics'] as $topic)
                                <span
                                    class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-1 rounded-full mr-1 mb-1">{{ $topic }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ===== KANDIDAT KEYWORD — checkbox, tim pilih mana yang dipakai ===== --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs text-slate-600">
                            Keyword Candidates ({{ count($aiRecommendations['main_keywords'] ?? []) }})
                            <span
                                class="ml-1 inline-block text-xs px-2 py-1 rounded-md {{ ($aiRecommendations['data_source'] ?? '') === 'google_ads_api' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                                {{ ($aiRecommendations['data_source'] ?? '') === 'google_ads_api' ? 'Google Ads Data' : 'AI Estimate' }}
                            </span>
                        </p>
                        <span id="keywordSelectedCount" class="text-xs text-slate-600"></span>
                    </div>

                    <form id="keywordSelectForm">
                        @csrf
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="text-left text-xs text-slate-600 border-b border-slate-100">
                                        <th class="pb-2 pr-2 w-6"></th>
                                        <th class="pb-2 pr-4">Keyword</th>
                                        <th class="pb-2 pr-4">Derivatives</th>
                                        <th class="pb-2 pr-4">Volume/month</th>
                                        <th class="pb-2 pr-4">Competition</th>
                                        <th class="pb-2">Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($aiRecommendations['main_keywords'] ?? [] as $kw)
                                        <tr class="border-b border-slate-50">
                                            <td class="py-2 pr-2">
                                                <input type="checkbox" name="selected_keywords[]"
                                                    value="{{ $kw['keyword'] ?? '' }}"
                                                    class="keyword-checkbox"
                                                    {{ !empty($kw['selected']) ? 'checked' : '' }}>
                                            </td>
                                            <td class="py-2 pr-4 font-semibold text-slate-700">
                                                {{ $kw['keyword'] ?? '-' }}
                                            </td>
                                            <td class="py-2 pr-4 text-slate-500 text-xs">
                                                {{ implode(', ', array_filter((array) ($kw['derivative_keywords'] ?? []))) ?: '-' }}
                                            </td>
                                            <td class="py-2 pr-4 text-slate-500">
                                                {{ $kw['avg_monthly_searches'] ?? '-' }}
                                            </td>
                                            <td class="py-2 pr-4 text-slate-500">
                                                {{ $kw['competition'] ?? '-' }}</td>
                                            <td class="py-2 text-slate-500">
                                                {{ $kw['reasoning'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex items-center gap-2 mt-3">
                            <button type="submit" id="btnSaveKeywordSelection"
                                class="min-h-11 inline-flex items-center gap-2 bg-brand-600 text-white text-xs font-semibold px-4 py-2 rounded-md">
                                <i class='bx bx-check'></i> Save Selection
                            </button>
                            <span id="keywordSelectSaved"
                                class="hidden text-xs text-emerald-700">Saved.</span>
                            <span id="keywordSelectError" class="hidden text-xs text-red-700"></span>
                        </div>
                    </form>
                </div>

                @if (!empty($aiRecommendations['related_keywords']))
                    <div>
                        <p class="text-xs text-slate-600 mb-1">Related keywords</p>
                        <div>
                            @foreach ($aiRecommendations['related_keywords'] as $kw)
                                <span
                                    class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-1 rounded-full mr-1 mb-1">{{ $kw }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (!empty($aiRecommendations['summary']))
                    <div>
                        <p class="text-xs text-slate-600 mb-1">Strategy summary</p>
                        <p class="text-sm text-slate-600">{{ $aiRecommendations['summary'] }}</p>
                    </div>
                @endif

                {{-- ===== PILIH & ANALISIS KOMPETITOR ===== --}}
                @if ($discoveredCompetitors->isNotEmpty())
                    @php
                        $selectedCompetitors =
                            $seo['selected_competitors'] ??
                            $discoveredCompetitors->take(3)->values()->all();
                        $competitorPagespeed = $seo['competitor_pagespeed'] ?? null;
                    @endphp
                    <div class="border-t border-slate-100 pt-4">
                        <p class="text-xs text-slate-600 mb-2">Check up to 3 competitors to analyze:
                        </p>

                        <form id="competitorSelectForm">
                            @csrf
                            <ul class="text-sm space-y-1 mb-3">
                                @foreach ($discoveredCompetitors as $url)
                                    <li class="flex items-center gap-2">
                                        <input type="checkbox" name="selected_urls[]"
                                            value="{{ $url }}" class="competitor-checkbox"
                                            {{ in_array($url, $selectedCompetitors) ? 'checked' : '' }}>
                                        <a href="{{ $url }}" target="_blank" rel="noopener"
                                            class="text-brand-600 hover:underline break-all">{{ $url }}</a>
                                    </li>
                                @endforeach
                            </ul>

                            <textarea name="manual_urls" rows="2"
                                placeholder="Add manually, 1 URL per line: https://other-competitor-example.com/"
                                class="min-h-11 w-full text-sm border border-slate-300 rounded-md px-3 py-2 mb-2"></textarea>

                            <button type="submit" id="btnSelectCompetitors"
                                class="min-h-11 inline-flex items-center gap-2 bg-brand-600 text-white text-xs font-semibold px-4 py-2 rounded-md">
                                <i class='bx bx-refresh'></i>
                                {{ $competitorPagespeed ? 'Re-select & Analyze' : 'Select & Analyze These Competitors' }}
                            </button>
                            <span id="compPagespeedSpinner"
                                class="hidden text-xs text-slate-600 ml-2">Processing (can take
                                1-2 minutes)...</span>
                            <p id="competitorSelectError" class="hidden text-xs text-red-700 mt-2">
                            </p>
                        </form>

                        @if ($competitorPagespeed)
                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                                @foreach ($competitorPagespeed as $comp)
                                    @php $compHost = parse_url($comp['url'], PHP_URL_HOST); @endphp
                                    <div class="text-center">
                                        <p class="text-xs font-semibold text-slate-600 mb-2 truncate"
                                            title="{{ $comp['url'] }}">
                                            {{ $compHost }}
                                        </p>
                                        <div class="flex justify-center gap-1">
                                            @include('pdf.partials.gauge', [
                                                'score' =>
                                                    $comp['scores']['performance'] ?? null,
                                                'label' => 'Performance',
                                            ])
                                            @include('pdf.partials.gauge', [
                                                'score' =>
                                                    $comp['scores']['accessibility'] ?? null,
                                                'label' => 'Accessibility',
                                            ])
                                            @include('pdf.partials.gauge', [
                                                'score' =>
                                                    $comp['scores']['best_practices'] ?? null,
                                                'label' => 'Best Practices',
                                            ])
                                            @include('pdf.partials.gauge', [
                                                'score' => $comp['scores']['seo'] ?? null,
                                                'label' => 'SEO',
                                            ])
                                        </div>

                                        @if (!empty($comp['error']))
                                            <p class="mt-2 text-xs text-red-700">{{ $comp['error'] }}
                                            </p>
                                        @endif

                                        <div class="mt-2 pt-2 border-t border-slate-100">
                                            <a href="https://www.semrush.com/analytics/overview/?q={{ urlencode($compHost) }}&searchType=domain"
                                                target="_blank" rel="noopener"
                                                class="inline-flex items-center px-1 min-h-11 text-xs text-brand-600 hover:underline">Open
                                                Semrush &rarr;</a>

                                            @if (!empty($seo['manual_screenshots']['competitor_semrush'][$compHost]))
                                                <div class="mt-1">
                                                    <img src="{{ Storage::url($seo['manual_screenshots']['competitor_semrush'][$compHost]) }}"
                                                        class="max-w-[140px] mx-auto border rounded-lg">
                                                    <form
                                                        action="{{ route('pages.projects.manual-screenshot.destroy', $project) }}"
                                                        method="POST" class="mt-1">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="target"
                                                            value="competitor_semrush:{{ $compHost }}">
                                                        <button type="submit"
                                                            class="inline-flex items-center px-1 min-h-11 text-xs text-red-700 hover:underline">Delete</button>
                                                    </form>
                                                </div>
                                            @else
                                                <form
                                                    action="{{ route('pages.projects.manual-screenshot.store', $project) }}"
                                                    method="POST" enctype="multipart/form-data"
                                                    class="mt-1">
                                                    @csrf
                                                    <input type="hidden" name="target"
                                                        value="competitor_semrush:{{ $compHost }}">
                                                    <input type="file" name="screenshot"
                                                        accept="image/*" required
                                                        class="min-h-11 text-xs w-full">
                                                    <button type="submit"
                                                        class="min-h-11 text-xs bg-brand-600 text-white px-3 py-1 rounded-md mt-1">Upload</button>
                                                </form>
                                            @endif
                                        </div>

                                        <div class="mt-2 pt-2 border-t border-slate-100">
                                            <a href="https://pagespeed.web.dev/report?url={{ urlencode($comp['url']) }}"
                                                target="_blank" rel="noopener"
                                                class="inline-flex items-center px-1 min-h-11 text-xs text-brand-600 hover:underline">Open
                                                PageSpeed &rarr;</a>

                                            @if (!empty($seo['manual_screenshots']['competitor_pagespeed'][$compHost]))
                                                <div class="mt-1">
                                                    <img src="{{ Storage::url($seo['manual_screenshots']['competitor_pagespeed'][$compHost]) }}"
                                                        class="max-w-[140px] mx-auto border rounded-lg">
                                                    <form
                                                        action="{{ route('pages.projects.manual-screenshot.destroy', $project) }}"
                                                        method="POST" class="mt-1">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="target"
                                                            value="competitor_pagespeed:{{ $compHost }}">
                                                        <button type="submit"
                                                            class="inline-flex items-center px-1 min-h-11 text-xs text-red-700 hover:underline">Delete</button>
                                                    </form>
                                                </div>
                                            @else
                                                <form
                                                    action="{{ route('pages.projects.manual-screenshot.store', $project) }}"
                                                    method="POST" enctype="multipart/form-data"
                                                    class="mt-1">
                                                    @csrf
                                                    <input type="hidden" name="target"
                                                        value="competitor_pagespeed:{{ $compHost }}">
                                                    <input type="file" name="screenshot"
                                                        accept="image/*" required
                                                        class="min-h-11 text-xs w-full">
                                                    <button type="submit"
                                                        class="min-h-11 text-xs bg-brand-600 text-white px-3 py-1 rounded-md mt-1">Upload</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <script>
                        (function() {
                            const form = document.getElementById('competitorSelectForm');
                            if (!form) return;

                            const checkboxes = document.querySelectorAll('.competitor-checkbox');
                            const spinner = document.getElementById('compPagespeedSpinner');
                            const errorBox = document.getElementById('competitorSelectError');
                            const btn = document.getElementById('btnSelectCompetitors');

                            const selectUrl = "{{ route('pages.projects.competitors.select', $project) }}";
                            const statusUrl = "{{ route('pages.projects.competitor-pagespeed.status', $project) }}";
                            let pollTimer = null;

                            function poll() {
                                fetch(statusUrl)
                                    .then(res => res.json())
                                    .then(data => {
                                        if (data.status === 'done') {
                                            clearInterval(pollTimer);
                                            window.location.reload();
                                        } else if (data.status === 'error') {
                                            clearInterval(pollTimer);
                                            btn.disabled = false;
                                            spinner.classList.add('hidden');
                                            errorBox.textContent = data.message || 'Failed to analyze.';
                                            errorBox.classList.remove('hidden');
                                        }
                                    });
                            }

                            form.addEventListener('submit', function(e) {
                                e.preventDefault();
                                errorBox.classList.add('hidden');
                                btn.disabled = true;
                                spinner.classList.remove('hidden');

                                fetch(selectUrl, {
                                        method: 'POST',
                                        headers: {
                                            Accept: 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                                                '',
                                        },
                                        body: new FormData(form),
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
                                            pollTimer = setInterval(poll, 4000);
                                        } else {
                                            btn.disabled = false;
                                            spinner.classList.add('hidden');
                                            errorBox.textContent = data.message || 'Failed to start analysis.';
                                            errorBox.classList.remove('hidden');
                                        }
                                    })
                                    .catch(() => {
                                        btn.disabled = false;
                                        spinner.classList.add('hidden');
                                        errorBox.textContent = 'Failed to start analysis. Try again.';
                                        errorBox.classList.remove('hidden');
                                    });
                            });

                            checkboxes.forEach(cb => cb.addEventListener('change', function() {
                                const checked = document.querySelectorAll('.competitor-checkbox:checked');
                                if (checked.length > 3) {
                                    this.checked = false;
                                    errorBox.textContent = 'Maximum 3 competitors (including those added manually).';
                                    errorBox.classList.remove('hidden');
                                } else {
                                    errorBox.classList.add('hidden');
                                }
                            }));
                        })();
                    </script>
                @else
                    <p class="empty-note text-xs text-slate-600">No competitors found for this project
                        yet.</p>
                @endif
            </div>
        @endif

        <script>
            const SEO_BACKLINK_ANALYZE_URL = "{{ route('pages.projects.seo-backlink.analyze', $project) }}";
            const SEO_BACKLINK_STATUS_URL = "{{ route('pages.projects.seo-backlink.status', $project) }}";

            (function() {
                const box = document.getElementById('aiAnalysisBox');
                if (!box) return;

                const idleState = document.getElementById('aiIdleState');
                const progressState = document.getElementById('aiProgressState');
                const errorState = document.getElementById('aiErrorState');
                const progressBar = document.getElementById('aiProgressBar');
                const progressMessage = document.getElementById('aiProgressMessage');
                const errorMessage = document.getElementById('aiErrorMessage');
                const btnStart = document.getElementById('btnStartAnalysis');
                const btnRetry = document.getElementById('btnRetryAnalysis');
                const btnRerun = document.getElementById('btnRerunAnalysis');

                let pollTimer = null;

                function showState(state) {
                    idleState.classList.toggle('hidden', state !== 'idle');
                    progressState.classList.toggle('hidden', state !== 'progress');
                    errorState.classList.toggle('hidden', state !== 'error');
                }

                function startPolling() {
                    showState('progress');
                    pollTimer = setInterval(checkStatus, 2500);
                    checkStatus();
                }

                function checkStatus() {
                    fetch(SEO_BACKLINK_STATUS_URL, {
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
                                errorMessage.textContent = data.message || 'Analysis failed, please try again.';
                                showState('error');
                            }
                        })
                        .catch(() => {});
                }

                function triggerAnalysis() {
                    fetch(SEO_BACKLINK_ANALYZE_URL, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                        })
                        .then(res => res.json())
                        .then(() => startPolling())
                        .catch(() => {
                            errorMessage.textContent = 'Failed to start analysis. Try again.';
                            showState('error');
                        });
                }

                btnStart?.addEventListener('click', triggerAnalysis);
                btnRetry?.addEventListener('click', triggerAnalysis);
                btnRerun?.addEventListener('click', triggerAnalysis);
            })();
        </script>

        <script>
            (function() {
                const form = document.getElementById('keywordSelectForm');
                if (!form) return;

                const checkboxes = document.querySelectorAll('.keyword-checkbox');
                const countLabel = document.getElementById('keywordSelectedCount');
                const savedMsg = document.getElementById('keywordSelectSaved');
                const errorMsg = document.getElementById('keywordSelectError');
                const btn = document.getElementById('btnSaveKeywordSelection');

                const selectUrl = "{{ route('pages.projects.keywords.select', $project) }}";

                function updateCount() {
                    const n = document.querySelectorAll('.keyword-checkbox:checked').length;
                    countLabel.textContent = n + ' selected';
                }

                checkboxes.forEach(cb => cb.addEventListener('change', updateCount));
                updateCount();

                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    savedMsg.classList.add('hidden');
                    errorMsg.classList.add('hidden');
                    btn.disabled = true;

                    fetch(selectUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                                    '',
                            },
                            body: new FormData(form),
                        })
                        .then(res => res.json().then(data => ({
                            ok: res.ok,
                            data
                        })))
                        .then(({
                            ok,
                            data
                        }) => {
                            btn.disabled = false;
                            if (ok && data.success) {
                                savedMsg.classList.remove('hidden');
                            } else {
                                errorMsg.textContent = data.message || 'Failed to save selection.';
                                errorMsg.classList.remove('hidden');
                            }
                        })
                        .catch(() => {
                            btn.disabled = false;
                            errorMsg.textContent = 'Failed to save selection. Try again.';
                            errorMsg.classList.remove('hidden');
                        });
                });
            })();
        </script>
    @endif
</x-card>
