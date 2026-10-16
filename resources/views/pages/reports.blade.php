@extends('layouts.app')
@section('title', 'Reports')

@section('content')

    <x-page-header title="Reports">
        <x-slot:actions>
            <div class="flex flex-col sm:flex-row gap-2">
                <a href="{{ route('pages.reports.download') }}"
                    class="bg-brand-600 text-white text-sm font-semibold px-4 py-3 rounded-md flex items-center justify-center gap-2 hover:bg-brand-700 transition">
                    <i class='bx bx-file-blank'></i> Unduh PDF
                </a>
                <a href="{{ route('pages.reports.download-excel') }}"
                    class="border border-slate-300 bg-white text-slate-700 text-sm font-semibold px-4 py-3 rounded-md hover:bg-slate-50 flex items-center justify-center gap-2 transition">
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
                @foreach (['request' => 'Request', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $key => $label)
                    <div class="bg-slate-50 rounded-md p-4">
                        <p class="text-xs text-slate-500">{{ $label }}</p>
                        <p class="text-xl font-bold text-slate-800 font-mono">{{ $projectByStatus[$key] ?? 0 }}</p>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- RINGKASAN KEUANGAN --}}
        <x-card>
            <h2 class="font-semibold text-slate-800 mb-4">Ringkasan Keuangan</h2>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-emerald-50 rounded-md p-4">
                    <p class="text-xs text-emerald-700">Terima bulan ini</p>
                    <p class="text-base font-bold text-emerald-700 break-words font-mono">
                        Rp{{ number_format($paidThisMonth, 0, ',', '.') }}</p>
                </div>
                <div class="bg-amber-50 rounded-md p-4">
                    <p class="text-xs text-amber-700">Belum dibayar</p>
                    <p class="text-base font-bold text-amber-700 break-words font-mono">Rp{{ number_format($unpaidTotal, 0, ',', '.') }}
                    </p>
                </div>
            </div>
            @if ($overdueInvoiceCount > 0)
                <div class="text-xs text-red-700 bg-red-50 px-3 py-2 rounded-md">
                    <i class='bx bx-error-circle'></i> {{ $overdueInvoiceCount }} invoice lewat jatuh tempo
                </div>
            @endif
        </x-card>
    </div>

    {{-- GRAFIK PEMASUKAN --}}
    @php
        $incomeTotal = $incomeChart->sum('total');
        // Batang setinggi 4% saat nol membuat bulan sepi terlihat seperti
        // ada pemasukan kecil. Angka bulan itu yang sekarang menjelaskan.
        $incomeHeight = fn ($total) => $total > 0
            ? max(6, ($total / $maxIncome) * 72)
            : 0;
        $incomeLabel = fn ($total) => match (true) {
            $total <= 0 => 'Rp0',
            $total >= 1_000_000 => 'Rp' . number_format($total / 1_000_000, 1, ',', '.') . 'jt',
            $total >= 1_000 => 'Rp' . number_format($total / 1_000, 0, ',', '.') . 'rb',
            default => 'Rp' . number_format($total, 0, ',', '.'),
        };
    @endphp
    <x-card class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-4">
            <h2 class="font-semibold text-slate-800">Pemasukan 6 Bulan Terakhir</h2>
            <span class="text-xs text-slate-500 font-mono">
                Total Rp{{ number_format($incomeTotal, 0, ',', '.') }}
            </span>
        </div>

        @if ($incomeTotal > 0)
            <div class="flex items-end gap-2 sm:gap-4 h-44">
                @foreach ($incomeChart as $item)
                    <div class="flex-1 flex flex-col items-center justify-end h-full min-w-0">
                        <p class="text-xs sm:text-xs font-semibold text-slate-700 truncate w-full text-center font-mono"
                            title="Rp{{ number_format($item['total'], 0, ',', '.') }}">
                            {{ $incomeLabel($item['total']) }}</p>
                        <div class="w-full bg-brand-500 rounded-t-md mt-1"
                            style="height: {{ $incomeHeight($item['total']) }}%"></div>
                        <p class="text-xs sm:text-xs text-slate-500 mt-2 leading-tight text-center">
                            {{ $item['month'] }}<br>{{ $item['year'] }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="py-10 text-center">
                <p class="text-sm text-slate-700">Belum ada invoice berstatus dibayar dalam 6 bulan terakhir.</p>
                <a href="{{ route('pages.finance') }}" class="inline-block mt-3 text-sm text-brand-600 hover:underline">
                    Lihat invoice</a>
            </div>
        @endif
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- RINGKASAN CLIENT --}}
        <x-card>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-4">
                <h2 class="font-semibold text-slate-800">Client Paling Aktif</h2>
                <span class="text-xs text-slate-500">{{ $newClientsThisMonth }} client baru bulan ini</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($topClients as $client)
                    <div class="py-2 flex items-center justify-between gap-2">
                        <p class="text-sm text-slate-700 truncate">{{ $client->company_name }}</p>
                        <span class="text-xs text-slate-500 flex-shrink-0">{{ $client->projects_count }} project</span>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <p class="text-sm text-slate-700">Belum ada client yang punya project.</p>
                        <a href="{{ route('pages.crm') }}" class="inline-block mt-3 text-sm text-brand-600 hover:underline">
                            Kelola client</a>
                    </div>
                @endforelse
            </div>
        </x-card>

        {{-- PERFORMA --}}
        <x-card>
            <h2 class="font-semibold text-slate-800 mb-4">Performa</h2>
            <div class="bg-slate-50 rounded-md p-4">
                <p class="text-xs text-slate-500 mb-1">Rata-rata lama project sampai selesai</p>
                @if ($avgDuration === null)
                    {{-- "0 hari" terbaca sebagai fakta bahwa semua project selesai
                         seketika, padahal yang benar adalah datangnya belum ada. --}}
                    <p class="text-sm text-slate-700 mt-2">
                        Belum ada project berstatus selesai, jadi rata-rata ini belum bisa dihitung.</p>
                @else
                    <p class="text-2xl font-bold text-slate-800 font-mono">{{ $avgDuration }} hari</p>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ $completedProjectCount }} project selesai dihitung.</p>
                @endif
            </div>
        </x-card>
    </div>

@endsection