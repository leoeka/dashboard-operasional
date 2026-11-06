@extends('layouts.app')

@section('title', 'Pratinjau web kandidat ' . $candidate->candidate_number)

@section('content')

    <div class="mb-3 flex flex-wrap items-start sm:items-center justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('pages.project-workspace', ['project' => $project->id]) }}"
                class="min-h-11 inline-flex items-center gap-1.5 rounded-md px-1 text-sm font-semibold text-brand-600 hover:text-brand-700 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                <i class='bx bx-left-arrow-alt' aria-hidden="true"></i> Kembali ke workspace
            </a>
            <h1 class="text-lg font-semibold text-slate-800 truncate">
                Kandidat {{ $candidate->candidate_number }} &mdash; {{ $project->name }}
            </h1>
        </div>
        <a href="{{ route('pages.projects.mockup.preview-live', ['candidate' => $candidate, 'page' => $current]) }}"
            target="_blank" rel="noopener"
            class="min-h-11 inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
            <i class='bx bx-window-open' aria-hidden="true"></i> Buka di tab baru
        </a>
    </div>

    <nav aria-label="Halaman mockup" class="border-b border-slate-200 bg-white overflow-x-auto">
        <ul class="flex gap-1">
            @foreach ($pages as $slug => $page)
                @php
                    $aktif = $slug === $current;
                    $kelasTab = $aktif
                        ? 'border-brand-600 text-brand-700'
                        : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-800';
                @endphp
                <li>
                    <a href="{{ route('pages.projects.mockup.preview-site', ['candidate' => $candidate, 'page' => $slug]) }}"
                        class="min-h-11 inline-flex items-center border-b-2 px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 {{ $kelasTab }}"
                        @if ($aktif) aria-current="page" @endif>
                        {{ $page['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <iframe src="{{ route('pages.projects.mockup.preview-live', ['candidate' => $candidate, 'page' => $current]) }}"
        title="Pratinjau web Kandidat {{ $candidate->candidate_number }} halaman {{ $pages[$current]['label'] }}"
        class="w-full border-0 bg-white"
        style="height: calc(100vh - 214px); min-height: 420px"></iframe>

@endsection
