{{-- FILTERS --}}
<x-card padding="p-4" class="mb-5">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-6 gap-2">
        <input type="hidden" name="tab" value="subscriptions">

        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari client / layanan…"
            class="col-span-2 bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">

        <select name="status" class="bg-slate-50 text-slate-600 rounded-lg px-3 py-2 text-sm outline-none">
            <option value="">Semua status</option>
            @foreach (['active' => 'Aktif', 'paused' => 'Dijeda', 'cancelled' => 'Dibatalkan', 'expired' => 'Berakhir'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="service_type" class="bg-slate-50 text-slate-600 rounded-lg px-3 py-2 text-sm outline-none">
            <option value="">Semua jenis</option>
            @foreach (\App\Models\BillingSubscription::SERVICE_TYPES as $type)
                <option value="{{ $type }}" @selected(request('service_type') === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>

        <select name="cycle" class="bg-slate-50 text-slate-600 rounded-lg px-3 py-2 text-sm outline-none">
            <option value="">Semua siklus</option>
            <option value="yearly" @selected(request('cycle') === 'yearly')>Tahunan</option>
            <option value="monthly" @selected(request('cycle') === 'monthly')>Bulanan</option>
        </select>

        <div class="flex gap-2">
            <select name="client" class="flex-1 bg-slate-50 text-slate-600 rounded-lg px-3 py-2 text-sm outline-none">
                <option value="">Semua client</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(request('client') == $client->id)>
                        {{ $client->company_name }}</option>
                @endforeach
            </select>
            <button type="submit"
                class="grad-blue text-white text-sm px-3 rounded-lg hover:opacity-90 transition flex-shrink-0">
                <i class='bx bx-search'></i>
            </button>
        </div>
    </form>

    <div class="flex flex-wrap gap-2 mt-3">
        <a href="{{ route('pages.finance', ['tab' => 'subscriptions']) }}"
            class="text-xs px-3 py-1.5 rounded-lg {{ !request('due') && !request('status') ? 'grad-blue text-white' : 'bg-slate-100 text-slate-600' }}">Semua</a>
        <a href="{{ route('pages.finance', ['tab' => 'subscriptions', 'due' => 'upcoming']) }}"
            class="text-xs px-3 py-1.5 rounded-lg {{ request('due') === 'upcoming' ? 'grad-blue text-white' : 'bg-slate-100 text-slate-600' }}">Perpanjangan
            30 hari</a>
        <a href="{{ route('pages.finance', ['tab' => 'subscriptions', 'status' => 'active']) }}"
            class="text-xs px-3 py-1.5 rounded-lg {{ request('status') === 'active' ? 'grad-blue text-white' : 'bg-slate-100 text-slate-600' }}">Aktif</a>
    </div>
</x-card>

<x-card padding="p-0">
    @if ($subscriptions->isEmpty())
        <x-empty-state icon="bx-repost" title="Belum ada langganan"
            description="Tambahkan layanan berulang seperti hosting, domain, maintenance atau SEO agar invoice dan reminder-nya berjalan otomatis." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[980px]">
                <thead>
                    <tr class="text-left text-slate-400 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">Client</th>
                        <th class="px-6 py-3 font-medium">Layanan</th>
                        <th class="px-6 py-3 font-medium">Siklus</th>
                        <th class="px-6 py-3 font-medium">Nominal</th>
                        <th class="px-6 py-3 font-medium">Perpanjangan</th>
                        <th class="px-6 py-3 font-medium">Otomatis</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($subscriptions as $s)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-slate-700">{{ $s->client?->company_name ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <p class="text-slate-700">{{ $s->name }}</p>
                                <p class="text-xs text-slate-400">
                                    {{ ucfirst($s->service_type) }}@if ($s->project)
                                        · {{ $s->project->name }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $s->isYearly() ? 'Tahunan' : 'Bulanan' }}</td>
                            <td class="px-6 py-4 text-slate-700">Rp{{ number_format((float) $s->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-slate-600">{{ $s->next_renewal_date->translatedFormat('d M Y') }}</p>
                                @if ($s->status === 'active')
                                    <x-badge :color="$s->countdown_tone" class="mt-1 inline-block">{{ $s->countdown }}</x-badge>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                {{-- Both flags shown together: "no invoice" and
                                     "no reminder" are different states and an
                                     operator needs to tell them apart. --}}
                                <div class="flex gap-1">
                                    <span class="text-xs px-2 py-1 rounded {{ $s->auto_invoice ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400' }}"
                                        title="Invoice otomatis">INV</span>
                                    <span class="text-xs px-2 py-1 rounded {{ $s->auto_reminder ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400' }}"
                                        title="Reminder otomatis">RMD</span>
                                </div>
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
                                <div class="flex items-center justify-end gap-3 text-slate-400">
                                    <a href="{{ route('pages.finance.subscriptions.edit', $s) }}"
                                        class="hover:text-brand-500" title="Edit">
                                        <i class='bx bx-edit-alt text-lg'></i>
                                    </a>

                                    @if ($s->status === 'active')
                                        <form method="POST"
                                            action="{{ route('pages.finance.subscriptions.status', $s) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="paused">
                                            <button type="submit" class="hover:text-amber-500" title="Jeda">
                                                <i class='bx bx-pause-circle text-lg'></i>
                                            </button>
                                        </form>
                                    @elseif (in_array($s->status, ['paused', 'expired'], true))
                                        <form method="POST"
                                            action="{{ route('pages.finance.subscriptions.status', $s) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="hover:text-emerald-500" title="Aktifkan">
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
                                            <button type="submit" class="hover:text-red-500" title="Batalkan">
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
