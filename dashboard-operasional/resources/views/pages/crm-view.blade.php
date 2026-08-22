@extends('layouts.app')
@section('title', $client->company_name)

@section('content')

    <a href="{{ route('pages.crm') }}" class="text-sm text-slate-400 flex items-center gap-1 mb-4 hover:text-slate-600">
        <i class='bx bx-arrow-back'></i> Back to Clients
    </a>

    <x-card class="mb-6">
        <div class="flex items-center gap-4 mb-5">
            <div class="w-14 h-14 rounded-2xl grad-purple flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                {{ strtoupper(substr($client->company_name, 0, 2)) }}
            </div>
            <div class="min-w-0">
                <p class="font-bold text-slate-800 text-lg truncate">{{ $client->company_name }}</p>
                <p class="text-sm text-slate-400 truncate">{{ $client->contact_name }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 text-sm">
            <div>
                <p class="text-slate-400 mb-1">Email</p>
                <p class="font-medium text-slate-700 break-words">{{ $client->email ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-400 mb-1">Phone</p>
                <p class="font-medium text-slate-700">{{ $client->phone ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-400 mb-1">Address</p>
                <p class="font-medium text-slate-700 break-words">{{ $client->address ?? '-' }}</p>
            </div>
        </div>
    </x-card>

    <x-card>
        <h2 class="font-semibold text-slate-800 mb-4">Project History</h2>
        <div class="divide-y divide-slate-100">
            @forelse ($client->projects as $project)
                <a href="{{ route('pages.projects.show', $project) }}"
                   class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 hover:bg-slate-50 -mx-2 px-2 rounded">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-700 truncate">{{ $project->name }}</p>
                        <p class="text-xs text-slate-400">{{ $project->type }}</p>
                    </div>
                    <x-badge :color="$project->statusColor()" class="w-fit">{{ $project->statusLabel() }}</x-badge>
                </a>
            @empty
                <p class="text-sm text-slate-400 py-4 text-center">No projects for this client yet.</p>
            @endforelse
        </div>
    </x-card>

@endsection