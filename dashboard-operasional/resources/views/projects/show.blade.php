@extends('layouts.app')
@section('title', $project->client_name)

@section('content')

    <a href="{{ route('projects.index') }}" class="text-sm text-slate-400 flex items-center gap-1 mb-4 hover:text-slate-600">
        <i class='bx bx-arrow-back'></i> Kembali ke Projects
    </a>

    <div class="bg-white rounded-2xl border border-slate-100 p-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-slate-800">{{ $project->client_name }}</h1>
                <p class="text-sm text-slate-400">No. Proyek {{ $project->code }} &middot; {{ $project->type }}</p>
            </div>
            <span class="text-xs px-3 py-1 rounded-full {{ $project->statusColor() }}">
                {{ ucfirst($project->status) }}
            </span>
        </div>

        <div class="grid grid-cols-3 gap-6 text-sm">
            <div>
                <p class="text-slate-400 mb-1">Nilai Proyek</p>
                <p class="font-semibold text-slate-700">Rp{{ number_format($project->value, 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-slate-400 mb-1">Tenggat</p>
                <p class="font-semibold text-slate-700">{{ $project->deadline?->translatedFormat('d M Y') ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-400 mb-1">Progress</p>
                <p class="font-semibold text-slate-700">{{ $project->progress }}%</p>
            </div>
        </div>
    </div>

@endsection
