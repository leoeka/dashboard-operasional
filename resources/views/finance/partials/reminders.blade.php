<x-card padding="p-4" class="mb-5">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-5 gap-2">
        <input type="hidden" name="tab" value="reminders">

        <select name="status" class="bg-slate-50 text-slate-600 rounded-lg px-3 py-2 text-sm outline-none">
            <option value="">Semua status</option>
            @foreach ([\App\Models\BillingReminderLog::PENDING => 'Menunggu', \App\Models\BillingReminderLog::SENT => 'Terkirim', \App\Models\BillingReminderLog::FAILED => 'Gagal'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        {{-- Thresholds offered are the ones actually recorded, so the list follows
             ReminderPolicy through the data instead of repeating [30, 7, 3] here
             and drifting the day the schedule changes. --}}
        <select name="threshold" class="bg-slate-50 text-slate-600 rounded-lg px-3 py-2 text-sm outline-none">
            <option value="">Semua threshold</option>
            @foreach ($thresholdOptions as $threshold)
                <option value="{{ $threshold }}" @selected(request('threshold') == $threshold)>H-{{ $threshold }}
                </option>
            @endforeach
        </select>

        <select name="client" class="bg-slate-50 text-slate-600 rounded-lg px-3 py-2 text-sm outline-none">
            <option value="">Semua client</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected(request('client') == $client->id)>{{ $client->company_name }}</option>
            @endforeach
        </select>

        <div class="flex gap-2 col-span-2 md:col-span-2">
            <button type="submit"
                class="grad-blue text-white text-sm px-4 py-2 rounded-lg hover:opacity-90 transition">Filter</button>
            <a href="{{ route('pages.finance', ['tab' => 'reminders']) }}"
                class="bg-slate-100 text-slate-500 text-sm px-3 py-2 rounded-lg hover:bg-slate-200 transition"
                title="Reset">
                <i class='bx bx-reset'></i>
            </a>
        </div>
    </form>
</x-card>

<x-card padding="p-0">
    @if ($reminderLogs->isEmpty())
        <x-empty-state icon="bx-bell" title="Belum ada reminder terkirim"
            description="Reminder perpanjangan tercatat di sini setiap kali penjadwal harian mengklaim sebuah threshold." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[1040px]">
                <thead>
                    <tr class="text-left text-slate-400 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">Client</th>
                        <th class="px-6 py-3 font-medium">Invoice</th>
                        <th class="px-6 py-3 font-medium">Layanan</th>
                        <th class="px-6 py-3 font-medium">Threshold</th>
                        <th class="px-6 py-3 font-medium">Dijadwalkan</th>
                        <th class="px-6 py-3 font-medium">Penerima</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Percobaan</th>
                        <th class="px-6 py-3 font-medium">Terkirim</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($reminderLogs as $log)
                        @php($invoice = $log->invoice)
                        <tr class="hover:bg-slate-50 align-top">
                            <td class="px-6 py-4 text-slate-600">
                                {{ $invoice?->billableClient()?->company_name ?? '—' }}</td>

                            <td class="px-6 py-4">
                                @if ($invoice)
                                    <a href="{{ route('pages.finance.invoices.show', $invoice) }}"
                                        class="font-medium text-brand-500 hover:underline">{{ $invoice->invoice_number }}</a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-slate-500">{{ $log->subscription?->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-slate-700">{{ $log->label() }}</td>
                            <td class="px-6 py-4 text-slate-500">
                                {{ $log->scheduled_for?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $log->recipient ?: '—' }}</td>

                            <td class="px-6 py-4">
                                @if ($log->wasSent())
                                    <x-badge color="emerald">Terkirim</x-badge>
                                @elseif ($log->status === \App\Models\BillingReminderLog::FAILED)
                                    <x-badge color="red">Gagal</x-badge>
                                    {{-- Escaped like any other text. The message is sanitised where
                                         it is written, and truncated here because a reader needs the
                                         reason, not a stack trace. --}}
                                    @if ($log->error_message)
                                        <p class="text-xs text-slate-400 mt-1 max-w-xs">
                                            {{ \Illuminate\Support\Str::limit($log->error_message, 120) }}</p>
                                    @endif
                                @else
                                    <x-badge color="amber">Menunggu</x-badge>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-slate-500">
                                {{ $log->attempts }}/{{ \App\Models\BillingReminderLog::MAX_ATTEMPTS }}</td>
                            {{-- Read in the billing timezone and labelled with it. The
                                 column is stored in UTC and is not touched here. --}}
                            <td class="px-6 py-4 text-slate-500 whitespace-nowrap">
                                {{ $log->sentAtForDisplay() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>

<div class="mt-4">{{ $reminderLogs->links() }}</div>
