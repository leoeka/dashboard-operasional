{{-- FILTERS — status keeps the original four, purpose is new and separates the
     project invoices from the recurring ones now sharing the table. --}}
<x-card padding="p-4" class="mb-4">
    <div class="flex flex-wrap gap-2 mb-3">
        @foreach ([null => 'Semua', 'unpaid' => 'Belum Dibayar', 'overdue' => 'Terlambat', 'paid' => 'Lunas'] as $value => $label)
            <a href="{{ route('pages.finance', array_filter(['tab' => 'invoices', 'status' => $value, 'purpose' => request('purpose')])) }}"
                class="min-h-11 text-xs px-3 py-2 rounded-md {{ request('status') === $value || (!$value && !request('status')) ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach ([null => 'Semua Jenis', 'project_payment' => 'Pembayaran Project', 'renewal' => 'Perpanjangan'] as $value => $label)
            <a href="{{ route('pages.finance', array_filter(['tab' => 'invoices', 'purpose' => $value, 'status' => request('status')])) }}"
                class="min-h-11 text-xs px-3 py-2 rounded-md border {{ request('purpose') === $value || (!$value && !request('purpose')) ? 'border-brand-500 text-brand-600 bg-brand-50' : 'border-slate-200 text-slate-500' }}">
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
                    <tr class="text-left text-xs text-slate-600 bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-3 font-semibold">No. Invoice</th>
                        <th class="px-6 py-3 font-semibold">Client</th>
                        <th class="px-6 py-3 font-semibold">Untuk</th>
                        <th class="px-6 py-3 font-semibold">Jenis</th>
                        <th class="px-6 py-3 font-semibold text-right">Terbit</th>
                        <th class="px-6 py-3 font-semibold text-right">Jatuh Tempo</th>
                        <th class="px-6 py-3 font-semibold text-right">Nominal</th>
                        <th class="px-6 py-3 font-semibold">Status</th>
                        <th class="px-6 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($invoices as $invoice)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-semibold text-slate-700">{{ $invoice->invoice_number }}</td>

                            <td class="px-6 py-4 text-slate-600">
                                {{ $invoice->billableClient()?->company_name ?? '-' }}
                            </td>

                            <td class="px-6 py-4">
                                @if ($invoice->isRenewal())
                                    <p class="text-slate-700">{{ $invoice->subscription?->name ?? 'Layanan' }}</p>
                                    @if ($invoice->billing_period_start && $invoice->billing_period_end)
                                        <p class="text-xs text-slate-500 font-mono">
                                            {{ $invoice->billing_period_start->translatedFormat('d M Y') }} –
                                            {{ $invoice->billing_period_end->translatedFormat('d M Y') }}
                                        </p>
                                    @endif
                                @else
                                    <p class="text-slate-700">{{ $invoice->project?->name ?? '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ $invoice->typeLabel() }}</p>
                                @endif
                            </td>

                            <td class="px-6 py-4">
                                @if ($invoice->isRenewal())
                                    <x-badge color="purple">Perpanjangan</x-badge>
                                @else
                                    <x-badge color="blue">Project</x-badge>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-slate-500 font-mono text-right">
                                {{ $invoice->issue_date?->translatedFormat('d M Y') ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-slate-500 font-mono text-right">{{ $invoice->due_date->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-6 py-4 text-slate-700 font-mono text-right">
                                Rp{{ number_format((float) $invoice->amount, 0, ',', '.') }}</td>

                            <td class="px-6 py-4">
                                <x-badge :color="$invoice->statusColor()">{{ $invoice->statusLabel() }}</x-badge>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-3 text-slate-500">
                                    <a href="{{ route('pages.finance.invoices.show', $invoice) }}"
                                        class="hover:text-brand-600 inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" title="Detail" aria-label="Detail">
                                        <i class='bx bx-show text-lg'></i>
                                    </a>

                                    {{-- Deleting a renewal would remove a billing record and the
                                         reminder logs cascading from it, so the action is only
                                         offered for an unpaid project invoice. --}}
                                    @if ($invoice->status !== 'paid' && !$invoice->isRenewal())
                                        <form method="POST" action="{{ route('pages.finance.destroy', $invoice) }}"
                                            onsubmit="return confirm('Hapus invoice ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="hover:text-red-700 inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" title="Hapus" aria-label="Hapus">
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
