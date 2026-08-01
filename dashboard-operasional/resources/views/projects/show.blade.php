@extends('layouts.app')
@section('title', $project->name)

@section('content')

    <a href="{{ route('pages.projects') }}" class="text-sm text-slate-400 flex items-center gap-1 mb-4 hover:text-slate-600">
        <i class='bx bx-arrow-back'></i> Kembali ke Projects
    </a>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm">{{ session('success') }}</div>
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
                        <form method="POST" action="{{ route('pages.projects.tasks.toggle', [$project, $task]) }}" class="flex items-center gap-3 flex-1">
                            @csrf @method('PATCH')
                            <button type="submit" class="flex items-center gap-3 text-left flex-1">
                                <i class='bx {{ $task->is_done ? "bx-checkbox-checked text-brand-500" : "bx-checkbox text-slate-300" }} text-xl'></i>
                                <span class="text-sm {{ $task->is_done ? 'text-slate-400 line-through' : 'text-slate-700' }}">{{ $task->title }}</span>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('pages.projects.tasks.destroy', [$project, $task]) }}" class="opacity-0 group-hover:opacity-100 transition">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-slate-300 hover:text-red-500"><i class='bx bx-x text-lg'></i></button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 py-4 text-center">Belum ada task.</p>
                @endforelse
            </div>
        </x-card>

        {{-- FILES --}}
        <x-card>
            <h2 class="font-semibold text-slate-800 mb-4">Files</h2>

            <form method="POST" action="{{ route('pages.projects.files.store', $project) }}" enctype="multipart/form-data" class="flex gap-2 mb-4">
                @csrf
                <select name="category" class="bg-slate-50 text-slate-700 rounded-lg px-2 py-2 text-xs outline-none">
                    <option value="logo">Logo</option>
                    <option value="company_profile">Company Profile</option>
                    <option value="foto">Foto</option>
                    <option value="dokumen">Dokumen</option>
                    <option value="pendukung">File Pendukung</option>
                </select>
                <input type="file" name="file" required
                       class="flex-1 text-xs text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-slate-100 file:text-slate-600">
                <button type="submit" class="grad-blue text-white text-sm px-4 rounded-lg hover:opacity-90 transition">
                    <i class='bx bx-upload'></i>
                </button>
            </form>

            <div class="divide-y divide-slate-100">
                @forelse ($project->files as $file)
                    <div class="py-3 flex items-center justify-between">
                        <div class="flex items-center gap-2 min-w-0">
                            @php $isImage = in_array(pathinfo($file->original_name, PATHINFO_EXTENSION), ['jpg','jpeg','png','gif','webp']); @endphp
                            @if ($isImage)
                                <img src="{{ $file->url() }}" class="w-9 h-9 rounded object-cover flex-shrink-0" alt="{{ $file->original_name }}">
                            @else
                                <i class='bx bx-file text-slate-400 text-xl flex-shrink-0'></i>
                            @endif
                            <div class="min-w-0">
                                <a href="{{ $file->url() }}" target="_blank" class="text-sm font-medium text-slate-700 hover:underline truncate block">
                                    {{ $file->original_name }}
                                </a>
                                <p class="text-xs text-slate-400">{{ $file->categoryLabel() }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <a href="{{ $file->url() }}" download class="text-slate-400 hover:text-brand-500" title="Download"><i class='bx bx-download text-lg'></i></a>
                            <form method="POST" action="{{ route('pages.projects.files.destroy', [$project, $file]) }}" onsubmit="return confirm('Hapus file ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-500" title="Hapus"><i class='bx bx-trash text-lg'></i></button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 py-4 text-center">Belum ada file diunggah.</p>
                @endforelse
            </div>
        </x-card>
    </div>

    {{-- ACTIVITY LOG --}}
    <x-card class="mt-6">
        <h2 class="font-semibold text-slate-800 mb-4">Activity Log</h2>

        <div class="space-y-4">
            @forelse ($project->activityLogs as $log)
                <div class="flex gap-3">
                    <span class="text-xs text-slate-400 font-mono w-12 flex-shrink-0 pt-0.5">{{ $log->created_at->format('H:i') }}</span>
                    <div class="flex-1 pb-4 border-l border-slate-100 pl-4 -ml-px">
                        <p class="text-sm text-slate-700">{{ $log->description }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $log->created_at->translatedFormat('d M Y') }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400 text-center py-4">Belum ada aktivitas.</p>
            @endforelse
        </div>
    </x-card>

@endsection