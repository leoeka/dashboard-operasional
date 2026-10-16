@extends('layouts.app')
@section('title', $client->company_name)

@section('content')

    <a href="{{ route('pages.crm') }}" class="min-h-11 text-sm text-slate-600 flex items-center gap-1 mb-4">
        <i class='bx bx-arrow-back'></i> Back to Clients
    </a>

    <x-card class="mb-6">
        <div class="flex items-center gap-4 mb-4">
            <div class="w-14 h-14 rounded-lg bg-brand-600 flex items-center justify-center text-white font-bold text-base flex-shrink-0">
                {{ strtoupper(substr($client->company_name, 0, 2)) }}
            </div>
            <div class="min-w-0">
                <p class="font-bold text-slate-800 text-base truncate">{{ $client->company_name }}</p>
                <p class="text-sm text-slate-600 truncate">{{ $client->contact_name }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 text-sm">
            <div>
                <p class="text-slate-600 mb-1">Email</p>
                <p class="font-semibold text-slate-700 break-words">{{ $client->email ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-600 mb-1">Phone</p>
                <p class="font-semibold text-slate-700">{{ $client->phone ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-600 mb-1">Address</p>
                <p class="font-semibold text-slate-700 break-words">{{ $client->address ?? '-' }}</p>
            </div>
        </div>
    </x-card>

    <x-card>
        <h2 class="font-semibold text-slate-800 mb-4">Project History</h2>
        <div class="divide-y divide-slate-100">
            @forelse ($client->projects as $project)
                <a href="{{ route('pages.projects.show', $project) }}"
                   class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 hover:bg-slate-50 -mx-2 px-2 rounded-md">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-700 truncate">{{ $project->name }}</p>
                        <p class="text-xs text-slate-600">{{ $project->type }}</p>
                    </div>
                    <x-badge :color="$project->statusColor()" class="w-fit">{{ $project->statusLabel() }}</x-badge>
                </a>
            @empty
                <p class="text-sm text-slate-600 py-4 text-center">No projects for this client yet.</p>
            @endforelse
        </div>
    </x-card>

@endsection