{{-- FILTERS --}}
<x-card padding="p-4" class="mb-4">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-6 gap-2">
        <input type="hidden" name="tab" value="subscriptions">

        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari client / layanan…"
            class="min-h-11 col-span-2 bg-slate-50 text-slate-700 rounded-md px-3 py-2 text-sm outline-none">

        <select name="status" class="min-h-11 bg-slate-50 text-slate-600 rounded-md px-3 py-2 text-sm outline-none">
            <option value="">Semua status</option>
            @foreach (['active' => 'Aktif', 'paused' => 'Dijeda', 'cancelled' => 'Dibatalkan', 'expired' => 'Berakhir'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="service_type" class="min-h-11 bg-slate-50 text-slate-600 rounded-md px-3 py-2 text-sm outline-none">
            <option value="">Semua jenis</option>
            @foreach (\App\Models\FinanceSubscription::SERVICE_TYPES as $type)
                <option value="{{ $type }}" @selected(request('service_type') === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>

        <select name="cycle" class="min-h-11 bg-slate-50 text-slate-600 rounded-md px-3 py-2 text-sm outline-none">
            <option value="">Semua siklus</option>
            <option value="yearly" @selected(request('cycle') === 'yearly')>Tahunan</option>
            <option value="monthly" @selected(request('cycle') === 'monthly')>Bulanan</option>
        </select>

        <div class="flex gap-2 min-w-0">
            <select name="client" class="min-h-11 min-w-0 flex-1 bg-slate-50 text-slate-600 rounded-md px-3 py-2 text-sm outline-none">
                <option value="">Semua client</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(request('client') == $client->id)>
                        {{ $client->company_name }}</option>
                @endforeach
            </select>
            <button type="submit" aria-label="Cari" title="Cari"
                class="min-h-11 inline-flex items-center justify-center bg-brand-600 text-white text-sm px-3 rounded-md hover:bg-brand-700 transition flex-shrink-0">
                <i class='bx bx-search'></i>
            </button>
        </div>
    </form>

    <div class="flex flex-wrap gap-2 mt-3">
        <a href="{{ route('pages.finance', ['tab' => 'subscriptions']) }}"
            class="min-h-11 text-xs px-3 py-2 rounded-md {{ !request('due') && !request('status') ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600' }}">Semua</a>
        <a href="{{ route('pages.finance', ['tab' => 'subscriptions', 'due' => 'upcoming']) }}"
            class="min-h-11 text-xs px-3 py-2 rounded-md {{ request('due') === 'upcoming' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600' }}">Perpanjangan
            30 hari</a>
        <a href="{{ route('pages.finance', ['tab' => 'subscriptions', 'status' => 'active']) }}"
            class="min-h-11 text-xs px-3 py-2 rounded-md {{ request('status') === 'active' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600' }}">Aktif</a>
    </div>
</x-card>

<x-card padding="p-0">
    @if ($subscriptions->isEmpty())
        <x-empty-state icon="bx-repost" title="Belum ada langganan"
            description="Tambahkan layanan berulang seperti hosting, domain, maintenance atau SEO agar invoice dan reminder-nya berjalan otomatis." />
    @else
        <p class="px-6 py-3 text-xs text-slate-600 border-b border-slate-100 bg-slate-50">
            Pembayaran ditangani di luar sistem, jadi periode bergulir mengikuti tanggal, bukan
            mengikuti pembayaran. Selama langganan aktif dan belum melewati tanggal berakhir,
            invoice dan reminder terus berjalan sendiri; tidak ada yang perlu digeser tiap periode.
        </p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[980px]">
                <thead>
                    <tr class="text-left text-xs text-slate-600 bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-3 font-semibold">Client</th>
                        <th class="px-6 py-3 font-semibold">Layanan</th>
                        <th class="px-6 py-3 font-semibold">Siklus</th>
                        <th class="px-6 py-3 font-semibold text-right">Nominal</th>
                        <th class="px-6 py-3 font-semibold text-right">Perpanjangan</th>
                        <th class="px-6 py-3 font-semibold">Pengingat</th>
                        <th class="px-6 py-3 font-semibold">Status</th>
                        <th class="px-6 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($subscriptions as $s)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-slate-700">{{ $s->client?->company_name ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <p class="text-slate-700">{{ $s->name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ ucfirst($s->service_type) }}@if ($s->project)
                                        · {{ $s->project->name }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $s->isYearly() ? 'Tahunan' : 'Bulanan' }}</td>
                            <td class="px-6 py-4 text-slate-700 font-mono text-right">Rp{{ number_format((float) $s->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 font-mono text-right">
                                <p class="text-slate-600">{{ $s->next_renewal_date->translatedFormat('d M Y') }}</p>
                                @if ($s->status === 'active')
                                    <x-badge :color="$s->countdown_tone" class="mt-1 inline-block">{{ $s->countdown }}</x-badge>
                                @endif
                                @if ($s->ends_on)
                                    <p class="text-xs text-slate-500 mt-1">Berakhir {{ $s->ends_on->translatedFormat('d M Y') }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                {{-- Only the reminder is a per-subscription choice; an
                                     invoice always follows the calendar. Spelled out
                                     rather than a code, so the colour is not the only
                                     thing carrying the difference. --}}
                                @if ($s->auto_reminder)
                                    <span class="text-xs px-2 py-1 rounded-md bg-emerald-50 text-emerald-700">Aktif</span>
                                @else
                                    <span class="text-xs px-2 py-1 rounded-md bg-slate-100 text-slate-600">Mati</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $tone = match ($s->status) {
                                        'active' => 'emerald',
                                        'paused' => 'amber',
                                        'cancelled' => 'red',
                                        default => 'slate',
                                    };
                                    $statusLabel = match ($s->status) {
                                        'active' => 'Aktif',
                                        'paused' => 'Dijeda',
                                        'cancelled' => 'Dibatalkan',
                                        default => 'Berakhir',
                                    };
                                @endphp
                                <x-badge :color="$tone">{{ $statusLabel }}</x-badge>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-3 text-slate-500">
                                    <a href="{{ route('pages.finance.subscriptions.edit', $s) }}"
                                        class="hover:text-brand-600 inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" title="Edit" aria-label="Edit">
                                        <i class='bx bx-edit-alt text-lg'></i>
                                    </a>

                                    @if ($s->status === 'active')
                                        <form method="POST"
                                            action="{{ route('pages.finance.subscriptions.status', $s) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="paused">
                                            <button type="submit" class="hover:text-amber-700 inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" title="Jeda" aria-label="Jeda">
                                                <i class='bx bx-pause-circle text-lg'></i>
                                            </button>
                                        </form>
                                    @elseif (in_array($s->status, ['paused', 'expired'], true))
                                        <form method="POST"
                                            action="{{ route('pages.finance.subscriptions.status', $s) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="hover:text-emerald-700 inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" title="Aktifkan" aria-label="Aktifkan">
                                                <i class='bx bx-play-circle text-lg'></i>
                                            </button>
                                        </form>
                                    @endif

                                    @if ($s->status !== 'cancelled')
                                        <form method="POST"
                                            action="{{ route('pages.finance.subscriptions.status', $s) }}"
                                            onsubmit="return confirm('Batalkan langganan ini? Invoice yang sudah terbit tetap tersimpan.')">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="hover:text-red-700 inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" title="Batalkan" aria-label="Batalkan">
                                                <i class='bx bx-x-circle text-lg'></i>
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

<div class="mt-4">{{ $subscriptions->links() }}</div>
