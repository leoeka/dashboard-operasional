<x-card padding="p-4" class="mb-4">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-5 gap-2">
        <input type="hidden" name="tab" value="reminders">

        <select name="status" class="min-h-11 bg-slate-50 text-slate-600 rounded-md px-3 py-2 text-sm outline-none">
            <option value="">Semua status</option>
            @foreach ([\App\Models\BillingReminderLog::PENDING => 'Menunggu', \App\Models\BillingReminderLog::SENT => 'Terkirim', \App\Models\BillingReminderLog::FAILED => 'Gagal'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        {{-- Thresholds offered are the ones actually recorded, so the list follows
             ReminderPolicy through the data instead of repeating [30, 7, 3] here
             and drifting the day the schedule changes. --}}
        <select name="threshold" class="min-h-11 bg-slate-50 text-slate-600 rounded-md px-3 py-2 text-sm outline-none">
            <option value="">Semua threshold</option>
            @foreach ($thresholdOptions as $threshold)
                <option value="{{ $threshold }}" @selected(request('threshold') == $threshold)>H-{{ $threshold }}
                </option>
            @endforeach
        </select>

        <select name="client" class="min-h-11 bg-slate-50 text-slate-600 rounded-md px-3 py-2 text-sm outline-none">
            <option value="">Semua client</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected(request('client') == $client->id)>{{ $client->company_name }}</option>
            @endforeach
        </select>

        <div class="flex gap-2 col-span-2 md:col-span-2">
            <button type="submit"
                class="min-h-11 bg-brand-600 text-white text-sm px-4 py-2 rounded-md hover:bg-brand-700 transition">Terapkan filter</button>
            <a href="{{ route('pages.finance', ['tab' => 'reminders']) }}"
                class="min-h-11 bg-slate-100 text-slate-600 text-sm px-4 py-2 rounded-md hover:bg-slate-200 transition"
                aria-label="Bersihkan filter">
                Bersihkan
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
            <table class="w-full text-sm min-w-[1120px]">
                <thead>
                    <tr class="text-left text-xs text-slate-600 bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-3 font-semibold">Client</th>
                        <th class="px-6 py-3 font-semibold">Invoice</th>
                        <th class="px-6 py-3 font-semibold">Layanan</th>
                        <th class="px-6 py-3 font-semibold">Threshold</th>
                        <th class="px-6 py-3 font-semibold text-right">Dijadwalkan</th>
                        <th class="px-6 py-3 font-semibold">Penerima</th>
                        <th class="px-6 py-3 font-semibold">Status</th>
                        <th class="px-6 py-3 font-semibold text-right">Percobaan</th>
                        <th class="px-6 py-3 font-semibold text-right">Terkirim</th>
                        <th class="px-6 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($reminderLogs as $log)
                        @php($invoice = $log->invoice)
                        <tr class="hover:bg-slate-50 align-top">
                            <td class="px-6 py-4 text-slate-600">
                                {{ $invoice?->billableClient()?->company_name ?? '-' }}</td>

                            <td class="px-6 py-4">
                                @if ($invoice)
                                    <a href="{{ route('pages.finance.invoices.show', $invoice) }}"
                                        class="font-semibold text-brand-600 hover:underline">{{ $invoice->invoice_number }}</a>
                                @else
                                    <span class="text-slate-500">Tanpa invoice</span>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-slate-500">{{ $log->subscription?->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-slate-700">{{ $log->label() }}</td>
                            <td class="px-6 py-4 text-slate-500 font-mono text-right">
                                {{ $log->scheduled_for?->translatedFormat('d M Y') ?? '-' }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $log->recipient ?: '-' }}</td>

                            <td class="px-6 py-4">
                                @if ($log->wasSent())
                                    <x-badge color="emerald">Terkirim</x-badge>
                                @elseif ($log->status === \App\Models\BillingReminderLog::FAILED)
                                    <x-badge color="red">Gagal</x-badge>
                                    {{-- Escaped like any other text. The message is sanitised where
                                         it is written, and truncated here because a reader needs the
                                         reason, not a stack trace. --}}
                                    @if ($log->error_message)
                                        <p class="text-xs text-slate-500 mt-1 max-w-xs">
                                            {{ \Illuminate\Support\Str::limit($log->error_message, 120) }}</p>
                                    @endif
                                @else
                                    <x-badge color="amber">Menunggu</x-badge>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-slate-500 font-mono text-right">
                                {{ $log->attempts }}/{{ \App\Models\BillingReminderLog::MAX_ATTEMPTS }}</td>
                            {{-- Read in the billing timezone and labelled with it. The
                                 column is stored in UTC and is not touched here. --}}
                            <td class="px-6 py-4 text-slate-500 whitespace-nowrap font-mono text-right">
                                {{ $log->sentAtForDisplay() ?? '-' }}</td>
                            <td class="px-6 py-4">
                                @if ($log->canRetry())
                                    <form method="POST" action="{{ route('pages.finance.reminders.retry', $log) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-1 min-h-11 text-sm text-brand-600 hover:underline whitespace-nowrap">Kirim ulang</button>
                                    </form>
                                @elseif ($log->status === \App\Models\BillingReminderLog::FAILED)
                                    <span class="text-xs text-slate-500">Batas percobaan tercapai</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>

<div class="mt-4">{{ $reminderLogs->links() }}</div>
