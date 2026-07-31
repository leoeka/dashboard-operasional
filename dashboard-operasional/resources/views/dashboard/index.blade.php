@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

    <div class="flex items-center gap-2 mb-6">
        <i class='bx bx-grid-alt text-2xl text-brand-500'></i>
        <h1 class="text-xl font-bold text-slate-800">Dashboard</h1>
    </div>

    {{-- KPI CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">

        <div class="grad-purple rounded-2xl p-6 text-white relative overflow-hidden">
            <i class='bx bx-briefcase-alt-2 text-3xl opacity-90'></i>
            <p class="mt-4 text-sm font-medium opacity-90">Total Project</p>
            <p class="text-3xl font-bold mt-1">{{ $totalProject }}</p>
        </div>

        <div class="grad-blue rounded-2xl p-6 text-white relative overflow-hidden">
            <i class='bx bx-file text-3xl opacity-90'></i>
            <p class="mt-4 text-sm font-medium opacity-90">Proposal Pending</p>
            <p class="text-3xl font-bold mt-1">{{ $proposalPending }}</p>
        </div>

        <div class="grad-orange rounded-2xl p-6 text-white relative overflow-hidden">
            <i class='bx bx-globe text-3xl opacity-90'></i>
            <p class="mt-4 text-sm font-medium opacity-90">Website Active</p>
            <p class="text-3xl font-bold mt-1">{{ $websiteActive }}</p>
        </div>

        <div class="grad-teal rounded-2xl p-6 text-white relative overflow-hidden">
            <i class='bx bx-time-five text-3xl opacity-90'></i>
            <p class="mt-4 text-sm font-medium opacity-90">Deadline (14 hari)</p>
            <p class="text-3xl font-bold mt-1">{{ $upcomingDeadline }}</p>
        </div>

    </div>

    {{-- RECENT ACTIVITY --}}
    <div class="bg-white rounded-2xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-800 mb-5">Recent Activity</h2>

        <div class="divide-y divide-slate-100">
            @forelse ($recentActivities as $activity)
                <div class="flex items-center justify-between py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 text-sm font-semibold">
                            {{ strtoupper(substr($activity->client_name, 0, 2)) }}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ $activity->client_name }}</p>
                            <p class="text-xs text-slate-400">{{ $activity->action }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs px-3 py-1 rounded-full {{ $activity->statusColor() }}">
                            {{ ucfirst($activity->status) }}
                        </span>
                        <span class="text-xs text-slate-400 w-20 text-right">{{ $activity->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400 py-6 text-center">Belum ada aktivitas.</p>
            @endforelse
        </div>
    </div>

@endsection
