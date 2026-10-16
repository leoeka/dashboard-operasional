@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

    <x-page-header title="Dashboard">
        <x-slot:actions>
            <a href="{{ route('pages.request') }}"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700 transition">
                <i class='bx bx-plus text-lg' aria-hidden="true"></i> Request Baru
            </a>
        </x-slot:actions>
    </x-page-header>

    @php
        // Urutan ditentukan dari apa yang jatuh tempo hari ini, bukan dari jenis data.
        // 'urgent' = kartu memaksa tindakan hari ini, dan hanya kartu begitu yang boleh
        // memakai bar kiri 3px (DESIGN bagian 6).
        $cards = [
            [
                'label' => 'Invoice Lewat Jatuh Tempo',
                'value' => $overdueInvoiceCount,
                'note' => 'Belum dibayar Rp ' . number_format((float) $overdueInvoiceAmount, 0, ',', '.'),
                'href' => route('pages.finance', ['tab' => 'invoices', 'status' => 'overdue']),
                'urgent' => $overdueInvoiceCount > 0,
            ],
            [
                'label' => 'Pengingat Gagal Terkirim',
                'value' => $failedReminderCount,
                'note' => 'Perlu dikirim ulang dari Finance',
                'href' => route('pages.finance', ['tab' => 'reminders', 'status' => 'failed']),
                'urgent' => $failedReminderCount > 0,
            ],
            [
                'label' => 'Request Menunggu',
                'value' => $requestProjectCount,
                'note' => 'Belum diubah menjadi project',
                'href' => route('pages.projects', ['status' => 'request']),
                'urgent' => $requestProjectCount > 0,
            ],
            [
                'label' => 'Project Berjalan',
                'value' => $activeProjectCount,
                'note' => "Total {$totalProject} project di semua status",
                'href' => route('pages.projects', ['status' => 'in_progress']),
                'urgent' => false,
            ],
        ];
    @endphp

    <div class="grid grid-cols-12 gap-6 mb-6">
        @foreach ($cards as $card)
            <a href="{{ $card['href'] }}"
                @class([
                    'col-span-12 sm:col-span-6 lg:col-span-3',
                    'bg-white rounded-xl p-4 hover:bg-brand-50/40 transition',
                    'border border-slate-200' => !$card['urgent'],
                    'border border-slate-200 border-l-[3px] border-l-brand-600' => $card['urgent'],
                ])>
                <p class="text-xs text-slate-600">{{ $card['label'] }}</p>
                <p class="text-[28px] leading-9 font-bold text-slate-900 font-mono mt-2">{{ $card['value'] }}</p>
                <p class="text-xs text-slate-600 mt-1">{{ $card['note'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-12 gap-6 mb-6">
        <x-card class="col-span-12 xl:col-span-8">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="font-semibold text-slate-800">Pipeline Project</h2>
                    <p class="text-xs text-slate-600 mt-1">Sebaran status project</p>
                </div>
                <a href="{{ route('pages.projects') }}"
                    class="inline-flex items-center px-1 min-h-11 text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline">Lihat semua</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach (['request' => 'Request', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $key => $label)
                    <a href="{{ route('pages.projects', ['status' => $key]) }}"
                        class="rounded-lg bg-slate-50 p-4 hover:bg-brand-50 transition">
                        <p class="text-xs text-slate-600">{{ $label }}</p>
                        <p class="text-2xl font-bold text-slate-900 font-mono mt-2">{{ $projectByStatus[$key] ?? 0 }}</p>
                    </a>
                @endforeach
            </div>
            <div class="border-t border-slate-100 mt-4 pt-4 flex items-center justify-between gap-3">
                <p class="text-xs text-slate-600">Omzet bulan ini</p>
                <a href="{{ route('pages.reports') }}"
                    class="inline-flex items-center px-1 min-h-11 gap-2 text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline">
                    <span class="font-mono">Rp {{ number_format((float) $monthlyRevenue, 0, ',', '.') }}</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </x-card>

        <x-card class="col-span-12 xl:col-span-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-slate-800">Aktivitas Terbaru</h2>
                <span class="text-xs text-slate-600">6 aktivitas terakhir</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($recentActivities as $activity)
                    <div class="flex items-center justify-between py-4 gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-sm font-semibold shrink-0">
                                {{ strtoupper(substr($activity->client_name, 0, 2)) }}</div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">{{ $activity->client_name }}</p>
                                <p class="text-xs text-slate-600 truncate">{{ $activity->action }}</p>
                            </div>
                        </div>
                        <span class="text-xs text-slate-600 whitespace-nowrap">{{ $activity->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <div class="py-6 text-center">
                        <p class="text-sm text-slate-700">Belum ada aktivitas tercatat.</p>
                        <a href="{{ route('pages.request') }}"
                            class="inline-flex items-center px-1 mt-2 min-h-11 text-sm text-brand-600 hover:underline">Buat request pertama</a>
                    </div>
                @endforelse
            </div>
        </x-card>
    </div>

    <x-card>
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-slate-800">Project Perlu Perhatian</h2>
            <a href="{{ route('pages.projects') }}" class="inline-flex items-center px-1 min-h-11 text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline">Lihat semua</a>
        </div>
        @if (count($attentionProjects) === 0)
            <x-empty-state icon="bx-check-circle" title="Tidak ada yang menunggu hari ini"
                description="Semua request sudah diproses dan tidak ada project yang sedang berjalan.">
                <a href="{{ route('pages.request') }}" class="inline-flex items-center px-1 min-h-11 text-sm text-brand-600 hover:underline">Buat request baru</a>
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($attentionProjects as $project)
                    <a href="{{ route('pages.projects.show', $project) }}"
                        class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-4 hover:border-slate-300 hover:bg-slate-50 transition">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800 truncate">{{ $project->name }}</p>
                            <p class="text-xs text-slate-600 mt-1">{{ $project->client_name }} &middot; {{ $project->serviceTypeLabel() }}</p>
                        </div>
                        <x-badge :color="$project->statusColor()">{{ $project->statusLabel() }}</x-badge>
                    </a>
                @endforeach
            </div>
        @endif
    </x-card>
@endsection
