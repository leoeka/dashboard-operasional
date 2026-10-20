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
        tahap yang bisa ditekan; kolom kanan: isi tahap yang dipilih. Jalur Backlink
        belum dibuat sebagai jalur sendiri karena publishing-nya belum ada, jadi hanya
        kebutuhan backlink yang ikut muncul di kartu Kebutuhan jalur SEO. --}}
        @php
            $proposal = $project->latestProposal;
            $proposalData = json_decode((string) ($proposal->ai_reasoning ?? ''), true) ?: [];
            $mockupCandidates = $proposalData['mockup_candidates'] ?? [];
            if (!is_array($mockupCandidates) || $mockupCandidates === []) {
                $mockupCandidates = isset($proposalData['mockup']) ? [$proposalData['mockup']] : [];
            }

            // Jawaban klien atas tiga opsi mockup: pilihan + approval dicatat
            // dalam satu aksi di kartu (lihat workspace.web-mockup). Build
            // WordPress membaca kedua state ini, tidak pernah menerima index
            // dari form, jadi ZIP tidak bisa dibuat dari opsi lain.
            $selectedMockupIndex = is_int($proposalData['selected_mockup_index'] ?? null)
                ? $proposalData['selected_mockup_index']
                : null;
            $mockupChoice = $proposal?->status ?? 'pending';
            $clientChose = $mockupChoice === 'approved' && $selectedMockupIndex !== null;

            // Tahap "Mockup & build" belum selesai hanya karena klien sudah
            // memilih: hasilnya adalah ZIP, jadi titik di rel baru muncul
            // setelah build terakhir berhasil di-export.
            $latestBundle = $project->bundles()
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();
            $wordpressReady = $latestBundle?->status === 'exported';

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

            $tracks = [
                'web' => [
                    'label' => 'Pembuatan Web',
                    'steps' => [
                        [
                            'key' => 'proposal',
                            'label' => 'Proposal',
                            'done' => (bool) $proposal,
                            'views' => ['workspace.web-proposal'],
                        ],
                        [
                            'key' => 'mockup',
                            'label' => 'Mockup & build',
                            'done' => $wordpressReady,
                            'views' => ['workspace.web-mockup'],
                        ],
                    ],
                ],
            ];

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
            $webAllDone = false;
            $seoHasOpen = false;
            foreach ($tracks as $trackKey => $track) {
                $open = $track['steps'][0]['key'];
                foreach ($track['steps'] as $stage) {
                    if (!$stage['done']) {
                        $open = $stage['key'];
                        break;
                    }
                }
                $initialStep[$trackKey] = $open;
                $unfinished = collect($track['steps'])->contains(fn($s) => !$s['done']);
                if ($trackKey === 'web') {
                    $webAllDone = !$unfinished;
                } else {
                    $seoHasOpen = $unfinished;
                }
            }
            $initialTrack = ($webAllDone && $seoHasOpen) ? 'seo' : 'web';
            $stageKeys = collect($tracks)
                ->map(fn($track) => collect($track['steps'])->pluck('key')->all())
                ->all();
        @endphp

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
                                <i class='bx bx-{{ $trackKey === 'web' ? 'window-alt' : 'line-chart' }} shrink-0'
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

        <x-progress-bar name="mockup-progress-modal" title="Generate" />
        <x-progress-bar name="bundle-progress-modal" title="Build WordPress" />

        <script>
            function startGenerateProposal(projectId) {
                const btn = document.getElementById('generate-proposal-btn');
                const idleWarning = document.getElementById('mockup-idle-warning');

                if (btn) {
                    btn.disabled = true;
                    btn.querySelector('span').innerText = 'Processing...';
                }
                idleWarning?.classList.add('hidden');

                ProgressModal.open('mockup-progress-modal');
                ProgressModal.update('mockup-progress-modal', {
                    percent: 0,
                    message: 'Starting process...',
                    status: 'processing',
                });

                fetch(`/projects/${projectId}/proposal/generate`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                }).then(response => {
                    if (!response.ok) {
                        throw new Error(`Generate failed (${response.status})`);
                    }

                    pollProposalStatus(projectId);
                }).catch(error => {
                    ProgressModal.update('mockup-progress-modal', {
                        percent: 0,
                        message: error.message || 'Failed to start proposal generation.',
                        status: 'failed',
                    });
                    if (btn) {
                        btn.disabled = false;
                        btn.querySelector('span').innerText = 'Generate Proposal';
                    }
                    idleWarning?.classList.remove('hidden');
                });

                function pollProposalStatus(projectId) {
                    fetch(`/projects/${projectId}/proposal/status`, {
                            headers: {
                                'Accept': 'application/json'
                            },
                        })
                        .then(res => {
                            if (!res.ok) {
                                throw new Error(`Failed to get proposal status (${res.status})`);
                            }
                            return res.json();
                        })
                        .then(data => {
                            const pct = data.progress ?? 0;
                            const status = ['done', 'completed'].includes(data.status) ? 'done' : data.status === 'failed' ?
                                'failed' :
                                'processing';

                            ProgressModal.update('mockup-progress-modal', {
                                percent: pct,
                                message: data.message || '',
                                status,
                            });

                            if (status === 'done') {
                                setTimeout(() => window.location.reload(), 800);
                            } else if (data.status === 'failed') {
                                if (btn) {
                                    btn.disabled = false;
                                    btn.querySelector('span').innerText = 'Generate Proposal';
                                }
                                idleWarning?.classList.remove('hidden');
                            } else {
                                setTimeout(() => pollProposalStatus(projectId), 1500);
                            }
                        })
                        .catch(error => {
                            ProgressModal.update('mockup-progress-modal', {
                                percent: 0,
                                message: error.message || 'Failed to read proposal status.',
                                status: 'failed',
                            });
                            if (btn) {
                                btn.disabled = false;
                                btn.querySelector('span').innerText = 'Generate Proposal';
                            }
                            idleWarning?.classList.remove('hidden');
                        });
                }
            }

            // Build sama sekali tidak menunggu di request ini: tombol hanya
            // mengirim job ke antrean, lalu lingkaran progres di atas membaca
            // /bundle/status tiap 1,5 detik sampai ZIP-nya jadi.
            function startBuildWebsite(projectId) {
                const buttons = document.querySelectorAll('[data-build-button]');
                const setBusy = (busy) => buttons.forEach((btn) => {
                    btn.disabled = busy;
                    const label = btn.querySelector('[data-build-label]');
                    if (label) {
                        label.innerText = busy ? 'Build sedang berjalan...' : label.dataset.idleLabel;
                    }
                });

                setBusy(true);
                ProgressModal.open('bundle-progress-modal');
                ProgressModal.update('bundle-progress-modal', {
                    percent: 0,
                    message: 'Build masuk antrean...',
                    status: 'processing',
                });

                fetch(`/projects/${projectId}/bundle/build`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                }).then((response) => {
                    if (!response.ok) {
                        return response.json()
                            .catch(() => ({}))
                            .then((body) => {
                                throw new Error(body.error || `Build gagal dimulai (${response.status}).`);
                            });
                    }
                    pollBundleStatus(projectId);
                }).catch((error) => {
                    ProgressModal.update('bundle-progress-modal', {
                        percent: 0,
                        message: error.message || 'Build WordPress belum bisa dimulai.',
                        status: 'failed',
                    });
                    setBusy(false);
                });

                function pollBundleStatus(projectId) {
                    fetch(`/projects/${projectId}/bundle/status`, {
                            headers: {
                                'Accept': 'application/json'
                            },
                        })
                        .then((res) => {
                            if (!res.ok) {
                                throw new Error(`Gagal membaca status build (${res.status})`);
                            }
                            return res.json();
                        })
                        .then((data) => {
                            const pct = data.progress ?? 0;
                            const status = ['done', 'completed'].includes(data.status) ? 'done' : data.status === 'failed' ?
                                'failed' :
                                'processing';

                            ProgressModal.update('bundle-progress-modal', {
                                percent: pct,
                                message: data.message || '',
                                status,
                            });

                            if (status === 'done') {
                                setTimeout(() => window.location.reload(), 800);
                            } else if (status === 'failed') {
                                setBusy(false);
                            } else {
                                setTimeout(() => pollBundleStatus(projectId), 1500);
                            }
                        })
                        .catch((error) => {
                            ProgressModal.update('bundle-progress-modal', {
                                percent: 0,
                                message: error.message || 'Gagal membaca status build.',
                                status: 'failed',
                            });
                            setBusy(false);
                        });
                }

                return false;
            }
        </script>
    @endif

@endsection
