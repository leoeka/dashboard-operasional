<x-card>
    <div class="flex items-start justify-between gap-2 mb-4">
        <div class="min-w-0">
            <p class="font-bold text-slate-800 text-base truncate">{{ $project->name }}</p>
            <p class="text-sm text-slate-600 truncate">{{ $project->client_name ?? '-' }}</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <x-badge :color="$project->statusColor()">{{ $project->statusLabel() }}</x-badge>
        </div>
    </div>

    <div class="space-y-4 text-sm">
        <div>
            <p class="text-slate-600 mb-1">Website Type</p>
            <p class="font-semibold text-slate-700">{{ $project->type ?? '-' }}</p>
        </div>
        <div>
            <p class="text-slate-600 mb-1">Date Created</p>
            <p class="font-semibold text-slate-700 font-mono">{{ $project->created_at->translatedFormat('d M Y') }}
            </p>
        </div>
    </div>
</x-card>
