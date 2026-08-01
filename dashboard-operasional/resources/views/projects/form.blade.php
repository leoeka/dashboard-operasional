@extends('layouts.app')
@section('title', $project->exists ? 'Edit Project' : 'Tambah Project')

@section('content')

    <a href="{{ $project->exists ? route('pages.projects.show', $project) : route('pages.projects') }}"
       class="text-sm text-slate-400 flex items-center gap-1 mb-4 hover:text-slate-600">
        <i class='bx bx-arrow-back'></i> Kembali
    </a>

    <x-page-header :title="$project->exists ? 'Edit Project' : 'Tambah Project'" />

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $project->exists ? route('pages.projects.update', $project) : route('pages.projects.store') }}" class="space-y-5">
            @csrf
            @if ($project->exists) @method('PUT') @endif

            <div>
                <label class="text-sm font-medium text-slate-600 mb-1 block">Nama Project</label>
                <input type="text" name="name" value="{{ old('name', $project->name) }}" placeholder="mis. Website PT ABC"
                       class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-600 mb-1 block">Nama Client</label>
                <input type="text" name="client_name" value="{{ old('client_name', $project->client_name) }}"
                       class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                @error('client_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-600 mb-1 block">Jenis Website</label>
                <input type="text" name="type" value="{{ old('type', $project->type) }}" placeholder="mis. Company Profile, E-commerce"
                       class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-600 mb-1 block">Status</label>
                    <select name="status" class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                        @foreach (['request' => 'Request', 'proposal' => 'Proposal', 'mockup' => 'Mockup', 'development' => 'Development', 'qa' => 'QA', 'done' => 'Selesai'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 mb-1 block">Deadline</label>
                    <input type="date" name="deadline" value="{{ old('deadline', $project->deadline?->format('Y-m-d')) }}"
                           class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>

            <button type="submit" class="grad-blue text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                {{ $project->exists ? 'Simpan Perubahan' : 'Buat Project' }}
            </button>
        </form>
    </x-card>

@endsection