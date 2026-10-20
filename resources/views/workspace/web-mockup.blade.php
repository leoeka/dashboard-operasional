<x-card class="mb-6">
    <div class="flex items-center gap-3">
        <div
            class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 border border-slate-300 flex items-center justify-center shrink-0">
            <i class='bx bx-image-alt text-xl'></i>
        </div>
        <div>
            <h2 class="font-bold text-slate-800 text-base">Website Mockup</h2>
            <p class="text-xs text-slate-600">
                @if ($mockupCandidates === [])
                    Dibuat bersama proposal, lalu dibangun menjadi WordPress.
                @elseif ($clientChose)
                    Klien memilih Opsi {{ $selectedMockupIndex + 1 }}. WordPress dibuat dari opsi itu.
                @elseif ($mockupChoice === 'rejected')
                    Belum ada opsi yang disetujui klien.
                @else
                    Opsi dari proposal. Tekan tombol pada opsi yang dipilih klien.
                @endif
            </p>
        </div>
    </div>

    @if ($mockupCandidates !== [])
        <div class="mt-4 grid gap-3 {{ count($mockupCandidates) > 1 ? 'md:grid-cols-3' : 'max-w-sm' }}"
            id="mockup-candidate-grid">
            @foreach ($mockupCandidates as $index => $candidate)
                @php
                    $candidatePath = $candidate['screenshot_path'] ?? null;
                    $isClientChoice = $clientChose && $index === $selectedMockupIndex;
                @endphp
                <div data-mockup-card data-index="{{ $index }}"
                    class="rounded-xl border bg-white p-2 transition {{ $isClientChoice ? 'border-brand-600' : 'border-slate-200' }}">
                    @if ($candidatePath)
                        <a href="{{ Storage::url($candidatePath) }}" target="_blank" rel="noopener"
                            class="block rounded-lg bg-slate-50 max-h-[280px] overflow-hidden"
                            title="Buka ukuran penuh di tab baru">
                            <img src="{{ Storage::url($candidatePath) }}"
                                alt="Mockup opsi {{ $index + 1 }}"
                                class="w-full h-full max-h-[280px] object-contain object-top">
                        </a>
                    @endif
                    <p class="px-1 pt-2 text-xs font-semibold text-slate-700 flex items-center justify-between gap-2">
                        <span>Opsi {{ $index + 1 }}</span>
                        @if ($isClientChoice)
                            <span class="inline-flex items-center gap-1 text-brand-700">
                                <i class='bx bx-check' aria-hidden="true"></i> Pilihan klien
                            </span>
                        @endif
                    </p>
                    {{-- Live, responsive website demo of this same blueprint (the PNG above stays for the PDF). --}}
                    <a href="{{ route('pages.projects.mockup.demo', [$project, $index]) }}"
                        target="_blank" rel="noopener"
                        class="min-h-11 mt-2 w-full inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        <i class='bx bx-desktop'></i> Open Demo
                    </a>
                    @if ($isClientChoice)
                        <p class="mt-2 px-1 py-2 text-xs text-slate-600">
                            Opsi ini yang dijadikan WordPress. Tekan tombol di opsi lain bila klien berganti pilihan.
                        </p>
                    @else
                        <form method="POST" action="{{ route('pages.projects.proposal.approve', $project) }}"
                            class="mt-2">
                            @csrf
                            <input type="hidden" name="mockup_index" value="{{ $index }}">
                            <button type="submit"
                                class="min-h-11 w-full rounded-md bg-slate-900 text-white hover:bg-slate-700 px-3 py-2 text-xs font-semibold transition">
                                <i class='bx bx-user-check'></i> Klien pilih opsi ini
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Satu tombol build untuk semua kondisi: opsi yang dibangun selalu
        dibaca dari pilihan yang tercatat, jadi panel ini tidak bisa
        mengirim build ke opsi lain. --}}
        <div class="mt-4 rounded-xl border border-slate-200 p-4">
            @if ($clientChose)
                <p class="text-xs text-slate-600">
                    Build memakai Opsi {{ $selectedMockupIndex + 1 }} yang disetujui klien.
                </p>
                @if ($wordpressReady)
                    <p class="mt-2 text-xs text-slate-700">
                        ZIP siap diunduh, dibuat
                        <span class="font-mono">{{ \Illuminate\Support\Carbon::parse($latestBundle->exported_at)->translatedFormat('d M Y') }}</span>.
                    </p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <a href="{{ route('pages.projects.bundle.download', $project) }}"
                            class="min-h-11 inline-flex items-center justify-center gap-2 bg-slate-900 text-white hover:bg-slate-700 text-xs font-semibold px-4 py-3 rounded-md transition">
                            <i class='bx bx-download'></i> Unduh WordPress
                        </a>
                        <form method="POST" action="{{ route('pages.projects.bundle.build', $project) }}"
                            onsubmit="return startBuildWebsite({{ $project->id }})">
                            @csrf
                            <button type="submit" data-build-button
                                class="min-h-11 w-full inline-flex items-center justify-center gap-2 border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 text-xs font-semibold px-4 py-3 rounded-md transition disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class='bx bx-refresh'></i> <span data-build-label
                                    data-idle-label="Buat ulang WordPress">Buat ulang WordPress</span>
                            </button>
                        </form>
                    </div>
                @else
                    @if ($latestBundle?->status === 'failed')
                        <p role="alert" class="mt-2 text-xs font-semibold text-red-700">
                            Build terakhir gagal. ZIP dari build sebelumnya tidak ditawarkan sebagai hasil terbaru.
                        </p>
                    @endif
                    <form method="POST" action="{{ route('pages.projects.bundle.build', $project) }}"
                        class="mt-3" onsubmit="return startBuildWebsite({{ $project->id }})">
                        @csrf
                        <button type="submit" data-build-button
                            class="min-h-11 w-full inline-flex items-center justify-center gap-2 bg-slate-900 text-white hover:bg-slate-700 text-xs font-semibold px-4 py-3 rounded-md transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class='bx bx-package'></i> <span data-build-label
                                data-idle-label="Buat WordPress siap install">Buat WordPress siap install</span>
                        </button>
                    </form>
                @endif
            @elseif ($mockupChoice === 'rejected')
                <p class="text-sm font-semibold text-slate-700">Belum ada opsi yang dipilih</p>
                <p class="mt-1 text-xs text-slate-600">
                    Build WordPress terkunci sampai klien menyetujui salah satu opsi. Buat opsi baru untuk mengirim
                    desain yang berbeda.
                </p>
                <button type="button" onclick="startGenerateProposal({{ $project->id }})"
                    class="min-h-11 mt-3 w-full inline-flex items-center justify-center gap-2 border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 text-xs font-semibold px-4 py-3 rounded-md transition">
                    <i class='bx bx-refresh'></i> Buat opsi baru
                </button>
            @else
                <p class="text-sm font-semibold text-slate-700">Build WordPress masih terkunci</p>
                <p class="mt-1 text-xs text-slate-600">
                    Rekam dulu jawaban klien dengan tombol "Klien pilih opsi ini" di atas. ZIP dibuat dari opsi itu,
                    tidak dari tombol build.
                </p>
                <button type="button" disabled
                    class="min-h-11 mt-3 w-full inline-flex items-center justify-center gap-2 bg-slate-900 text-white text-xs font-semibold px-4 py-3 rounded-md opacity-50 cursor-not-allowed">
                    <i class='bx bx-lock-alt'></i> Buat WordPress siap install
                </button>
            @endif

            {{-- Once the ZIP exists the answer has already produced its result;
            a client who changes their mind from here needs a new round, not a
            control that rewinds a delivered file. --}}
            @if ($mockupChoice !== 'rejected' && ! $wordpressReady)
                <form method="POST" action="{{ route('pages.projects.proposal.revise', $project) }}" class="mt-3">
                    @csrf
                    <button type="submit"
                        class="min-h-11 w-full text-xs font-semibold text-slate-600 hover:text-slate-800 underline underline-offset-2 transition">
                        Klien menjawab tidak ada yang cocok
                    </button>
                </form>
            @endif
        </div>
    @else
        {{-- BELUM ADA MOCKUP: project baru dibuat, atau proposal belum
        di-generate. Progress ditampilkan lewat popup <x-progress-modal>
        (dipanggil dari script di bawah), bukan bar inline lagi. --}}
        <div id="mockup-idle-warning" class="mt-4 text-center py-8 border border-dashed border-slate-200 rounded-xl">
            <div
                class="mx-auto mb-3 w-10 h-10 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center">
                <i class='bx bx-image-alt text-xl'></i>
            </div>
            <p class="text-sm font-semibold text-slate-600">Mockup not yet created</p>
            <p class="text-xs text-slate-600 mt-1 max-w-sm mx-auto">
                The mockup will be automatically generated once the proposal is successfully created. Please
                generate the proposal first.
            </p>
        </div>
    @endif
</x-card>
