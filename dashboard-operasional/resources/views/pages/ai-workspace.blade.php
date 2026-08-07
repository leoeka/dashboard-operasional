@extends('layouts.app')
@section('title', 'AI Workspace')

@section('content')

    <x-page-header title="AI Workspace" />

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm">{{ session('success') }}</div>
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
            <p class="text-sm text-slate-400 text-center">Pilih project di atas untuk generate konten AI.</p>
        </x-card>
    @else
        {{-- INFO PROJECT --}}
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
        </x-card>

    @endif

@endsection
