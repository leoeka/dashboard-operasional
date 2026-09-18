{{-- FILTERS — status keeps the original four, purpose is new and separates the
     project invoices from the recurring ones now sharing the table. --}}
<x-card padding="p-4" class="mb-5">
    <div class="flex flex-wrap gap-2 mb-3">
        @foreach ([null => 'Semua', 'unpaid' => 'Belum Dibayar', 'overdue' => 'Terlambat', 'paid' => 'Lunas'] as $value => $label)
            <a href="{{ route('pages.finance', array_filter(['tab' => 'invoices', 'status' => $value, 'purpose' => request('purpose')])) }}"
                class="text-xs px-3 py-1.5 rounded-lg {{ request('status') === $value || (!$value && !request('status')) ? 'grad-blue text-white' : 'bg-slate-100 text-slate-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach ([null => 'Semua Jenis', 'project_payment' => 'Pembayaran Project', 'renewal' => 'Perpanjangan'] as $value => $label)
            <a href="{{ route('pages.finance', array_filter(['tab' => 'invoices', 'purpose' => $value, 'status' => request('status')])) }}"
                class="text-xs px-3 py-1.5 rounded-lg border {{ request('purpose') === $value || (!$value && !request('purpose')) ? 'border-brand-500 text-brand-500 bg-blue-50' : 'border-slate-200 text-slate-500' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</x-card>

<x-card padding="p-0">
    @if ($invoices->isEmpty())
        <x-empty-state icon="bx-receipt" title="Belum ada invoice"
            description="Invoice project dibuat manual, sedangkan invoice perpanjangan terbit otomatis mengikuti jadwal langganan." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[980px]">
                <thead>
                    <tr class="text-left text-slate-400 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">No. Invoice</th>
                        <th class="px-6 py-3 font-medium">Client</th>
                        <th class="px-6 py-3 font-medium">Untuk</th>
                        <th class="px-6 py-3 font-medium">Jenis</th>
                        <th class="px-6 py-3 font-medium">Terbit</th>
                        <th class="px-6 py-3 font-medium">Jatuh Tempo</th>
                        <th class="px-6 py-3 font-medium">Nominal</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($invoices as $invoice)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium text-slate-700">{{ $invoice->invoice_number }}</td>

                            <td class="px-6 py-4 text-slate-600">
                                {{ $invoice->billableClient()?->company_name ?? '—' }}
                            </td>

                            <td class="px-6 py-4">
                                @if ($invoice->isRenewal())
                                    <p class="text-slate-700">{{ $invoice->subscription?->name ?? 'Layanan' }}</p>
                                    @if ($invoice->billing_period_start && $invoice->billing_period_end)
                                        <p class="text-xs text-slate-400">
                                            {{ $invoice->billing_period_start->translatedFormat('d M Y') }} –
                                            {{ $invoice->billing_period_end->translatedFormat('d M Y') }}
                                        </p>
                                    @endif
                                @else
                                    <p class="text-slate-700">{{ $invoice->project?->name ?? '—' }}</p>
                                    <p class="text-xs text-slate-400">{{ $invoice->typeLabel() }}</p>
                                @endif
                            </td>

                            <td class="px-6 py-4">
                                @if ($invoice->isRenewal())
                                    <x-badge color="purple">Perpanjangan</x-badge>
                                @else
                                    <x-badge color="blue">Project</x-badge>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-slate-500">
                                {{ $invoice->issue_date?->translatedFormat('d M Y') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $invoice->due_date->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-6 py-4 text-slate-700">
                                Rp{{ number_format((float) $invoice->amount, 0, ',', '.') }}</td>

                            <td class="px-6 py-4">
                                <x-badge :color="$invoice->statusColor()">{{ $invoice->statusLabel() }}</x-badge>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-3 text-slate-400">
                                    <a href="{{ route('pages.finance.invoices.show', $invoice) }}"
                                        class="hover:text-brand-500" title="Detail">
                                        <i class='bx bx-show text-lg'></i>
                                    </a>

                                    @if ($invoice->status !== 'paid')
                                        {{-- Manual reminder stays a project-invoice action. A renewal
                                             follows its H-30/H-7/H-3 schedule and records each
                                             threshold; sending one by hand would sidestep that. --}}
                                        @unless ($invoice->isRenewal())
                                            <form method="POST" action="{{ route('pages.finance.remind', $invoice) }}">
                                                @csrf
                                                <button type="submit" class="hover:text-brand-500"
                                                    title="Kirim Reminder">
                                                    <i class='bx bx-send text-lg'></i>
                                                </button>
                                            </form>
                                        @endunless

                                        <form method="POST" action="{{ route('pages.finance.paid', $invoice) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="hover:text-emerald-500"
                                                title="Tandai Lunas">
                                                <i class='bx bx-check-circle text-lg'></i>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Deleting a settled invoice or a renewal would remove a
                                         finance record and the payments cascading from it, so the
                                         action is only offered where it always was. --}}
                                    @if ($invoice->status !== 'paid' && !$invoice->isRenewal())
                                        <form method="POST" action="{{ route('pages.finance.destroy', $invoice) }}"
                                            onsubmit="return confirm('Hapus invoice ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="hover:text-red-500" title="Hapus">
                                                <i class='bx bx-trash text-lg'></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>

<div class="mt-4">{{ $invoices->links() }}</div>
