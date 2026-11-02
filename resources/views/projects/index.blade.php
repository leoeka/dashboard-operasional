@extends('layouts.app')
@section('title', 'Projects')

@section('content')

    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Daftar Projects</h1>
        </div>
        <a href="{{ route('pages.request') }}"
            class="bg-brand-600 text-white text-sm font-semibold px-4 py-3 rounded-md flex items-center gap-2 hover:bg-brand-700 transition">
            <i class='bx bx-plus'></i> Tambah Project
        </a>
    </div>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-md bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <x-card padding="p-4" class="mb-4">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="min-h-11 flex items-center gap-2 bg-slate-50 rounded-md px-3 flex-1 min-w-0">
                <i class='bx bx-search text-slate-600'></i>
                <input type="text" name="search" value="{{ request('search') }}" aria-label="Cari project"
                    placeholder="Search project name or client..."
                    class="bg-transparent outline-none text-sm w-full min-h-11 text-slate-600">
            </div>
            <select name="status" onchange="this.form.submit()"
                class="min-h-11 bg-slate-50 text-slate-600 text-sm rounded-md px-3 py-2 outline-none">
                <option value="">All Statuses</option>
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
                    <tr class="text-left text-slate-600 border-b border-slate-100">
                        <th class="px-6 py-4 font-semibold">Project Name</th>
                        <th class="px-6 py-4 font-semibold">Client</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold">Project Type</th>
                        <th class="px-6 py-4 font-semibold text-right">Created</th>
                        <th class="px-6 py-4 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($projects as $project)
                        <tr class="group relative hover:bg-slate-50 cursor-pointer transition">
                            <td class="px-6 py-4 font-semibold text-slate-700">
                                {{-- Link utama: "after:absolute after:inset-0" membuat seluruh baris bisa diklik --}}
                                <a href="{{ route('pages.projects.show', $project) }}"
                                    class="group-hover:text-brand-600 after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-inset focus-visible:after:ring-brand-500">
                                    {{ $project->name }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $project->client_name }}</td>
                            <td class="px-6 py-4">
                                <x-badge :color="$project->statusColor()">{{ $project->statusLabel() }}</x-badge>
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $project->serviceTypeLabel() }}</td>
                            <td class="px-6 py-4 text-slate-500 font-mono text-right">
                                {{ $project->created_at->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-6 py-4">
                                {{-- "relative z-10" supaya tombol Edit/Delete berada di atas overlay link baris --}}
                                <div class="relative z-10 flex items-center justify-end gap-3 text-slate-600">
                                    <a href="{{ route('pages.projects.edit', $project) }}"
                                        class="inline-flex items-center justify-center min-h-11 min-w-11 hover:text-brand-600"
                                        title="Edit" aria-label="Edit project"><i class='bx bx-edit text-lg'></i></a>
                                    <form method="POST" action="{{ route('pages.projects.destroy', $project) }}"
                                        onsubmit="return confirm('Delete this project?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center justify-center min-h-11 min-w-11 hover:text-red-700"
                                            title="Delete" aria-label="Delete project"><i
                                                class='bx bx-trash text-lg'></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-slate-600">No projects yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $projects->links() }}</div>

@endsection
