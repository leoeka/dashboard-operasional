@extends('layouts.app')
@section('title', 'Finance')

@section('content')

    <x-page-header title="Finance" />

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ session('error') }}</div>
    @endif

    {{-- TAMBAH INVOICE --}}
    <x-card class="mb-6">
        <h2 class="font-semibold text-slate-800 mb-4">Buat Invoice Baru</h2>
        <form method="POST" action="{{ route('pages.finance.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            @csrf
            <select name="project_id" required class="bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                <option value="">-- Project --</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->client_name }})</option>
                @endforeach
            </select>
            <select name="type" required class="bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                <option value="dp">DP</option>
                <option value="pelunasan">Pelunasan</option>
                <option value="full">Full Payment</option>
            </select>
            <input type="number" name="amount" placeholder="Jumlah (Rp)" required
                class="bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
            <div class="flex gap-2">
                <input type="date" name="due_date" required
                    class="flex-1 bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                <button type="submit" class="grad-blue text-white text-sm px-4 rounded-lg hover:opacity-90 transition">
                    <i class='bx bx-plus'></i>
                </button>
            </div>
        </form>
    </x-card>

    {{-- FILTER --}}
    <x-card padding="p-4" class="mb-5">
        <form method="GET" class="flex gap-2">
            <a href="{{ route('pages.finance') }}"
                class="text-xs px-3 py-1.5 rounded-lg {{ !request('status') ? 'grad-blue text-white' : 'bg-slate-100 text-slate-600' }}">Semua</a>
            <a href="{{ route('pages.finance', ['status' => 'unpaid']) }}"
                class="text-xs px-3 py-1.5 rounded-lg {{ request('status') === 'unpaid' ? 'grad-blue text-white' : 'bg-slate-100 text-slate-600' }}">Belum
                Dibayar</a>
            <a href="{{ route('pages.finance', ['status' => 'overdue']) }}"
                class="text-xs px-3 py-1.5 rounded-lg {{ request('status') === 'overdue' ? 'grad-blue text-white' : 'bg-slate-100 text-slate-600' }}">Terlambat</a>
            <a href="{{ route('pages.finance', ['status' => 'paid']) }}"
                class="text-xs px-3 py-1.5 rounded-lg {{ request('status') === 'paid' ? 'grad-blue text-white' : 'bg-slate-100 text-slate-600' }}">Lunas</a>
        </form>
    </x-card>

    {{-- LIST INVOICE --}}
    <x-card padding="p-0">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-slate-100">
                    <th class="px-6 py-4 font-medium">No. Invoice</th>
                    <th class="px-6 py-4 font-medium">Project</th>
                    <th class="px-6 py-4 font-medium">Jenis</th>
                    <th class="px-6 py-4 font-medium">Jumlah</th>
                    <th class="px-6 py-4 font-medium">Jatuh Tempo</th>
                    <th class="px-6 py-4 font-medium">Status</th>
                    <th class="px-6 py-4 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoices as $invoice)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 font-medium text-slate-700">{{ $invoice->invoice_number }}</td>
                        <td class="px-6 py-4 text-slate-600">{{ $invoice->project->name }}</td>
                        <td class="px-6 py-4 text-slate-500">{{ $invoice->typeLabel() }}</td>
                        <td class="px-6 py-4 text-slate-700">Rp{{ number_format($invoice->amount, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 text-slate-500">{{ $invoice->due_date->translatedFormat('d M Y') }}</td>
                        <td class="px-6 py-4"><x-badge :color="$invoice->statusColor()">{{ $invoice->statusLabel() }}</x-badge>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-3 text-slate-400">
                                @if ($invoice->status !== 'paid')
                                    <form method="POST" action="{{ route('pages.finance.remind', $invoice) }}">
                                        @csrf
                                        <button type="submit" class="hover:text-brand-500" title="Kirim Reminder"><i
                                                class='bx bx-send text-lg'></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('pages.finance.paid', $invoice) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="hover:text-emerald-500" title="Tandai Lunas"><i
                                                class='bx bx-check-circle text-lg'></i></button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('pages.finance.destroy', $invoice) }}"
                                    onsubmit="return confirm('Hapus invoice ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="hover:text-red-500" title="Hapus"><i
                                            class='bx bx-trash text-lg'></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-slate-400">Belum ada invoice.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $invoices->links() }}</div>

@endsection