@extends('layouts.app')
@section('title', 'SEO & Backlink Workspace')

@section('content')

    <x-page-header title="SEO & Backlink Workspace" />

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ session('error') }}</div>
    @endif

    {{-- PILIH PROJECT --}}
    <x-card class="mb-6">
        <form method="GET" class="flex flex-col sm:flex-row sm:items-center gap-3">
            <label class="text-sm font-medium text-slate-600">Project:</label>
            <select name="project" onchange="this.form.submit()"
                class="flex-1 bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none">
                <option value="">-- Pilih project --</option>
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
            <p class="text-sm text-slate-400 text-center">Pilih project di atas untuk kelola SEO & Backlink.</p>
        </x-card>
    @else
        <!-- {{-- INFO PROJECT --}}
        <x-card class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="font-semibold text-slate-800">{{ $project->name }}</p>
                    <p class="text-sm text-slate-400">{{ $project->client_name }} &middot; {{ $project->type ?? '-' }}</p>
                </div>
                @if ($project->mockupTemplate)
                    <div
                        class="flex items-center gap-2 text-xs text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-lg w-fit">
                        <i class='bx bx-check-circle'></i> Mockup: {{ $project->mockupTemplate->name }}
                    </div>
                @else
                    <div class="text-xs text-amber-600 bg-amber-50 px-3 py-1.5 rounded-lg w-fit">
                        Belum ada mockup dipilih
                    </div>
                @endif
            </div>
        </x-card> -->

        {{-- SEO & BACKLINK --}}
        @if (!$project->wants_seo && !$project->wants_backlink)
            <x-card padding="p-10">
                <p class="text-sm text-slate-400 text-center">
                    Project ini tidak meminta layanan SEO atau Backlink saat pengajuan.
                </p>
            </x-card>
        @else
            @php
                $seo = $project->seo_requirements ?? [];
                $backlink = $project->backlink_requirements ?? [];
                $cmsPlatform = $seo['cms_platform'] ?? null;

                // Status koneksi SELALU mulai dari "belum terhubung" — tidak
                // ada cara otomatis dapat kredensial WordPress dari ZipWP
                // maupun dari client, keduanya butuh 1x isi manual lewat
                // form "Hubungkan Website" (belum diimplementasikan di sini,
                // placeholder dulu sampai fitur Connect Website dibangun).
                $isConnected = false; // TODO: ganti jadi cek kolom wp_application_password setelah fitur Connect Website dibangun

                $connectHint = match (true) {
                    $cmsPlatform === 'baru' => 'Ambil username & password dari dashboard sandbox ZipWP, lalu tempel di sini.',
                    $cmsPlatform === 'wordpress' => 'Minta akses WordPress dari client, lalu tempel di sini.',
                    in_array($cmsPlatform, ['shopify', 'wix']) => 'Platform ini (' . ucfirst($cmsPlatform) . ') belum didukung untuk publish otomatis — artikel akan disediakan untuk diunduh/disalin manual.',
                    default => 'Tanyakan ke client platform website mereka untuk mengaktifkan publish otomatis.',
                };

                $keywordList = collect(explode(',', $seo['keywords'] ?? ''))
                    ->map(fn($k) => trim($k))
                    ->filter()
                    ->values();
            @endphp

            <x-card class="mb-6">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="font-semibold text-slate-800">SEO & Backlink</h2>
                    @if ($isConnected)
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                            <i class='bx bx-check'></i> Terhubung
                        </span>
                    @else
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
                            Belum terhubung
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-400 mb-4">Kebutuhan yang diisi client saat pengajuan project</p>

                {{-- SEO --}}
                @if ($project->wants_seo)
                    <div class="border-t border-slate-100 pt-4 mb-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i class='bx bx-search text-blue-600'></i>
                            <span class="text-sm font-medium text-slate-700">Kebutuhan SEO</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                            <div class="flex gap-2">
                                <span class="text-slate-400 w-28 flex-shrink-0">URL target</span>
                                <span class="text-slate-700 break-all">{{ $seo['target_url'] ?: '-' }}</span>
                            </div>
                            <div class="flex gap-2">
                                <span class="text-slate-400 w-28 flex-shrink-0">Lokasi</span>
                                <span class="text-slate-700">{{ $seo['location'] ?: '-' }}</span>
                            </div>
                            <div class="flex gap-2 sm:col-span-2">
                                <span class="text-slate-400 w-28 flex-shrink-0">Keyword</span>
                                <span class="text-slate-700">
                                    @forelse ($keywordList->take(4) as $kw)
                                        <span class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-full mr-1 mb-1">{{ $kw }}</span>
                                    @empty
                                        -
                                    @endforelse
                                    @if ($keywordList->count() > 4)
                                        <span class="text-xs text-slate-400">+{{ $keywordList->count() - 4 }} lainnya</span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <span class="text-slate-400 w-28 flex-shrink-0">Platform</span>
                                <span>
                                    @if ($cmsPlatform)
                                        <span class="inline-block bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded-full">{{ ucfirst($cmsPlatform === 'baru' ? 'Website Baru (dari kami)' : $cmsPlatform) }}</span>
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
                            <span class="text-sm font-medium text-slate-700">Kebutuhan Backlink</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                            <div class="flex gap-2">
                                <span class="text-slate-400 w-28 flex-shrink-0">Jumlah</span>
                                <span class="text-slate-700">{{ $backlink['quantity'] ?? '-' }} backlink</span>
                            </div>
                            <div class="flex gap-2">
                                <span class="text-slate-400 w-28 flex-shrink-0">Prioritas</span>
                                <span class="text-slate-700">{{ $backlink['priority'] === 'quality' ? 'Kualitas' : ($backlink['priority'] === 'quantity' ? 'Kuantitas' : '-') }}</span>
                            </div>
                            <div class="flex gap-2">
                                <span class="text-slate-400 w-28 flex-shrink-0">Niche</span>
                                <span class="text-slate-700">{{ $backlink['niche'] ?: '-' }}</span>
                            </div>
                            <div class="flex gap-2">
                                <span class="text-slate-400 w-28 flex-shrink-0">Jenis anchor</span>
                                <span class="text-slate-700">
                                    @forelse (($backlink['anchor_type'] ?? []) as $type)
                                        <span class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-full mr-1">{{ str_replace('_', ' ', ucfirst($type)) }}</span>
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
                            <i class='bx bx-plug'></i> Hubungkan Website
                        </button>
                        <p class="text-xs text-slate-400">{{ $connectHint }}</p>
                    @else
                        <button type="button" disabled
                            class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg opacity-60 cursor-not-allowed w-fit">
                            <i class='bx bx-magic-wand'></i> Generate Artikel
                        </button>
                        <p class="text-xs text-slate-400">Fitur generate artikel belum tersedia</p>
                    @endif
                </div>
            </x-card>
        @endif
    @endif

@endsection