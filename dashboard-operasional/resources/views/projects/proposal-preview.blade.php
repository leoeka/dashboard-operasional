@extends('layouts.app')

@section('title', 'View Proposal - ' . $project->name)

@section('content')

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('pages.projects.show', $project) }}"
                class="text-xs text-slate-500 hover:text-slate-700 flex items-center gap-1 mb-2">
                <i class='bx bx-arrow-back'></i>
                Kembali ke Detail Project
            </a>
            <h1 class="text-xl font-bold text-slate-800">
                Proposal Proyek
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                Preview proposal berdasarkan data request proyek.
            </p>
        </div>
        {{-- Action --}}
        <div class="flex flex-wrap gap-2">
            {{-- Download PDF --}}
            @if ($proposal && $proposal->pdf_path)
                <a href="{{ route('pages.projects.proposal.download', $project) }}"
                    class="inline-flex items-center gap-2
                    bg-emerald-600 text-white
                    text-xs font-semibold px-4 py-2.5 rounded-lg
                    hover:bg-emerald-700
                    active:scale-95
                    transition shadow-sm">
                    <i class='bx bx-download text-base'></i>
                    Download PDF
                </a>
            @endif
        </div>
    </div>

    {{-- Main Content --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- LEFT : Proposal Information --}}
        <div class="lg:col-span-4 space-y-4">
            {{-- Proposal Status --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center">
                            <i class='bx bx-file-blank text-xl'></i>
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-800 text-sm">
                                Proposal
                            </h2>
                            <p class="text-[11px] text-slate-400">
                                Dokumen penawaran proyek
                            </p>
                        </div>
                    </div>
                    @if ($proposal)
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200/60">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Versi {{ $proposal->version }}
                        </span>
                    @else
                        <span class="px-2.5 py-1 text-[11px] font-semibold bg-slate-100 text-slate-500 rounded-full">
                            Belum dibuat
                        </span>
                    @endif
                </div>

                {{-- Project Information --}}
                <div class="space-y-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Client</p>
                        <p class="text-xs font-semibold text-slate-700 mt-1">{{ $project->client_name }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Project</p>
                        <p class="text-xs font-semibold text-slate-700 mt-1">{{ $project->name }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Tipe Website</p>
                        <p class="text-xs font-semibold text-slate-700 mt-1">{{ $project->type ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Project Code</p>
                        <p class="text-xs font-semibold text-slate-700 mt-1">{{ $project->code }}</p>
                    </div>
                </div>
            </div>

            {{-- AI Analysis --}}
            @if ($proposal && $proposal->ai_reasoning)
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                            <i class='bx bx-bot text-lg'></i>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-blue-800">AI Analysis</h3>
                            <p class="text-[10px] text-blue-500">Analisis berdasarkan request proyek</p>
                        </div>
                    </div>
                    <p class="text-xs text-blue-700 leading-relaxed whitespace-pre-line">
                        @if (is_array($proposal->ai_reasoning))
                            {{ json_encode($proposal->ai_reasoning, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                        @else
                            {{ $proposal->ai_reasoning }}
                        @endif
                    </p>
                </div>
            @endif

            {{-- Ringkasan Proposal --}}
            @if ($proposal && $proposal->summary)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <h3 class="text-xs font-bold text-slate-700 mb-2">Ringkasan Proposal</h3>
                    <p class="text-xs text-slate-500 leading-relaxed whitespace-pre-line">{{ $proposal->summary }}</p>
                </div>
            @endif

            {{-- Information --}}
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                <div class="flex gap-3">
                    <i class='bx bx-info-circle text-slate-400 text-lg'></i>
                    <p class="text-[11px] text-slate-500 leading-relaxed">
                        Proposal ini digunakan sebagai dokumen gambaran awal untuk disampaikan kepada client. Setelah
                        proposal dibuat, proses berikutnya dapat dilanjutkan ke tahap mockup website.
                    </p>
                </div>
            </div>
        </div>

        {{-- RIGHT : PDF Viewer --}}
        <div class="lg:col-span-8">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden h-[750px] flex flex-col">
                <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-700 flex items-center gap-2">
                        <i class='bx bxs-file-pdf text-red-500 text-base'></i>
                        Dokumen Viewer
                    </span>
                    @if ($proposal && $proposal->pdf_path)
                        <a href="{{ Storage::url($proposal->pdf_path) }}" target="_blank"
                            class="text-[11px] text-blue-600 hover:underline flex items-center gap-1">
                            Buka Tab Baru <i class='bx bx-link-external'></i>
                        </a>
                    @endif
                </div>

                <div class="flex-1 bg-slate-100">
                    @if ($proposal && $proposal->pdf_path)
                        {{-- PDF Preview iframe --}}
                        <iframe src="{{ Storage::url($proposal->pdf_path) }}" class="w-full h-full border-none"
                            title="Proposal PDF Viewer">
                        </iframe>
                    @else
                        {{-- State Saat PDF Belum Ada --}}
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

    </div>
@endsection
