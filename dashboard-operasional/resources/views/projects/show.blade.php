@extends('layouts.app')
@section('title', $project->name)

@section('content')

    <a href="{{ route('pages.projects') }}" class="text-sm text-slate-400 flex items-center gap-1 mb-4 hover:text-slate-600">
        <i class='bx bx-arrow-back'></i> Kembali ke Projects
    </a>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ session('error') }}</div>
    @endif

    {{-- INFORMASI UMUM --}}
    <x-card class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
            <div class="min-w-0">
                <p class="font-bold text-slate-800 text-lg truncate">{{ $project->name }}</p>
                <p class="text-sm text-slate-400">{{ $project->client_name }}</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <x-badge :color="$project->statusColor()">{{ $project->statusLabel() }}</x-badge>
                <a href="{{ route('pages.projects.edit', $project) }}"
                    class="text-sm px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                    <i class='bx bx-edit'></i> Edit
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-sm">
            <div>
                <p class="text-slate-400 mb-1">Jenis Website</p>
                <p class="font-medium text-slate-700">{{ $project->type ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-400 mb-1">Progress</p>
                <p class="font-medium text-slate-700">{{ $project->progress }}%</p>
            </div>
            <div>
                <p class="text-slate-400 mb-1">Deadline</p>
                <p class="font-medium text-slate-700">{{ $project->deadline?->translatedFormat('d M Y') ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-400 mb-1">Tanggal Dibuat</p>
                <p class="font-medium text-slate-700">{{ $project->created_at->translatedFormat('d M Y') }}</p>
            </div>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- TASK CHECKLIST --}}
        <x-card>
            <h2 class="font-semibold text-slate-800 mb-4">Task Checklist</h2>

            <form method="POST" action="{{ route('pages.projects.tasks.store', $project) }}" class="flex gap-2 mb-4">
                @csrf
                <input type="text" name="title" placeholder="Tambah task baru..." required
                    class="flex-1 min-w-0 bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                <button type="submit" class="grad-blue text-white text-sm px-4 rounded-lg hover:opacity-90 transition flex-shrink-0">
                    <i class='bx bx-plus'></i>
                </button>
            </form>

            <div class="space-y-1">
                @forelse ($project->tasks as $task)
                    <div class="flex items-center justify-between gap-2 group py-1.5">
                        <form method="POST" action="{{ route('pages.projects.tasks.toggle', [$project, $task]) }}"
                            class="flex items-center gap-3 flex-1 min-w-0">
                            @csrf @method('PATCH')
                            <button type="submit" class="flex items-center gap-3 text-left flex-1 min-w-0">
                                <i
                                    class='bx {{ $task->is_done ? 'bx-checkbox-checked text-brand-500' : 'bx-checkbox text-slate-300' }} text-xl flex-shrink-0'></i>
                                <span
                                    class="text-sm truncate {{ $task->is_done ? 'text-slate-400 line-through' : 'text-slate-700' }}">{{ $task->title }}</span>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('pages.projects.tasks.destroy', [$project, $task]) }}"
                            class="opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-slate-300 hover:text-red-500"><i
                                    class='bx bx-x text-lg'></i></button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 py-4 text-center">Belum ada task.</p>
                @endforelse
            </div>
        </x-card>

        {{-- PROPOSAL --}}
        <x-card class="mt-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3 min-w-0">
                    <div
                        class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                        <i class='bx bx-file-blank text-xl'></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="font-bold text-slate-800 text-sm">Proposal Proyek</h2>
                            @if ($project->latestProposal)
                                <span
                                    class="px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-600 rounded-full border border-emerald-100">
                                    Versi {{ $project->latestProposal->version }}
                                </span>
                            @else
                                <span
                                    class="px-2 py-0.5 text-[10px] font-semibold bg-slate-100 text-slate-500 rounded-full">
                                    Belum Dibuat
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 mt-1 max-w-xl leading-relaxed">
                            @if ($project->latestProposal)
                                Proposal sudah pernah digenerate. Klik untuk melihat preview, mengubah template, atau
                                mengunduh PDF.
                            @else
                                Klik untuk membuat proposal pertama kali dengan rekomendasi AI Workspace.
                            @endif
                        </p>
                    </div>
                </div>

                <div class="shrink-0 pt-2 sm:pt-0 border-t sm:border-0 border-slate-100 flex items-center justify-end">
                    @if ($project->latestProposal)
                        <a href="{{ route('pages.projects.proposal.edit', $project) }}"
                            class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg hover:opacity-90 shadow-sm transition">
                            <i class='bx bx-edit-alt text-sm'></i>
                            <span>Edit & Preview Proposal</span>
                        </a>
                    @else
                        <a href="{{ route('pages.projects.proposal.generate', $project) }}"
                            class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-4 py-2 rounded-lg hover:opacity-90 shadow-sm transition">
                            <i class='bx bx-magic-wand text-sm'></i>
                            <span>Generate Proposal</span>
                        </a>
                    @endif
                </div>
            </div>
        </x-card>
    </div>


    {{-- ADD MOCKUP --}}
    <x-card class="mt-6">
        <h2 class="font-semibold text-slate-800 mb-4">Add Mockup</h2>

        @if ($project->mockupTemplate)
            <div class="mb-4 p-3 bg-emerald-50 rounded-lg flex items-center gap-3">
                @if ($project->mockupTemplate->previewUrl())
                    <img src="{{ $project->mockupTemplate->previewUrl() }}" class="w-12 h-12 rounded object-cover flex-shrink-0">
                @endif
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-emerald-700 truncate">{{ $project->mockupTemplate->name }}</p>
                    <p class="text-xs text-emerald-600">Mockup yang dipilih client</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('pages.projects.mockup.add', $project) }}" class="flex flex-col sm:flex-row gap-2 mb-4">
            @csrf @method('PUT')
            <select name="mockup_template_id" required
                class="flex-1 min-w-0 bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                <option value="">-- Pilih mockup yang disetujui client --</option>
                @foreach (\App\Models\MockupTemplate::orderBy('name')->get() as $tpl)
                    <option value="{{ $tpl->id }}" @selected($project->mockup_template_id == $tpl->id)>
                        {{ $tpl->name }} ({{ $tpl->categoryLabel() }})
                    </option>
                @endforeach
            </select>
            <button type="submit" class="grad-blue text-white text-sm px-4 py-2 rounded-lg hover:opacity-90 transition flex-shrink-0">
                Simpan
            </button>
        </form>

        @if ($project->mockupTemplate)
            <a href="{{ route('pages.ai-workspace') }}?project={{ $project->id }}"
                class="inline-flex items-center gap-2 grad-blue text-white text-xs font-semibold px-3 py-1.5 rounded-lg hover:opacity-90 transition">
                <i class='bx bx-magic-wand'></i> Generate ke AI Workspace
            </a>
        @else
            <span class="inline-flex items-center gap-2 text-xs text-slate-300 px-3 py-1.5 rounded-lg bg-slate-50">
                <i class='bx bx-magic-wand'></i> Generate ke AI Workspace
            </span>
        @endif
    </x-card>

    {{-- ACTIVITY LOG --}}
    <x-card class="mt-6">
        <h2 class="font-semibold text-slate-800 mb-4">Activity Log</h2>

        <div class="space-y-4">
            @forelse ($project->activityLogs as $log)
                <div class="flex gap-3">
                    <span
                        class="text-xs text-slate-400 font-mono w-12 flex-shrink-0 pt-0.5">{{ $log->created_at->format('H:i') }}</span>
                    <div class="flex-1 min-w-0 pb-4 border-l border-slate-100 pl-4 -ml-px">
                        <p class="text-sm text-slate-700 break-words">{{ $log->action }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $log->created_at->translatedFormat('d M Y') }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400 text-center py-4">Belum ada aktivitas.</p>
            @endforelse
        </div>
    </x-card>

@endsection