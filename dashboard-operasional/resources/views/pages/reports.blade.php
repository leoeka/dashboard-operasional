@extends('layouts.app')
@section('title', 'Reports')

@section('content')

    <x-page-header title="Reports">
        <x-slot:actions>
            <div class="flex flex-col sm:flex-row gap-2">
                <a href="{{ route('pages.reports.download') }}"
                    class="grad-blue text-white text-sm font-semibold px-4 py-2.5 rounded-lg flex items-center justify-center gap-2 hover:opacity-90 transition">
                    <i class='bx bx-file-blank'></i> Unduh PDF
                </a>
                <a href="{{ route('pages.reports.download-excel') }}"
                    class="bg-emerald-600 text-white text-sm font-semibold px-4 py-2.5 rounded-lg flex items-center justify-center gap-2 hover:opacity-90 transition">
                    <i class='bx bx-spreadsheet'></i> Unduh Excel
                </a>
            </div>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- RINGKASAN PROJECT --}}
        <x-card>
            <h2 class="font-semibold text-slate-800 mb-4">Ringkasan Project</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                @foreach (['request' => 'Request', 'proposal' => 'Proposal', 'mockup' => 'Mockup', 'development' => 'Development', 'qa' => 'QA', 'done' => 'Selesai'] as $key => $label)
                    <div class="bg-slate-50 rounded-lg p-3">
                        <p class="text-xs text-slate-400">{{ $label }}</p>
                        <p class="text-xl font-bold text-slate-800">{{ $projectByStatus[$key] ?? 0 }}</p>
                    </div>
                @endforeach
            </div>
            @if ($overdueCount > 0)
                <div class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg">
                    <i class='bx bx-error-circle'></i> {{ $overdueCount }} project sudah lewat deadline
                </div>
            @endif
        </x-card>

        {{-- RINGKASAN KEUANGAN --}}
        <x-card>
            <h2 class="font-semibold text-slate-800 mb-4">Ringkasan Keuangan</h2>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-emerald-50 rounded-lg p-3">
                    <p class="text-xs text-emerald-600">Masuk Bulan Ini</p>
                    <p class="text-lg font-bold text-emerald-700 break-words">
                        Rp{{ number_format($paidThisMonth, 0, ',', '.') }}</p>
                </div>
                <div class="bg-amber-50 rounded-lg p-3">
                    <p class="text-xs text-amber-600">Belum Dibayar</p>
                    <p class="text-lg font-bold text-amber-700 break-words">Rp{{ number_format($unpaidTotal, 0, ',', '.') }}
                    </p>
                </div>
            </div>
            @if ($overdueInvoiceCount > 0)
                <div class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg">
                    <i class='bx bx-error-circle'></i> {{ $overdueInvoiceCount }} invoice sudah lewat jatuh tempo
                </div>
            @endif
        </x-card>
    </div>

    {{-- GRAFIK PEMASUKAN --}}
    <x-card class="mb-6">
        <h2 class="font-semibold text-slate-800 mb-4">Pemasukan 6 Bulan Terakhir</h2>
        <div class="flex items-end gap-1.5 sm:gap-4 h-40">
            @foreach ($incomeChart as $item)
                <div class="flex-1 flex flex-col items-center justify-end h-full min-w-0">
                    <div class="w-full grad-blue rounded-t-md"
                        style="height: {{ $maxIncome > 0 ? max(4, ($item['total'] / $maxIncome) * 100) : 4 }}%"></div>
                    <p class="text-[10px] sm:text-xs text-slate-400 mt-2 truncate w-full text-center">{{ $item['label'] }}</p>
                </div>
            @endforeach
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- RINGKASAN CLIENT --}}
        <x-card>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-4">
                <h2 class="font-semibold text-slate-800">Client Paling Aktif</h2>
                <span class="text-xs text-slate-400">{{ $newClientsThisMonth }} client baru bulan ini</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($topClients as $client)
                    <div class="py-2 flex items-center justify-between gap-2">
                        <p class="text-sm text-slate-700 truncate">{{ $client->company_name }}</p>
                        <span class="text-xs text-slate-400 flex-shrink-0">{{ $client->projects_count }} project</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 py-4 text-center">Belum ada data client.</p>
                @endforelse
            </div>
        </x-card>

        {{-- PERFORMA --}}
        <x-card>
            <h2 class="font-semibold text-slate-800 mb-4">Performa</h2>
            <div class="bg-slate-50 rounded-lg p-4">
                <p class="text-xs text-slate-400 mb-1">Rata-rata Durasi Project Selesai</p>
                <p class="text-2xl font-bold text-slate-800">{{ $avgDuration }} hari</p>
            </div>
        </x-card>
    </div>

@endsection