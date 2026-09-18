@extends('layouts.app')
@section('title', 'Billing & Finance')

@section('content')

    <x-page-header title="Billing & Finance">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('pages.finance.subscriptions.create') }}"
                    class="grad-blue text-white text-sm px-4 py-2 rounded-lg hover:opacity-90 transition inline-flex items-center gap-2">
                    <i class='bx bx-plus'></i> Langganan Baru
                </a>
                <button type="button" x-data @click="$dispatch('open-invoice-form')"
                    class="bg-slate-100 text-slate-600 text-sm px-4 py-2 rounded-lg hover:bg-slate-200 transition inline-flex items-center gap-2">
                    <i class='bx bx-receipt'></i> Invoice Manual
                </button>
            </div>
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
        'payments' => ['Pembayaran', 'bx-wallet'],
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

    {{-- The legacy manual invoice form, unchanged in behaviour and still posting
         to the same route. Moved into a collapsible panel so it stops taking up
         the top of the page, but nothing about it was rewritten. --}}
    <div x-data="{ open: false }" @open-invoice-form.window="open = true" class="mt-6">
        {{-- x-transition, not x-collapse: the layout loads Alpine core from the
             CDN without the Collapse plugin, so x-collapse would be an unknown
             directive. Core transitions are already available. --}}
        <x-card x-show="open" x-cloak x-transition.duration.200ms>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-semibold text-slate-800">Invoice Manual (Project)</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Untuk DP, pelunasan, atau pembayaran penuh sebuah project.
                        Invoice perpanjangan dibuat otomatis oleh sistem.</p>
                </div>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600">
                    <i class='bx bx-x text-xl'></i>
                </button>
            </div>

            <form method="POST" action="{{ route('pages.finance.store') }}"
                class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                @csrf
                <select name="project_id" required
                    class="bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                    <option value="">-- Project --</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->client_name }})</option>
                    @endforeach
                </select>
                <select name="type" required
                    class="bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                    <option value="dp">DP</option>
                    <option value="pelunasan">Final Payment</option>
                    <option value="full">Full Payment</option>
                </select>
                <input type="number" name="amount" placeholder="Amount (Rp)" required
                    class="bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                <div class="flex gap-2">
                    <input type="date" name="due_date" required
                        class="flex-1 bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                    <button type="submit"
                        class="grad-blue text-white text-sm px-4 rounded-lg hover:opacity-90 transition flex-shrink-0">
                        <i class='bx bx-plus'></i>
                    </button>
                </div>
            </form>
        </x-card>
    </div>

@endsection
