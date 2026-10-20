@extends('layouts.app')

@section('title', 'Proposal Preview')

@section('content')
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-600">Proposal Preview</p>
                <h1 class="text-2xl font-bold text-slate-800 mt-1">{{ $project->client_name }}</h1>
                <p class="text-sm text-slate-600 mt-1">{{ $project->name }} · {{ $project->code }}</p>
            </div>
            <a href="{{ route('pages.project-workspace', ['project' => $project->id]) }}"
                class="min-h-11 inline-flex items-center gap-2 rounded-md bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                <i class='bx bx-arrow-back'></i>
                Back to Workspace
            </a>
        </div>

        @if ($proposal->pdf_path)
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <i class='bx bxs-file-pdf text-lg text-red-700'></i>
                        Proposal Document
                    </span>
                    <div class="flex items-center gap-3 text-xs font-semibold">
                        <a href="{{ Storage::url($proposal->pdf_path) }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-1 text-brand-600 hover:underline">
                            Open in New Tab <i class='bx bx-link-external'></i>
                        </a>
                        <a href="{{ route('pages.projects.proposal.download', $project) }}"
                            class="inline-flex items-center gap-1 text-slate-600 hover:underline">
                            Download <i class='bx bx-download'></i>
                        </a>
                    </div>
                </div>
                {{--
                #toolbar=0&navpanes=0&scrollbar=0 mematikan toolbar gelap +
                sidebar thumbnail bawaan viewer PDF browser, biar yang kelihatan
                cuma dokumennya sendiri, bukan "aplikasi PDF reader" di dalam kartu.
                --}}
                <iframe src="{{ Storage::url($proposal->pdf_path) }}#toolbar=0&navpanes=0&scrollbar=0"
                    class="h-[calc(100vh-220px)] min-h-[650px] w-full border-0" title="Proposal PDF Viewer"></iframe>
            </div>
        @else
            <div class="rounded-xl border border-slate-200 bg-white px-6 py-16 text-center">
                <div
                    class="mx-auto mb-3 w-16 h-16 bg-slate-100 text-slate-600 rounded-full flex items-center justify-center">
                    <i class='bx bx-file-blank text-3xl'></i>
                </div>
                <h2 class="text-sm font-semibold text-slate-700">PDF File Not Available Yet</h2>
                <p class="text-xs text-slate-600 mt-1 max-w-sm mx-auto">
                    The proposal has not been generated yet, or the PDF file has not been saved on the server.
                </p>
            </div>
        @endif
    </div>
@endsection