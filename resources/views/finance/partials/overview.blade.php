{{-- The working view: what is about to need money collected for it. --}}
<x-card padding="p-0">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold text-slate-800">Perpanjangan Mendatang</h2>
            <p class="text-xs text-slate-500 mt-1">Layanan yang akan diperpanjang dalam 45 hari ke depan.</p>
        </div>
        <a href="{{ route('pages.finance', ['tab' => 'subscriptions']) }}"
            class="inline-flex items-center px-1 min-h-11 text-xs text-brand-600 hover:underline whitespace-nowrap">Lihat semua</a>
    </div>

    @if ($renewals->isEmpty())
        <x-empty-state icon="bx-calendar-check" title="Belum ada perpanjangan mendekat"
            description="Langganan aktif akan muncul di sini ketika tanggal perpanjangannya sudah dekat." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[820px]">
                <thead>
                    <tr class="text-left text-xs text-slate-600 bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-3 font-semibold">Client</th>
                        <th class="px-6 py-3 font-semibold">Layanan</th>
                        <th class="px-6 py-3 font-semibold">Siklus</th>
                        <th class="px-6 py-3 font-semibold text-right">Nominal</th>
                        <th class="px-6 py-3 font-semibold text-right">Perpanjangan</th>
                        <th class="px-6 py-3 font-semibold text-right">Hitung Mundur</th>
                        <th class="px-6 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($renewals as $row)
                        @php($s = $row['model'])
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-slate-700">{{ $s->client?->company_name ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <p class="text-slate-700">{{ $s->name }}</p>
                                <p class="text-xs text-slate-500">{{ ucfirst($s->service_type) }}@if ($s->project)
                                        · {{ $s->project->name }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $s->isYearly() ? 'Tahunan' : 'Bulanan' }}</td>
                            <td class="px-6 py-4 text-slate-700 font-mono text-right">Rp{{ number_format((float) $s->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-slate-500 font-mono text-right">
                                {{ $s->next_renewal_date->translatedFormat('d M Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <x-badge :color="$row['tone']">{{ $row['countdown'] }}</x-badge>
                            </td>

                            {{-- Only a link to the subscription. Invoices are issued by the
                                 daily renewal run, which owns the numbering and the
                                 idempotency; a button here would be a second way to create
                                 one and could duplicate a period. --}}
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('pages.finance.subscriptions.edit', $s) }}"
                                    class="text-slate-500 hover:text-brand-600 inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" title="Kelola langganan" aria-label="Kelola langganan">
                                    <i class='bx bx-edit-alt text-lg'></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
