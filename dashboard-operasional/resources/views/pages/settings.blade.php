@extends('layouts.app')
@section('title', __('Pengaturan'))

@section('content')

    <div class="flex items-center gap-2 mb-6">
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ __('Pengaturan') }}</h1>
    </div>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm dark:bg-emerald-900/30 dark:text-emerald-400">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-6 max-w-2xl">

        {{-- BAHASA --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-6">
            <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-1">{{ __('Bahasa') }}</h2>
            <p class="text-sm text-slate-400 mb-4">{{ __('Pilih bahasa tampilan website.') }}</p>

            <form method="POST" action="{{ route('pages.settings.language') }}" class="flex gap-3">
                @csrf
                <button type="submit" name="locale" value="id"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition
                               {{ $currentLocale === 'id' ? 'grad-blue text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400' }}">
                    🇮🇩 {{ __('Indonesia') }}
                </button>
                <button type="submit" name="locale" value="en"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition
                               {{ $currentLocale === 'en' ? 'grad-blue text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400' }}">
                    🇬🇧 {{ __('English') }}
                </button>
            </form>
        </div>

        {{-- TEMA --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-6"
             x-data="{
                theme: localStorage.theme || 'light',
                setTheme(value) {
                    this.theme = value;
                    localStorage.theme = value;
                    document.documentElement.classList.toggle('dark', value === 'dark');
                }
             }">
            <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-1">{{ __('Tema') }}</h2>
            <p class="text-sm text-slate-400 mb-4">{{ __('Pilih tema tampilan website.') }}</p>

            <div class="flex gap-3">
                <button type="button" @click="setTheme('light')"
                        :class="theme === 'light' ? 'grad-blue text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition flex items-center gap-2">
                    <i class='bx bx-sun text-lg'></i> {{ __('Terang') }}
                </button>
                <button type="button" @click="setTheme('dark')"
                        :class="theme === 'dark' ? 'grad-blue text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition flex items-center gap-2">
                    <i class='bx bx-moon text-lg'></i> {{ __('Gelap') }}
                </button>
            </div>
        </div>

    </div>

@endsection