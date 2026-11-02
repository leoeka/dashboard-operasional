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
    $isBuilding = $wpBuild && in_array($wpBuild->status, [
        \App\Models\WpBuild::STATUS_PENDING,
        \App\Models\WpBuild::STATUS_BUILDING,
        \App\Models\WpBuild::STATUS_INSPECTION,
    ], true);
    $failedCandidates = $mockupSet->filter(fn ($c) => $c->hasFailed());
@endphp

<x-card class="mb-6">
    <h2 class="font-semibold text-slate-800">Gerbang tinjau mockup</h2>
    <p class="text-sm text-slate-600">Periksa tiga kandidat buatan AI. Setujui kalau sudah layak
        dikirim ke klien — kandidat yang disetujui lanjut ke proposal PDF.</p>
</x-card>

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
                    Periksa pesan kendala pada kartu di bawah. Jika kuota/saldo OpenAI habis, silakan isi ulang saldo API lalu klik tombol <b>Coba lagi</b> pada kandidat terkait.
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
                    <span class="text-xs text-red-700 font-medium">Build gagal: {{ $wpBuild->inspection_results['error'] ?? 'Terjadi kesalahan' }}</span>
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
            <div class="border border-slate-200 rounded-xl overflow-hidden bg-white flex flex-col">
                @if ($candidate->isRunning())
                    <div class="aspect-[16/10] bg-slate-50 flex items-center justify-center">
                        <div class="text-center px-4">
                            <i class='bx bx-loader-alt animate-spin text-2xl text-brand-600' aria-hidden="true"></i>
                            <p class="text-sm text-slate-600 mt-2">Sedang dibuat…</p>
                        </div>
                    </div>
                    <div class="p-4 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-800">Kandidat {{ $candidate->candidate_number }}</span>
                            <span class="text-xs px-2 py-1 rounded-full bg-brand-50 text-brand-700">Sedang dibuat</span>
                        </div>
                        <p class="text-sm text-slate-600">AI sedang merancang dan mengoreksi hasilnya.
                            Muat ulang halaman beberapa saat lagi.</p>
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
                            action="{{ route('pages.projects.mockup.regenerate', [$project, $candidate]) }}">
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
                            <img src="{{ route('pages.projects.mockup.preview', $candidate) }}"
                                alt="Pratinjau kandidat {{ $candidate->candidate_number }}"
                                class="w-full h-full object-cover object-top" loading="lazy">
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

    @if ($hasRunning || $isBuilding)
        <script>
            (function() {
                setTimeout(function() {
                    window.location.reload();
                }, 4000);
            })();
        </script>
    @endif
@endif
