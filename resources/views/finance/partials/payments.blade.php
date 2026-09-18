<x-card padding="p-4" class="mb-5">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-6 gap-2">
        <input type="hidden" name="tab" value="payments">

        <input type="search" name="q" value="{{ request('q') }}" placeholder="No. invoice…"
            class="col-span-2 md:col-span-1 bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">

        <select name="client" class="bg-slate-50 text-slate-600 rounded-lg px-3 py-2 text-sm outline-none">
            <option value="">Semua client</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected(request('client') == $client->id)>{{ $client->company_name }}</option>
            @endforeach
        </select>

        <select name="method" class="bg-slate-50 text-slate-600 rounded-lg px-3 py-2 text-sm outline-none">
            <option value="">Semua metode</option>
            @foreach (\App\Models\Payment::METHODS as $method)
                <option value="{{ $method }}" @selected(request('method') === $method)>
                    {{ ucwords(str_replace('_', ' ', $method)) }}</option>
            @endforeach
        </select>

        <input type="date" name="from" value="{{ request('from') }}" title="Dari tanggal"
            class="bg-slate-50 text-slate-600 rounded-lg px-3 py-2 text-sm outline-none">
        <input type="date" name="to" value="{{ request('to') }}" title="Sampai tanggal"
            class="bg-slate-50 text-slate-600 rounded-lg px-3 py-2 text-sm outline-none">

        <div class="flex gap-2">
            <button type="submit"
                class="grad-blue text-white text-sm px-4 py-2 rounded-lg hover:opacity-90 transition flex-1">Filter</button>
            <a href="{{ route('pages.finance', ['tab' => 'payments']) }}"
                class="bg-slate-100 text-slate-500 text-sm px-3 py-2 rounded-lg hover:bg-slate-200 transition"
                title="Reset">
                <i class='bx bx-reset'></i>
            </a>
        </div>
    </form>
</x-card>

<x-card padding="p-0">
    @if ($payments->isEmpty())
        <x-empty-state icon="bx-wallet" title="Belum ada pembayaran tercatat"
            description="Pembayaran muncul di sini setelah sebuah invoice ditandai lunas." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[900px]">
                <thead>
                    <tr class="text-left text-slate-400 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">Tanggal</th>
                        <th class="px-6 py-3 font-medium">Invoice</th>
                        <th class="px-6 py-3 font-medium">Client</th>
                        <th class="px-6 py-3 font-medium">Untuk</th>
                        <th class="px-6 py-3 font-medium">Nominal</th>
                        <th class="px-6 py-3 font-medium">Metode</th>
                        <th class="px-6 py-3 font-medium">Referensi</th>
                        <th class="px-6 py-3 font-medium">Dicatat Oleh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($payments as $payment)
                        @php($invoice = $payment->invoice)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-slate-600">{{ $payment->paid_on->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-6 py-4">
                                @if ($invoice)
                                    <a href="{{ route('pages.finance.invoices.show', $invoice) }}"
                                        class="font-medium text-brand-500 hover:underline">{{ $invoice->invoice_number }}</a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                {{ $invoice?->billableClient()?->company_name ?? '—' }}</td>
                            <td class="px-6 py-4 text-slate-500">
                                @if ($invoice?->isRenewal())
                                    {{ $invoice->subscription?->name ?? 'Perpanjangan' }}
                                @else
                                    {{ $invoice?->project?->name ?? '—' }}
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-700 whitespace-nowrap">
                                {{ $payment->currency }} {{ number_format((float) $payment->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $payment->methodLabel() }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $payment->reference ?: '—' }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $payment->recorder?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>

<div class="mt-4">{{ $payments->links() }}</div>
