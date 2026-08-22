@extends('layouts.app')
@section('title', 'Projects')

@section('content')

    <x-page-header title="Projects">
        <x-slot:actions>
            <a href="{{ route('pages.request') }}"
                class="grad-blue text-white text-sm font-semibold px-4 py-2.5 rounded-lg flex items-center gap-2 hover:opacity-90 transition">
                <i class='bx bx-plus'></i> Request Project
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm">{{ session('success') }}</div>
    @endif

    <x-card padding="p-4" class="mb-5">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="flex items-center gap-2 bg-slate-50 rounded-lg px-3 py-2 flex-1">
                <i class='bx bx-search text-slate-400'></i>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Cari nama project atau client..."
                    class="bg-transparent outline-none text-sm w-full text-slate-600">
            </div>
            <select name="status" onchange="this.form.submit()"
                class="bg-slate-50 text-slate-600 text-sm rounded-lg px-3 py-2 outline-none">
                <option value="">Semua Status</option>
                <option value="request" @selected(request('status') === 'request')>Request</option>
                <option value="in_progress" @selected(request('status') === 'in_progress')>In Progress</option>
                <option value="completed" @selected(request('status') === 'completed')>Completed</option>
            </select>
        </form>
    </x-card>

    <x-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-400 border-b border-slate-100">
                        <th class="px-6 py-4 font-medium">Nama Project</th>
                        <th class="px-6 py-4 font-medium">Client</th>
                        <th class="px-6 py-4 font-medium">Status</th>
                        <th class="px-6 py-4 font-medium">Jenis Project</th>
                        <th class="px-6 py-4 font-medium">Dibuat</th>
                        <th class="px-6 py-4 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($projects as $project)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium text-slate-700">{{ $project->name }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ $project->client_name }}</td>
                            <td class="px-6 py-4"><x-badge
                                    :color="$project->statusColor()">{{ $project->statusLabel() }}</x-badge></td>
                            <td class="px-6 py-4 text-slate-500">{{ $project->serviceTypeLabel() }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $project->created_at->translatedFormat('d M Y') }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-3 text-slate-400">
                                    <a href="{{ route('pages.projects.show', $project) }}" class="hover:text-brand-500"
                                        title="Detail"><i class='bx bx-show text-lg'></i></a>
                                    <a href="{{ route('pages.projects.edit', $project) }}" class="hover:text-brand-500"
                                        title="Edit"><i class='bx bx-edit text-lg'></i></a>
                                    <form method="POST" action="{{ route('pages.projects.destroy', $project) }}"
                                        onsubmit="return confirm('Hapus project ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="hover:text-red-500" title="Hapus"><i
                                                class='bx bx-trash text-lg'></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-slate-400">Belum ada project.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $projects->links() }}</div>

@endsection