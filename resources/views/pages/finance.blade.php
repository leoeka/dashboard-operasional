@extends('layouts.app')
@section('title', 'Billing & Finance')

@section('content')

    <x-page-header title="Billing & Finance">
        <x-slot:actions>
            <a href="{{ route('pages.finance.subscriptions.create') }}"
                class="grad-blue text-white text-sm px-4 py-2 rounded-lg hover:opacity-90 transition inline-flex items-center gap-2">
                <i class='bx bx-plus'></i> Langganan Baru
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- SUMMARY — the same six numbers on every tab, so the state of the book is
         never more than a glance away regardless of what you are looking at. --}}
    @include('finance.partials.summary', ['summary' => $summary])

    {{-- TABS --}}
    <div class="mb-5 overflow-x-auto">
        <div class="inline-flex gap-1 bg-slate-100 p-1 rounded-xl">
            @foreach ([
        'overview' => ['Overview', 'bx-grid-alt'],
        'subscriptions' => ['Langganan', 'bx-repost'],
        'invoices' => ['Invoice', 'bx-receipt'],
        'reminders' => ['Riwayat Reminder', 'bx-bell'],
    ] as $key => [$label, $icon])
                <a href="{{ route('pages.finance', ['tab' => $key]) }}"
                    class="text-sm px-4 py-2 rounded-lg whitespace-nowrap transition inline-flex items-center gap-2
                       {{ $tab === $key ? 'bg-white text-slate-800 shadow-sm font-medium' : 'text-slate-500 hover:text-slate-700' }}">
                    <i class='bx {{ $icon }}'></i> {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @include('finance.partials.' . $tab)

@endsection
