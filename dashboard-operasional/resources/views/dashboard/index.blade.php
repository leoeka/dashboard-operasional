@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

    <x-page-header title="Dashboard" />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div class="grad-purple rounded-2xl p-6 text-white">
            <i class='bx bx-briefcase-alt-2 text-3xl opacity-90'></i>
            <p class="mt-4 text-sm font-medium opacity-90">Total Project</p>
            <p class="text-3xl font-bold mt-1">{{ $totalProject }}</p>
        </div>
        <div class="grad-blue rounded-2xl p-6 text-white">
            <i class='bx bx-file text-3xl opacity-90'></i>
            <p class="mt-4 text-sm font-medium opacity-90">Proposal Pending</p>
            <p class="text-3xl font-bold mt-1">{{ $proposalPending }}</p>
        </div>
        <div class="grad-orange rounded-2xl p-6 text-white">
            <i class='bx bx-globe text-3xl opacity-90'></i>
            <p class="mt-4 text-sm font-medium opacity-90">Website Active</p>
            <p class="text-3xl font-bold mt-1">{{ $websiteActive }}</p>
        </div>
        <div class="grad-teal rounded-2xl p-6 text-white">
            <i class='bx bx-time-five text-3xl opacity-90'></i>
            <p class="mt-4 text-sm font-medium opacity-90">Deadline (14 hari)</p>
            <p class="text-3xl font-bold mt-1">{{ $upcomingDeadline }}</p>
        </div>
    </div>

    <x-card>
        <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-5">Recent Activity</h2>

        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($recentActivities as $activity)
                <div class="flex items-center justify-between py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-300 text-sm font-semibold">
                            {{ strtoupper(substr($activity->client_name, 0, 2)) }}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $activity->client_name }}</p>
                            <p class="text-xs text-slate-400">{{ $activity->action }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <x-badge :color="$activity->statusColor()">{{ ucfirst($activity->status) }}</x-badge>
                        <span class="text-xs text-slate-400 w-20 text-right">{{ $activity->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400 py-6 text-center">Belum ada aktivitas.</p>
            @endforelse
        </div>
    </x-card>

@endsection