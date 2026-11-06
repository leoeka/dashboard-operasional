@extends('layouts.app')
@section('title', 'Analisa SEO / SXO / GEO / AEO')

@section('content')
    <div class="max-w-3xl mx-auto">
        <x-page-header title="Analisa SEO / SXO / GEO / AEO" />

        <x-card class="mt-5">
            <div class="mb-6">
                <div class="w-12 h-12 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center mb-4">
                    <i class='bx bx-search-alt text-2xl'></i>
                </div>
                <h2 class="text-lg font-bold text-slate-800">Analisa website client dari URL</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Masukkan URL website publik. Sistem akan membuat atau mencocokkan project berdasarkan domain,
                    menjalankan pemeriksaan SEO teknis/on-page, SXO berbasis performa, GEO, dan kesiapan Answer Engine (AEO), lalu menyimpan hasilnya di Workspace.
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-5 rounded-md border-l-4 border-red-600 bg-red-50 p-4 text-sm text-red-700">
                    {{ $errors->first('url') }}
                </div>
            @endif

            <form method="POST" action="{{ route('pages.seo-analysis.analyze') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="website-url" class="block text-sm font-semibold text-slate-800">URL website client</label>
                    <input id="website-url" type="text" name="url" value="{{ old('url') }}"
                        placeholder="https://contohwebsite.com" required autocomplete="url"
                        class="mt-1 block min-h-12 w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-800 focus:border-brand-600 focus:ring-brand-500">
                    <p class="mt-1 text-xs text-slate-500">Boleh masukkan domain saja; sistem akan menambahkan https://.</p>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('pages.projects') }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        Kembali ke Project
                    </a>
                    <button type="submit"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                        <i class='bx bx-analyse'></i> Mulai Analisa
                    </button>
                </div>
            </form>
        </x-card>

        <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-xs leading-relaxed text-amber-800">
            Pemeriksaan berbasis URL mencakup data yang dapat dibaca publik. Data Search Console dan GA4 memerlukan akses akun
            Google client, sehingga tidak otomatis tersedia hanya dari URL. Riset backlink / keyword kompetitif tetap tersedia
            melalui layanan SEO &amp; Backlink pada project yang sudah dipilih.
        </div>
    </div>
@endsection
