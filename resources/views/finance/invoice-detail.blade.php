@extends('layouts.app')
@section('title', 'Invoice ' . $invoice->invoice_number)

@section('content')

    <x-page-header :title="'Invoice ' . $invoice->invoice_number">
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                @if ($invoice->status !== 'paid')
                    {{-- Both actions post to the routes that already own them; this
                         page reads, it does not implement a second way to pay. --}}
                    <form method="POST" action="{{ route('pages.finance.paid', $invoice) }}"
                        onsubmit="return confirm('Tandai invoice ini sebagai lunas?')">
                        @csrf @method('PATCH')
                        <button type="submit"
                            class="grad-blue text-white text-sm px-4 py-2 rounded-lg hover:opacity-90 transition inline-flex items-center gap-2">
                            <i class='bx bx-check-circle'></i> Tandai Lunas
                        </button>
                    </form>

                    {{-- A renewal follows its own H-30/H-7/H-3 schedule and records
                         every threshold it claims. Sending one by hand would send an
                         email no threshold accounts for. --}}
                    @unless ($invoice->isRenewal())
                        <form method="POST" action="{{ route('pages.finance.remind', $invoice) }}">
                            @csrf
                            <button type="submit"
                                class="bg-slate-100 text-slate-600 text-sm px-4 py-2 rounded-lg hover:bg-slate-200 transition inline-flex items-center gap-2">
                                <i class='bx bx-send'></i> Kirim Reminder
                            </button>
                        </form>
                    @endunless
                @endif

                <a href="{{ route('pages.finance', ['tab' => 'invoices']) }}"
                    class="bg-slate-100 text-slate-600 text-sm px-4 py-2 rounded-lg hover:bg-slate-200 transition inline-flex items-center gap-2">
                    <i class='bx bx-arrow-back'></i> Kembali
                </a>
            </div>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-600 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- LEFT: the invoice itself --}}
        <div class="lg:col-span-2 space-y-5">

            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
                    <div>
                        <p class="text-xl font-semibold text-slate-800">{{ $invoice->invoice_number }}</p>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            @if ($invoice->isRenewal())
                                <x-badge color="purple">Perpanjangan</x-badge>
                            @else
                                <x-badge color="blue">Project · {{ $invoice->typeLabel() }}</x-badge>
                            @endif
                            <x-badge :color="$invoice->statusColor()">{{ $invoice->statusLabel() }}</x-badge>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-400">Total Tagihan</p>
                        <p class="text-2xl font-semibold text-slate-800">
                            {{ $invoice->currency }} {{ number_format((float) $invoice->amount, 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm border-t border-slate-100 pt-4">
                    <div>
                        <p class="text-xs text-slate-400 mb-1">Tanggal Terbit</p>
                        <p class="text-slate-700">{{ $invoice->issue_date?->translatedFormat('d M Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 mb-1">Jatuh Tempo</p>
                        <p class="text-slate-700">{{ $invoice->due_date->translatedFormat('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 mb-1">Dibayar Pada</p>
                        <p class="text-slate-700">{{ $invoice->paid_at?->translatedFormat('d M Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 mb-1">Periode Layanan</p>
                        <p class="text-slate-700">
                            @if ($invoice->billing_period_start && $invoice->billing_period_end)
                                {{ $invoice->billing_period_start->translatedFormat('d M Y') }} –
                                {{ $invoice->billing_period_end->translatedFormat('d M Y') }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>

                @if ($invoice->notes)
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <p class="text-xs text-slate-400 mb-1">Catatan</p>
                        <p class="text-sm text-slate-600 whitespace-pre-line">{{ $invoice->notes }}</p>
                    </div>
                @endif
            </x-card>

            {{-- ITEMS — the frozen snapshot. These rows keep the price as it was
                 when the invoice was issued, so editing a subscription later never
                 rewrites what a client was actually billed. --}}
            <x-card padding="p-0">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-800">Rincian</h2>
                </div>

                @if ($invoice->items->isEmpty())
                    <x-empty-state icon="bx-list-ul" title="Tanpa rincian item"
                        description="Invoice ini hanya mencatat nominal total." />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[560px]">
                            <thead>
                                <tr class="text-left text-slate-400 border-b border-slate-100">
                                    <th class="px-6 py-3 font-medium">Deskripsi</th>
                                    <th class="px-6 py-3 font-medium text-right">Qty</th>
                                    <th class="px-6 py-3 font-medium text-right">Harga Satuan</th>
                                    <th class="px-6 py-3 font-medium text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($invoice->items as $item)
                                    <tr>
                                        <td class="px-6 py-4 text-slate-700">{{ $item->description }}</td>
                                        <td class="px-6 py-4 text-right text-slate-500">
                                            {{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}
                                        </td>
                                        <td class="px-6 py-4 text-right text-slate-500">
                                            {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                        <td class="px-6 py-4 text-right text-slate-700">
                                            {{ number_format((float) $item->total, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="px-6 py-4 border-t border-slate-100 flex justify-end">
                    <dl class="w-full max-w-xs text-sm space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Subtotal</dt>
                            <dd class="text-slate-700">
                                {{ $invoice->currency }}
                                {{ number_format((float) ($invoice->subtotal ?? $invoice->amount), 0, ',', '.') }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Diskon</dt>
                            <dd class="text-slate-700">
                                {{ $invoice->currency }}
                                {{ number_format((float) $invoice->discount, 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Pajak</dt>
                            <dd class="text-slate-700">
                                {{ $invoice->currency }} {{ number_format((float) $invoice->tax, 0, ',', '.') }}
                            </dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 pt-2 font-semibold">
                            <dt class="text-slate-600">Total</dt>
                            <dd class="text-slate-800">
                                {{ $invoice->currency }}
                                {{ number_format((float) $invoice->amount, 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                </div>
            </x-card>

            {{-- PAYMENTS --}}
            <x-card padding="p-0">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-800">Riwayat Pembayaran</h2>
                </div>

                @if ($invoice->payments->isEmpty())
                    <div class="px-6 py-8 text-center text-sm text-slate-400">Belum ada pembayaran tercatat.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[720px]">
                            <thead>
                                <tr class="text-left text-slate-400 border-b border-slate-100">
                                    <th class="px-6 py-3 font-medium">Tanggal</th>
                                    <th class="px-6 py-3 font-medium">Nominal</th>
                                    <th class="px-6 py-3 font-medium">Metode</th>
                                    <th class="px-6 py-3 font-medium">Referensi</th>
                                    <th class="px-6 py-3 font-medium">Dicatat Oleh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($invoice->payments as $payment)
                                    <tr class="align-top">
                                        <td class="px-6 py-4 text-slate-600">
                                            {{ $payment->paid_on->translatedFormat('d M Y') }}</td>
                                        <td class="px-6 py-4 text-slate-700 whitespace-nowrap">
                                            {{ $payment->currency }}
                                            {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                        </td>
                                        <td class="px-6 py-4 text-slate-500">{{ $payment->methodLabel() }}</td>
                                        <td class="px-6 py-4 text-slate-500">{{ $payment->reference ?: '—' }}</td>
                                        <td class="px-6 py-4 text-slate-500">
                                            {{ $payment->recorder?->name ?? '—' }}
                                            @if ($payment->notes)
                                                <p class="text-xs text-slate-400 mt-1">{{ $payment->notes }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        {{-- RIGHT: who is billed, what for, and what the reminders did --}}
        <div class="space-y-5">

            <x-card>
                <h2 class="font-semibold text-slate-800 mb-4">Client</h2>

                @if ($billingClient)
                    <p class="font-medium text-slate-700">{{ $billingClient->company_name }}</p>

                    @php
                        // Billing contact falls back to the client's general contact,
                        // which is what the reminder emails do too.
                        $contactName = $billingClient->billingName();
                        $contactEmail = $billingClient->billingEmail();
                        $contactPhone = $billingClient->billingPhone();
                    @endphp

                    @if (filled($contactName) || filled($contactEmail) || filled($contactPhone))
                        <dl class="mt-3 space-y-2 text-sm">
                            @if (filled($contactName))
                                <div>
                                    <dt class="text-xs text-slate-400">Kontak Billing</dt>
                                    <dd class="text-slate-600">{{ $contactName }}</dd>
                                </div>
                            @endif
                            @if (filled($contactEmail))
                                <div>
                                    <dt class="text-xs text-slate-400">Email</dt>
                                    <dd class="text-slate-600 break-all">{{ $contactEmail }}</dd>
                                </div>
                            @endif
                            @if (filled($contactPhone))
                                <div>
                                    <dt class="text-xs text-slate-400">Telepon</dt>
                                    <dd class="text-slate-600">{{ $contactPhone }}</dd>
                                </div>
                            @endif
                        </dl>
                    @else
                        <p class="text-sm text-slate-400 mt-2">—</p>
                    @endif
                @else
                    <p class="text-sm text-slate-400">—</p>
                @endif

                <div class="mt-4 border-t border-slate-100 pt-4">
                    <p class="text-xs text-slate-400 mb-1">Project</p>
                    @if ($invoice->project)
                        <a href="{{ route('pages.projects.show', $invoice->project) }}"
                            class="text-sm text-brand-500 hover:underline">{{ $invoice->project->name }}</a>
                    @else
                        <p class="text-sm text-slate-400">—</p>
                    @endif
                </div>
            </x-card>

            @if ($invoice->subscription)
                <x-card>
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <h2 class="font-semibold text-slate-800">Langganan</h2>
                        <a href="{{ route('pages.finance.subscriptions.edit', $invoice->subscription) }}"
                            class="text-xs text-brand-500 hover:underline whitespace-nowrap">Kelola</a>
                    </div>

                    <p class="font-medium text-slate-700">{{ $invoice->subscription->name }}</p>

                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-400">Jenis Layanan</dt>
                            <dd class="text-slate-600">
                                {{ ucfirst($invoice->subscription->service_type) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-400">Siklus</dt>
                            <dd class="text-slate-600">
                                {{ $invoice->subscription->isYearly() ? 'Tahunan' : 'Bulanan' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-400">Status</dt>
                            <dd><x-badge :color="$invoice->subscription->isActive() ? 'emerald' : 'slate'">
                                    {{ ucfirst($invoice->subscription->status) }}</x-badge></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-400">Perpanjangan Berikutnya</dt>
                            <dd class="text-slate-600">
                                {{ $invoice->subscription->next_renewal_date?->translatedFormat('d M Y') ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-400">Invoice Otomatis</dt>
                            <dd class="text-slate-600">
                                {{ $invoice->subscription->auto_invoice ? 'Ya' : 'Tidak' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-400">Reminder Otomatis</dt>
                            <dd class="text-slate-600">
                                {{ $invoice->subscription->auto_reminder ? 'Ya' : 'Tidak' }}</dd>
                        </div>
                    </dl>
                </x-card>
            @endif

            {{-- REMINDER TIMELINE — derived, never stored. The thresholds come from
                 ReminderPolicy via ReminderTimeline, so this panel follows the
                 schedule the engine actually uses instead of a copy of it. Empty
                 for a project invoice, which has no schedule. --}}
            @if (!empty($timeline))
                <x-card>
                    <h2 class="font-semibold text-slate-800 mb-4">Jadwal Reminder</h2>

                    @php
                        // Written out rather than interpolated into "bg-{$tone}-400":
                        // a class assembled at runtime is invisible to Tailwind's
                        // scanner and would come out unstyled in a compiled build.
                        $dotClasses = [
                            'emerald' => 'bg-emerald-400',
                            'red' => 'bg-red-400',
                            'amber' => 'bg-amber-400',
                            'blue' => 'bg-blue-400',
                            'slate' => 'bg-slate-300',
                        ];
                    @endphp

                    <ol class="space-y-4">
                        @foreach ($timeline as $step)
                            <li class="flex gap-3">
                                <div class="flex flex-col items-center">
                                    <span
                                        class="w-2.5 h-2.5 rounded-full mt-1.5 {{ $dotClasses[$step['tone']] ?? $dotClasses['slate'] }}"></span>
                                    @unless ($loop->last)
                                        <span class="flex-1 w-px bg-slate-200 my-1"></span>
                                    @endunless
                                </div>

                                <div class="flex-1 pb-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-medium text-slate-700">{{ $step['label'] }}</p>
                                        <x-badge :color="$step['tone']">{{ $step['state_label'] }}</x-badge>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-0.5">
                                        {{ $step['due_on']->translatedFormat('d M Y') }}
                                    </p>

                                    @if ($step['log']?->recipient)
                                        <p class="text-xs text-slate-400 break-all">
                                            {{ $step['log']->recipient }}</p>
                                    @endif

                                    {{-- Sanitised at write time, escaped and truncated here. --}}
                                    @if ($step['state'] === 'failed' && $step['log']?->error_message)
                                        <p class="text-xs text-red-500 mt-1">
                                            {{ \Illuminate\Support\Str::limit($step['log']->error_message, 120) }}
                                        </p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </x-card>
            @endif
        </div>
    </div>

@endsection
