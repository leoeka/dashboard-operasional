@extends('layouts.app')
@section('title', $project->name)

@section('content')

    <a href="{{ route('pages.projects') }}"
        class="text-sm text-slate-400 flex items-center gap-1 mb-4 hover:text-slate-600">
        <i class='bx bx-arrow-back'></i> Kembali ke Projects
    </a>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ session('error') }}</div>
    @endif

    {{-- INFORMASI UMUM --}}
    <x-card class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
            <div class="min-w-0">
                <p class="font-bold text-slate-800 text-lg truncate">{{ $project->name }}</p>
                <p class="text-sm text-slate-400">{{ $project->client_name }}</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <x-badge :color="$project->statusColor()">{{ $project->statusLabel() }}</x-badge>
                <a href="{{ route('pages.projects.edit', $project) }}"
                    class="text-sm px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                    <i class='bx bx-edit'></i> Edit
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-sm">
            <div>
                <p class="text-slate-400 mb-1">Jenis Website</p>
                <p class="font-medium text-slate-700">{{ $project->type ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-400 mb-1">Progress</p>
                <p class="font-medium text-slate-700">{{ $project->progress }}%</p>
            </div>
            <div>
                <p class="text-slate-400 mb-1">Deadline</p>
                <p class="font-medium text-slate-700">{{ $project->deadline?->translatedFormat('d M Y') ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-400 mb-1">Tanggal Dibuat</p>
                <p class="font-medium text-slate-700">{{ $project->created_at->translatedFormat('d M Y') }}</p>
            </div>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        {{-- TASK CHECKLIST --}}
        <x-card>
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-slate-800 text-base">Task Checklist</h2>
                @if ($project->tasks->count() > 0)
                    <span class="text-xs text-slate-400 font-medium">
                        {{ $project->tasks->where('is_done', true)->count() }}/{{ $project->tasks->count() }} Selesai
                    </span>
                @endif
            </div>

            {{-- Form Tambah Task --}}
            <form method="POST" action="{{ route('pages.projects.tasks.store', $project) }}" class="flex gap-2 mb-4">
                @csrf
                <input type="text" name="title" placeholder="Tambah task baru..." required
                    class="flex-1 min-w-0 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg px-3.5 py-2 text-sm outline-none transition focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 placeholder:text-slate-400">
                <button type="submit"
                    class="grad-blue text-white text-sm px-4 rounded-lg hover:opacity-90 active:scale-95 transition flex-shrink-0 flex items-center justify-center shadow-sm">
                    <i class='bx bx-plus text-lg'></i>
                </button>
            </form>

            {{-- List Tasks --}}
            <div class="space-y-1">
                @forelse ($project->tasks as $task)
                    <div
                        class="flex items-center justify-between gap-2 group px-2 py-1.5 rounded-lg hover:bg-slate-50 transition">
                        <form method="POST" action="{{ route('pages.projects.tasks.toggle', [$project, $task]) }}"
                            class="flex items-center gap-3 flex-1 min-w-0">
                            @csrf @method('PATCH')
                            <button type="submit" class="flex items-center gap-2.5 text-left flex-1 min-w-0 group/btn">
                                <i
                                    class='bx {{ $task->is_done ? 'bx-checkbox-checked text-blue-600' : 'bx-checkbox text-slate-300 group-hover/btn:text-slate-400' }} text-2xl flex-shrink-0 transition-colors'></i>
                                <span
                                    class="text-sm truncate transition-colors {{ $task->is_done ? 'text-slate-400 line-through' : 'text-slate-700 font-medium' }}">
                                    {{ $task->title }}
                                </span>
                            </button>
                        </form>

                        <form method="POST" action="{{ route('pages.projects.tasks.destroy', [$project, $task]) }}"
                            class="opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                            @csrf @method('DELETE')
                            <button type="submit"
                                class="p-1 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded transition">
                                <i class='bx bx-x text-lg block'></i>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="text-center py-8 border border-dashed border-slate-200 rounded-lg">
                        <p class="text-xs text-slate-400">Belum ada task tersimpan.</p>
                    </div>
                @endforelse
            </div>
        </x-card>

        {{-- PROPOSAL --}}
        <x-card class="flex flex-col justify-between h-full">
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
                                Proposal Proyek
                            </h2>
                            <p class="text-xs text-slate-400">
                                Analisis & dokumen penawaran
                            </p>
                        </div>
                    </div>
                    {{-- Status --}}
                    <div>
                        @if ($project->latestProposal)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold
                                bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200/60">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Versi {{ $project->latestProposal->version }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold
                                bg-slate-100 text-slate-500 rounded-full">
                                Belum Dibuat

                            </span>
                        @endif
                    </div>
                </div>
                {{-- Description --}}
                <p class="text-xs text-slate-500 leading-relaxed">
                    @if ($project->latestProposal)
                        Proposal telah berhasil dibuat berdasarkan data request proyek.
                        Anda dapat melihat hasil analisis dan preview proposal sebelum
                        membuat dokumen PDF.
                    @else
                        Generate proposal berdasarkan data request proyek.
                        Sistem akan menyiapkan analisis kebutuhan, strategi website,
                        target market, dan struktur website.
                    @endif
                </p>
            </div>
            {{-- Actions --}}
            <div class="pt-5 mt-4 border-t border-slate-100">
                @if ($project->latestProposal)
                    <div class="flex flex-col sm:flex-row gap-2">
                        {{-- Preview --}}
                        <a href="{{ route('pages.projects.proposal.preview', $project) }}" class="flex-1 inline-flex items-center justify-center gap-2
                            bg-slate-100 text-slate-700 text-xs font-semibold
                            px-4 py-2.5 rounded-lg
                            hover:bg-slate-200 active:scale-95 transition">
                            <i class='bx bx-show text-sm'></i>
                            <span>Preview</span>
                        </a>
                    </div>
                    {{-- Download jika PDF sudah tersedia --}}
                    @if ($project->latestProposal->pdf_path)
                        <a href="{{ route('pages.projects.proposal.download', $project) }}" class="mt-2 w-full inline-flex items-center justify-center gap-2
                                text-slate-500 hover:text-blue-600
                                text-xs font-medium py-2 transition">
                            <i class='bx bx-download'></i>
                            Download PDF Proposal
                        </a>
                    @endif
                @else
                    {{-- Generate pertama kali — JS-driven, memicu job async + progress bar di kartu Mockup di bawah --}}
                    <button type="button" id="generate-proposal-btn" onclick="startGenerateProposal({{ $project->id }})" class="w-full inline-flex items-center justify-center gap-2
                           grad-blue text-white text-xs font-semibold
                           px-4 py-2.5 rounded-lg
                           hover:opacity-90 active:scale-95
                           shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed">
                        <i class='bx bx-magic-wand text-sm'></i>
                        <span>Generate Proposal</span>
                    </button>
                @endif
            </div>
        </x-card>
    </div>

    {{-- ADD MOCKUP --}}
    <x-card class="mt-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-800">Mockup</h2>
            @if ($project->mockupTemplate)
                <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                    Mockup Aktif
                </span>
            @endif
        </div>

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

                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-bold text-slate-800 truncate">
                                {{ $project->mockupTemplate->name }}
                            </h3>
                            <p class="text-xs text-slate-500 mt-1 line-clamp-2">
                                {{ $project->mockupTemplate->description ?? 'Mockup hasil analisa AI untuk project ini.' }}
                            </p>
                        </div>
                    </div>

                    {{--
                    LIVE PREVIEW: embed situs demo asli dari ZipWP (source_url)
                    lewat iframe, bukan cuma gambar screenshot statis. Kalau
                    template ini nggak punya source_url (mis. template katalog
                    manual lama yang belum pernah diisi field itu), fallback
                    ke gambar screenshot statis biasa.
                    --}}
                    @if ($project->mockupTemplate->source_url)
                        <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                            <div class="flex items-center justify-between px-3 py-2 bg-slate-100 border-b border-slate-200">
                                <span class="text-xs font-medium text-slate-500 flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Live Preview
                                </span>
                                <a href="{{ $project->mockupTemplate->source_url }}" target="_blank" rel="noopener"
                                    class="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">
                                    Buka di tab baru <i class='bx bx-link-external'></i>
                                </a>
                            </div>
                            <iframe src="{{ $project->mockupTemplate->source_url }}" loading="lazy" referrerpolicy="no-referrer"
                                sandbox="allow-scripts allow-same-origin" class="w-full aspect-video"
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
                    @if ($project->mockupTemplate->source_url)
                        <a href="{{ rtrim($project->mockupTemplate->source_url, '/') }}/wp-admin"
                            target="_blank" rel="noopener"
                            class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg hover:opacity-90 transition">
                            <i class='bx bx-log-in-circle'></i> Login ke WP-Admin
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
                    <a href="https://app.zipwp.com"
                        target="_blank" rel="noopener"
                        class="inline-flex items-center gap-2 bg-slate-100 text-slate-600 text-xs font-semibold px-4 py-2 rounded-lg hover:bg-slate-200 transition">
                        <i class='bx bx-key'></i> Buka Dashboard ZipWP (lupa password?)
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
                            <p class="text-sm font-bold text-red-700 mb-2 flex items-center gap-1.5">
                                <i class='bx bx-error'></i> Peringatan — baca dulu sebelum lanjut
                            </p>
                            <ul class="text-xs text-slate-600 space-y-1.5 mb-4 list-disc list-inside">
                                <li>Project akan ditandai <strong>selesai (done)</strong>.</li>
                                <li>Site ini akan dihapus <strong>PERMANEN</strong> dari ZipWP — tidak bisa dikembalikan lagi.</li>
                                <li>Pastikan situs SUDAH dimigrasi ke hosting client sebelum lanjut.</li>
                                <li>Kalau client masih butuh revisi atau situsnya belum benar-benar dipindah, JANGAN klik done dulu.</li>
                            </ul>

                            <form method="POST" action="{{ route('pages.projects.mockup.zipwp-delete', $project) }}">
                                @csrf
                                @method('DELETE')

                                <label class="flex items-start gap-2 text-xs text-slate-700 mb-4 cursor-pointer">
                                    <input type="checkbox" name="confirm_migrated" value="1" required
                                        onchange="document.getElementById('btn-confirm-done-{{ $project->id }}').disabled = !this.checked;"
                                        class="mt-0.5">
                                    <span>Saya sudah baca peringatan di atas dan memastikan situs ini sudah aman untuk ditandai selesai & dihapus dari ZipWP.</span>
                                </label>

                                <div class="flex items-center gap-2 justify-end">
                                    <button type="button" x-on:click="$dispatch('close')"
                                        class="text-xs text-slate-500 px-4 py-2 rounded-lg hover:bg-slate-100 transition">
                                        Batal
                                    </button>
                                    <button type="submit" id="btn-confirm-done-{{ $project->id }}" disabled
                                        class="text-xs bg-red-600 text-white rounded-lg px-4 py-2 hover:bg-red-700 transition disabled:opacity-40 disabled:cursor-not-allowed">
                                        Ya, Tandai Done & Hapus
                                    </button>
                                </div>
                            </form>
                        </div>
                    </x-modal>
                @elseif ($project->mockupTemplate->zipwp_deleted_at)
                    <div class="pt-3 border-t border-slate-200/60">
                        <p class="text-xs text-slate-400 flex items-center gap-1.5">
                            <i class='bx bx-check-circle'></i>
                            Project sudah done, site dihapus dari ZipWP pada {{ $project->mockupTemplate->zipwp_deleted_at->format('d M Y, H:i') }}.
                        </p>
                    </div>
                @endif
            </div>
        @endif

        {{-- OPSI MANUAL / JAGA-JAGA (PILIH ATAU GANTI MOCKUP LAIN) --}}
        <details class="group mb-2">
            <summary
                class="text-xs text-slate-500 hover:text-slate-700 cursor-pointer font-medium flex items-center gap-1 select-none">
                <i class='bx bx-chevron-right group-open:rotate-90 transition-transform'></i>
                {{ $project->mockupTemplate ? 'Ganti atau pilih mockup cadangan manual' : 'Pilih mockup manual' }}
            </summary>

            <form method="POST" action="{{ route('pages.projects.mockup.add', $project) }}"
                class="mt-3 flex flex-col sm:flex-row gap-2">
                @csrf
                @method('PUT')

                <select name="mockup_template_id" required onchange="this.form.submit()"
                    class="flex-1 min-w-0 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Pilih mockup dari daftar --</option>
                    {{--
                    FIX: sebelumnya query ini nampilin SEMUA baris mockup_templates,
                    termasuk hasil AI-generate milik client lain (yang sudah ada
                    logo/nama client lain nempel di gambarnya) — bikin bingung
                    karena kelihatannya seperti "template" padahal itu hasil jadi
                    khusus client lain.

                    Sekarang difilter: sembunyikan baris yang theme_slug-nya
                    diawali "ai:{project_id_lain}:" (AI-generate punya project
                    lain). Template katalog manual biasa (theme_slug TIDAK
                    diawali "ai:") dan hasil AI milik project INI SENDIRI
                    (kalau pernah di-generate ulang beberapa kali) tetap tampil.
                    --}}
                    @foreach (\App\Models\MockupTemplate::where(function ($q) use ($project) {
                            $q->where('theme_slug', 'not like', 'ai:%')
                                ->orWhere('theme_slug', 'like', 'ai:' . $project->id . ':%');
                        })->orderBy('id', 'desc')->get() as $tpl)
                        <option value="{{ $tpl->id }}" @selected($project->mockup_template_id == $tpl->id)>
                            Mockup #{{ $tpl->id }} - {{ $tpl->name }} ({{ $tpl->categoryLabel() }})
                        </option>
                    @endforeach
                </select>
                {{-- Tombol "Simpan Mockup" dihapus — pilih dari dropdown langsung tersimpan (auto-submit). --}}
            </form>
        </details>

        {{--
        Area ini menggantikan warning text "Mockup belum dipilih..." lama.
        Default: kalau belum ada mockup, tampilkan progress bar (hidden,
        di-unhide oleh JS saat tombol "Generate Proposal" diklik). Kalau
        mockup sudah ada, area ini tidak ditampilkan sama sekali.
        --}}
        @if (!$project->mockupTemplate)
            <div id="mockup-progress-wrapper" class="hidden p-3 bg-blue-50 rounded-lg border border-blue-200">
                <div class="flex items-center justify-between text-xs mb-2">
                    <span id="mockup-progress-message" class="text-blue-700 font-medium">Memulai proses...</span>
                    <span id="mockup-progress-percent" class="text-blue-400">0%</span>
                </div>
                <div class="w-full h-2 bg-blue-100 rounded-full overflow-hidden">
                    <div id="mockup-progress-bar"
                        class="h-full bg-gradient-to-r from-blue-500 to-purple-600 transition-all duration-500 ease-out"
                        style="width: 0%"></div>
                </div>
            </div>

            <div id="mockup-idle-warning"
                class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-amber-700 text-xs flex items-center gap-2">
                <i class='bx bx-info-circle text-base'></i>
                <span>Mockup belum dipilih atau gagal di-generate. Silakan pilih manual dari opsi di atas.</span>
            </div>
        @endif
    </x-card>

    <script>
        function startGenerateProposal(projectId) {
            const btn = document.getElementById('generate-proposal-btn');
            const progressWrapper = document.getElementById('mockup-progress-wrapper');
            const idleWarning = document.getElementById('mockup-idle-warning');
            const progressBar = document.getElementById('mockup-progress-bar');
            const progressMessage = document.getElementById('mockup-progress-message');
            const progressPercent = document.getElementById('mockup-progress-percent');

            if (btn) {
                btn.disabled = true;
                btn.querySelector('span').innerText = 'Memproses...';
            }
            idleWarning?.classList.add('hidden');
            progressWrapper?.classList.remove('hidden');

            fetch(`/projects/${projectId}/proposal/generate`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
            }).then(() => pollProposalStatus(projectId));

            function pollProposalStatus(projectId) {
                fetch(`/projects/${projectId}/proposal/status`, {
                    headers: { 'Accept': 'application/json' },
                })
                    .then(res => res.json())
                    .then(data => {
                        const pct = data.progress ?? 0;
                        if (progressBar) progressBar.style.width = pct + '%';
                        if (progressPercent) progressPercent.innerText = pct + '%';
                        if (progressMessage) progressMessage.innerText = data.message || '';

                        if (data.status === 'done') {
                            window.location.reload();
                        } else if (data.status === 'failed') {
                            if (btn) {
                                btn.disabled = false;
                                btn.querySelector('span').innerText = 'Generate Proposal';
                            }
                            progressMessage.innerText = data.message || 'Gagal, silakan coba lagi.';
                            progressBar.classList.add('bg-red-500');
                        } else {
                            setTimeout(() => pollProposalStatus(projectId), 1500);
                        }
                    });
            }
        }
    </script>

    {{-- ACTIVITY LOG --}}
    <x-card class="mt-6">
        <h2 class="font-semibold text-slate-800 mb-4">Activity Log</h2>

        <div class="space-y-4">
            @forelse ($project->activityLogs as $log)
                <div class="flex gap-3">
                    <span
                        class="text-xs text-slate-400 font-mono w-12 flex-shrink-0 pt-0.5">{{ $log->created_at->format('H:i') }}</span>
                    <div class="flex-1 min-w-0 pb-4 border-l border-slate-100 pl-4 -ml-px">
                        <p class="text-sm text-slate-700 break-words">{{ $log->action }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $log->created_at->translatedFormat('d M Y') }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400 text-center py-4">Belum ada aktivitas.</p>
            @endforelse
        </div>
    </x-card>

@endsection