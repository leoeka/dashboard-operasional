{{-- FILTERS: Filter Jenis Layanan (Kiri) dan Filter Status Pembayaran (Kanan) --}}
<x-card padding="p-4" class="mb-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        {{-- Sisi Kiri: Filter Jenis / Siklus Layanan --}}
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wider">Jenis Layanan</label>
            <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-50">
                @php
                    $activeCycle = request('cycle');
                @endphp
                <a href="{{ route('pages.finance', array_filter(['tab' => 'invoices', 'cycle' => null, 'status' => request('status')])) }}"
                    class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-md transition inline-flex items-center {{ empty($activeCycle) ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    Semua
                </a>
                <a href="{{ route('pages.finance', array_filter(['tab' => 'invoices', 'cycle' => 'monthly', 'status' => request('status')])) }}"
                    class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-md transition inline-flex items-center {{ $activeCycle === 'monthly' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    Bulanan
                </a>
                <a href="{{ route('pages.finance', array_filter(['tab' => 'invoices', 'cycle' => 'yearly', 'status' => request('status')])) }}"
                    class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-md transition inline-flex items-center {{ $activeCycle === 'yearly' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    Tahunan
                </a>
            </div>
        </div>

        {{-- Sisi Kanan: Filter Status Pembayaran --}}
        <div class="sm:text-right">
            <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wider">Status Pembayaran</label>
            <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-50">
                @php
                    $activeStatus = request('status');
                @endphp
                <a href="{{ route('pages.finance', array_filter(['tab' => 'invoices', 'status' => null, 'cycle' => request('cycle')])) }}"
                    class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-md transition inline-flex items-center {{ empty($activeStatus) ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    Semua
                </a>
                <a href="{{ route('pages.finance', array_filter(['tab' => 'invoices', 'status' => 'unpaid', 'cycle' => request('cycle')])) }}"
                    class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-md transition inline-flex items-center {{ $activeStatus === 'unpaid' ? 'bg-white text-amber-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    Belum Dibayar
                </a>
                <a href="{{ route('pages.finance', array_filter(['tab' => 'invoices', 'status' => 'paid', 'cycle' => request('cycle')])) }}"
                    class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-md transition inline-flex items-center {{ $activeStatus === 'paid' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    Sudah Dibayar
                </a>
            </div>
        </div>
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
                                    @if ($invoice->subscription?->billing_cycle === 'monthly')
                                        <x-badge color="purple">Bulanan</x-badge>
                                    @else
                                        <x-badge color="blue">Tahunan</x-badge>
                                    @endif
                                @else
                                    <x-badge color="slate">Project</x-badge>
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
