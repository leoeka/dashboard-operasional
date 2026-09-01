@extends('layouts.app')

@section('title', 'Proposal Workshop')

@section('content')
    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600">AI Production Workspace</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-800">Website Proposal Workshop</h1>
                <p class="mt-1 text-sm text-slate-500">Request → AI Analysis → Website Mockup → Proposal PDF</p>
            </div>
            @if ($project)
                <button type="button" id="generate-proposal-btn" onclick="startGenerateProposal({{ $project->id }})"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <i class='bx bx-magic-wand text-lg'></i>
                    <span>{{ $project->latestProposal ? 'Regenerate Proposal with AI' : 'Generate Proposal with AI' }}</span>
                </button>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <label for="project" class="text-sm font-semibold text-slate-700">Project</label>
                <select id="project" name="project" onchange="this.form.submit()" class="min-w-0 flex-1 rounded-lg border-slate-200 bg-slate-50 text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Select a project to open its workshop</option>
                    @foreach ($projects as $item)
                        <option value="{{ $item->id }}" @selected($project?->id === $item->id)>{{ $item->name }} — {{ $item->client_name }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if (!$project)
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-20 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100 text-blue-600"><i class='bx bx-cube-alt text-3xl'></i></div>
                <h2 class="mt-4 font-semibold text-slate-800">Open a project workspace</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">Select a project to review its request, generate AI output, and open the completed proposal.</p>
            </div>
        @else
            @php
                $proposal = $project->latestProposal;
                $design = $mockup['design'] ?? [];
                $pages = $mockup['pages'] ?? [];
                $home = collect($pages)->first(fn ($page) => strtolower($page['name'] ?? '') === 'home');
                $sections = $home['sections'] ?? [];
                $hero = collect($sections)->first(fn ($section) => strtolower($section['type'] ?? $section['name'] ?? '') === 'hero') ?? ($sections[0] ?? []);
                $structure = data_get($analysis, 'recommended_structure', data_get($analysis, 'sitemap.primary_navigation', []));
                $features = data_get($analysis, 'recommended_features', []);
            @endphp

            <div id="proposal-progress" class="hidden rounded-xl border border-blue-200 bg-blue-50 p-4">
                <div class="flex items-center justify-between gap-3 text-sm"><span id="proposal-progress-message" class="font-medium text-blue-800">Starting process…</span><span id="proposal-progress-value" class="font-bold text-blue-700">0%</span></div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-blue-100"><div id="proposal-progress-bar" class="h-full rounded-full bg-blue-600 transition-all" style="width:0%"></div></div>
            </div>

            <div class="grid gap-6 xl:grid-cols-5">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><i class='bx bx-clipboard text-xl'></i></div>
                        <div><h2 class="font-bold text-slate-800">1. Project Request</h2><p class="text-xs text-slate-400">Source data provided by the client</p></div>
                    </div>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Client / Website</dt><dd class="mt-1 font-semibold text-slate-700">{{ $project->client?->company_name ?? $project->client_name }} <span class="font-normal text-slate-400">· {{ $project->name }}</span></dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Website Type</dt><dd class="mt-1 text-slate-700">{{ $project->type ?? 'Not specified' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Business Brief</dt><dd class="mt-1 leading-relaxed text-slate-600">{{ $project->description ?? 'No business brief has been added.' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Target Market</dt><dd class="mt-1 leading-relaxed text-slate-600">{{ $project->target_market ?? 'Not specified' }}</dd></div>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">Logo: {{ $project->client?->logo_path ? 'available' : 'optional / none' }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">Reference: {{ $project->design_reference_type && $project->design_reference_type !== 'none' ? $project->design_reference_type : 'optional / none' }}</span>
                        </div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
                    <div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600"><i class='bx bx-brain text-xl'></i></div><div><h2 class="font-bold text-slate-800">2. AI Analysis</h2><p class="text-xs text-slate-400">Business and website decisions used by the mockup</p></div></div>
                    @if (!$proposal)
                        <div class="mt-8 rounded-xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-400">Generate the proposal to see the analysis.</div>
                    @else
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            @foreach (['Business Overview' => data_get($analysis, 'business_analysis.value_proposition', data_get($analysis, 'business_overview')), 'Target Market' => data_get($analysis, 'target_market.demographics', data_get($analysis, 'target_market')), 'Website Goal' => data_get($analysis, 'website_objective.primary_goal', data_get($analysis, 'website_goal')), 'Design Direction' => data_get($analysis, 'design_direction', data_get($design, 'style'))] as $label => $value)
                                <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-semibold text-slate-500">{{ $label }}</p><p class="mt-1 text-sm leading-relaxed text-slate-700">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value ?: '—') }}</p></div>
                            @endforeach
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div><p class="text-xs font-semibold text-slate-500">Recommended Structure</p><div class="mt-2 flex flex-wrap gap-1.5">@foreach ((array) $structure as $item)<span class="rounded-md bg-blue-50 px-2 py-1 text-xs text-blue-700">{{ is_array($item) ? implode(', ', $item) : $item }}</span>@endforeach</div></div>
                            <div><p class="text-xs font-semibold text-slate-500">Recommended Features</p><div class="mt-2 flex flex-wrap gap-1.5">@foreach ((array) $features as $item)<span class="rounded-md bg-emerald-50 px-2 py-1 text-xs text-emerald-700">{{ is_array($item) ? implode(', ', $item) : $item }}</span>@endforeach</div></div>
                        </div>
                    @endif
                </section>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3"><div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><i class='bx bx-palette text-xl'></i></div><div><h2 class="font-bold text-slate-800">3. Mockup Result</h2><p class="text-xs text-slate-400">Visual blueprint generated from AI analysis</p></div></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ $proposal ? 'Ready for review' : 'Waiting for generation' }}</span></div>
                @if ($proposal)
                    <div class="mx-auto mt-5 max-w-4xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between px-4 py-3 text-xs font-semibold text-white" style="background:{{ $design['primary_color'] ?? '#1e3a5f' }}"><span>{{ strtoupper($project->name) }}</span><span>HOME · ABOUT · SERVICES · CONTACT</span></div>
                        <div class="p-7 text-white" style="background:{{ $design['primary_color'] ?? '#1e3a5f' }}"><p class="max-w-xl text-2xl font-bold leading-tight">{{ $hero['headline'] ?? $project->name }}</p><p class="mt-3 max-w-xl text-sm text-white/80">{{ $hero['description'] ?? '' }}</p>@if (!empty($hero['cta'] ?? $mockup['global_cta'] ?? null))<span class="mt-5 inline-flex rounded-lg bg-white px-3 py-2 text-xs font-bold" style="color:{{ $design['primary_color'] ?? '#1e3a5f' }}">{{ $hero['cta'] ?? $mockup['global_cta'] }}</span>@endif</div>
                        <div class="grid gap-3 p-4 sm:grid-cols-3">@foreach (array_slice($sections, 1, 6) as $section)<div class="rounded-lg border border-slate-100 bg-slate-50 p-3"><p class="text-sm font-semibold text-slate-800">{{ $section['headline'] ?? $section['name'] ?? 'Section' }}</p><p class="mt-1 text-xs leading-relaxed text-slate-500">{{ str($section['description'] ?? '')->limit(115) }}</p></div>@endforeach</div>
                    </div>
                @else
                    <div class="mt-5 rounded-xl border border-dashed border-slate-200 p-10 text-center text-sm text-slate-400">The full website mockup will appear here after AI generation.</div>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-500"><i class='bx bxs-file-pdf text-xl'></i></div><div><h2 class="font-bold text-slate-800">4. Proposal PDF</h2><p class="text-xs text-slate-400">Analysis, mockup, and project proposal in one document</p></div></div>@if ($proposal?->pdf_path)<div class="flex gap-3"><a href="{{ route('pages.projects.proposal.preview', $project) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Open preview</a><a href="{{ route('pages.projects.proposal.download', $project) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Download</a></div>@endif</div>
            </section>
        @endif
    </div>

    @if ($project)
        <script>
            function startGenerateProposal(projectId) {
                const button = document.getElementById('generate-proposal-btn');
                const progress = document.getElementById('proposal-progress');
                const message = document.getElementById('proposal-progress-message');
                const value = document.getElementById('proposal-progress-value');
                const bar = document.getElementById('proposal-progress-bar');
                button.disabled = true; progress.classList.remove('hidden');
                fetch(`/projects/${projectId}/proposal/generate`, { method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'} })
                    .then(response => { if (!response.ok) throw new Error('Failed to start generation.'); return poll(); })
                    .catch(error => { message.textContent = error.message; button.disabled = false; });
                function poll() {
                    fetch(`/projects/${projectId}/proposal/status`, {headers:{'Accept':'application/json'}}).then(r => r.json()).then(data => {
                        const percent = data.progress || 0; bar.style.width = percent + '%'; value.textContent = percent + '%'; message.textContent = data.message || 'Processing…';
                        if (['completed', 'done'].includes(data.status)) setTimeout(() => window.location.reload(), 600);
                        else if (data.status === 'failed') button.disabled = false;
                        else setTimeout(poll, 1500);
                    }).catch(() => { message.textContent = 'Unable to read generation progress.'; button.disabled = false; });
                }
            }
        </script>
    @endif
@endsection
