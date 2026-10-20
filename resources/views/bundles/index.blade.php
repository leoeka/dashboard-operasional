@extends('layouts.app')
@section('title', 'Build WordPress')

@section('content')
    @php
        $latestBundle = $project->bundles()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
        $hasBundle = $latestBundle && $latestBundle->status === 'exported';
    @endphp

    <div class="mb-6 rounded-md border-l-4 border-l-brand-600 bg-brand-50 px-4 py-3 text-sm text-brand-700">
        <div class="flex items-start gap-3">
            <i class='bx bx-info-circle mt-1 text-xl'></i>
            <div>
                <p class="font-bold">Anda sudah berada di langkah terakhir.</p>
                <p class="mt-1 leading-6">Proposal memuat beberapa opsi mockup. Catat pilihan klien di workspace dengan tombol pada opsi tersebut, lalu build theme WordPress siap install dari opsi yang tercatat itu.</p>
            </div>
        </div>
    </div>

    <x-page-header title="Build WordPress">
        <x-slot:actions>
            <a href="{{ route('pages.projects') }}"
                class="min-h-11 inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-800 px-3 py-2 rounded-md border border-slate-300 bg-white transition">
                <i class='bx bx-arrow-back'></i> Back to Projects
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-md bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 px-4 py-3 rounded-md bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>
    @endif
    @if ($latestBundle?->status === 'building')
        <div role="status" class="mb-6 px-4 py-3 rounded-md border-l-4 border-l-brand-700 bg-brand-50 text-brand-700 text-sm">
            Build WordPress sedang berjalan. ZIP belum siap diunduh. Halaman ini memuat sendiri saat build selesai atau gagal.
        </div>
    @elseif ($latestBundle?->status === 'failed')        <div role="alert" class="mb-6 px-4 py-3 rounded-md border-l-4 border-l-red-700 bg-red-50 text-red-700 text-sm">
            Build terakhir gagal. ZIP dari build sebelumnya tidak tersedia sebagai hasil terbaru. Kembali ke workspace untuk mencoba lagi.
        </div>
    @endif

    <div>
        <x-card>
            <div class="space-y-6">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-600 font-semibold">Project yang akan dibuat</p>
                    <p class="text-sm text-slate-500">{{ $project->client_name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-slate-600">Type</p>
                        <p class="mt-1 font-semibold text-slate-700">{{ $project->type ?? 'Website' }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-slate-600">Status</p>
                        <p class="mt-1 font-semibold text-slate-700">{{ $project->statusLabel() }}</p>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-600 font-semibold mb-3">Alur pekerjaan</p>
                    <ol class="space-y-3 text-sm text-slate-600">
                        <li><span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">1</span>Data client dan user story</li>
                        <li><span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">2</span>Gemini membaca target pasar dari user story</li>
                        <li><span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">3</span>GPT membuat konten, mockup, dan proposal</li>
                        <li><span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700">4</span>Klien memilih salah satu opsi mockup</li>
                        <li><span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">5</span>Catat pilihan itu di workspace, lalu build WordPress dari opsi tersebut</li>
                    </ol>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('pages.projects.show', $project) }}" class="inline-flex items-center gap-2 bg-slate-900 text-white text-sm font-semibold px-4 py-3 rounded-md hover:bg-slate-700 transition">
                        <i class='bx bx-layout'></i> Kembali ke Workspace untuk pilih mockup
                    </a>

                    <a href="{{ route('pages.projects.bundle.download', $project) }}"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 border border-slate-300 bg-white hover:bg-slate-50 px-4 py-3 rounded-md transition {{ $hasBundle ? '' : 'pointer-events-none opacity-40' }}">
                        <i class='bx bx-download'></i> Download Theme ZIP siap install
                    </a>
                </div>
            </div>
        </x-card>

    </div>

    @if ($latestBundle?->status === 'building')
        {{-- Baris bundle hanya tahu build belum selesai, bukan sampai mana.
        Status yang sama dibaca popup workspace, jadi halaman ini berhenti
        menunggu sendiri begitu ZIP jadi, build gagal, atau tidak ada lagi
        yang melaporkannya. --}}
        <script>
            (function watchBundleBuild() {
                fetch(@js(route('pages.projects.bundle.status', $project)), {
                        headers: {
                            'Accept': 'application/json'
                        },
                    })
                    .then((res) => (res.ok ? res.json() : Promise.reject(new Error('Status build tidak tersedia.'))))
                    .then((data) => {
                        if (data.status === 'done' || data.status === 'failed') {
                            window.location.reload();
                            return;
                        }
                        // idle = tidak ada lagi yang melaporkan build ini. Baris
                        // bundle tetap "building" dan halaman ini dibiarkan apa
                        // adanya; memuat ulang hanya akan mengulang polling.
                        if (data.status !== 'idle') {
                            setTimeout(watchBundleBuild, 3000);
                        }
                    })
                    .catch(() => setTimeout(watchBundleBuild, 10000));
            })();
        </script>
    @endif
@endsection
