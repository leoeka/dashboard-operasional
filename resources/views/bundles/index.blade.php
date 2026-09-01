@extends('layouts.app')
@section('title', 'Build WordPress')

@section('content')
    @php
        $latestBundle = $project->bundles()->latest()->first();
        $hasBrief = filled($project->description);
        $hasMockup = $project->proposals()->exists();
        $hasBundle = $latestBundle && $latestBundle->status === 'exported';
    @endphp

    <div class="mb-6 rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-800">
        <div class="flex items-start gap-3">
            <i class='bx bx-info-circle mt-0.5 text-xl'></i>
            <div>
                <p class="font-bold">Anda sudah berada di langkah terakhir.</p>
                <p class="mt-1 leading-6">Proposal dan mockup dibuat oleh Gemini + GPT. Sekarang klik tombol build untuk meminta Claude membuat theme dan plugin WordPress siap install.</p>
            </div>
        </div>
    </div>

    <x-page-header title="Build WordPress dengan Claude">
        <x-slot:actions>
            <a href="{{ route('pages.projects') }}"
                class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-800 px-3 py-2 rounded-lg border border-slate-200 bg-white transition">
                <i class='bx bx-arrow-back'></i> Back to Projects
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <x-card>
            <div class="space-y-5">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Project yang akan dibuat</p>
                    <p class="text-sm text-slate-500">{{ $project->client_name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-slate-400">Type</p>
                        <p class="mt-1 font-semibold text-slate-700">{{ $project->type ?? 'Website' }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-slate-400">Status</p>
                        <p class="mt-1 font-semibold text-slate-700">{{ $project->statusLabel() }}</p>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-400 font-semibold mb-3">Alur pekerjaan</p>
                    <ol class="space-y-3 text-sm text-slate-600">
                        <li><span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-600">1</span>Data client dan user story</li>
                        <li><span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-600">2</span>Gemini menganalisis bisnis dan target market</li>
                        <li><span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-600">3</span>GPT membuat mockup, proposal, dan konten</li>
                        <li><span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700">4</span>Client menyetujui konsep</li>
                        <li><span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">5</span>Claude membangun package WordPress</li>
                    </ol>
                </div>

                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('pages.projects.bundle.build', $project) }}">
                        @csrf
                        <button type="submit" class="bg-slate-900 text-white text-sm font-semibold px-4 py-2.5 rounded-lg hover:bg-slate-700 transition">
                            <i class='bx bx-package'></i> Bangun WordPress dengan Claude
                        </button>
                    </form>

                    <a href="{{ route('pages.projects.bundle.download', $project) }}"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 hover:text-emerald-800 border border-emerald-200 hover:bg-emerald-50 px-4 py-2.5 rounded-lg transition {{ $hasBundle ? '' : 'pointer-events-none opacity-40' }}">
                        <i class='bx bx-download'></i> Download Theme ZIP siap install
                    </a>
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="space-y-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Template WordPress (opsional)</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Tidak perlu menambah template untuk build pertama. Claude akan membuat package berdasarkan proposal dan konten project.</p>
                </div>

                @php
                    $templates = \App\Models\TemplateBundle::query()->where('is_active', true)->get();
                @endphp

                @forelse ($templates as $template)
                    <div class="rounded-xl border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-700">{{ $template->name }}</p>
                                <p class="text-xs text-slate-400">{{ $template->category }}</p>
                            </div>
                            <span class="rounded-full bg-blue-50 text-blue-600 px-2 py-1 text-[10px] font-bold uppercase">{{ $template->slug }}</span>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-400">
                        No template bundle yet. Add one from the template library.
                    </div>
                @endforelse

                <form method="POST" action="{{ route('templates.bundles.store') }}" class="pt-4 border-t border-slate-100 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Template Name</label>
                        <input type="text" name="name" placeholder="Restaurant Modern" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-300" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Slug</label>
                        <input type="text" name="slug" placeholder="restaurant-modern" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-300" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Category</label>
                        <input type="text" name="category" placeholder="restaurant" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-300" required>
                    </div>
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 text-sm font-semibold text-white bg-slate-800 hover:bg-slate-700 px-4 py-2.5 rounded-lg transition">
                        <i class='bx bx-plus-circle'></i> Add Template Bundle
                    </button>
                </form>
            </div>
        </x-card>
    </div>
@endsection
