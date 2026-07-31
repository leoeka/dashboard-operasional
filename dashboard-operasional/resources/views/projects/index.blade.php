@extends('layouts.app')
@section('title', 'Projects')

@section('content')

    <x-page-header title="Projects">
        <x-slot:actions>
            <form method="GET" class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 w-72">
                <i class='bx bx-search text-slate-400'></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari klien..."
                       class="outline-none text-sm w-full bg-transparent text-slate-600 dark:text-slate-200">
            </form>
        </x-slot:actions>
    </x-page-header>

    <x-card padding="p-0">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-slate-100 dark:border-slate-700">
                    <th class="px-6 py-4 font-medium">No. Proyek</th>
                    <th class="px-6 py-4 font-medium">Klien</th>
                    <th class="px-6 py-4 font-medium">Jenis</th>
                    <th class="px-6 py-4 font-medium">Tenggat</th>
                    <th class="px-6 py-4 font-medium">Progress</th>
                    <th class="px-6 py-4 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse ($projects as $project)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 cursor-pointer" onclick="window.location='{{ route('projects.show', $project) }}'">
                        <td class="px-6 py-4 font-medium text-slate-700 dark:text-slate-200">{{ $project->code }}</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $project->client_name }}</td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ $project->type }}</td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ $project->deadline?->translatedFormat('d M Y') ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <div class="w-28 h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full grad-blue" style="width: {{ $project->progress }}%"></div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <x-badge :color="$project->statusColor()">{{ ucfirst($project->status) }}</x-badge>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">Belum ada proyek.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $projects->links() }}</div>

@endsection