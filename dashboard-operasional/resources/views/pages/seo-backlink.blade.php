@extends('layouts.app')
@section('title', 'SEO & Backlink Workspace')

@section('content')

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-2">
        <x-page-header title="SEO & Backlink Workspace" />
        @if ($project)
            <div class="flex flex-col sm:flex-row gap-2 w-fit">
                <a href="{{ route('pages.projects.seo-proposal.download', $project) }}"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-600 hover:text-emerald-800 border border-emerald-200 hover:bg-emerald-50 px-3 py-2 rounded-lg transition w-fit">
                    <i class='bx bx-file'></i> Unduh Proposal (PDF)
                </a>
                <a href="{{ route('pages.projects.seo-backlink.report.download', $project) }}"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-blue-600 hover:text-blue-800 border border-blue-200 hover:bg-blue-50 px-3 py-2 rounded-lg transition w-fit">
                    <i class='bx bx-download'></i> Unduh Laporan (PDF)
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
                            Ringkasan
                        </button>
                        <button type="button" @click="tab = 'performa'; location.hash = 'performa'"
                            :class="tab === 'performa' ? 'border-brand-500 text-brand-600 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-600'"
                            class="px-4 py-2.5 text-sm border-b-2 -mb-px transition whitespace-nowrap">
                            Performa
                        </button>
                        <button type="button" @click="tab = 'traffic'; location.hash = 'traffic'"
                            :class="tab === 'traffic' ? 'border-brand-500 text-brand-600 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-600'"
                            class="px-4 py-2.5 text-sm border-b-2 -mb-px transition whitespace-nowrap">
                            Laporan Traffic
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
                                                    <span
                                                        class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-full mr-1 mb-1">{{ $kw }}</span>
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
                                                    <span
                                                        class="inline-block bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded-full">{{ ucfirst($cmsPlatform === 'baru' ? 'Website Baru (dari kami)' : $cmsPlatform) }}</span>
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
                                            <span
                                                class="text-slate-700">{{ $backlink['priority'] === 'quality' ? 'Kualitas' : ($backlink['priority'] === 'quantity' ? 'Kuantitas' : '-') }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-slate-400 w-28 flex-shrink-0">Niche</span>
                                            <span class="text-slate-700">{{ $backlink['niche'] ?: '-' }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-slate-400 w-28 flex-shrink-0">Jenis anchor</span>
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

                        {{-- KARTU ANALISIS AI — keyword & kompetitor otomatis --}}
                        <x-card class="mb-6">
                            <div class="flex items-center justify-between mb-1">
                                <h2 class="font-semibold text-slate-800">Analisis AI — Keyword & Kompetitor</h2>
                                @if ($aiRecommendations)
                                    <span
                                        class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                        <i class='bx bx-check'></i> Sudah dianalisis
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-400 mb-4">
                                Riset keyword & temukan kompetitor otomatis dari URL website client, dibantu Gemini AI + Google Ads.
                            </p>

                            @if (!$resolvedUrl)
                                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                    URL website client belum diisi. Isi dulu di
                                    <a href="{{ route('pages.projects.edit', $project) }}" class="underline font-medium">halaman Edit
                                        Project</a>
                                    — analisis akan otomatis jalan sekali setelah URL tersimpan, atau bisa dipicu manual di sini
                                    setelahnya.
                                </div>
                            @else
                                <div id="aiAnalysisBox" data-project-id="{{ $project->id }}">
                                    <div id="aiIdleState" class="{{ $aiRecommendations ? 'hidden' : '' }}">
                                        <button type="button" id="btnStartAnalysis"
                                            class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg">
                                            <i class='bx bx-magic-wand'></i> Mulai Analisis AI
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
                                            class="mt-2 text-xs font-semibold text-red-700 underline">Coba lagi</button>
                                    </div>

                                    @if ($aiRecommendations)
                                        <button type="button" id="btnRerunAnalysis"
                                            class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                                            Jalankan ulang analisis
                                        </button>
                                    @endif
                                </div>

                                @if ($aiRecommendations)
                                    <div class="border-t border-slate-100 mt-4 pt-4 space-y-4">
                                        @if (!empty($aiTopics['core_topics']))
                                            <div>
                                                <p class="text-xs text-slate-400 mb-1">Topik inti terdeteksi</p>
                                                <div>
                                                    @foreach ($aiTopics['core_topics'] as $topic)
                                                        <span
                                                            class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-full mr-1 mb-1">{{ $topic }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <div>
                                            <p class="text-xs text-slate-400 mb-2">
                                                10 Keyword Utama
                                                <span
                                                    class="ml-1 inline-block text-[10px] px-1.5 py-0.5 rounded-full {{ ($aiRecommendations['data_source'] ?? '') === 'google_ads_api' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}">
                                                    {{ ($aiRecommendations['data_source'] ?? '') === 'google_ads_api' ? 'Data Google Ads' : 'Estimasi AI' }}
                                                </span>
                                            </p>
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-sm">
                                                    <thead>
                                                        <tr class="text-left text-xs text-slate-400 border-b border-slate-100">
                                                            <th class="pb-2 pr-4">Keyword</th>
                                                            <th class="pb-2 pr-4">Volume/bulan</th>
                                                            <th class="pb-2 pr-4">Persaingan</th>
                                                            <th class="pb-2">Alasan</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach (($aiRecommendations['main_keywords'] ?? []) as $kw)
                                                            <tr class="border-b border-slate-50">
                                                                <td class="py-1.5 pr-4 font-medium text-slate-700">{{ $kw['keyword'] ?? '-' }}</td>
                                                                <td class="py-1.5 pr-4 text-slate-500">{{ $kw['avg_monthly_searches'] ?? '-' }}</td>
                                                                <td class="py-1.5 pr-4 text-slate-500">{{ $kw['competition'] ?? '-' }}</td>
                                                                <td class="py-1.5 text-slate-500">{{ $kw['reasoning'] ?? '-' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
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
                                                <p class="text-xs text-slate-400 mb-1">Ringkasan strategi</p>
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
                                                <p class="text-xs text-slate-400 mb-2">
                                                    Kompetitor ditemukan — centang maks 3 yang mau dianalisis, atau tambahkan URL lain secara
                                                    manual di bawah kalau menurut tim lebih relevan
                                                </p>

                                                <form id="competitorSelectForm">
                                                    @csrf
                                                    <ul class="text-sm space-y-1 mb-3">
                                                        @foreach ($discoveredCompetitors as $url)
                                                            <li class="flex items-center gap-2">
                                                                <input type="checkbox" name="selected_urls[]" value="{{ $url }}"
                                                                    class="competitor-checkbox"
                                                                    {{ in_array($url, $selectedCompetitors) ? 'checked' : '' }}>
                                                                <a href="{{ $url }}" target="_blank" rel="noopener"
                                                                    class="text-blue-600 hover:underline break-all">{{ $url }}</a>
                                                            </li>
                                                        @endforeach
                                                    </ul>

                                                    <label class="text-xs text-slate-400 mb-1 block">
                                                        Tambahkan kompetitor lain secara manual (1 URL per baris, ikut dihitung dalam batas 3):
                                                    </label>
                                                    <textarea name="manual_urls" rows="2" placeholder="https://contoh-kompetitor-lain.com/"
                                                        class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 mb-2"></textarea>

                                                    <button type="submit" id="btnSelectCompetitors"
                                                        class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg">
                                                        <i class='bx bx-refresh'></i>
                                                        {{ $competitorPagespeed ? 'Pilih Ulang & Analisis' : 'Pilih & Analisis Kompetitor Ini' }}
                                                    </button>
                                                    <span id="compPagespeedSpinner" class="hidden text-xs text-slate-400 ml-2">Memproses (bisa
                                                        1-2 menit)...</span>
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
                                                                        class="text-[11px] text-blue-600 hover:underline block">Buka Semrush &rarr;</a>

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
                                                                                    class="text-[11px] text-red-600 hover:underline">Hapus</button>
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
                                                                                class="text-[11px] bg-blue-600 text-white px-2 py-1 rounded mt-1">Unggah</button>
                                                                        </form>
                                                                    @endif
                                                                </div>

                                                                <div class="mt-2 pt-2 border-t border-slate-100">
                                                                    <a href="https://pagespeed.web.dev/report?url={{ urlencode($comp['url']) }}"
                                                                        target="_blank" rel="noopener"
                                                                        class="text-[11px] text-blue-600 hover:underline block">Buka PageSpeed &rarr;</a>

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
                                                                                    class="text-[11px] text-red-600 hover:underline">Hapus</button>
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
                                                                                class="text-[11px] bg-blue-600 text-white px-2 py-1 rounded mt-1">Unggah</button>
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
                                                                    errorBox.textContent = data.message || 'Gagal menganalisis.';
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
                                                                    errorBox.textContent = data.message || 'Gagal memulai analisis.';
                                                                    errorBox.classList.remove('hidden');
                                                                }
                                                            })
                                                            .catch(() => {
                                                                btn.disabled = false;
                                                                spinner.classList.add('hidden');
                                                                errorBox.textContent = 'Gagal memulai analisis. Coba lagi.';
                                                                errorBox.classList.remove('hidden');
                                                            });
                                                    });

                                                    checkboxes.forEach(cb => cb.addEventListener('change', function () {
                                                        const checked = document.querySelectorAll('.competitor-checkbox:checked');
                                                        if (checked.length > 3) {
                                                            this.checked = false;
                                                            errorBox.textContent = 'Maksimal 3 kompetitor (termasuk yang ditambah manual).';
                                                            errorBox.classList.remove('hidden');
                                                        } else {
                                                            errorBox.classList.add('hidden');
                                                        }
                                                    }));
                                                })();
                                            </script>
                                        @else
                                            <p class="empty-note text-xs text-slate-400">Belum ada kompetitor yang ditemukan untuk project ini.</p>
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
                                                        errorMessage.textContent = data.message || 'Analisis gagal, silakan coba lagi.';
                                                        showState('error');
                                                    }
                                                })
                                                .catch(() => {});
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
                                                    errorMessage.textContent = 'Gagal memulai analisis. Coba lagi.';
                                                    showState('error');
                                                });
                                        }

                                        btnStart?.addEventListener('click', triggerAnalysis);
                                        btnRetry?.addEventListener('click', triggerAnalysis);
                                        btnRerun?.addEventListener('click', triggerAnalysis);
                                    })();
                                </script>
                            @endif
                        </x-card>
                    </div>

                    {{-- ===================== TAB: PERFORMA ===================== --}}
                    <div x-show="tab === 'performa'">
                        <x-card class="mb-6">
                            <div class="flex items-center justify-between mb-1">
                                <h2 class="font-semibold text-slate-800">Performa Website (PageSpeed)</h2>
                                @if ($pagespeed)
                                    <span
                                        class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                        <i class='bx bx-check'></i> Sudah dianalisis
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-400 mb-4">
                                Cek kecepatan & Core Web Vitals website dari Google PageSpeed Insights (Lighthouse) — dipakai
                                Google sebagai salah satu faktor ranking.
                            </p>

                            @if (!$resolvedUrl)
                                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                    URL website client belum diisi. Isi dulu di
                                    <a href="{{ route('pages.projects.edit', $project) }}" class="underline font-medium">halaman Edit
                                        Project</a>.
                                </div>
                            @else
                                <div id="pagespeedBox">
                                    <button type="button" id="btnAnalyzePagespeed"
                                        class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg">
                                        <i class='bx bx-tachometer'></i> {{ $pagespeed ? 'Jalankan Ulang Analisis' : 'Analisis Performa' }}
                                    </button>
                                    <p class="text-xs text-slate-400 mt-1">Butuh waktu 30-60 detik — Google menjalankan audit langsung,
                                        bukan data instan.</p>

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
                                                                    <th class="pb-1 pr-4">Metrik</th>
                                                                    <th class="pb-1 pr-4">Nilai</th>
                                                                    <th class="pb-1 pr-4">Status</th>
                                                                    <th class="pb-1">Sumber</th>
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
                                                                            {{ ($m['source'] ?? null) === 'field' ? 'Data pengguna nyata' : (($m['source'] ?? null) === 'lab' ? 'Simulasi' : '-') }}
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                        <p class="text-xs text-slate-400">Terakhir dianalisis: {{ $pagespeed['analyzed_at'] ?? '-' }}</p>

                                        <div class="border-t border-slate-100 mt-4 pt-4">
                                            <p class="text-xs font-medium text-slate-600 mb-1">Screenshot Semrush Overview (untuk Proposal)</p>
                                            <a href="https://www.semrush.com/analytics/overview/?q={{ urlencode(parse_url($resolvedUrl, PHP_URL_HOST)) }}&searchType=domain"
                                                target="_blank" rel="noopener" class="text-xs text-blue-600 hover:underline">
                                                Buka Semrush untuk domain ini &rarr;
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
                                                        <button type="submit" class="text-xs text-red-600 hover:underline">Hapus & unggah
                                                            ulang</button>
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
                                                        class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg">Unggah</button>
                                                </form>
                                            @endif
                                        </div>

                                        <div class="border-t border-slate-100 mt-4 pt-4">
                                            <p class="text-xs font-medium text-slate-600 mb-1">Screenshot Laporan PageSpeed (untuk Proposal)</p>
                                            <a href="https://pagespeed.web.dev/report?url={{ urlencode($resolvedUrl) }}" target="_blank"
                                                rel="noopener" class="text-xs text-blue-600 hover:underline">Buka PageSpeed untuk situs ini
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
                                                        <button type="submit" class="text-xs text-red-600 hover:underline">Hapus & unggah
                                                            ulang</button>
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
                                                        class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg">Unggah</button>
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
                                                        errorBox.textContent = data.message || 'Analisis gagal.';
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
                                                    errorBox.textContent = 'Gagal memulai analisis.';
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
                                <h2 class="font-semibold text-slate-800">Laporan Search Console</h2>
                                @if ($searchConsole)
                                    <span
                                        class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                        <i class='bx bx-check'></i> Ada data
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-400 mb-4">
                                Data klik, tayang, dan kata kunci pencarian dari Google Search Console (28 hari terakhir).
                            </p>

                            @if (!$resolvedUrl)
                                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                    URL website client belum diisi.
                                </div>
                            @else
                                <button type="button" id="btnAnalyzeGsc"
                                    class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg">
                                    <i class='bx bx-refresh'></i> {{ $searchConsole ? 'Refresh Data' : 'Tarik Data Search Console' }}
                                </button>
                                <span id="gscSpinner" class="hidden text-xs text-slate-400 ml-2">Memuat...</span>

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
                                                <div class="text-[11px] text-slate-400">Total Klik</div>
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
                                                <div class="text-[11px] text-slate-400">Posisi Rata-rata</div>
                                            </div>
                                        </div>

                                        @if (!empty($gscTopQueries))
                                            <p class="text-xs text-slate-400 mb-2">Top Kata Kunci Pencarian</p>
                                            <div class="overflow-x-auto mb-4">
                                                <table class="w-full text-sm">
                                                    <thead>
                                                        <tr class="text-left text-xs text-slate-400 border-b border-slate-100">
                                                            <th class="pb-1 pr-4">Query</th>
                                                            <th class="pb-1 pr-4">Klik</th>
                                                            <th class="pb-1 pr-4">Tayang</th>
                                                            <th class="pb-1 pr-4">CTR</th>
                                                            <th class="pb-1">Posisi</th>
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
                                            <p class="text-xs text-slate-400 mb-2">Klik per Device</p>
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-sm">
                                                    <thead>
                                                        <tr class="text-left text-xs text-slate-400 border-b border-slate-100">
                                                            <th class="pb-1 pr-4">Device</th>
                                                            <th class="pb-1">Klik</th>
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
                                            Periode: {{ $searchConsole['period']['start'] ?? '-' }} s/d
                                            {{ $searchConsole['period']['end'] ?? '-' }}
                                            &middot; Terakhir ditarik: {{ $searchConsole['analyzed_at'] ?? '-' }}
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
                                                        errorBox.textContent = data.message || 'Gagal mengambil data Search Console.';
                                                        errorBox.classList.remove('hidden');
                                                    }
                                                })
                                                .catch(() => {
                                                    btn.disabled = false;
                                                    spinner.classList.add('hidden');
                                                    errorBox.textContent = 'Gagal mengambil data. Coba lagi.';
                                                    errorBox.classList.remove('hidden');
                                                });
                                        });
                                    })();
                                </script>
                            @endif
                        </x-card>

                        <x-card class="mb-6">
                            <div class="flex items-center justify-between mb-1">
                                <h2 class="font-semibold text-slate-800">Laporan Google Analytics (GA4)</h2>
                                @if ($ga4)
                                    <span
                                        class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                        <i class='bx bx-check'></i> Ada data
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-400 mb-4">
                                Sessions, engagement, dan konversi dari pengunjung organik (28 hari terakhir).
                            </p>

                            @if (!$resolvedUrl)
                                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                    URL website client belum diisi.
                                </div>
                            @else
                                <button type="button" id="btnAnalyzeGa4"
                                    class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg">
                                    <i class='bx bx-refresh'></i> {{ $ga4 ? 'Refresh Data' : 'Tarik Data GA4' }}
                                </button>
                                <span id="ga4Spinner" class="hidden text-xs text-slate-400 ml-2">Memuat...</span>

                                <div id="ga4Error" class="hidden mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
                                </div>

                                <div id="ga4SelectBox" class="hidden mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <p class="text-sm text-blue-700 mb-2">Ketemu beberapa Property GA4 yang cocok, pilih salah satu:</p>
                                    <select id="ga4PropertySelect"
                                        class="w-full bg-white border border-blue-200 rounded-lg px-3 py-2 text-sm mb-2"></select>
                                    <button type="button" id="btnConfirmGa4Property"
                                        class="text-xs font-semibold bg-blue-600 text-white px-3 py-1.5 rounded-lg">Pakai Property
                                        Ini</button>
                                </div>

                                <div id="ga4ManualBox" class="hidden mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                    <p class="text-sm text-amber-700 mb-2">Tidak ketemu Property GA4 otomatis. Isi Property ID manual (GA4 →
                                        Admin → Property Settings, formatnya angka saja, mis. 123456789):</p>
                                    <input type="text" id="ga4ManualInput" placeholder="123456789"
                                        class="w-full border border-amber-200 rounded-lg px-3 py-2 text-sm mb-2">
                                    <button type="button" id="btnConfirmGa4Manual"
                                        class="text-xs font-semibold bg-amber-600 text-white px-3 py-1.5 rounded-lg">Simpan & Tarik
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
                                                            <th class="pb-1 pr-4">Halaman</th>
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
                                            &middot; Periode: {{ $ga4['period']['start'] ?? '-' }} s/d {{ $ga4['period']['end'] ?? '-' }}
                                            &middot; Terakhir ditarik: {{ $ga4['analyzed_at'] ?? '-' }}
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
                                                        errorBox.textContent = data.message || 'Gagal mengambil data GA4.';
                                                        errorBox.classList.remove('hidden');
                                                    }
                                                })
                                                .catch(() => {
                                                    btn.disabled = false;
                                                    spinner.classList.add('hidden');
                                                    errorBox.textContent = 'Gagal mengambil data. Coba lagi.';
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
