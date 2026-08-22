@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-8">
        <div>
            <p class="text-sm text-slate-400">Ringkasan operasional</p>
            <h1 class="text-2xl font-bold text-slate-800 mt-1">Dashboard</h1>
        </div>
        <a href="{{ route('pages.request') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 transition">
            <i class='bx bx-plus text-lg'></i> Request baru
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        @foreach ([
            ['label' => 'Request baru', 'value' => $requestProjectCount, 'note' => 'Menunggu diproses', 'icon' => 'bx-list-plus', 'color' => 'blue'],
            ['label' => 'Proposal pending', 'value' => $proposalPending, 'note' => 'Menunggu keputusan', 'icon' => 'bx-file', 'color' => 'amber'],
            ['label' => 'Project dalam proses', 'value' => $activeProjectCount, 'note' => "Dari {$totalProject} total project", 'icon' => 'bx-loader-circle', 'color' => 'emerald'],
            ['label' => 'Invoice belum lunas', 'value' => $unpaidInvoiceCount, 'note' => 'Rp ' . number_format($unpaidInvoiceAmount, 0, ',', '.'), 'icon' => 'bx-wallet', 'color' => 'rose'],
        ] as $card)
            <div class="bg-white border border-slate-100 rounded-2xl p-5 border-l-4 border-l-{{ $card['color'] }}-400">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-500">{{ $card['label'] }}</p>
                    <i class='bx {{ $card['icon'] }} text-xl text-{{ $card['color'] }}-500'></i>
                </div>
                <p class="text-3xl font-bold text-slate-800 mt-4">{{ $card['value'] }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ $card['note'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5 mb-5">
        <x-card class="xl:col-span-3">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="font-semibold text-slate-800">Pipeline project</h2>
                    <p class="text-xs text-slate-400 mt-1">Posisi project berdasarkan status proses</p>
                </div>
                <a href="{{ route('pages.projects') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Lihat semua</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach (['request' => 'Request', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $key => $label)
                    <a href="{{ route('pages.projects', ['status' => $key]) }}" class="rounded-xl bg-slate-50 p-4 hover:bg-blue-50 transition">
                        <p class="text-xs text-slate-400">{{ $label }}</p>
                        <p class="text-2xl font-bold text-slate-800 mt-2">{{ $projectByStatus[$key] ?? 0 }}</p>
                    </a>
                @endforeach
            </div>
        </x-card>

        <x-card class="xl:col-span-2">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="font-semibold text-slate-800">Jenis project</h2>
                    <p class="text-xs text-slate-400 mt-1">Layanan yang sedang dikelola</p>
                </div>
                <i class='bx bx-category text-xl text-blue-500'></i>
            </div>
            <div class="space-y-4">
                @foreach (['Web' => $webProjectCount, 'SEO' => $seoProjectCount, 'Backlink' => $backlinkProjectCount] as $label => $count)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-600">{{ $label }}</span>
                        <span class="text-sm font-bold text-slate-800">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
            <div class="border-t border-slate-100 mt-5 pt-4 flex items-center justify-between">
                <span class="text-xs text-slate-500">Pendapatan bulan ini</span>
                <span class="text-sm font-bold text-emerald-600">Rp {{ number_format($monthlyRevenue, 0, ',', '.') }}</span>
            </div>
        </x-card>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        <x-card>
            <div class="flex items-center justify-between mb-5">
                <h2 class="font-semibold text-slate-800">Project yang perlu diproses</h2>
                <span class="text-xs text-slate-400">{{ $activeProjectCount }} aktif</span>
            </div>
            <div class="space-y-3">
                @forelse ($attentionProjects as $project)
                    <a href="{{ route('pages.projects.show', $project) }}" class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 p-4 hover:border-blue-200 hover:bg-blue-50/30 transition">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800 truncate">{{ $project->name }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ $project->client_name }} · {{ $project->serviceTypeLabel() }}</p>
                        </div>
                        <x-badge :color="$project->statusColor()">{{ $project->statusLabel() }}</x-badge>
                    </a>
                @empty
                    <p class="text-sm text-slate-400 py-8 text-center">Tidak ada project yang perlu diproses.</p>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <div class="flex items-center justify-between mb-5">
                <h2 class="font-semibold text-slate-800">Aktivitas terbaru</h2>
                <span class="text-xs text-slate-400">6 aktivitas terakhir</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($recentActivities as $activity)
                    <div class="flex items-center justify-between py-4 gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 text-sm font-semibold shrink-0">{{ strtoupper(substr($activity->client_name, 0, 2)) }}</div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">{{ $activity->client_name }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ $activity->action }}</p>
                            </div>
                        </div>
                        <span class="text-xs text-slate-400 whitespace-nowrap">{{ $activity->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 py-6 text-center">Belum ada aktivitas.</p>
                @endforelse
            </div>
        </x-card>
    </div>
@endsection
