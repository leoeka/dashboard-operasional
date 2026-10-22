@extends('layouts.app')
@section('title', 'Workspace')

@section('content')

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-2">
        <h1 class="text-2xl font-bold text-slate-800">Project Workspace</h1>
        @if ($project)
            <div class="flex flex-col sm:flex-row gap-2 w-fit">
                <a href="{{ route('pages.projects.seo-backlink.report.download', $project) }}"
                    class="min-h-11 inline-flex items-center gap-2 text-xs font-semibold text-brand-600 hover:text-brand-700 border border-slate-300 hover:bg-brand-50 px-3 py-2 rounded-md transition w-fit">
                    <i class='bx bx-download'></i> Download Report (PDF)
                </a>
            </div>
        @endif
    </div>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-md bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 px-4 py-3 rounded-md bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-6 px-4 py-3 rounded-md bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
    @endif

    @if (!$project)
        <a href="{{ route('pages.projects') }}"
            class="group block bg-white border-2 border-dashed border-slate-200 rounded-2xl px-6 py-12 sm:py-16 text-center hover:border-brand-300 hover:bg-brand-50/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 transition">
            <div
                class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-brand-600 ring-8 ring-brand-50/60 group-hover:bg-brand-100 group-hover:ring-brand-100/60 transition">
                <i class='bx bx-folder-open text-3xl' aria-hidden="true"></i>
            </div>
            <h3 class="text-base font-semibold text-slate-800">
                Project tidak tersedia.
            </h3>
            <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                Pilih salah satu project terlebih dahulu untuk melihat dan mengelola di sini.
            </p>
            <span
                class="mt-6 inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-brand-600 px-5 py-2 text-sm font-semibold text-white group-hover:bg-brand-700 transition">
                Buka menu Project
                <i class='bx bx-right-arrow-alt text-lg transition-transform group-hover:translate-x-1'
                    aria-hidden="true"></i>
            </span>
        </a>
    @else
        {{-- Satu layar, satu jalur kerja. Kolom kiri: identitas project lalu daftar
        tahap yang bisa ditekan; kolom kanan: isi tahap yang dipilih. Jalur Mockup
        memimpin untuk project website (produk utama); jalur Backlink belum dibuat
        sebagai jalur sendiri karena publishing-nya belum ada, jadi hanya kebutuhan
        backlink yang ikut muncul di kartu Kebutuhan jalur SEO. --}}
        @php
            $seo = $project->seo_requirements ?? [];
            $backlink = $project->backlink_requirements ?? [];
            $cmsPlatform = $seo['cms_platform'] ?? null;

            $keywordList = collect(explode(',', $seo['keywords'] ?? ''))
                ->map(fn($k) => trim($k))
                ->filter()
                ->values();

            // URL kebutuhan hanya dari layanan yang masih dicentang: form edit
            // tidak menghapus blob lama, jadi data basi tidak boleh membuat
            // langkah "Kebutuhan" terlihat selesai.
            $seoUrl = $project->wants_seo ? ($seo['target_url'] ?? null) : null;
            $backlinkUrl = $project->wants_backlink ? ($backlink['target_url'] ?? null) : null;
            $resolvedUrl = $seoUrl ?: $backlinkUrl;
            $aiRecommendations = $seo['ai_recommendations'] ?? null;
            $aiTopics = $seo['ai_identified_topics'] ?? null;
            $discoveredCompetitors = collect(explode("\n", $seo['competitors'] ?? ''))
                ->map(fn($u) => trim($u))
                ->filter()
                ->values();

            $pagespeed = $seo['pagespeed'] ?? null;
            $searchConsole = $seo['search_console'] ?? null;
            $ga4 = $seo['google_analytics'] ?? null;

            $tracks = [];

            if (in_array('website', $project->serviceTypes())) {
                $tracks['mockup'] = [
                    'label' => 'Mockup',
                    'icon' => 'bx-palette',
                    'steps' => [
                        [
                            'key' => 'tinjau',
                            'label' => 'Tinjau mockup',
                            'done' => $mockupSet->count() === 3 && $mockupSet->every(
                                fn ($candidate) => in_array($candidate->status, [
                                    \App\Models\MockupCandidate::STATUS_TEAM_APPROVED,
                                    \App\Models\MockupCandidate::STATUS_SENT,
                                    \App\Models\MockupCandidate::STATUS_CHOSEN,
                                    \App\Models\MockupCandidate::STATUS_REJECTED,
                                ], true)
                            ),
                            'views' => ['workspace.mockup-gate'],
                        ],
                    ],
                ];
            }

            if ($project->wants_seo || $project->wants_backlink) {
                $seoTrackLabel = match (true) {
                    $project->wants_seo && $project->wants_backlink => 'SEO & Backlink',
                    $project->wants_seo => 'SEO',
                    default => 'Backlink',
                };
                $tracks['seo'] = [
                    'label' => $seoTrackLabel,
                    'steps' => [
                        [
                            'key' => 'kebutuhan',
                            'label' => 'Kebutuhan',
                            'done' => (bool) $resolvedUrl,
                            'views' => ['workspace.seo-requirements'],
                        ],
                        [
                            'key' => 'analisis',
                            'label' => 'Analisis AI',
                            'done' => (bool) $aiRecommendations,
                            'views' => ['workspace.seo-analysis'],
                        ],
                        [
                            'key' => 'performa',
                            'label' => 'Performa',
                            'done' => (bool) $pagespeed,
                            'views' => ['workspace.seo-pagespeed', 'workspace.seo-geo'],
                        ],
                        [
                            'key' => 'traffic',
                            'label' => 'Laporan traffic',
                            'done' => (bool) ($searchConsole ?: $ga4),
                            'views' => ['workspace.seo-search-console', 'workspace.seo-ga4'],
                        ],
                    ],
                ];
            }

            // Tahap yang dibuka lebih dulu: tahap nyata pertama yang belum selesai.
            $initialStep = [];
            foreach ($tracks as $trackKey => $track) {
                $open = $track['steps'][0]['key'];
                foreach ($track['steps'] as $stage) {
                    if (!$stage['done']) {
                        $open = $stage['key'];
                        break;
                    }
                }
                $initialStep[$trackKey] = $open;
            }
            $initialTrack = array_key_first($tracks);
            $stageKeys = collect($tracks)
                ->map(fn($track) => collect($track['steps'])->pluck('key')->all())
                ->all();
        @endphp

        @if (empty($tracks))
            {{-- Project murni SEO/Backlink tanpa jalur yang cocok, atau data yang
            belum lengkap: jangan tinggalkan rel dan panel kosong. --}}
            <x-card>
                <x-empty-state icon="bx-inbox" title="Belum ada jalur kerja untuk project ini"
                    description="Jalur kerja muncul otomatis mengikuti layanan yang dicentang pada project." />
            </x-card>
        @else
        <script>
            function workspaceRail(stageKeys, initial) {
                return {
                    stageKeys: stageKeys,
                    track: initial.track,
                    step: initial.step,
                    showTrack(key) {
                        this.track = key;
                        history.replaceState(null, '', '#' + this.step[key]);
                    },
                    show(track, key) {
                        this.track = track;
                        this.step[track] = key;
                        history.replaceState(null, '', '#' + key);
                    },
                    init() {
                        const hash = location.hash.slice(1);
                        if (!hash) {
                            return;
                        }
                        for (const track of Object.keys(this.stageKeys)) {
                            if (this.stageKeys[track].includes(hash)) {
                                this.track = track;
                                this.step[track] = hash;
                            }
                        }
                    },
                };
            }
        </script>

        <div x-data="workspaceRail(@js($stageKeys), @js(['track' => $initialTrack, 'step' => $initialStep]))"
            class="grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] gap-6 items-start">

            {{-- REL: identitas project + jalur + daftar tahap --}}
            <div class="space-y-4">
                @include('workspace.info')

                <x-card padding="p-3">
                    <div class="space-y-1">
                        @foreach ($tracks as $trackKey => $track)
                            <button type="button" @click="showTrack('{{ $trackKey }}')"
                                :class="track === '{{ $trackKey }}' ? 'bg-brand-50 text-brand-700 font-semibold' :
                                    'text-slate-700 hover:bg-slate-50'"
                                :aria-current="track === '{{ $trackKey }}' ? 'true' : null"
                                class="min-h-11 w-full inline-flex items-center gap-2 px-3 rounded-md text-sm text-left transition">
                                <i class='bx {{ $track['icon'] ?? 'bx-line-chart' }} shrink-0'
                                    aria-hidden="true"></i>
                                <span>{{ $track['label'] }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-2 border-t border-slate-100 pt-2">
                        @foreach ($tracks as $trackKey => $track)
                            <div x-show="track === '{{ $trackKey }}'" class="space-y-1">
                                @foreach ($track['steps'] as $stage)
                                    <button type="button"
                                        @click="show('{{ $trackKey }}', '{{ $stage['key'] }}')"
                                        :class="track === '{{ $trackKey }}' && step['{{ $trackKey }}'] === '{{ $stage['key'] }}' ?
                                            'bg-brand-50 text-brand-700 font-semibold' : 'text-slate-700 hover:bg-slate-50'"
                                        :aria-current="track === '{{ $trackKey }}' && step['{{ $trackKey }}'] === '{{ $stage['key'] }}' ?
                                            'page' : null"
                                        class="min-h-11 w-full inline-flex items-center gap-2 px-3 rounded-md text-sm text-left transition">
                                        @if ($stage['done'])
                                            <i class='bx bx-check-circle text-emerald-600 shrink-0'
                                                aria-hidden="true"></i>
                                        @else
                                            <i class='bx bx-circle text-slate-600 shrink-0' aria-hidden="true"></i>
                                        @endif
                                        <span>{{ $stage['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </x-card>
            </div>

            {{-- PANEL: kartu milik tahap yang dipilih --}}
            <div class="min-w-0">
                @foreach ($tracks as $trackKey => $track)
                    @foreach ($track['steps'] as $stage)
                        <div
                            x-show="track === '{{ $trackKey }}' && step['{{ $trackKey }}'] === '{{ $stage['key'] }}'">
                            @foreach ($stage['views'] as $partial)
                                @include($partial)
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
        @endif
    @endif

@endsection
