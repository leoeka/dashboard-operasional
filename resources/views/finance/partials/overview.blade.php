{{-- The working view: what is about to need money collected for it. --}}
<x-card padding="p-0">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold text-slate-800">Perpanjangan Mendatang</h2>
            <p class="text-xs text-slate-400 mt-0.5">Layanan yang akan diperpanjang dalam 45 hari ke depan.</p>
        </div>
        <a href="{{ route('pages.finance', ['tab' => 'subscriptions']) }}"
            class="text-xs text-brand-500 hover:underline whitespace-nowrap">Lihat semua</a>
    </div>

    @if ($renewals->isEmpty())
        <x-empty-state icon="bx-calendar-check" title="Belum ada perpanjangan mendekat"
            description="Langganan aktif akan muncul di sini ketika tanggal perpanjangannya sudah dekat." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[820px]">
                <thead>
                    <tr class="text-left text-slate-400 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">Client</th>
                        <th class="px-6 py-3 font-medium">Layanan</th>
                        <th class="px-6 py-3 font-medium">Siklus</th>
                        <th class="px-6 py-3 font-medium">Nominal</th>
                        <th class="px-6 py-3 font-medium">Perpanjangan</th>
                        <th class="px-6 py-3 font-medium text-right">Hitung Mundur</th>
                        <th class="px-6 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($renewals as $row)
                        @php($s = $row['model'])
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-slate-700">{{ $s->client?->company_name ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <p class="text-slate-700">{{ $s->name }}</p>
                                <p class="text-xs text-slate-400">{{ ucfirst($s->service_type) }}@if ($s->project)
                                        · {{ $s->project->name }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $s->isYearly() ? 'Tahunan' : 'Bulanan' }}</td>
                            <td class="px-6 py-4 text-slate-700">Rp{{ number_format((float) $s->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-slate-500">
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
                                    class="text-slate-400 hover:text-brand-500" title="Kelola langganan">
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
