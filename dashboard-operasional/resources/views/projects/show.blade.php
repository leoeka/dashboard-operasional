@extends('layouts.app')
@section('title', $project->name)

@section('content')

    <a href="{{ route('pages.projects') }}"
        class="text-sm text-slate-400 flex items-center gap-1 mb-4 hover:text-slate-600">
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
        <div class="flex items-start justify-between mb-5">
            <div>
                <p class="font-bold text-slate-800 text-lg">{{ $project->name }}</p>
                <p class="text-sm text-slate-400">{{ $project->client_name }}</p>
            </div>
            <div class="flex items-center gap-2">
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
                    class="flex-1 bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                <button type="submit" class="grad-blue text-white text-sm px-4 rounded-lg hover:opacity-90 transition">
                    <i class='bx bx-plus'></i>
                </button>
            </form>

            <div class="space-y-1">
                @forelse ($project->tasks as $task)
                    <div class="flex items-center justify-between group py-1.5">
                        <form method="POST" action="{{ route('pages.projects.tasks.toggle', [$project, $task]) }}"
                            class="flex items-center gap-3 flex-1">
                            @csrf @method('PATCH')
                            <button type="submit" class="flex items-center gap-3 text-left flex-1">
                                <i
                                    class='bx {{ $task->is_done ? "bx-checkbox-checked text-brand-500" : "bx-checkbox text-slate-300" }} text-xl'></i>
                                <span
                                    class="text-sm {{ $task->is_done ? 'text-slate-400 line-through' : 'text-slate-700' }}">{{ $task->title }}</span>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('pages.projects.tasks.destroy', [$project, $task]) }}"
                            class="opacity-0 group-hover:opacity-100 transition">
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
        <x-card>
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-slate-800">Proposal</h2>
                <form method="POST" action="{{ route('pages.projects.proposal.generate', $project) }}">
                    @csrf
                    <button type="submit" class="grad-blue text-white text-xs font-semibold px-3 py-1.5 rounded-lg hover:opacity-90 transition">
                        <i class='bx bx-magic-wand'></i> {{ $project->proposal_content ? 'Generate Ulang' : 'Generate Proposal' }}
                    </button>
                </form>
            </div>

            @if ($project->proposal_content)
                <form method="POST" action="{{ route('pages.projects.proposal.update', $project) }}">
                    @csrf @method('PUT')
                    <textarea name="proposal_content" rows="10"
                              class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-brand-500 font-mono">{{ $project->proposal_content }}</textarea>
                    <button type="submit" class="mt-2 text-sm px-4 py-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                        Simpan Perubahan
                    </button>
                </form>
            @else
                <p class="text-sm text-slate-400 py-4 text-center">Belum ada proposal. Klik "Generate Proposal" untuk membuat draft awal.</p>
            @endif
        </x-card>

    </div>

    {{-- MOCKUP --}}
    <x-card class="mt-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-800">Mockup</h2>
            <a href="{{ route('pages.mockup') }}" class="text-xs text-brand-500 hover:underline">Kelola Katalog</a>
        </div>

        @if ($project->mockupTemplate)
            <div class="mb-4 p-3 bg-emerald-50 rounded-lg flex items-center gap-3">
                @if ($project->mockupTemplate->previewUrl())
                    <img src="{{ $project->mockupTemplate->previewUrl() }}" class="w-14 h-14 rounded object-cover">
                @endif
                <div class="flex-1">
                    <p class="text-sm font-semibold text-emerald-700">{{ $project->mockupTemplate->name }}</p>
                    <p class="text-xs text-emerald-600">Terpilih untuk project ini</p>
                </div>
                <form method="POST" action="{{ route('pages.projects.mockup.install', $project) }}">
                    @csrf
                    <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-white border border-emerald-200 text-emerald-700 hover:bg-emerald-100">
                        Install ke WordPress
                    </button>
                </form>
            </div>
        @endif

        <p class="text-xs text-slate-400 mb-2">
            Rekomendasi berdasarkan jenis website: <span class="font-medium text-slate-600">{{ $project->type ?? '-' }}</span>
        </p>

        @php
            $categoryKey = collect(\App\Models\MockupTemplate::categories())->search($project->type);
            $recommendations = \App\Models\MockupTemplate::when($categoryKey, fn($q) => $q->where('category', $categoryKey))->take(4)->get();
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @forelse ($recommendations as $tpl)
                <div class="border border-slate-100 rounded-lg overflow-hidden">
                    @if ($tpl->previewUrl())
                        <img src="{{ $tpl->previewUrl() }}" class="w-full h-24 object-cover">
                    @else
                        <div class="w-full h-24 bg-slate-50 flex items-center justify-center text-slate-300">
                            <i class='bx bx-image text-2xl'></i>
                        </div>
                    @endif
                    <div class="p-2">
                        <p class="text-xs font-medium text-slate-700 truncate">{{ $tpl->name }}</p>
                        <form method="POST" action="{{ route('pages.projects.mockup.select', $project) }}" class="mt-1">
                            @csrf @method('PUT')
                            <input type="hidden" name="mockup_template_id" value="{{ $tpl->id }}">
                            <button type="submit" class="text-xs text-brand-500 hover:underline">Pilih</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400 py-4 text-center col-span-4">
                    Belum ada template di katalog untuk kategori ini.
                    <a href="{{ route('pages.mockup') }}" class="text-brand-500 hover:underline">Tambah sekarang</a>
                </p>
            @endforelse
        </div>
    </x-card>

    {{-- ACTIVITY LOG --}}
    <x-card class="mt-6">
        <h2 class="font-semibold text-slate-800 mb-4">Activity Log</h2>

        <div class="space-y-4">
            @forelse ($project->activityLogs as $log)
                <div class="flex gap-3">
                    <span
                        class="text-xs text-slate-400 font-mono w-12 flex-shrink-0 pt-0.5">{{ $log->created_at->format('H:i') }}</span>
                    <div class="flex-1 pb-4 border-l border-slate-100 pl-4 -ml-px">
                        <p class="text-sm text-slate-700">{{ $log->action }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $log->created_at->translatedFormat('d M Y') }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400 text-center py-4">Belum ada aktivitas.</p>
            @endforelse
        </div>
    </x-card>

@endsection