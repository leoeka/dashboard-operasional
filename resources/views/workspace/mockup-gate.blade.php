@php use App\Models\MockupCandidate; @endphp

@php
    // Disetujui atau sudah lewat tahapnya (terkirim/dipilih/ditolak) — kalau
    // status 'sent' ikut dihitung belum-selesai, banner proposal hilang begitu
    // PDF dibuat; 'rejected' hanya lahir setelah pilihan terkunci, jadi ikut
    // dihitung selesai supaya langkah tinjau tidak kebuka lagi.
    $beyondReview = [
        MockupCandidate::STATUS_TEAM_APPROVED,
        MockupCandidate::STATUS_SENT,
        MockupCandidate::STATUS_CHOSEN,
        MockupCandidate::STATUS_REJECTED,
    ];
    $ready = $mockupSet->filter(fn ($c) => $c->status === MockupCandidate::STATUS_AWAITING_REVIEW)->sortKeys();
    $allApproved = $mockupSet->isNotEmpty()
        && $mockupSet->every(fn ($c) => in_array($c->status, $beyondReview, true));
    $choiceLocked = $clientProposal && $clientProposal->chosen_candidate_id !== null;
    $chosenNumber = $choiceLocked ? $clientProposal->chosenCandidate?->candidate_number : null;
    $hasRunning = $mockupSet->contains(fn ($c) => $c->isRunning());
    $codexStopped = is_array($codexStatus ?? null)
        && in_array($codexStatus['keadaan'] ?? null, ['berhenti', 'gagal', 'selesai'], true)
        && $hasRunning;

    if ($codexStopped) {
        $hasRunning = false;
    }
    $isBuilding = $wpBuild && in_array($wpBuild->status, [
        \App\Models\WpBuild::STATUS_PENDING,
        \App\Models\WpBuild::STATUS_BUILDING,
        \App\Models\WpBuild::STATUS_INSPECTION,
    ], true);
    $failedCandidates = $mockupSet->filter(fn ($c) => $c->hasFailed());
    $perluUlang = $mockupSet->filter(fn ($c) => $c->hasFailed() || ($c->isRunning() && $codexStopped));
    $inspection = $wpBuild?->inspection_results ?? [];
    $inspectionLabels = [
        'required_files' => 'Berkas wajib tema',
        'style_header' => 'Kepala style.css',
        'syntax_lint' => 'Sintaks PHP',
        'zero_markers' => 'Sisa penanda __EXITO_',
        'menu_locations' => 'Lokasi menu',
        'page_payloads' => 'Halaman isi',
        'menu_filled' => 'Menu terisi',
        'media_payload' => 'Pustaka media',
        'no_external_embeds' => 'Skrip, formulir, alamat luar (K-9)',
        'file_size' => 'Ukuran berkas (M-11)',
        'color_contrast' => 'Kontras warna (M-1/M-2)',
        'elementor_payload' => 'Muatan Elementor',
        'front_page' => 'Halaman depan statis',
    ];
@endphp

<x-card class="mb-6">
    <h2 class="font-semibold text-slate-800">Gerbang tinjau mockup</h2>
    <p class="text-sm text-slate-600">Periksa tiga kandidat buatan AI. Setujui kalau sudah layak
        dikirim ke klien — kandidat yang disetujui lanjut ke proposal PDF.</p>
    <p class="mt-2 text-xs text-slate-500">
        Desain dibuat di luar server memakai akun ChatGPT yang login di mesin ini.
        Proses berjalan di latar sekitar 13 menit per kandidat; halaman ini tidak perlu ditunggu.
    </p>

    {{-- Proposal yang sudah terlanjur dibuat tetap bisa diunduh di semua keadaan —
        termasuk saat ada kandidat buat-ulang yang sedang ditinjau. --}}
    @if ($clientProposal)
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3 border-t border-slate-100 pt-4">
            <a href="{{ route('pages.projects.proposals.download', [$project, $clientProposal]) }}"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                <i class='bx bx-download' aria-hidden="true"></i> Unduh proposal PDF
            </a>
            <span class="text-xs text-slate-500">
                @if ($clientProposal->sent_at)
                    Diunduh/dikirim {{ $clientProposal->sent_at->format('d M Y H:i') }}
                @else
                    Dokumen sudah dibuat, belum diunduh atau dikirim ke klien.
                @endif
            </span>
        </div>
    @endif
</x-card>

@if ($codexStopped)
    <div class="mb-6 px-4 py-3 rounded-md bg-amber-50 border border-amber-200 text-amber-900 text-sm flex items-start gap-3">
        <i class='bx bx-pause-circle text-xl text-amber-600 mt-0.5' aria-hidden="true"></i>
        <div>
            <p class="font-semibold text-amber-800">Pembuatan mockup berhenti.</p>
            <p class="text-xs text-amber-700 mt-0.5">
                {{ $codexStatus['pesan'] ?: 'Proses tidak memberi kabar lagi.' }}
                Klik <b>Coba lagi</b> pada kandidat yang kosong untuk memulai ulang kandidat itu.
            </p>
        </div>
    </div>
@endif

@if ($mockupSet->isEmpty())
    <x-card>
        <x-empty-state icon="bx-palette" title="Belum ada kandidat mockup"
            description="AI akan merancang tiga konsep website berbeda dari brief project ini. Tiap konsep punya gambar pratinjau dan satu kalimat ide. Pembuatannya memakan waktu beberapa menit.">
            <form method="POST" action="{{ route('pages.projects.mockup.store', $project) }}" class="mt-6">
                @csrf
                <button type="submit"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition">
                    <i class='bx bx-wand-2' aria-hidden="true"></i> Buat 3 kandidat
                </button>
            </form>
        </x-empty-state>
    </x-card>
@else
    @if ($failedCandidates->isNotEmpty())
        <div class="mb-6 px-4 py-3 rounded-md bg-amber-50 border border-amber-200 text-amber-900 text-sm flex items-start gap-3">
            <i class='bx bx-error text-xl text-amber-600 mt-0.5' aria-hidden="true"></i>
            <div>
                <p class="font-semibold text-amber-800">
                    {{ $failedCandidates->count() }} kandidat mockup belum berhasil dibuat.
                </p>
                <p class="text-xs text-amber-700 mt-0.5">
                    Periksa pesan kendala pada kartu di bawah, atau buat ulang semuanya sekaligus.
                </p>
            </div>
        </div>
    @endif

    @if ($choiceLocked)
        <div class="mb-6 px-4 py-4 rounded-md bg-emerald-50 text-emerald-700 text-sm">
            <p class="font-semibold">Klien memilih Kandidat {{ $chosenNumber }}. Pilihan terkunci —
                tidak bisa diganti dan kandidat tidak bisa dibuat ulang.</p>
            <div class="mt-3 flex flex-col sm:flex-row sm:items-center gap-3">
                <a href="{{ route('pages.projects.proposals.download', [$project, $clientProposal]) }}"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-white border border-emerald-200 px-5 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 transition">
                    <i class='bx bx-download' aria-hidden="true"></i> Unduh proposal PDF
                </a>
                @if ($wpBuild && $wpBuild->status === \App\Models\WpBuild::STATUS_READY)
                    <a href="{{ route('pages.projects.builds.download', [$project, $wpBuild]) }}"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition">
                        <i class='bx bx-archive-out' aria-hidden="true"></i> Unduh ZIP WordPress
                    </a>
                    <span class="text-xs text-emerald-700 font-medium">Tema siap diinstall (Lolos meja periksa)</span>
                @elseif ($wpBuild && $wpBuild->status === \App\Models\WpBuild::STATUS_INSPECTION_FAILED)
                    <span class="text-xs text-red-700 font-medium">Build gagal meja periksa.</span>
                    <form method="POST" action="{{ route('pages.projects.builds.store', $project) }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-700 transition">
                            <i class='bx bx-refresh' aria-hidden="true"></i> Coba lagi build
                        </button>
                    </form>
                @elseif ($wpBuild)
                    <span class="text-xs text-emerald-700 inline-flex items-center gap-1.5">
                        <i class='bx bx-loader-alt bx-spin' aria-hidden="true"></i>
                        Build sedang diproses ({{ $wpBuild->status }})...
                    </span>
                @else
                    <form method="POST" action="{{ route('pages.projects.builds.store', $project) }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition">
                            <i class='bx bx-hammer' aria-hidden="true"></i> Mulai build WordPress
                        </button>
                    </form>
                @endif
            </div>
            @if ($wpBuild && $wpBuild->status === \App\Models\WpBuild::STATUS_INSPECTION_FAILED)
                <div class="mt-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-800 space-y-1">
                    @if (! empty($inspection['error']))
                        <p class="font-semibold">Kendala: {{ $inspection['error'] }}</p>
                    @endif
                    @if (is_array($inspection['checks'] ?? null) && $inspection['checks'] !== [])
                        <ul class="space-y-0.5">
                            @foreach ($inspection['checks'] as $checkKey => $checkPassed)
                                <li>{{ $inspectionLabels[$checkKey] ?? $checkKey }}: {{ $checkPassed ? 'lolos' : 'gagal' }}</li>
                            @endforeach
                        </ul>
                        <p>{{ (int) ($inspection['pages_count'] ?? 0) }} halaman isi terbawa di tema ini &mdash; {{ (int) ($inspection['elementor_pages_count'] ?? 0) }} di antaranya siap disunting lewat Elementor.</p>
                    @endif
                    @if (is_array($inspection['errors'] ?? null) && $inspection['errors'] !== [])
                        <ul class="list-disc pl-4 space-y-0.5">
                            @foreach ($inspection['errors'] as $inspectionError)
                                <li>{{ $inspectionError }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>
    @elseif ($allApproved)
        <div class="mb-6 px-4 py-4 rounded-md bg-emerald-50 text-emerald-700 text-sm">
            <p>Ketiga kandidat sudah disetujui tim. Proposal PDF berisi ketiganya plus
                kotak centang untuk klien.</p>
            <div class="mt-3 flex flex-col sm:flex-row sm:items-center gap-3">
                @if ($clientProposal)
                    <a href="{{ route('pages.projects.proposals.download', [$project, $clientProposal]) }}"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition">
                        <i class='bx bx-download' aria-hidden="true"></i> Unduh proposal PDF
                    </a>
                    <form method="POST" action="{{ route('pages.projects.proposals.store', $project) }}">
                        @csrf
                        <button type="submit"
                            class="min-h-11 inline-flex items-center justify-center gap-2 text-xs font-semibold text-brand-600 hover:text-brand-700 border border-slate-300 hover:bg-brand-50 px-4 py-2 rounded-md transition">
                            <i class='bx bx-refresh' aria-hidden="true"></i> Buat ulang proposal
                        </button>
                    </form>
                    @if ($clientProposal->sent_at)
                        <span class="text-xs text-emerald-700">Diunduh/dikirim
                            {{ $clientProposal->sent_at->format('d M Y H:i') }}</span>
                    @endif
                @else
                    <form method="POST" action="{{ route('pages.projects.proposals.store', $project) }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition">
                            <i class='bx bx-file-pdf' aria-hidden="true"></i> Buat proposal PDF
                        </button>
                    </form>
                    <p class="text-xs text-emerald-700">Isinya: sampul, salam, satu halaman per kandidat
                        (gambar + kalimat ide + kotak centang), tabel biaya.</p>
                @endif
            </div>
            @if ($clientProposal)
                <p class="mt-2 text-xs text-emerald-700">Setelah PDF kembali dari klien dengan
                    centangannya, klik tombol <b>Klien pilih opsi ini</b> di kartu kandidat yang
                    dicoret — pilihan terkunci dan build WordPress bisa dimulai.</p>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach ($mockupSet->sortKeys() as $candidate)
            <div id="mockup-card-slot-{{ $candidate->candidate_number }}" class="border border-slate-200 rounded-xl overflow-hidden bg-white flex flex-col">
                @if ($candidate->isRunning() && ! $codexStopped)
                    @php
                        $progress = $candidate->progress();
                    @endphp
                    <div class="aspect-[16/10] bg-slate-50 border-b border-slate-100 flex flex-col items-center justify-center p-6 text-center">
                        <div class="w-full max-w-[220px] space-y-3">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                                <span class="inline-flex items-center gap-1.5 text-brand-700">
                                    <i class='bx bx-loader-alt animate-spin text-sm' aria-hidden="true"></i>
                                    Sedang diproses
                                </span>
                                <span id="mockup-progress-percent-{{ $candidate->candidate_number }}" class="font-mono text-brand-700">{{ $progress['percent'] }}%</span>
                            </div>

                            {{-- Bar proses (Progress bar) --}}
                            <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
                                <div id="mockup-progress-bar-{{ $candidate->candidate_number }}" class="bg-brand-600 h-2.5 rounded-full transition-all duration-500 ease-out"
                                    style="width: {{ $progress['percent'] }}%;"></div>
                            </div>

                            <p id="mockup-progress-message-{{ $candidate->candidate_number }}" class="text-xs text-slate-600 truncate" title="{{ $progress['message'] }}">
                                {{ $progress['message'] }}
                            </p>
                        </div>
                    </div>
                    <div class="p-4 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-800">Kandidat {{ $candidate->candidate_number }}</span>
                            <span class="text-xs px-2.5 py-1 rounded-full bg-brand-50 text-brand-700 font-medium">Sedang dibuat</span>
                        </div>
                        <p class="text-sm text-slate-600">
                            Desain sedang ditulis di latar. Tampilan terisi otomatis tanpa perlu me-refresh.
                        </p>
                        <p class="text-[11px] text-slate-500" data-runner-note="{{ $candidate->candidate_number }}">
                            {{ is_array($codexStatus ?? null) && ! empty($codexStatus['kandidat_ke'])
                                ? 'Kandidat ke-' . $codexStatus['kandidat_ke'] . ' dari ' . ($codexStatus['jumlah'] ?? 3) . ' · '
                                : '' }}{{ $codexStatus['tahap'] ?? 'menunggu runner' }}
                        </p>
                        <p class="text-[10px] text-slate-400" data-runner-beat="{{ $candidate->candidate_number }}">
                            @if (is_array($codexStatus ?? null) && isset($codexStatus['heartbeat_seconds']))
                                bergerak {{ $codexStatus['heartbeat_seconds'] }} detik lalu ·
                                {{ $codexStatus['token'] ?? 0 }} token
                            @endif
                        </p>
                    </div>
                @elseif ($candidate->isRunning())
                    <div class="aspect-[16/10] bg-amber-50 flex items-center justify-center">
                        <div class="text-center px-4">
                            <i class='bx bx-pause-circle text-2xl text-amber-600' aria-hidden="true"></i>
                            <p class="text-sm text-amber-700 mt-2">Berhenti</p>
                        </div>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-800">Kandidat {{ $candidate->candidate_number }}</span>
                            <span class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700">Berhenti</span>
                        </div>
                        <p class="text-sm text-amber-800">{{ $codexStatus['pesan'] ?? 'Proses berhenti sebelum selesai.' }}</p>
                        <form method="POST"
                            action="{{ route('pages.projects.mockup.regenerate', [$project, $candidate]) }}"
                            class="mockup-action-form"
                            data-candidate-number="{{ $candidate->candidate_number }}">
                            @csrf
                            <button type="submit"
                                class="min-h-11 inline-flex w-full items-center justify-center gap-2 rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition">
                                <i class='bx bx-revision' aria-hidden="true"></i> Coba lagi
                            </button>
                        </form>
                    </div>
                @elseif ($candidate->hasFailed())
                    <div class="aspect-[16/10] bg-red-50 flex items-center justify-center">
                        <div class="text-center px-4">
                            <i class='bx bx-error-circle text-2xl text-red-600' aria-hidden="true"></i>
                            <p class="text-sm text-red-600 mt-2">Gagal dibuat</p>
                        </div>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-800">Kandidat {{ $candidate->candidate_number }}</span>
                            <span class="text-xs px-2 py-1 rounded-full bg-red-50 text-red-700">Gagal</span>
                        </div>
                        <p class="text-sm text-red-600">{{ $candidate->failure_reason }}</p>
                        <form method="POST"
                            action="{{ route('pages.projects.mockup.regenerate', [$project, $candidate]) }}"
                            class="mockup-action-form"
                            data-candidate-number="{{ $candidate->candidate_number }}">
                            @csrf
                            <button type="submit"
                                class="min-h-11 inline-flex w-full items-center justify-center gap-2 rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition">
                                <i class='bx bx-revision' aria-hidden="true"></i> Coba lagi
                            </button>
                        </form>
                    </div>
                @else
                    <div class="aspect-[16/10] bg-slate-50 border-b border-slate-100">
                        @if ($candidate->png_path)
                            <a href="{{ route('pages.projects.mockup.preview', $candidate) }}"
                                target="_blank" rel="noopener" class="block w-full h-full"
                                title="Buka pratinjau ukuran penuh di tab baru">
                                <img src="{{ route('pages.projects.mockup.preview', $candidate) }}"
                                    alt="Pratinjau kandidat {{ $candidate->candidate_number }}"
                                    class="w-full h-full object-cover object-top" loading="lazy">
                            </a>
                        @endif
                    </div>
                    <div class="p-4 space-y-2 flex-1 flex flex-col">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-800">Kandidat {{ $candidate->candidate_number }}</span>
                            @if ($candidate->status === MockupCandidate::STATUS_CHOSEN)
                                <span class="text-xs px-2 py-1 rounded-full bg-emerald-100 text-emerald-800">Dipilih klien</span>
                            @elseif ($candidate->status === MockupCandidate::STATUS_REJECTED)
                                <span class="text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-500">Ditolak</span>
                            @elseif ($candidate->status === MockupCandidate::STATUS_SENT)
                                <span class="text-xs px-2 py-1 rounded-full bg-brand-100 text-brand-700">Terkirim</span>
                            @elseif ($candidate->status === MockupCandidate::STATUS_TEAM_APPROVED)
                                <span class="text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">Disetujui</span>
                            @else
                                <span class="text-xs px-2 py-1 rounded-full bg-brand-50 text-brand-700">Siap ditinjau</span>
                            @endif
                        </div>
                        <p class="text-sm text-slate-600 flex-1">{{ $candidate->idea_sentence }}</p>
                        @if ($candidate->png_path)
                            <a href="{{ route('pages.projects.mockup.preview', $candidate) }}"
                                target="_blank" rel="noopener"
                                class="min-h-11 inline-flex w-full items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                                <i class='bx bx-expand-alt' aria-hidden="true"></i> Pratinjau ukuran penuh
                            </a>
                        @endif
                        {{-- Hanya kandidat yang pernah dicetak di PDF klien (status 'sent')
                            yang boleh dicatat sebagai pilihan — pengganti buat-ulang yang
                            baru disetujui belum dilihat klien dan menunggu proposal baru.
                            Tombol disembunyikan juga selama ada anggota yang belum selesai
                            tinjaunya, supaya tim tak menemui tolakkan dari server. --}}
                        @if ($clientProposal && ! $choiceLocked && $allApproved && $candidate->status === MockupCandidate::STATUS_SENT)
                            <form method="POST"
                                action="{{ route('pages.projects.proposals.choose', [$project, $clientProposal, $candidate]) }}"
                                onsubmit="return confirm('Catat Kandidat {{ $candidate->candidate_number }} sebagai pilihan klien? Setelah tercatat, pilihan terkunci dan tidak bisa diganti.')">
                                @csrf
                                <button type="submit"
                                    class="min-h-11 inline-flex w-full items-center justify-center gap-2 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition">
                                    <i class='bx bx-check-double' aria-hidden="true"></i> Klien pilih opsi ini
                                </button>
                            </form>
                        @endif
                        @unless ($choiceLocked)
                            <form method="POST"
                                action="{{ route('pages.projects.mockup.regenerate', [$project, $candidate]) }}"
                                class="mockup-action-form"
                                data-candidate-number="{{ $candidate->candidate_number }}"
                                onsubmit="return confirm('Buat ulang kandidat {{ $candidate->candidate_number }}? Kandidat lama tetap tersimpan sebagai jejak, yang baru perlu disetujui ulang.')">
                                @csrf
                                <button type="submit"
                                    class="min-h-11 inline-flex w-full items-center justify-center gap-2 text-xs font-semibold text-brand-600 hover:text-brand-700 border border-slate-300 hover:bg-brand-50 px-3 py-2 rounded-md transition">
                                    <i class='bx bx-revision' aria-hidden="true"></i> Buat ulang
                                </button>
                            </form>
                        @endunless
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($perluUlang->isNotEmpty() && ! $choiceLocked)
        <div class="mt-6 flex flex-col sm:flex-row sm:items-center gap-3">
            <form method="POST"
                action="{{ route('pages.projects.mockup.regenerate-all', $project) }}"
                class="mockup-action-form"
                onsubmit="return confirm('Buat ulang {{ $perluUlang->count() }} kandidat yang gagal atau berhenti? Kandidat yang sudah siap tidak disentuh.')">
                @csrf
                <button type="submit"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition">
                    <i class='bx bx-revision' aria-hidden="true"></i>
                    Buat ulang {{ $perluUlang->count() }} kandidat
                </button>
            </form>
            <p class="text-sm text-slate-600">Kandidat yang sudah siap ditinjau tetap seperti sekarang.</p>
        </div>
    @endif

    @if ($ready->isNotEmpty())
        <div class="mt-6 flex flex-col sm:flex-row sm:items-center gap-3">
            <form method="POST" action="{{ route('pages.projects.mockup.approve', $project) }}">
                @csrf
                <button type="submit"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition">
                    <i class='bx bx-check' aria-hidden="true"></i>
                    Setujui {{ $ready->count() }} kandidat
                </button>
            </form>
            <p class="text-sm text-slate-600">Kandidat yang disetujui lanjut ke proposal PDF untuk
                dikirim ke klien.</p>
        </div>
    @endif

    {{-- Section Aset & Materi Klien (Mockup Assets) --}}
    <div class="mt-8 border border-slate-200 rounded-xl bg-white p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-slate-100">
            <div>
                <h3 class="text-base font-semibold text-slate-800 flex items-center gap-2">
                    <i class='bx bx-folder text-brand-600 text-lg' aria-hidden="true"></i>
                    Aset &amp; Materi Desain Klien
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Logo, gambar, dokumen, dan materi pendukung dari klien untuk perancangan website.
                </p>
            </div>
            <div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                    {{ $project->mockupAssets->count() }} File tersimpan
                </span>
            </div>
        </div>

        {{-- Daftar File --}}
        @if ($project->mockupAssets->isEmpty())
            <div class="py-6 text-center">
                <i class='bx bx-cloud-upload text-3xl text-slate-400 mb-1' aria-hidden="true"></i>
                <p class="text-xs text-slate-500">Belum ada aset khusus yang diunggah untuk proyek ini.</p>
            </div>
        @else
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($project->mockupAssets as $asset)
                    <div class="border border-slate-200 rounded-lg p-3 flex items-start justify-between gap-3 bg-slate-50/50 hover:bg-slate-50 transition">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold border
                                    @if ($asset->category === 'logo') bg-blue-50 text-blue-700 border-blue-200
                                    @elseif ($asset->category === 'foto') bg-emerald-50 text-emerald-700 border-emerald-200
                                    @elseif ($asset->category === 'dokumen' || $asset->category === 'company_profile') bg-amber-50 text-amber-700 border-amber-200
                                    @elseif ($asset->category === 'referensi') bg-purple-50 text-purple-700 border-purple-200
                                    @else bg-slate-100 text-slate-700 border-slate-300 @endif">
                                    {{ $asset->categoryLabel() }}
                                </span>
                                @if ($asset->file_size)
                                    <span class="text-[11px] text-slate-400">
                                        {{ number_format($asset->file_size / 1024, 0) }} KB
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs font-medium text-slate-800 truncate" title="{{ $asset->original_name }}">
                                {{ $asset->original_name }}
                            </p>
                            <p class="text-[10px] text-slate-400 mt-0.5">
                                Diunggah {{ $asset->created_at->diffForHumans() }}
                            </p>
                        </div>

                        <div class="flex items-center gap-1 shrink-0">
                            <a href="{{ $asset->url() }}" target="_blank" rel="noopener"
                                class="inline-flex min-h-8 min-w-8 items-center justify-center rounded border border-slate-300 bg-white text-slate-600 hover:text-brand-600 hover:border-brand-500 text-sm transition"
                                title="Buka / Unduh File">
                                <i class='bx bx-show' aria-hidden="true"></i>
                            </a>
                            <form method="POST" action="{{ route('pages.projects.files.destroy', [$project, $asset]) }}"
                                onsubmit="return confirm('Hapus aset ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex min-h-8 min-w-8 items-center justify-center rounded border border-slate-300 bg-white text-slate-500 hover:text-red-600 hover:border-red-400 text-sm transition"
                                    title="Hapus Aset">
                                    <i class='bx bx-trash' aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Form Upload Cepat Aset Tambahan --}}
        <div class="mt-5 pt-4 border-t border-slate-100">
            <details class="group">
                <summary class="flex items-center gap-2 text-xs font-semibold text-brand-600 hover:text-brand-700 cursor-pointer select-none">
                    <i class='bx bx-plus-circle text-sm group-open:rotate-45 transition-transform' aria-hidden="true"></i>
                    <span>Unggah Aset Tambahan</span>
                </summary>
                <form method="POST" action="{{ route('pages.projects.files.store', $project) }}" enctype="multipart/form-data" class="mt-3 grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                    @csrf
                    <div class="sm:col-span-5">
                        <label for="mockup_file_input" class="block text-xs font-semibold text-slate-700 mb-1">Pilih File</label>
                        <input type="file" id="mockup_file_input" name="file" required
                            class="min-h-10 block w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 border border-slate-300 rounded-md">
                    </div>
                    <div class="sm:col-span-4">
                        <label for="mockup_category_select" class="block text-xs font-semibold text-slate-700 mb-1">Kategori File</label>
                        <select id="mockup_category_select" name="category" required
                            class="min-h-10 block w-full text-xs border border-slate-300 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                            <option value="logo">Logo Klien</option>
                            <option value="foto">Foto / Gambar</option>
                            <option value="dokumen">Dokumen</option>
                            <option value="company_profile">Company Profile</option>
                            <option value="referensi">Referensi Desain</option>
                            <option value="pendukung" selected>File Pendukung</option>
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <button type="submit"
                            class="min-h-10 w-full inline-flex items-center justify-center gap-1.5 rounded-md bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition">
                            <i class='bx bx-upload' aria-hidden="true"></i>
                            Unggah
                        </button>
                    </div>
                </form>
            </details>
        </div>
    </div>

    <script>
        (function() {
            const statusUrl = "{{ route('pages.projects.mockup.status', $project) }}";
            let pollTimer = null;
            let isChecking = false;
            let wasBuilding = {{ $isBuilding ? 'true' : 'false' }};

            function runnerNote(runner) {
                if (!runner) return '';
                const urutan = runner.candidate_index ? 'Kandidat ke-' + runner.candidate_index + ' dari ' + (runner.total || 3) + ' · ' : '';
                return urutan + (runner.stage || 'sedang bekerja');
            }

            function runnerBeat(runner) {
                if (!runner || typeof runner.heartbeat_seconds !== 'number') return '';
                return 'bergerak ' + runner.heartbeat_seconds + ' detik lalu · ' + (runner.tokens || 0) + ' token';
            }

            function renderRunningCard(slotNum, percent, message, runner) {
                const card = document.getElementById('mockup-card-slot-' + slotNum);
                if (!card) return;

                const note = runnerNote(runner);
                const beat = runnerBeat(runner);

                card.innerHTML = `
                    <div class="aspect-[16/10] bg-slate-50 border-b border-slate-100 flex flex-col items-center justify-center p-6 text-center">
                        <div class="w-full max-w-[220px] space-y-3">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                                <span class="inline-flex items-center gap-1.5 text-brand-700">
                                    <i class='bx bx-loader-alt animate-spin text-sm' aria-hidden="true"></i>
                                    Sedang diproses
                                </span>
                                <span id="mockup-progress-percent-${slotNum}" class="font-mono text-brand-700">${percent}%</span>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
                                <div id="mockup-progress-bar-${slotNum}" class="bg-brand-600 h-2.5 rounded-full transition-all duration-500 ease-out"
                                    style="width: ${percent}%;"></div>
                            </div>
                            <p id="mockup-progress-message-${slotNum}" class="text-xs text-slate-600 truncate" title="${message}">
                                ${message}
                            </p>
                        </div>
                    </div>
                    <div class="p-4 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-800">Kandidat ${slotNum}</span>
                            <span class="text-xs px-2.5 py-1 rounded-full bg-brand-50 text-brand-700 font-medium">Sedang dibuat</span>
                        </div>
                        <p class="text-sm text-slate-600">Desain sedang ditulis di latar. Tampilan terisi otomatis tanpa perlu me-refresh.</p>
                        <p class="text-[11px] text-slate-500" data-runner-note="${slotNum}">${note}</p>
                        <p class="text-[10px] text-slate-400" data-runner-beat="${slotNum}">${beat}</p>
                    </div>
                `;
            }

            function renderStoppedCard(slotNum, candidateId, stopMessage) {
                const card = document.getElementById('mockup-card-slot-' + slotNum);
                if (!card) return;

                const regenUrl = `{{ url('/projects/' . $project->id . '/mockup-candidates') }}/${candidateId}/regenerate`;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

                card.innerHTML = `
                    <div class="aspect-[16/10] bg-amber-50 flex items-center justify-center">
                        <div class="text-center px-4">
                            <i class='bx bx-pause-circle text-2xl text-amber-600' aria-hidden="true"></i>
                            <p class="text-sm text-amber-700 mt-2">Berhenti</p>
                        </div>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-800">Kandidat ${slotNum}</span>
                            <span class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700">Berhenti</span>
                        </div>
                        <p class="text-sm text-amber-800">${stopMessage || 'Proses berhenti sebelum selesai.'}</p>
                        <form method="POST" action="${regenUrl}" class="mockup-action-form" data-candidate-number="${slotNum}">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <button type="submit"
                                class="min-h-11 inline-flex w-full items-center justify-center gap-2 rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition">
                                <i class='bx bx-revision' aria-hidden="true"></i> Coba lagi
                            </button>
                        </form>
                    </div>
                `;
            }

            function renderFailedCard(slotNum, candidateId, failureReason) {
                const card = document.getElementById('mockup-card-slot-' + slotNum);
                if (!card) return;

                const regenUrl = `{{ url('/projects/' . $project->id . '/mockup-candidates') }}/${candidateId}/regenerate`;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

                card.innerHTML = `
                    <div class="aspect-[16/10] bg-red-50 flex items-center justify-center">
                        <div class="text-center px-4">
                            <i class='bx bx-error-circle text-2xl text-red-600' aria-hidden="true"></i>
                            <p class="text-sm text-red-600 mt-2">Gagal dibuat</p>
                        </div>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-800">Kandidat ${slotNum}</span>
                            <span class="text-xs px-2 py-1 rounded-full bg-red-50 text-red-700">Gagal</span>
                        </div>
                        <p class="text-sm text-red-600">${failureReason || 'Terjadi kesalahan pada pembuatan mockup.'}</p>
                        <form method="POST" action="${regenUrl}" class="mockup-action-form" data-candidate-number="${slotNum}">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <button type="submit"
                                class="min-h-11 inline-flex w-full items-center justify-center gap-2 rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition">
                                <i class='bx bx-revision' aria-hidden="true"></i> Coba lagi
                            </button>
                        </form>
                    </div>
                `;
            }

            function startMockupPolling() {
                if (pollTimer) return;

                pollTimer = setInterval(function() {
                    if (isChecking) return;
                    isChecking = true;

                    const controller = new AbortController();
                    const timeoutId = setTimeout(function() {
                        controller.abort();
                    }, 5000);

                    fetch(statusUrl, {
                        signal: controller.signal,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(res) {
                        clearTimeout(timeoutId);
                        if (!res.ok) throw new Error('Status error');
                        return res.json();
                    })
                    .then(function(data) {
                        isChecking = false;
                        if (!data || typeof data.has_running !== 'boolean') return;

                        let needsReload = false;

                        if (wasBuilding && !data.is_building) {
                            needsReload = true;
                            wasBuilding = false;
                        }

                        const runner = data.runner || null;

                        if (data.candidates && Array.isArray(data.candidates)) {
                            data.candidates.forEach(function(c) {
                                const card = document.getElementById('mockup-card-slot-' + c.candidate_number);
                                if (!card) return;

                                if (c.stopped) {
                                    const isShowingProgress = document.getElementById('mockup-progress-bar-' + c.candidate_number);

                                    if (isShowingProgress) {
                                        renderStoppedCard(c.candidate_number, c.id, runner && runner.message);
                                    }
                                } else if (c.is_running) {
                                    const bar = document.getElementById('mockup-progress-bar-' + c.candidate_number);
                                    if (!bar) {
                                        // Ubah kartu ke mode running jika sebelumnya belum
                                        renderRunningCard(c.candidate_number, c.percent || 20, c.message || 'Sedang diproses...', runner);
                                    } else {
                                        // Update bar & teks yang sudah ada
                                        bar.style.width = (c.percent || 20) + '%';
                                        const pct = document.getElementById('mockup-progress-percent-' + c.candidate_number);
                                        const msg = document.getElementById('mockup-progress-message-' + c.candidate_number);
                                        if (pct) pct.textContent = (c.percent || 20) + '%';
                                        if (msg) {
                                            msg.textContent = c.message || 'Sedang diproses...';
                                            msg.title = c.message || 'Sedang diproses...';
                                        }
                                        const note = document.getElementById('mockup-runner-note-' + c.candidate_number)
                                            || document.querySelector('[data-runner-note="' + c.candidate_number + '"]');
                                        if (note) note.textContent = runnerNote(runner);
                                        const beat = document.getElementById('mockup-runner-beat-' + c.candidate_number)
                                            || document.querySelector('[data-runner-beat="' + c.candidate_number + '"]');
                                        if (beat) beat.textContent = runnerBeat(runner);
                                    }
                                } else if (c.has_failed) {
                                    // Tampilkan kartu gagal tanpa me-refresh halaman browser
                                    const isShowingProgress = document.getElementById('mockup-progress-bar-' + c.candidate_number);
                                    if (isShowingProgress) {
                                        renderFailedCard(c.candidate_number, c.id, c.failure_reason);
                                    }
                                } else if (c.status === 'awaiting_review' || c.preview_url) {
                                    const isShowingProgress = document.getElementById('mockup-progress-bar-' + c.candidate_number);
                                    if (isShowingProgress) {
                                        needsReload = true;
                                    }
                                }
                            });
                        }

                        // Jika seluruh antrean selesai dan ada kandidat yang berhasil dibuat
                        if (!data.has_running && !data.is_building) {
                            clearInterval(pollTimer);
                            pollTimer = null;
                            if (needsReload) {
                                window.location.reload();
                            }
                        }
                    })
                    .catch(function() {
                        clearTimeout(timeoutId);
                        isChecking = false;
                    });
                }, 2500);
            }

            // Tangani submit form aksi kandidat secara asinkron tanpa reload browser
            document.addEventListener('submit', function(e) {
                if (e.defaultPrevented) return;

                const form = e.target.closest('.mockup-action-form');
                if (!form) return;

                e.preventDefault();

                const slotNum = form.getAttribute('data-candidate-number');
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Melepas proses...";
                }

                if (slotNum) {
                    renderRunningCard(slotNum, 15, 'Melepas proses Codex...');
                }

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                    form.querySelector('input[name="_token"]')?.value || '';

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(res) {
                    if (!res.ok) {
                        // Server menolak (mis. masih ada proses lain): muat
                        // ulang supaya kartu kembali ke keadaan yang benar.
                        window.location.reload();
                        return null;
                    }
                    return res.json();
                })
                .then(function(data) {
                    if (!data) return;
                    if (data.success === false) {
                        window.location.reload();
                        return;
                    }
                    startMockupPolling();
                })
                .catch(function() {
                    startMockupPolling();
                });
            });

            @if ($hasRunning || $isBuilding)
                startMockupPolling();
            @endif
        })();
    </script>
@endif
