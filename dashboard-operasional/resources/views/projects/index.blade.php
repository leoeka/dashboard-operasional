@extends('layouts.app')
@section('title', 'Projects')

@section('content')

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-2">
            <i class='bx bx-briefcase text-2xl text-brand-500'></i>
            <h1 class="text-xl font-bold text-slate-800">Projects</h1>
        </div>

        <form method="GET" class="flex items-center gap-2 bg-white border border-slate-200 rounded-lg px-3 py-2 w-72">
            <i class='bx bx-search text-slate-400'></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari klien..."
                   class="outline-none text-sm w-full text-slate-600">
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-slate-100">
                    <th class="px-6 py-4 font-medium">No. Proyek</th>
                    <th class="px-6 py-4 font-medium">Klien</th>
                    <th class="px-6 py-4 font-medium">Jenis</th>
                    <th class="px-6 py-4 font-medium">Tenggat</th>
                    <th class="px-6 py-4 font-medium">Progress</th>
                    <th class="px-6 py-4 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($projects as $project)
                    <tr class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('projects.show', $project) }}'">
                        <td class="px-6 py-4 font-medium text-slate-700">{{ $project->code }}</td>
                        <td class="px-6 py-4 text-slate-600">{{ $project->client_name }}</td>
                        <td class="px-6 py-4 text-slate-500">{{ $project->type }}</td>
                        <td class="px-6 py-4 text-slate-500">
                            {{ $project->deadline?->translatedFormat('d M Y') ?? '-' }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full grad-blue" style="width: {{ $project->progress }}%"></div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs px-3 py-1 rounded-full {{ $project->statusColor() }}">
                                {{ ucfirst($project->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-slate-400">Belum ada proyek.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $projects->links() }}
    </div>

@endsection
