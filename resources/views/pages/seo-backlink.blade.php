@extends('layouts.app')
@section('title', 'Workspace')

@section('content')

    @if ($project)
        <a href="{{ route('pages.projects') }}" class="text-sm text-slate-400 flex items-center gap-1 mb-4 hover:text-slate-600">
            <i class='bx bx-arrow-back'></i> Back to Projects
        </a>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-2">
        <x-page-header title="Workspace" />
        @if ($project)
            <div class="flex flex-col sm:flex-row gap-2 w-fit">
                <a href="{{ route('pages.projects.seo-proposal.download', $project) }}"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-600 hover:text-emerald-800 border border-emerald-200 hover:bg-emerald-50 px-3 py-2 rounded-lg transition w-fit">
                    <i class='bx bx-file'></i> Download Proposal (PDF)
                </a>
                <a href="{{ route('pages.projects.seo-backlink.report.download', $project) }}"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-blue-600 hover:text-blue-800 border border-blue-200 hover:bg-blue-50 px-3 py-2 rounded-lg transition w-fit">
                    <i class='bx bx-download'></i> Download Report (PDF)
                </a>
            </div>
        @endif
    </div>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- PILIH PROJECT --}}
    <x-card class="mb-6">
        <form method="GET" class="flex flex-col sm:flex-row sm:items-center gap-3">
            <label class="text-sm font-medium text-slate-600">Project:</label>
            <select name="project" onchange="this.form.submit()"
                class="flex-1 bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none">
                <option value="">-- Select project --</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}" @selected($project && $project->id === $p->id)>
                        {{ $p->name }} ({{ $p->client_name }})
                    </option>
                @endforeach
            </select>
        </form>
    </x-card>

    @if (!$project)
        <x-card padding="p-16">
            <p class="text-sm text-slate-400 text-center">Select a project above to manage SEO & Backlink.</p>
        </x-card>
    @else
        {{-- INFORMASI UMUM --}}
        <x-card class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
                <div class="min-w-0">
                    <p class="font-bold text-slate-800 text-lg truncate">{{ $project->name }}</p>
                    <p class="text-sm text-slate-400">{{ $project->client_name }}</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <x-badge :color="$project->statusColor()">{{ $project->statusLabel() }}</x-badge>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-sm">
                <div>
                    <p class="text-slate-400 mb-1">Website Type</p>
                    <p class="font-medium text-slate-700">{{ $project->type ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 mb-1">Date Created</p>
                    <p class="font-medium text-slate-700">{{ $project->created_at->translatedFormat('d M Y') }}</p>
                </div>
            </div>
        </x-card>

        {{-- PROPOSAL --}}
        <x-card class="mb-6">
            <div class="space-y-4">
                {{-- Header --}}
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center shrink-0">
                            <i class='bx bx-file-blank text-xl'></i>
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-800 text-base">
                                Project Proposal
                            </h2>
                            <p class="text-xs text-slate-400">
                                Analysis & proposal document
                            </p>
                        </div>
                    </div>
                </div>
                {{-- Description --}}
                <p class="text-xs text-slate-500 leading-relaxed">
                    @if ($project->latestProposal)
                        The proposal has been successfully created based on the project request data. Here are
                        the analysis results and document.
                    @else
                        Generate a proposal based on the project request data.
                        The system will prepare a needs analysis, website strategy,
                        target market, and website structure.
                    @endif
                </p>
            </div>
            {{-- PREVIEW INLINE: AI Analysis, ringkasan, dan PDF viewer — langsung
            tampil di dalam kartu ini begitu proposal ada, tanpa tombol/halaman
            preview terpisah lagi. --}}
            @if ($project->latestProposal)
                @php $proposal = $project->latestProposal; @endphp
                <div class="pt-5 mt-5 border-t border-slate-100 space-y-4">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden h-[650px] flex flex-col">
                        <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-700 flex items-center gap-2">
                                <i class='bx bxs-file-pdf text-red-500 text-base'></i>
                                Document Viewer
                            </span>
                            @if ($proposal->pdf_path)
                                <a href="{{ Storage::url($proposal->pdf_path) }}" target="_blank"
                                    class="text-[11px] text-blue-600 hover:underline flex items-center gap-1">
                                    Open New Tab <i class='bx bx-link-external'></i>
                                </a>
                            @endif
                        </div>

                        <div class="flex-1 bg-slate-100">
                            @if ($proposal->pdf_path)
                                {{--
                                #toolbar=0&navpanes=0&scrollbar=0 mematikan toolbar
                                gelap + sidebar thumbnail bawaan viewer PDF browser,
                                biar yang kelihatan cuma dokumennya sendiri — bukan
                                "aplikasi PDF reader" di dalam kartu.
                                --}}
                                <iframe src="{{ Storage::url($proposal->pdf_path) }}#toolbar=0&navpanes=0&scrollbar=0"
                                    class="w-full h-full border-none" title="Proposal PDF Viewer">
                                </iframe>
                            @else
                                <div class="h-full flex flex-col items-center justify-center text-center p-6">
                                    <div
                                        class="w-16 h-16 bg-slate-200 text-slate-400 rounded-full flex items-center justify-center mb-3">
                                        <i class='bx bx-file-blank text-3xl'></i>
                                    </div>
                                    <h3 class="text-sm font-semibold text-slate-700">PDF File Not Available Yet</h3>
                                    <p class="text-xs text-slate-400 mt-1 max-w-sm">
                                        The proposal has not been generated yet, or the PDF file has not been saved on the server.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif


            {{-- Actions --}}
            <div class="pt-5 mt-4 border-t border-slate-100">
                @if ($project->latestProposal)
                    {{-- Download jika PDF sudah tersedia --}}
                    @if ($project->latestProposal->pdf_path)
                        <a href="{{ route('pages.projects.proposal.download', $project) }}"
                            class="w-full inline-flex items-center justify-center gap-2
                                bg-slate-100 text-slate-700 hover:bg-slate-200
                                text-xs font-semibold px-4 py-2.5 rounded-lg
                                active:scale-95 transition">
                            <i class='bx bx-download'></i>
                            Download Proposal PDF
                        </a>
                    @endif
                    <a href="{{ route('pages.projects.bundle', $project) }}"
                        class="mt-3 w-full inline-flex items-center justify-center gap-2
                            bg-slate-900 text-white hover:bg-slate-700
                            text-xs font-semibold px-4 py-2.5 rounded-lg
                            active:scale-95 transition">
                        <i class='bx bx-package'></i>
                        Lanjut ke Build WordPress dengan Claude
                    </a>
                @else
                    {{-- Generate pertama kali — JS-driven, memicu job async + progress bar di kartu Mockup di bawah --}}
                    <button type="button" id="generate-proposal-btn" onclick="startGenerateProposal({{ $project->id }})"
                        class="w-full inline-flex items-center justify-center gap-2
                               grad-blue text-white text-xs font-semibold
                               px-4 py-2.5 rounded-lg
                               hover:opacity-90 active:scale-95
                               shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed">
                        <i class='bx bx-magic-wand text-sm'></i>
                        <span>Generate</span>
                    </button>
                @endif
            </div>
        </x-card>

        {{-- ADD MOCKUP --}}
        <x-card class="mb-6">
            {{-- DISPLAY MOCKUP HASIL GENERATE / PILIHAN --}}
            @if ($project->mockupTemplate)
                <div class="mb-5 p-4 border border-slate-200 bg-slate-50 rounded-xl space-y-3">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-start gap-4">
                            {{-- Thumbnail kecil, tetap dari screenshot statis --}}
                            @if ($project->mockupTemplate->previewUrl())
                                <a href="{{ $project->mockupTemplate->previewUrl() }}" target="_blank"
                                    class="block flex-shrink-0 group relative">
                                    <img src="{{ $project->mockupTemplate->previewUrl() }}"
                                        class="w-16 h-16 rounded-lg object-cover border border-slate-200 group-hover:opacity-80 transition shadow-sm">
                                </a>
                            @endif
                        </div>

                        {{--
                        LIVE PREVIEW: embed situs demo asli dari ZipWP (source_url)
                        lewat iframe, bukan cuma gambar screenshot statis. Kalau
                        template ini nggak punya source_url (mis. template katalog
                        manual lama yang belum pernah diisi field itu), fallback
                        ke gambar screenshot statis biasa.

                        Tapi kalau site-nya SUDAH dihapus dari ZipWP (project sudah
                        "Done"), iframe jangan ditampilkan sama sekali — ZipWP bakal
                        nunjukin halaman "This site is deleted" mereka sendiri di
                        dalam iframe, yang bikin tim kira ada yang error/bug. Ganti
                        dengan pesan "project selesai" + screenshot statis terakhir.
                        --}}
                        @if (!$project->mockupTemplate->isLiveOnZipWp())
                            <div class="rounded-xl border border-slate-200 bg-emerald-50/60 p-5 text-center">
                                <div class="mx-auto mb-2 w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center">
                                    <i class='bx bx-check text-emerald-600 text-lg'></i>
                                </div>
                                <p class="text-sm font-semibold text-slate-700">Project is complete</p>
                                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                                    Live preview is no longer active because the ZipWP site was automatically deleted
                                    after the project was marked "Done". This is the last screenshot before
                                    the site was deleted:
                                </p>

                                @if ($project->mockupTemplate->previewUrl())
                                    <img src="{{ $project->mockupTemplate->previewUrl() }}"
                                        class="mt-4 mx-auto rounded-xl border border-slate-200 object-cover shadow-sm max-w-lg w-full">
                                @else
                                    <p class="text-xs text-slate-400 mt-3">Screenshot not available.</p>
                                @endif
                            </div>
                        @elseif ($project->mockupTemplate->source_url)
                            <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                                <div class="flex items-center justify-between px-3 py-2 bg-slate-100 border-b border-slate-200">
                                    <span class="text-xs font-medium text-slate-500 flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Live Preview
                                    </span>
                                    <a href="{{ $project->mockupTemplate->source_url }}" target="_blank" rel="noopener"
                                        class="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">
                                        Open in new tab <i class='bx bx-link-external'></i>
                                    </a>
                                </div>
                                <iframe src="{{ $project->mockupTemplate->source_url }}" loading="lazy"
                                    referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin"
                                    class="w-full aspect-video"
                                    title="Live preview mockup {{ $project->mockupTemplate->name }}">
                                </iframe>
                            </div>
                        @elseif ($project->mockupTemplate->previewUrl())
                            <img src="{{ $project->mockupTemplate->previewUrl() }}"
                                class="w-full rounded-xl border border-slate-200 object-cover shadow-sm">
                        @endif
                    </div>

                    {{-- Tombol Login ke WP-Admin + jalan pintas ke halaman situs di ZipWP --}}
                    <div class="pt-2 border-t border-slate-200/60 flex items-center gap-2">
                        @if ($project->mockupTemplate->source_url && $project->mockupTemplate->isLiveOnZipWp())
                            <a href="{{ rtrim($project->mockupTemplate->source_url, '/') }}/wp-admin" target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg hover:opacity-90 transition">
                                <i class='bx bx-log-in-circle'></i> Log in to WP-Admin
                            </a>
                        @endif

                        {{--
                        CATATAN: sebelumnya dicoba deep-link ke
                        https://app.zipwp.com/sites/{site_uuid} berdasarkan cek manual
                        di address bar, TAPI ternyata 404 — site_uuid yang kita simpan
                        (dari respons create-ai-site) rupanya beda dengan ID yang
                        dipakai ZipWP untuk routing halaman dashboard mereka. Karena
                        nggak ada dokumentasi resmi soal pola URL ini, aman-nya
                        arahkan ke dashboard umum saja — tetap perlu cari situsnya
                        manual, tapi minimal nggak nyasar ke 404.
                        --}}
                        <a href="https://app.zipwp.com" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-2 bg-slate-100 text-slate-600 text-xs font-semibold px-4 py-2 rounded-lg hover:bg-slate-200 transition">
                            <i class='bx bx-key'></i> Open ZipWP Dashboard (forgot password?)
                        </a>
                    </div>

                    {{--
                    TOMBOL DONE: muncul selama site masih hidup di ZipWP.
                    Diklik -> buka popup (dialog) peringatan wajib dibaca +
                    checkbox konfirmasi. Submit -> project ditandai status
                    'done' DAN site dihapus dari ZipWP, dua-duanya sekaligus.
                    --}}
                    @if ($project->mockupTemplate->isLiveOnZipWp())
                        <div class="pt-3 border-t border-slate-200/60">
                            <button type="button" x-data
                                x-on:click="$dispatch('open-modal', 'done-zipwp-{{ $project->id }}')"
                                class="inline-flex items-center gap-2 bg-emerald-600 text-white text-xs font-semibold px-4 py-2 rounded-lg hover:bg-emerald-700 transition">
                                <i class='bx bx-check-circle'></i> Done
                            </button>
                        </div>

                        <x-modal name="done-zipwp-{{ $project->id }}" maxWidth="md" focusable>
                            <div class="p-6">

                                <!-- ALERT BOX CONTAINER -->
                                <div class="bg-red-50 border-l-4 border-red-600 p-4 rounded-r-lg mb-5 shadow-sm">
                                    <div class="flex items-center gap-2 text-red-700 font-bold text-sm mb-2">
                                        <!-- Icon dengan animasi pulse -->
                                        <i class='bx bx-error-circle text-lg animate-pulse'></i>
                                        <span>CRITICAL WARNING — READ BEFORE PROCEEDING</span>
                                    </div>

                                    <ul class="text-xs text-red-950 space-y-2 list-disc list-inside leading-relaxed">
                                        <li>
                                            This project will be marked as <strong
                                                class="uppercase underline decoration-red-400">Done</strong>.
                                        </li>
                                        <li>
                                            <strong>Action Required:</strong> Ensure the website is <span
                                                class="bg-red-200/80 px-1 py-0.5 rounded font-semibold text-red-900">FULLY
                                                MIGRATED</span> to the client's web server.
                                        </li>
                                        <li>
                                            <strong>Do NOT click done if:</strong> The client still asks for revisions OR the
                                            migration process is not completely finished!
                                        </li>
                                    </ul>
                                </div>

                                <form method="POST" action="{{ route('pages.projects.mockup.zipwp-delete', $project) }}">
                                    @csrf
                                    @method('DELETE')

                                    <!-- CHECKBOX PERSETUJUAN -->
                                    <label
                                        class="flex items-start gap-2.5 text-xs text-slate-800 mb-6 cursor-pointer bg-slate-50 p-3 rounded-lg border border-slate-200 hover:bg-slate-100 transition">
                                        <input type="checkbox" name="confirm_migrated" value="1" required
                                            onchange="document.getElementById('btn-confirm-done-{{ $project->id }}').disabled = !this.checked;"
                                            class="mt-0.5 rounded border-slate-300 text-red-600 focus:ring-red-500">
                                        <span class="font-medium select-none">
                                            I have read the warning above and confirm that this site is <span
                                                class="underline font-bold text-red-600">100% migrated & safe</span> to be
                                            marked as completed.
                                        </span>
                                    </label>

                                    <!-- ACTION BUTTONS: CANCEL SEBAGAI PRIORITAS UTAMA -->
                                    <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                                        <!-- Secondary/Danger Action (Konfirmasi) dibuat subtle/kurang menonjol -->
                                        <button type="submit" id="btn-confirm-done-{{ $project->id }}" disabled
                                            class="text-xs font-semibold text-red-600 hover:text-red-800 hover:bg-red-50 px-3 py-2 rounded-lg transition disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-red-600 disabled:cursor-not-allowed">
                                            Yes, Mark as Done
                                        </button>

                                        <!-- Primary Action (Cancel) dibuat menonjol dengan background gelap & tebal -->
                                        <button type="button" x-on:click="$dispatch('close')"
                                            class="text-xs font-bold bg-slate-900 text-white hover:bg-slate-800 px-5 py-2.5 rounded-lg shadow-md transition transform active:scale-95 flex items-center gap-1.5">
                                            <i class='bx bx-x text-sm'></i> Cancel / Go Back
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </x-modal>
                    @endif
                </div>
            @else
                {{-- BELUM ADA MOCKUP: project baru dibuat, atau proposal belum
                di-generate. Progress ditampilkan lewat popup <x-progress-modal>
                (dipanggil dari script di bawah), bukan bar inline lagi. --}}
                <div id="mockup-idle-warning" class="text-center py-8 border border-dashed border-slate-200 rounded-xl">
                    <div
                        class="mx-auto mb-3 w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center">
                        <i class='bx bx-image-alt text-xl'></i>
                    </div>
                    <p class="text-sm font-medium text-slate-600">Mockup not yet created</p>
                    <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                        The mockup will be automatically generated once the proposal is successfully created. Please
                        generate the proposal first.
                    </p>
                </div>
            @endif
        </x-card>

        <x-progress-bar name="mockup-progress-modal" title="Generate" />

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
                            const status = ['done', 'completed'].includes(data.status) ? 'done' : data.status === 'failed' ? 'failed' :
                                'processing';

                            ProgressModal.update('mockup-progress-modal', {
                                percent: pct,
                                message: data.message || '',
                                status,
                            });

                            if (data.status === 'done') {
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
        </script>

        {{-- SEO & BACKLINK --}}
        @if ($project->wants_seo || $project->wants_backlink)
            @php
                $seo = $project->seo_requirements ?? [];
                $backlink = $project->backlink_requirements ?? [];
                $cmsPlatform = $seo['cms_platform'] ?? null;

                $isConnected = false; // TODO: ganti jadi cek kolom wp_application_password setelah fitur Connect Website dibangun

                $connectHint = match (true) {
                    $cmsPlatform === 'baru' => 'Get the username & password from the ZipWP sandbox dashboard, then paste them here.',
                    $cmsPlatform === 'wordpress' => 'Request WordPress access from the client, then paste it here.',
                    in_array($cmsPlatform, ['shopify', 'wix']) => 'This platform (' . ucfirst($cmsPlatform) . ') is not yet supported for automatic publishing — articles will be provided for manual download/copy.',
                    default => 'Ask the client about their website platform to enable automatic publishing.',
                };

                $keywordList = collect(explode(',', $seo['keywords'] ?? ''))
                    ->map(fn($k) => trim($k))
                    ->filter()
                    ->values();

                $resolvedUrl = $seo['target_url'] ?? $backlink['target_url'] ?? optional($project->mockupTemplate)->source_url ?? null;
                $aiRecommendations = $seo['ai_recommendations'] ?? null;
                $aiTopics = $seo['ai_identified_topics'] ?? null;
                $discoveredCompetitors = collect(explode("\n", $seo['competitors'] ?? ''))
                    ->map(fn($u) => trim($u))
                    ->filter()
                    ->values();

                $pagespeed = $seo['pagespeed'] ?? null;
                $searchConsole = $seo['search_console'] ?? null;
                $ga4 = $seo['google_analytics'] ?? null;
            @endphp

            <div
                x-data="{ tab: ['ringkasan', 'performa', 'traffic'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'ringkasan' }">

                {{-- NAVIGASI TAB --}}
                <div class="flex gap-1 mb-6 border-b border-slate-200 overflow-x-auto">
                    <button type="button" @click="tab = 'ringkasan'; location.hash = 'ringkasan'"
                        :class="tab === 'ringkasan' ? 'border-brand-500 text-brand-600 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-600'"
                        class="px-4 py-2.5 text-sm border-b-2 -mb-px transition whitespace-nowrap">
                        Summary
                    </button>
                    <button type="button" @click="tab = 'performa'; location.hash = 'performa'"
                        :class="tab === 'performa' ? 'border-brand-500 text-brand-600 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-600'"
                        class="px-4 py-2.5 text-sm border-b-2 -mb-px transition whitespace-nowrap">
                        Performance
                    </button>
                    <button type="button" @click="tab = 'traffic'; location.hash = 'traffic'"
                        :class="tab === 'traffic' ? 'border-brand-500 text-brand-600 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-600'"
                        class="px-4 py-2.5 text-sm border-b-2 -mb-px transition whitespace-nowrap">
                        Traffic Report
                    </button>
                </div>

                {{-- ===================== TAB: RINGKASAN ===================== --}}
                <div x-show="tab === 'ringkasan'">
                    <x-card class="mb-6">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="font-semibold text-slate-800">SEO & Backlink</h2>
                            @if ($isConnected)
                                <span
                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                    <i class='bx bx-check'></i> Connected
                                </span>
                            @else
                                <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
                                    Not connected
                                </span>
                            @endif
                        </div>

                        {{-- SEO --}}
                        @if ($project->wants_seo)
                            <div class="border-t border-slate-100 pt-4 mb-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class='bx bx-search text-blue-600'></i>
                                    <span class="text-sm font-medium text-slate-700">SEO Requirements</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                                    <div class="flex gap-2">
                                        <span class="text-slate-400 w-28 flex-shrink-0">Target URL</span>
                                        <span class="text-slate-700 break-all">{{ $seo['target_url'] ?: '-' }}</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="text-slate-400 w-28 flex-shrink-0">Location</span>
                                        <span class="text-slate-700">{{ $seo['location'] ?: '-' }}</span>
                                    </div>
                                    <div class="flex gap-2 sm:col-span-2">
                                        <span class="text-slate-400 w-28 flex-shrink-0">Keyword</span>
                                        <span class="text-slate-700">
                                            @forelse ($keywordList->take(4) as $kw)
                                                <span
                                                    class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-full mr-1 mb-1">{{ $kw }}</span>
                                            @empty
                                                -
                                            @endforelse
                                            @if ($keywordList->count() > 4)
                                                <span class="text-xs text-slate-400">+{{ $keywordList->count() - 4 }} more</span>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="text-slate-400 w-28 flex-shrink-0">Platform</span>
                                        <span>
                                            @if ($cmsPlatform)
                                                <span
                                                    class="inline-block bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded-full">{{ ucfirst($cmsPlatform === 'baru' ? 'New Website (from us)' : $cmsPlatform) }}</span>
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
                                    <i class='bx bx-link text-blue-600'></i>
                                    <span class="text-sm font-medium text-slate-700">Backlink Requirements</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                                    <div class="flex gap-2">
                                        <span class="text-slate-400 w-28 flex-shrink-0">Quantity</span>
                                        <span class="text-slate-700">{{ $backlink['quantity'] ?? '-' }} backlink</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="text-slate-400 w-28 flex-shrink-0">Priority</span>
                                        <span
                                            class="text-slate-700">{{ $backlink['priority'] === 'quality' ? 'Quality' : ($backlink['priority'] === 'quantity' ? 'Quantity' : '-') }}</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="text-slate-400 w-28 flex-shrink-0">Niche</span>
                                        <span class="text-slate-700">{{ $backlink['niche'] ?: '-' }}</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="text-slate-400 w-28 flex-shrink-0">Anchor type</span>
                                        <span class="text-slate-700">
                                            @forelse (($backlink['anchor_type'] ?? []) as $type)
                                                <span
                                                    class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-full mr-1">{{ str_replace('_', ' ', ucfirst($type)) }}</span>
                                            @empty
                                                -
                                            @endforelse
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- ACTIONS --}}
                        <div class="border-t border-slate-100 pt-4 flex flex-col sm:flex-row sm:items-center gap-3">
                            @if (!$isConnected)
                                <button type="button" disabled
                                    class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg opacity-60 cursor-not-allowed w-fit">
                                    <i class='bx bx-plug'></i> Connect Website
                                </button>
                                <p class="text-xs text-slate-400">{{ $connectHint }}</p>
                            @else
                                <button type="button" disabled
                                    class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg opacity-60 cursor-not-allowed w-fit">
                                    <i class='bx bx-magic-wand'></i> Generate Article
                                </button>
                                <p class="text-xs text-slate-400">Article generation feature not yet available</p>
                            @endif
                        </div>
                    </x-card>

                    {{-- KARTU ANALISIS AI — keyword & kompetitor otomatis --}}
                    <x-card class="mb-6">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="font-semibold text-slate-800">AI Analysis — Keyword & Competitor</h2>
                            @if ($aiRecommendations)
                                <span
                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                    <i class='bx bx-check'></i> Analyzed
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mb-4">Automatic keyword & competitor discovery via AI.</p>

                        @if (!$resolvedUrl)
                            <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                The client's website URL has not been filled in. Fill it in first on the
                                <a href="{{ route('pages.projects.edit', $project) }}" class="underline font-medium">Edit
                                    Project page</a>
                                — the analysis will run automatically once after the URL is saved, or it can be triggered
                                manually here afterward.
                            </div>
                        @else
                            <div id="aiAnalysisBox" data-project-id="{{ $project->id }}">
                                <div id="aiIdleState" class="{{ $aiRecommendations ? 'hidden' : '' }}">
                                    <button type="button" id="btnStartAnalysis"
                                        class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg">
                                        <i class='bx bx-magic-wand'></i> Start AI Analysis
                                    </button>
                                </div>

                                <div id="aiProgressState" class="hidden">
                                    <div class="w-full bg-slate-100 rounded-full h-2 mb-2 overflow-hidden">
                                        <div id="aiProgressBar" class="bg-blue-600 h-2 rounded-full transition-all" style="width:0%">
                                        </div>
                                    </div>
                                    <p id="aiProgressMessage" class="text-xs text-slate-500"></p>
                                </div>

                                <div id="aiErrorState"
                                    class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
                                    <p id="aiErrorMessage"></p>
                                    <button type="button" id="btnRetryAnalysis"
                                        class="mt-2 text-xs font-semibold text-red-700 underline">Try again</button>
                                </div>

                                @if ($aiRecommendations)
                                    <button type="button" id="btnRerunAnalysis"
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                                        Re-run analysis
                                    </button>
                                @endif
                            </div>

                            @if ($aiRecommendations)
                                <div class="border-t border-slate-100 mt-4 pt-4 space-y-4">
                                    @if (!empty($aiTopics['core_topics']))
                                        <div>
                                            <p class="text-xs text-slate-400 mb-1">Core topics detected</p>
                                            <div>
                                                @foreach ($aiTopics['core_topics'] as $topic)
                                                    <span
                                                        class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-full mr-1 mb-1">{{ $topic }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    {{-- ===== KANDIDAT KEYWORD — checkbox, tim pilih mana yang dipakai ===== --}}
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <p class="text-xs text-slate-400">
                                                Keyword Candidates ({{ count($aiRecommendations['main_keywords'] ?? []) }})
                                                <span
                                                    class="ml-1 inline-block text-[10px] px-1.5 py-0.5 rounded-full {{ ($aiRecommendations['data_source'] ?? '') === 'google_ads_api' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}">
                                                    {{ ($aiRecommendations['data_source'] ?? '') === 'google_ads_api' ? 'Google Ads Data' : 'AI Estimate' }}
                                                </span>
                                            </p>
                                            <span id="keywordSelectedCount" class="text-xs text-slate-400"></span>
                                        </div>

                                        <form id="keywordSelectForm">
                                            @csrf
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-sm">
                                                    <thead>
                                                        <tr class="text-left text-xs text-slate-400 border-b border-slate-100">
                                                            <th class="pb-2 pr-2 w-6"></th>
                                                            <th class="pb-2 pr-4">Keyword</th>
                                                            <th class="pb-2 pr-4">Volume/month</th>
                                                            <th class="pb-2 pr-4">Competition</th>
                                                            <th class="pb-2">Reason</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach (($aiRecommendations['main_keywords'] ?? []) as $kw)
                                                            <tr class="border-b border-slate-50">
                                                                <td class="py-1.5 pr-2">
                                                                    <input type="checkbox" name="selected_keywords[]"
                                                                        value="{{ $kw['keyword'] ?? '' }}" class="keyword-checkbox" {{ !empty($kw['selected']) ? 'checked' : '' }}>
                                                                </td>
                                                                <td class="py-1.5 pr-4 font-medium text-slate-700">{{ $kw['keyword'] ?? '-' }}
                                                                </td>
                                                                <td class="py-1.5 pr-4 text-slate-500">{{ $kw['avg_monthly_searches'] ?? '-' }}
                                                                </td>
                                                                <td class="py-1.5 pr-4 text-slate-500">{{ $kw['competition'] ?? '-' }}</td>
                                                                <td class="py-1.5 text-slate-500">{{ $kw['reasoning'] ?? '-' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div class="flex items-center gap-2 mt-3">
                                                <button type="submit" id="btnSaveKeywordSelection"
                                                    class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg">
                                                    <i class='bx bx-check'></i> Save Selection
                                                </button>
                                                <span id="keywordSelectSaved" class="hidden text-xs text-emerald-600">Saved.</span>
                                                <span id="keywordSelectError" class="hidden text-xs text-red-600"></span>
                                            </div>
                                        </form>
                                    </div>

                                    @if (!empty($aiRecommendations['related_keywords']))
                                        <div>
                                            <p class="text-xs text-slate-400 mb-1">Related keywords</p>
                                            <div>
                                                @foreach ($aiRecommendations['related_keywords'] as $kw)
                                                    <span
                                                        class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-full mr-1 mb-1">{{ $kw }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if (!empty($aiRecommendations['summary']))
                                        <div>
                                            <p class="text-xs text-slate-400 mb-1">Strategy summary</p>
                                            <p class="text-sm text-slate-600">{{ $aiRecommendations['summary'] }}</p>
                                        </div>
                                    @endif

                                    {{-- ===== PILIH & ANALISIS KOMPETITOR ===== --}}
                                    @if ($discoveredCompetitors->isNotEmpty())
                                        @php
                                            $selectedCompetitors = $seo['selected_competitors'] ?? $discoveredCompetitors->take(3)->values()->all();
                                            $competitorPagespeed = $seo['competitor_pagespeed'] ?? null;
                                        @endphp
                                        <div class="border-t border-slate-100 pt-4">
                                            <p class="text-xs text-slate-400 mb-2">Check up to 3 competitors to analyze:</p>

                                            <form id="competitorSelectForm">
                                                @csrf
                                                <ul class="text-sm space-y-1 mb-3">
                                                    @foreach ($discoveredCompetitors as $url)
                                                        <li class="flex items-center gap-2">
                                                            <input type="checkbox" name="selected_urls[]" value="{{ $url }}"
                                                                class="competitor-checkbox" {{ in_array($url, $selectedCompetitors) ? 'checked' : '' }}>
                                                            <a href="{{ $url }}" target="_blank" rel="noopener"
                                                                class="text-blue-600 hover:underline break-all">{{ $url }}</a>
                                                        </li>
                                                    @endforeach
                                                </ul>

                                                <textarea name="manual_urls" rows="2"
                                                    placeholder="Add manually, 1 URL per line: https://other-competitor-example.com/"
                                                    class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 mb-2"></textarea>

                                                <button type="submit" id="btnSelectCompetitors"
                                                    class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg">
                                                    <i class='bx bx-refresh'></i>
                                                    {{ $competitorPagespeed ? 'Re-select & Analyze' : 'Select & Analyze These Competitors' }}
                                                </button>
                                                <span id="compPagespeedSpinner" class="hidden text-xs text-slate-400 ml-2">Processing (can take
                                                    1-2 minutes)...</span>
                                                <p id="competitorSelectError" class="hidden text-xs text-red-600 mt-2"></p>
                                            </form>

                                            @if ($competitorPagespeed)
                                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                                                    @foreach ($competitorPagespeed as $comp)
                                                        @php $compHost = parse_url($comp['url'], PHP_URL_HOST); @endphp
                                                        <div class="text-center">
                                                            <p class="text-xs font-medium text-slate-600 mb-2 truncate" title="{{ $comp['url'] }}">
                                                                {{ $compHost }}
                                                            </p>
                                                            <div class="flex justify-center gap-1">
                                                                @include('pdf.partials.gauge', ['score' => $comp['scores']['performance'] ?? null, 'label' => 'Performance'])
                                                                @include('pdf.partials.gauge', ['score' => $comp['scores']['accessibility'] ?? null, 'label' => 'Accessibility'])
                                                                @include('pdf.partials.gauge', ['score' => $comp['scores']['best_practices'] ?? null, 'label' => 'Best Practices'])
                                                                @include('pdf.partials.gauge', ['score' => $comp['scores']['seo'] ?? null, 'label' => 'SEO'])
                                                            </div>

                                                            @if (!empty($comp['error']))
                                                                <p class="mt-2 text-[11px] text-red-600">{{ $comp['error'] }}</p>
                                                            @endif

                                                            <div class="mt-2 pt-2 border-t border-slate-100">
                                                                <a href="https://www.semrush.com/analytics/overview/?q={{ urlencode($compHost) }}&searchType=domain"
                                                                    target="_blank" rel="noopener"
                                                                    class="text-[11px] text-blue-600 hover:underline block">Open Semrush &rarr;</a>

                                                                @if (!empty($seo['manual_screenshots']['competitor_semrush'][$compHost]))
                                                                    <div class="mt-1">
                                                                        <img src="{{ Storage::url($seo['manual_screenshots']['competitor_semrush'][$compHost]) }}"
                                                                            class="max-w-[140px] mx-auto border rounded-lg">
                                                                        <form action="{{ route('pages.projects.manual-screenshot.destroy', $project) }}"
                                                                            method="POST" class="mt-1">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <input type="hidden" name="target" value="competitor_semrush:{{ $compHost }}">
                                                                            <button type="submit"
                                                                                class="text-[11px] text-red-600 hover:underline">Delete</button>
                                                                        </form>
                                                                    </div>
                                                                @else
                                                                    <form action="{{ route('pages.projects.manual-screenshot.store', $project) }}"
                                                                        method="POST" enctype="multipart/form-data" class="mt-1">
                                                                        @csrf
                                                                        <input type="hidden" name="target" value="competitor_semrush:{{ $compHost }}">
                                                                        <input type="file" name="screenshot" accept="image/*" required
                                                                            class="text-[11px] w-full">
                                                                        <button type="submit"
                                                                            class="text-[11px] bg-blue-600 text-white px-2 py-1 rounded mt-1">Upload</button>
                                                                    </form>
                                                                @endif
                                                            </div>

                                                            <div class="mt-2 pt-2 border-t border-slate-100">
                                                                <a href="https://pagespeed.web.dev/report?url={{ urlencode($comp['url']) }}"
                                                                    target="_blank" rel="noopener"
                                                                    class="text-[11px] text-blue-600 hover:underline block">Open PageSpeed &rarr;</a>

                                                                @if (!empty($seo['manual_screenshots']['competitor_pagespeed'][$compHost]))
                                                                    <div class="mt-1">
                                                                        <img src="{{ Storage::url($seo['manual_screenshots']['competitor_pagespeed'][$compHost]) }}"
                                                                            class="max-w-[140px] mx-auto border rounded-lg">
                                                                        <form action="{{ route('pages.projects.manual-screenshot.destroy', $project) }}"
                                                                            method="POST" class="mt-1">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <input type="hidden" name="target" value="competitor_pagespeed:{{ $compHost }}">
                                                                            <button type="submit"
                                                                                class="text-[11px] text-red-600 hover:underline">Delete</button>
                                                                        </form>
                                                                    </div>
                                                                @else
                                                                    <form action="{{ route('pages.projects.manual-screenshot.store', $project) }}"
                                                                        method="POST" enctype="multipart/form-data" class="mt-1">
                                                                        @csrf
                                                                        <input type="hidden" name="target" value="competitor_pagespeed:{{ $compHost }}">
                                                                        <input type="file" name="screenshot" accept="image/*" required
                                                                            class="text-[11px] w-full">
                                                                        <button type="submit"
                                                                            class="text-[11px] bg-blue-600 text-white px-2 py-1 rounded mt-1">Upload</button>
                                                                    </form>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>

                                        <script>
                                            (function () {
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

                                                form.addEventListener('submit', function (e) {
                                                    e.preventDefault();
                                                    errorBox.classList.add('hidden');
                                                    btn.disabled = true;
                                                    spinner.classList.remove('hidden');

                                                    fetch(selectUrl, {
                                                        method: 'POST',
                                                        headers: {
                                                            Accept: 'application/json',
                                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                                        },
                                                        body: new FormData(form),
                                                    })
                                                        .then(res => res.json().then(data => ({ ok: res.ok, data })))
                                                        .then(({ ok, data }) => {
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

                                                checkboxes.forEach(cb => cb.addEventListener('change', function () {
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
                                        <p class="empty-note text-xs text-slate-400">No competitors found for this project yet.</p>
                                    @endif
                                </div>
                            @endif

                            <script>
                                const SEO_BACKLINK_ANALYZE_URL = "{{ route('pages.projects.seo-backlink.analyze', $project) }}";
                                const SEO_BACKLINK_STATUS_URL = "{{ route('pages.projects.seo-backlink.status', $project) }}";

                                (function () {
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
                                        fetch(SEO_BACKLINK_STATUS_URL, { headers: { Accept: 'application/json' } })
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
                                            .catch(() => { });
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
                                (function () {
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

                                    form.addEventListener('submit', function (e) {
                                        e.preventDefault();
                                        savedMsg.classList.add('hidden');
                                        errorMsg.classList.add('hidden');
                                        btn.disabled = true;

                                        fetch(selectUrl, {
                                            method: 'POST',
                                            headers: {
                                                Accept: 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                            },
                                            body: new FormData(form),
                                        })
                                            .then(res => res.json().then(data => ({ ok: res.ok, data })))
                                            .then(({ ok, data }) => {
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
                </div>

                {{-- ===================== TAB: PERFORMA ===================== --}}
                <div x-show="tab === 'performa'">
                    <x-card class="mb-6">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="font-semibold text-slate-800">Website Performance (PageSpeed)</h2>
                            @if ($pagespeed)
                                <span
                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                    <i class='bx bx-check'></i> Analyzed
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mb-4">Speed & Core Web Vitals (Lighthouse).</p>

                        @if (!$resolvedUrl)
                            <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                The client's website URL has not been filled in. Fill it in first on the
                                <a href="{{ route('pages.projects.edit', $project) }}" class="underline font-medium">Edit
                                    Project page</a>.
                            </div>
                        @else
                            <div id="pagespeedBox">
                                <button type="button" id="btnAnalyzePagespeed"
                                    class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg">
                                    <i class='bx bx-tachometer'></i> {{ $pagespeed ? 'Re-run Analysis' : 'Analyze Performance' }}
                                </button>
                                <p class="text-xs text-slate-400 mt-1">Takes ~30-60 seconds.</p>

                                <div id="pagespeedProgress" class="hidden mt-3">
                                    <div class="w-full bg-slate-100 rounded-full h-2 mb-2 overflow-hidden">
                                        <div id="pagespeedProgressBar" class="bg-blue-600 h-2 rounded-full transition-all"
                                            style="width:0%"></div>
                                    </div>
                                    <p id="pagespeedProgressMessage" class="text-xs text-slate-500"></p>
                                </div>

                                <div id="pagespeedError"
                                    class="hidden mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3"></div>
                            </div>

                            @if ($pagespeed)
                                <div class="border-t border-slate-100 mt-4 pt-4">
                                    @foreach (['mobile' => 'Mobile', 'desktop' => 'Desktop'] as $key => $label)
                                        @php $d = $pagespeed[$key] ?? null; @endphp
                                        @if ($d)
                                            <div class="mb-5">
                                                <p class="text-xs font-semibold text-slate-600 mb-2">{{ $label }}</p>
                                                <div class="flex justify-center gap-2 mb-3">
                                                    @include('pdf.partials.gauge', ['score' => $d['scores']['performance'] ?? null, 'label' => 'Performance'])
                                                    @include('pdf.partials.gauge', ['score' => $d['scores']['accessibility'] ?? null, 'label' => 'Accessibility'])
                                                    @include('pdf.partials.gauge', ['score' => $d['scores']['best_practices'] ?? null, 'label' => 'Best Practices'])
                                                    @include('pdf.partials.gauge', ['score' => $d['scores']['seo'] ?? null, 'label' => 'SEO'])
                                                </div>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-sm">
                                                        <thead>
                                                            <tr class="text-left text-xs text-slate-400 border-b border-slate-100">
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
                                                                    <td class="py-1.5 pr-4 text-slate-700">{{ $metricLabel }}</td>
                                                                    <td class="py-1.5 pr-4 text-slate-600">{{ $m['value'] ?? '-' }}
                                                                        {{ $m['unit'] ?? '' }}
                                                                    </td>
                                                                    <td class="py-1.5 pr-4">
                                                                        @php
                                                                            $statusMap = ['good' => ['Good', 'bg-emerald-50 text-emerald-700'], 'needs_improvement' => ['Needs Improvement', 'bg-amber-50 text-amber-700'], 'poor' => ['Poor', 'bg-red-50 text-red-700']];
                                                                            [$statusLabel, $statusColor] = $statusMap[$m['status'] ?? ''] ?? ['-', 'bg-slate-100 text-slate-500'];
                                                                        @endphp
                                                                        <span
                                                                            class="text-xs px-2 py-0.5 rounded-full {{ $statusColor }}">{{ $statusLabel }}</span>
                                                                    </td>
                                                                    <td class="py-1.5 text-xs text-slate-400">
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
                                    <p class="text-xs text-slate-400">Last analyzed: {{ $pagespeed['analyzed_at'] ?? '-' }}</p>

                                    <div class="border-t border-slate-100 mt-4 pt-4">
                                        <p class="text-xs font-medium text-slate-600 mb-1">Semrush Overview Screenshot (for Proposal)</p>
                                        <a href="https://www.semrush.com/analytics/overview/?q={{ urlencode(parse_url($resolvedUrl, PHP_URL_HOST)) }}&searchType=domain"
                                            target="_blank" rel="noopener" class="text-xs text-blue-600 hover:underline">
                                            Open Semrush for this domain &rarr;
                                        </a>

                                        @if (!empty($seo['manual_screenshots']['own_semrush']))
                                            <div class="mt-2">
                                                <img src="{{ Storage::url($seo['manual_screenshots']['own_semrush']) }}"
                                                    class="max-w-xs border rounded-lg">
                                                <form action="{{ route('pages.projects.manual-screenshot.destroy', $project) }}" method="POST"
                                                    class="mt-1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="target" value="own_semrush">
                                                    <input type="hidden" name="return_tab" value="performa">
                                                    <button type="submit" class="text-xs text-red-600 hover:underline">Delete & re-upload</button>
                                                </form>
                                            </div>
                                        @else
                                            <form action="{{ route('pages.projects.manual-screenshot.store', $project) }}" method="POST"
                                                enctype="multipart/form-data" class="mt-2 flex items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="target" value="own_semrush">
                                                <input type="hidden" name="return_tab" value="performa">
                                                <input type="file" name="screenshot" accept="image/*" required class="text-xs">
                                                <button type="submit"
                                                    class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg">Upload</button>
                                            </form>
                                        @endif
                                    </div>

                                    <div class="border-t border-slate-100 mt-4 pt-4">
                                        <p class="text-xs font-medium text-slate-600 mb-1">PageSpeed Report Screenshot (for Proposal)</p>
                                        <a href="https://pagespeed.web.dev/report?url={{ urlencode($resolvedUrl) }}" target="_blank"
                                            rel="noopener" class="text-xs text-blue-600 hover:underline">Open PageSpeed for this site
                                            &rarr;</a>

                                        @if (!empty($seo['manual_screenshots']['own_pagespeed']))
                                            <div class="mt-2">
                                                <img src="{{ Storage::url($seo['manual_screenshots']['own_pagespeed']) }}"
                                                    class="max-w-xs border rounded-lg">
                                                <form action="{{ route('pages.projects.manual-screenshot.destroy', $project) }}" method="POST"
                                                    class="mt-1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="target" value="own_pagespeed">
                                                    <input type="hidden" name="return_tab" value="performa">
                                                    <button type="submit" class="text-xs text-red-600 hover:underline">Delete & re-upload</button>
                                                </form>
                                            </div>
                                        @else
                                            <form action="{{ route('pages.projects.manual-screenshot.store', $project) }}" method="POST"
                                                enctype="multipart/form-data" class="mt-2 flex items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="target" value="own_pagespeed">
                                                <input type="hidden" name="return_tab" value="performa">
                                                <input type="file" name="screenshot" accept="image/*" required class="text-xs">
                                                <button type="submit"
                                                    class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg">Upload</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <script>
                                const PAGESPEED_ANALYZE_URL = "{{ route('pages.projects.pagespeed.analyze', $project) }}";
                                const PAGESPEED_STATUS_URL = "{{ route('pages.projects.pagespeed.status', $project) }}";

                                (function () {
                                    const btn = document.getElementById('btnAnalyzePagespeed');
                                    const progress = document.getElementById('pagespeedProgress');
                                    const progressBar = document.getElementById('pagespeedProgressBar');
                                    const progressMessage = document.getElementById('pagespeedProgressMessage');
                                    const errorBox = document.getElementById('pagespeedError');
                                    let pollTimer = null;

                                    function checkStatus() {
                                        fetch(PAGESPEED_STATUS_URL, { headers: { Accept: 'application/json' } })
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
                                            .catch(() => { });
                                    }

                                    btn?.addEventListener('click', function () {
                                        errorBox.classList.add('hidden');
                                        fetch(PAGESPEED_ANALYZE_URL, {
                                            method: 'POST',
                                            headers: {
                                                Accept: 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
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
                </div>

                {{-- ===================== TAB: LAPORAN TRAFFIC ===================== --}}
                <div x-show="tab === 'traffic'">
                    <x-card class="mb-6">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="font-semibold text-slate-800">Search Console Report</h2>
                            @if ($searchConsole)
                                <span
                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                    <i class='bx bx-check'></i> Data available
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mb-4">Last 28 days.</p>

                        @if (!$resolvedUrl)
                            <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                The client's website URL has not been filled in.
                            </div>
                        @else
                            <button type="button" id="btnAnalyzeGsc"
                                class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg">
                                <i class='bx bx-refresh'></i> {{ $searchConsole ? 'Refresh Data' : 'Fetch Search Console Data' }}
                            </button>
                            <span id="gscSpinner" class="hidden text-xs text-slate-400 ml-2">Loading...</span>

                            <div id="gscError" class="hidden mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
                            </div>

                            @if ($searchConsole)
                                @php
                                    $gscTotals = $searchConsole['totals'] ?? null;
                                    $gscTopQueries = $searchConsole['top_queries'] ?? [];
                                    $gscByDevice = $searchConsole['by_device'] ?? [];
                                @endphp
                                <div class="border-t border-slate-100 mt-4 pt-4">
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                                        <div class="rounded-lg p-3 text-center bg-slate-50">
                                            <div class="text-lg font-bold text-slate-700">{{ $gscTotals['clicks'] ?? 0 }}</div>
                                            <div class="text-[11px] text-slate-400">Total Clicks</div>
                                        </div>
                                        <div class="rounded-lg p-3 text-center bg-slate-50">
                                            <div class="text-lg font-bold text-slate-700">{{ $gscTotals['impressions'] ?? 0 }}</div>
                                            <div class="text-[11px] text-slate-400">Impressions</div>
                                        </div>
                                        <div class="rounded-lg p-3 text-center bg-slate-50">
                                            <div class="text-lg font-bold text-slate-700">
                                                {{ isset($gscTotals['ctr']) ? round($gscTotals['ctr'] * 100, 1) . '%' : '-' }}
                                            </div>
                                            <div class="text-[11px] text-slate-400">CTR</div>
                                        </div>
                                        <div class="rounded-lg p-3 text-center bg-slate-50">
                                            <div class="text-lg font-bold text-slate-700">
                                                {{ isset($gscTotals['position']) ? round($gscTotals['position'], 1) : '-' }}
                                            </div>
                                            <div class="text-[11px] text-slate-400">Average Position</div>
                                        </div>
                                    </div>

                                    @if (!empty($gscTopQueries))
                                        <p class="text-xs text-slate-400 mb-2">Top Search Queries</p>
                                        <div class="overflow-x-auto mb-4">
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="text-left text-xs text-slate-400 border-b border-slate-100">
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
                                                            <td class="py-1.5 pr-4 text-slate-700">{{ $q['keys'][0] ?? '-' }}</td>
                                                            <td class="py-1.5 pr-4 text-slate-600">{{ $q['clicks'] ?? 0 }}</td>
                                                            <td class="py-1.5 pr-4 text-slate-600">{{ $q['impressions'] ?? 0 }}</td>
                                                            <td class="py-1.5 pr-4 text-slate-600">
                                                                {{ isset($q['ctr']) ? round($q['ctr'] * 100, 1) . '%' : '-' }}
                                                            </td>
                                                            <td class="py-1.5 text-slate-600">
                                                                {{ isset($q['position']) ? round($q['position'], 1) : '-' }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    @if (!empty($gscByDevice))
                                        <p class="text-xs text-slate-400 mb-2">Clicks per Device</p>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="text-left text-xs text-slate-400 border-b border-slate-100">
                                                        <th class="pb-1 pr-4">Device</th>
                                                        <th class="pb-1">Clicks</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($gscByDevice as $d)
                                                        <tr class="border-b border-slate-50">
                                                            <td class="py-1.5 pr-4 text-slate-700 capitalize">{{ strtolower($d['keys'][0] ?? '-') }}
                                                            </td>
                                                            <td class="py-1.5 text-slate-600">{{ $d['clicks'] ?? 0 }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    <p class="text-xs text-slate-400 mt-3">
                                        Period: {{ $searchConsole['period']['start'] ?? '-' }} to
                                        {{ $searchConsole['period']['end'] ?? '-' }}
                                        &middot; Last fetched: {{ $searchConsole['analyzed_at'] ?? '-' }}
                                    </p>
                                </div>
                            @endif

                            <script>
                                const GSC_ANALYZE_URL = "{{ route('pages.projects.search-console.analyze', $project) }}";

                                (function () {
                                    const btn = document.getElementById('btnAnalyzeGsc');
                                    const spinner = document.getElementById('gscSpinner');
                                    const errorBox = document.getElementById('gscError');

                                    btn?.addEventListener('click', function () {
                                        errorBox.classList.add('hidden');
                                        btn.disabled = true;
                                        spinner.classList.remove('hidden');

                                        fetch(GSC_ANALYZE_URL, {
                                            method: 'POST',
                                            headers: {
                                                Accept: 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                            },
                                        })
                                            .then(res => res.json().then(data => ({ ok: res.ok, data })))
                                            .then(({ ok, data }) => {
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

                    <x-card class="mb-6">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="font-semibold text-slate-800">Google Analytics (GA4) Report</h2>
                            @if ($ga4)
                                <span
                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                    <i class='bx bx-check'></i> Data available
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mb-4">Last 28 days.</p>

                        @if (!$resolvedUrl)
                            <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                The client's website URL has not been filled in.
                            </div>
                        @else
                            <button type="button" id="btnAnalyzeGa4"
                                class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg">
                                <i class='bx bx-refresh'></i> {{ $ga4 ? 'Refresh Data' : 'Fetch GA4 Data' }}
                            </button>
                            <span id="ga4Spinner" class="hidden text-xs text-slate-400 ml-2">Loading...</span>

                            <div id="ga4Error" class="hidden mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
                            </div>

                            <div id="ga4SelectBox" class="hidden mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-sm text-blue-700 mb-2">Found several matching GA4 Properties, choose one:</p>
                                <select id="ga4PropertySelect"
                                    class="w-full bg-white border border-blue-200 rounded-lg px-3 py-2 text-sm mb-2"></select>
                                <button type="button" id="btnConfirmGa4Property"
                                    class="text-xs font-semibold bg-blue-600 text-white px-3 py-1.5 rounded-lg">Use This
                                    Property</button>
                            </div>

                            <div id="ga4ManualBox" class="hidden mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                <p class="text-sm text-amber-700 mb-2">Could not find a GA4 Property automatically. Enter the Property ID
                                    manually (GA4 → Admin → Property Settings, numbers only, e.g. 123456789):</p>
                                <input type="text" id="ga4ManualInput" placeholder="123456789"
                                    class="w-full border border-amber-200 rounded-lg px-3 py-2 text-sm mb-2">
                                <button type="button" id="btnConfirmGa4Manual"
                                    class="text-xs font-semibold bg-amber-600 text-white px-3 py-1.5 rounded-lg">Save & Fetch
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
                                        <div class="rounded-lg p-3 text-center bg-slate-50">
                                            <div class="text-lg font-bold text-slate-700">{{ $ga4Totals['organic_sessions'] ?? 0 }}</div>
                                            <div class="text-[11px] text-slate-400">Organic Sessions</div>
                                        </div>
                                        <div class="rounded-lg p-3 text-center bg-slate-50">
                                            <div class="text-lg font-bold text-slate-700">{{ $ga4Totals['total_users'] ?? 0 }}</div>
                                            <div class="text-[11px] text-slate-400">Total Users</div>
                                        </div>
                                        <div class="rounded-lg p-3 text-center bg-slate-50">
                                            <div class="text-lg font-bold text-slate-700">{{ $ga4Totals['conversions'] ?? 0 }}</div>
                                            <div class="text-[11px] text-slate-400">Conversions</div>
                                        </div>
                                    </div>

                                    @if ($ga4NewVsReturning)
                                        <p class="text-xs text-slate-400 mb-2">New vs Returning Users</p>
                                        <div class="flex gap-3 mb-4">
                                            <div class="rounded-lg p-3 text-center bg-slate-50 flex-1">
                                                <div class="text-lg font-bold text-slate-700">{{ $ga4NewVsReturning['new'] ?? 0 }}</div>
                                                <div class="text-[11px] text-slate-400">New Users</div>
                                            </div>
                                            <div class="rounded-lg p-3 text-center bg-slate-50 flex-1">
                                                <div class="text-lg font-bold text-slate-700">{{ $ga4NewVsReturning['returning'] ?? 0 }}</div>
                                                <div class="text-[11px] text-slate-400">Returning Users</div>
                                            </div>
                                        </div>
                                    @endif

                                    @if (!empty($ga4Pages))
                                        <p class="text-xs text-slate-400 mb-2">Top Landing Pages</p>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="text-left text-xs text-slate-400 border-b border-slate-100">
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
                                                            <td class="py-1.5 pr-4 text-slate-700 break-all">{{ $p['landing_page'] ?? '-' }}</td>
                                                            <td class="py-1.5 pr-4 text-slate-600">{{ $p['sessions'] ?? 0 }}</td>
                                                            <td class="py-1.5 pr-4 text-slate-600">
                                                                {{ isset($p['engagement_rate']) ? round($p['engagement_rate'] * 100, 1) . '%' : '-' }}
                                                            </td>
                                                            <td class="py-1.5 pr-4 text-slate-600">
                                                                {{ isset($p['avg_engagement_time']) ? $p['avg_engagement_time'] . 's' : '-' }}
                                                            </td>
                                                            <td class="py-1.5 text-slate-600">{{ $p['conversions'] ?? 0 }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    <p class="text-xs text-slate-400 mt-3">
                                        Property ID: {{ $seo['ga4_property_id'] ?? '-' }}
                                        &middot; Period: {{ $ga4['period']['start'] ?? '-' }} to {{ $ga4['period']['end'] ?? '-' }}
                                        &middot; Last fetched: {{ $ga4['analyzed_at'] ?? '-' }}
                                    </p>
                                </div>
                            @endif

                            <script>
                                const GA4_ANALYZE_URL = "{{ route('pages.projects.ga4.analyze', $project) }}";

                                (function () {
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
                                            body: JSON.stringify(propertyId ? { property_id: propertyId } : {}),
                                        })
                                            .then(res => res.json().then(data => ({ ok: res.ok, data })))
                                            .then(({ ok, data }) => {
                                                spinner.classList.add('hidden');

                                                if (ok && data.success) {
                                                    window.location.reload();
                                                } else if (data.needs_selection) {
                                                    btn.disabled = false;
                                                    propertySelect.innerHTML = data.candidates.map(c => `<option value="${c.property_id}">${c.name} (${c.property_id}) — ${c.url}</option>`).join('');
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
                </div>

            </div>
        @endif
    @endif

@endsection
