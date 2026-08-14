@extends('layouts.app')
@section('title', $project->name)

@section('content')

    <a href="{{ route('pages.projects') }}" class="text-sm text-slate-400 flex items-center gap-1 mb-4 hover:text-slate-600">
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
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-sm">
            <div>
                <p class="text-slate-400 mb-1">Jenis Website</p>
                <p class="font-medium text-slate-700">{{ $project->type ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-400 mb-1">Tanggal Dibuat</p>
                <p class="font-medium text-slate-700">{{ $project->created_at->translatedFormat('d M Y') }}</p>
            </div>
        </div>
    </x-card>


    {{-- PROPOSAL --}}
    <x-card>
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
            </div>
            {{-- Description --}}
            <p class="text-xs text-slate-500 leading-relaxed">
                @if ($project->latestProposal)
                    Proposal telah berhasil dibuat berdasarkan data request proyek. Berikut hasil
                    analisis dan dokumennya.
                @else
                    Generate proposal berdasarkan data request proyek.
                    Sistem akan menyiapkan analisis kebutuhan, strategi website,
                    target market, dan struktur website.
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
                            Dokumen Viewer
                        </span>
                        @if ($proposal->pdf_path)
                            <a href="{{ Storage::url($proposal->pdf_path) }}" target="_blank"
                                class="text-[11px] text-blue-600 hover:underline flex items-center gap-1">
                                Buka Tab Baru <i class='bx bx-link-external'></i>
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
                                <h3 class="text-sm font-semibold text-slate-700">File PDF Belum Tersedia</h3>
                                <p class="text-xs text-slate-400 mt-1 max-w-sm">
                                    Proposal belum digenerate atau file PDF belum tersimpan di server.
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
                        Download PDF Proposal
                    </a>
                @endif
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
    <x-card class="mt-6">
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
                            <p class="text-sm font-semibold text-slate-700">Project sudah selesai</p>
                            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                                Live preview sudah tidak aktif karena site ZipWP otomatis dihapus
                                setelah project ditandai "Done". Ini screenshot terakhir sebelum
                                site dihapus:
                            </p>

                            @if ($project->mockupTemplate->previewUrl())
                                <img src="{{ $project->mockupTemplate->previewUrl() }}"
                                    class="mt-4 mx-auto rounded-xl border border-slate-200 object-cover shadow-sm max-w-lg w-full">
                            @else
                                <p class="text-xs text-slate-400 mt-3">Screenshot tidak tersedia.</p>
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
                                    Buka di tab baru <i class='bx bx-link-external'></i>
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
                    <a href="https://app.zipwp.com" target="_blank" rel="noopener"
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
                <p class="text-sm font-medium text-slate-600">Mockup belum dibuat</p>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                    Mockup akan otomatis digenerate setelah proposal berhasil dibuat. Silakan generate
                    proposal terlebih dahulu.
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
                btn.querySelector('span').innerText = 'Memproses...';
            }
            idleWarning?.classList.add('hidden');

            ProgressModal.open('mockup-progress-modal');
            ProgressModal.update('mockup-progress-modal', {
                percent: 0,
                message: 'Memulai proses...',
                status: 'processing',
            });

            fetch(`/projects/${projectId}/proposal/generate`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
            }).then(() => pollProposalStatus(projectId));

            function pollProposalStatus(projectId) {
                fetch(`/projects/${projectId}/proposal/status`, {
                        headers: {
                            'Accept': 'application/json'
                        },
                    })
                    .then(res => res.json())
                    .then(data => {
                        const pct = data.progress ?? 0;
                        const status = data.status === 'done' ? 'done' : data.status === 'failed' ? 'failed' :
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
                    });
            }
        }
    </script>

@endsection
