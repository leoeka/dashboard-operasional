@extends('layouts.app')

@section('title', 'Preview Proposal')

@section('content')
    <div class="max-w-6xl mx-auto space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Proposal Preview</p>
                <h1 class="text-2xl font-bold text-slate-800 mt-1">{{ $project->client_name }}</h1>
                <p class="text-sm text-slate-500 mt-1">{{ $project->name }} · {{ $project->code }}</p>
            </div>
            <a href="{{ route('pages.projects.show', $project) }}"
                class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                <i class='bx bx-arrow-back'></i>
                Kembali
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <i class='bx bxs-file-pdf text-lg text-red-500'></i>
                    Dokumen Proposal
                </span>
                <div class="flex items-center gap-3 text-xs font-semibold">
                    <a href="{{ Storage::url($proposal->pdf_path) }}" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1 text-blue-600 hover:underline">
                        Buka Tab Baru <i class='bx bx-link-external'></i>
                    </a>
                    <a href="{{ route('pages.projects.proposal.download', $project) }}"
                        class="inline-flex items-center gap-1 text-slate-600 hover:underline">
                        Download <i class='bx bx-download'></i>
                    </a>
                </div>
            </div>
            <iframe src="{{ Storage::url($proposal->pdf_path) }}#toolbar=0&navpanes=0&scrollbar=0"
                class="h-[calc(100vh-220px)] min-h-[650px] w-full border-0" title="Proposal PDF Viewer"></iframe>
        </div>
    </div>
@endsection