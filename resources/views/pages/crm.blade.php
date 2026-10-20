@extends('layouts.app')
@section('title', 'CRM')

@section('content')

    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Daftar Client</h1>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full lg:w-auto">
            <form action="{{ route('pages.crm') }}" method="GET" role="search"
                class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                <div class="relative flex-1 lg:w-80">
                    <label class="sr-only" for="crm-search">Cari client</label>
                    <i class='bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none'
                        aria-hidden="true"></i>
                    <input id="crm-search" type="search" name="search" value="{{ request('search') }}"
                        placeholder="Cari perusahaan, kontak, email, telepon..."
                        class="min-h-11 w-full border border-slate-200 bg-slate-50 rounded-md pl-10 pr-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="min-h-11 bg-slate-800 text-white px-4 py-2 rounded-md text-sm font-semibold hover:bg-slate-900 transition">
                        Cari
                    </button>
                    @if (request('search'))
                        <a href="{{ route('pages.crm') }}"
                            class="min-h-11 inline-flex items-center bg-slate-100 text-slate-600 px-4 py-2 rounded-md text-sm font-semibold hover:bg-slate-200 transition">
                            Bersihkan
                        </a>
                    @endif
                </div>
            </form>

            <a href="{{ route('pages.request') }}"
                class="min-h-11 bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-md inline-flex items-center justify-center gap-2 hover:bg-brand-700 transition whitespace-nowrap">
                <i class='bx bx-plus' aria-hidden="true"></i> Tambah Client
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 p-4 bg-emerald-50 border-l-4 border-l-emerald-700 text-emerald-700 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <x-card padding="" class="overflow-hidden mt-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-left text-xs text-slate-600 bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 font-semibold">Perusahaan</th>
                        <th class="px-6 py-4 font-semibold">Nama Kontak</th>
                        <th class="px-6 py-4 font-semibold">Kontak</th>
                        <th class="px-6 py-4 font-semibold">Project</th>
                        <th class="px-6 py-4 font-semibold text-right">Tanggal Dibuat</th>
                        <th class="px-6 py-4 font-semibold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @forelse ($clients as $client)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 font-semibold text-slate-900">
                                {{ $client->company_name }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $client->contact_name ?: '-' }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    @if ($client->email)
                                        <span class="text-xs text-slate-600">{{ $client->email }}</span>
                                    @endif
                                    @if ($client->phone)
                                        <span class="text-xs text-slate-600">{{ $client->phone }}</span>
                                    @endif
                                    @if (!$client->email && !$client->phone)
                                        <span class="text-xs text-slate-500">Belum ada kontak</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <x-badge color="blue">{{ $client->projects->count() }} project</x-badge>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500 font-mono text-right">
                                {{ $client->created_at ? $client->created_at->format('d M Y') : '-' }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="{{ route('pages.crm-view', $client->id) }}"
                                    class="inline-flex items-center justify-center h-11 px-4 rounded-lg text-xs font-semibold text-brand-600 hover:bg-brand-50 border border-slate-300 transition"
                                    aria-label="Lihat detail {{ $client->company_name }}">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        {{-- Two different empties read as one failure if they share
                             a sentence: an empty book and a search that matched
                             nothing need different next steps. --}}
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center">
                                @if (request('search'))
                                    <p class="text-sm text-slate-700">Tidak ada client yang cocok dengan
                                        "{{ request('search') }}".</p>
                                    <a href="{{ route('pages.crm') }}"
                                        class="inline-block mt-3 text-sm text-brand-600 hover:underline">Hapus kata
                                        kunci</a>
                                @else
                                    <p class="text-sm text-slate-700">Belum ada client terdaftar.</p>
                                    <a href="{{ route('pages.request') }}"
                                        class="inline-block mt-3 text-sm text-brand-600 hover:underline">Tambah client
                                        pertama</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($clients->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $clients->links() }}
            </div>
        @endif
    </x-card>

@endsection
