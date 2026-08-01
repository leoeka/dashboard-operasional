@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')

    <div class="flex items-center gap-2 mb-6">
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Pengaturan</h1>
    </div>

    <div class="space-y-6 max-w-2xl">

        {{-- IDENTITAS WEBSITE --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-6">
            <div class="flex items-center gap-4 mb-5">
                <div class="w-14 h-14 rounded-2xl grad-purple flex items-center justify-center text-white font-bold text-xl">
                    {{ substr($appName, 0, 1) }}
                </div>
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100 text-lg">{{ $appName }}</p>
                    <p class="text-sm text-slate-400">{{ $appDescription }}</p>
                </div>
            </div>
        </div>

        {{-- INFO VERSI --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-6">
            <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-4">Informasi Sistem</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                <div>
                    <p class="text-slate-400 mb-1">Versi Aplikasi</p>
                    <p class="font-medium text-slate-700 dark:text-slate-200">{{ $appVersion }}</p>
                </div>
                <div>
                    <p class="text-slate-400 mb-1">Environment</p>
                    <p class="font-medium text-slate-700 dark:text-slate-200">{{ ucfirst($appEnvironment) }}</p>
                </div>
            </div>
        </div>

    </div>

@endsection