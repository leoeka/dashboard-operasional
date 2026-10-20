@extends('layouts.app')
@section('title', 'Invoice ' . $invoice->invoice_number)

@section('content')

    <x-page-header :title="'Invoice ' . $invoice->invoice_number">
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                {{-- Payment itself stays off-system: this records that a bill was
                     settled and when, nothing about the money. The button is blue,
                     not green, because bagian 4 DESIGN.md keeps status colours out
                     of actions. --}}
                @if ($invoice->status === 'paid')
                    <form method="POST" action="{{ route('pages.finance.invoices.unpaid', $invoice) }}"
                        onsubmit="return confirm('Batalkan penandaan lunas invoice ini?')">
                        @csrf @method('PATCH')
                        <button type="submit"
                            class="min-h-11 border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 text-sm font-semibold px-4 py-2 rounded-md transition inline-flex items-center gap-2">
                            <i class='bx bx-undo'></i> Batalkan lunas
                        </button>
                    </form>
                @elseif ($invoice->status !== 'cancelled')
                    <form method="POST" action="{{ route('pages.finance.invoices.paid', $invoice) }}"
                        class="flex flex-wrap items-center gap-2">
                        @csrf @method('PATCH')
                        <label for="paid_on" class="text-sm text-slate-600">Dibayar tanggal</label>
                        <input type="date" id="paid_on" name="paid_on"
                            value="{{ old('paid_on', $billingToday->format('Y-m-d')) }}"
                            max="{{ $billingToday->format('Y-m-d') }}" required
                            class="min-h-11 rounded-md border border-slate-500 bg-white text-sm text-slate-800 font-mono px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500" />
                        <button type="submit"
                            class="min-h-11 bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-md hover:bg-brand-700 transition inline-flex items-center gap-2">
                            <i class='bx bx-check'></i> Tandai lunas
                        </button>
                    </form>
                @endif
                <a href="{{ route('pages.finance', ['tab' => 'invoices']) }}"
                    class="min-h-11 bg-slate-100 text-slate-600 text-sm px-4 py-2 rounded-md hover:bg-slate-200 transition inline-flex items-center gap-2">
                    <i class='bx bx-arrow-back'></i> Kembali
                </a>
            </div>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="mb-6 px-4 py-3 rounded-md bg-red-50 text-red-700 text-sm border-l-4 border-l-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @if (session('success'))
        <div class="mb-6 px-4 py-3 rounded-md bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 px-4 py-3 rounded-md bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- LEFT: the invoice itself --}}
        <div class="lg:col-span-2 space-y-6">

            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
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
                        <p class="text-xs text-slate-500">Total Tagihan</p>
                        <p class="text-2xl font-semibold text-slate-800 font-mono">
                            {{ $invoice->currency }} {{ number_format((float) $invoice->amount, 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm border-t border-slate-100 pt-4">
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Tanggal Terbit</p>
                        <p class="text-slate-700 font-mono">{{ $invoice->issue_date?->translatedFormat('d M Y') ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Jatuh Tempo</p>
                        <p class="text-slate-700 font-mono">{{ $invoice->due_date->translatedFormat('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Dibayar Pada</p>
                        <p class="text-slate-700 font-mono">{{ $invoice->paid_at?->translatedFormat('d M Y') ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Periode Layanan</p>
                        <p class="text-slate-700 font-mono">
                            @if ($invoice->billing_period_start && $invoice->billing_period_end)
                                {{ $invoice->billing_period_start->translatedFormat('d M Y') }} –
                                {{ $invoice->billing_period_end->translatedFormat('d M Y') }}
                            @else
                                -
                            @endif
                        </p>
                    </div>
                </div>

                @if ($invoice->notes)
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <p class="text-xs text-slate-500 mb-1">Catatan</p>
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
                                <tr class="text-left text-xs text-slate-600 bg-slate-50 border-b border-slate-100">
                                    <th class="px-6 py-3 font-semibold">Deskripsi</th>
                                    <th class="px-6 py-3 font-semibold text-right">Qty</th>
                                    <th class="px-6 py-3 font-semibold text-right">Harga Satuan</th>
                                    <th class="px-6 py-3 font-semibold text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($invoice->items as $item)
                                    <tr>
                                        <td class="px-6 py-4 text-slate-700">{{ $item->description }}</td>
                                        <td class="px-6 py-4 text-right text-slate-500 font-mono">
                                            {{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}
                                        </td>
                                        <td class="px-6 py-4 text-right text-slate-500 font-mono">
                                            {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                        <td class="px-6 py-4 text-right text-slate-700 font-mono">
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
                            <dt class="text-slate-500">Subtotal</dt>
                            <dd class="text-slate-700 font-mono">
                                {{ $invoice->currency }}
                                {{ number_format((float) ($invoice->subtotal ?? $invoice->amount), 0, ',', '.') }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Diskon</dt>
                            <dd class="text-slate-700 font-mono">
                                {{ $invoice->currency }}
                                {{ number_format((float) $invoice->discount, 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Pajak</dt>
                            <dd class="text-slate-700 font-mono">
                                {{ $invoice->currency }} {{ number_format((float) $invoice->tax, 0, ',', '.') }}
                            </dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 pt-2 font-semibold">
                            <dt class="text-slate-600">Total</dt>
                            <dd class="text-slate-800 font-mono">
                                {{ $invoice->currency }}
                                {{ number_format((float) $invoice->amount, 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                </div>
            </x-card>

            {{-- PAYMENTS — nothing writes here any more, but invoices settled
                 before payments moved outside the system still have their
                 history, and it stays readable. --}}
            @if ($invoice->payments->isNotEmpty())
                <x-card padding="p-0">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h2 class="font-semibold text-slate-800">Riwayat Pembayaran</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[720px]">
                            <thead>
                                <tr class="text-left text-xs text-slate-600 bg-slate-50 border-b border-slate-100">
                                    <th class="px-6 py-3 font-semibold">Tanggal</th>
                                    <th class="px-6 py-3 font-semibold">Nominal</th>
                                    <th class="px-6 py-3 font-semibold">Metode</th>
                                    <th class="px-6 py-3 font-semibold">Referensi</th>
                                    <th class="px-6 py-3 font-semibold">Dicatat Oleh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($invoice->payments as $payment)
                                    <tr class="align-top">
                                        <td class="px-6 py-4 text-slate-600 font-mono">
                                            {{ $payment->paid_on->translatedFormat('d M Y') }}</td>
                                        <td class="px-6 py-4 text-slate-700 whitespace-nowrap font-mono">
                                            {{ $payment->currency }}
                                            {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                        </td>
                                        <td class="px-6 py-4 text-slate-500">{{ $payment->methodLabel() }}</td>
                                        <td class="px-6 py-4 text-slate-500">{{ $payment->reference ?: '-' }}</td>
                                        <td class="px-6 py-4 text-slate-500">
                                            {{ $payment->recorder?->name ?? '-' }}
                                            @if ($payment->notes)
                                                <p class="text-xs text-slate-500 mt-1">{{ $payment->notes }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- RIGHT: who is billed, what for, and what the reminders did --}}
        <div class="space-y-6">

            <x-card>
                <h2 class="font-semibold text-slate-800 mb-4">Client</h2>

                @if ($billingClient)
                    <p class="font-semibold text-slate-700">{{ $billingClient->company_name }}</p>

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
                                    <dt class="text-xs text-slate-500">Kontak Billing</dt>
                                    <dd class="text-slate-600">{{ $contactName }}</dd>
                                </div>
                            @endif
                            @if (filled($contactEmail))
                                <div>
                                    <dt class="text-xs text-slate-500">Email</dt>
                                    <dd class="text-slate-600 break-all">{{ $contactEmail }}</dd>
                                </div>
                            @endif
                            @if (filled($contactPhone))
                                <div>
                                    <dt class="text-xs text-slate-500">Telepon</dt>
                                    <dd class="text-slate-600">{{ $contactPhone }}</dd>
                                </div>
                            @endif
                        </dl>
                    @else
                        <p class="text-sm text-slate-500 mt-2">Belum ada kontak penagihan.</p>
                    @endif
                @else
                    <p class="text-sm text-slate-500">Client tidak tercatat.</p>
                @endif

                <div class="mt-4 border-t border-slate-100 pt-4">
                    <p class="text-xs text-slate-500 mb-1">Project</p>
                    @if ($invoice->project)
                        <a href="{{ route('pages.projects.show', $invoice->project) }}"
                            class="inline-flex items-center min-h-11 text-sm text-brand-600 hover:underline">{{ $invoice->project->name }}</a>
                    @else
                        <p class="text-sm text-slate-500">Invoice langganan, tanpa project.</p>
                    @endif
                </div>
            </x-card>

            @if ($invoice->subscription)
                <x-card>
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <h2 class="font-semibold text-slate-800">Langganan</h2>
                        <a href="{{ route('pages.finance.subscriptions.edit', $invoice->subscription) }}"
                            class="inline-flex items-center px-1 min-h-11 text-xs text-brand-600 hover:underline whitespace-nowrap">Kelola</a>
                    </div>

                    <p class="font-semibold text-slate-700">{{ $invoice->subscription->name }}</p>

                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Jenis Layanan</dt>
                            <dd class="text-slate-600">
                                {{ ucfirst($invoice->subscription->service_type) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Siklus</dt>
                            <dd class="text-slate-600">
                                {{ $invoice->subscription->isYearly() ? 'Tahunan' : 'Bulanan' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Status</dt>
                            <dd><x-badge :color="$invoice->subscription->isActive() ? 'emerald' : 'slate'">
                                    {{ ucfirst($invoice->subscription->status) }}</x-badge></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Perpanjangan Berikutnya</dt>
                            <dd class="text-slate-600 font-mono">
                                {{ $invoice->subscription->next_renewal_date?->translatedFormat('d M Y') ?? '-' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Reminder Otomatis</dt>
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
                                        class="w-2.5 h-2.5 rounded-full mt-2 {{ $dotClasses[$step['tone']] ?? $dotClasses['slate'] }}"></span>
                                    @unless ($loop->last)
                                        <span class="flex-1 w-px bg-slate-200 my-1"></span>
                                    @endunless
                                </div>

                                <div class="flex-1 pb-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-semibold text-slate-700">{{ $step['label'] }}</p>
                                        <x-badge :color="$step['tone']">{{ $step['state_label'] }}</x-badge>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1 font-mono">
                                        {{ $step['due_on']->translatedFormat('d M Y') }}
                                    </p>

                                    @if ($step['log']?->recipient)
                                        <p class="text-xs text-slate-500 break-all">
                                            {{ $step['log']->recipient }}</p>
                                    @endif

                                    {{-- Sanitised at write time, escaped and truncated here. --}}
                                    @if ($step['state'] === 'failed' && $step['log']?->error_message)
                                        <p class="text-xs text-red-700 mt-1">
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
