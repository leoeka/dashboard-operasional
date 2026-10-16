@extends('layouts.app')
@section('title', $subscription->exists ? 'Edit Langganan' : 'Langganan Baru')

@section('content')

    @php($editing = $subscription->exists)

    <x-page-header :title="$editing ? 'Edit Langganan' : 'Langganan Baru'" />

    @if ($errors->any())
        <div class="mb-6 px-4 py-3 rounded-md bg-red-50 text-red-700 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
        action="{{ $editing ? route('pages.finance.subscriptions.update', $subscription) : route('pages.finance.subscriptions.store') }}"
        x-data="{
            packages: {{ Js::from($packages->keyBy('id')) }},
            projects: {{ Js::from($projects->map->only(['id', 'name', 'client_id'])->values()) }},
            clientId: '{{ old('client_id', $subscription->client_id) }}',
            projectId: '{{ old('project_id', $subscription->project_id) }}',
            serviceType: '{{ old('service_type', $subscription->service_type) }}',
            yearlyTypes: {{ Js::from(\App\Models\BillingSubscription::YEARLY_SERVICE_TYPES) }},
            get cycleHint() {
                return this.yearlyTypes.includes(this.serviceType)
                    ? 'Tahunan: invoice terbit H-30, reminder H-30 / H-7 / H-3.'
                    : 'Bulanan: invoice terbit H-7, reminder H-7 / H-3 / H-1.';
            },
            get clientProjects() {
                // Narrowing the list is a convenience; the server rejects a
                // mismatch regardless of what reaches it.
                if (!this.clientId) return [];
                return this.projects.filter(p => String(p.client_id) === String(this.clientId));
            },
            onClientChange() {
                // A project belonging to the previous client must not stay
                // selected after the client changes.
                if (!this.clientProjects.some(p => String(p.id) === String(this.projectId))) {
                    this.projectId = '';
                }
            },
            applyPackage(id) {
                const p = this.packages[id];
                if (!p) return;
                // A package is a template, never a binding: everything it fills
                // in stays editable, and a subscription can be built without one.
                if (!this.$refs.name.value) this.$refs.name.value = p.name;
                if (!this.$refs.amount.value) this.$refs.amount.value = Math.round(p.price);
            }
        }">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 space-y-6">
                <x-card>
                    <h2 class="font-semibold text-slate-800 mb-4">Layanan</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Client <span
                                    class="text-red-700">*</span></label>
                            <select name="client_id" required x-model="clientId" @change="onClientChange()"
                                class="min-h-11 w-full bg-slate-50 text-slate-700 rounded-md px-3 py-2 text-sm outline-none">
                                <option value="">-- Pilih client --</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(old('client_id', $subscription->client_id) == $client->id)>
                                        {{ $client->company_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Paket Layanan
                                <span class="text-slate-500">(opsional, hanya untuk mengisi form)</span>
                            </label>
                            <select name="service_package_id" @change="applyPackage($event.target.value)"
                                class="min-h-11 w-full bg-slate-50 text-slate-700 rounded-md px-3 py-2 text-sm outline-none">
                                <option value="">-- Tanpa paket / custom --</option>
                                @foreach ($packages as $package)
                                    <option value="{{ $package->id }}" @selected(old('service_package_id', $subscription->service_package_id) == $package->id)>
                                        {{ $package->name }} · Rp{{ number_format((float) $package->price, 0, ',', '.') }}
                                        {{ $package->unit ? '/ ' . $package->unit : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Nama Layanan <span
                                    class="text-red-700">*</span></label>
                            <input type="text" name="name" x-ref="name" required
                                value="{{ old('name', $subscription->name) }}" placeholder="Hosting + Domain"
                                class="min-h-11 w-full bg-slate-50 text-slate-700 rounded-md px-3 py-2 text-sm outline-none">
                        </div>

                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Jenis <span
                                    class="text-red-700">*</span></label>
                            <select name="service_type" required x-model="serviceType"
                                class="min-h-11 w-full bg-slate-50 text-slate-700 rounded-md px-3 py-2 text-sm outline-none">
                                @foreach (\App\Models\BillingSubscription::SERVICE_TYPES as $type)
                                    <option value="{{ $type }}" @selected(old('service_type', $subscription->service_type) === $type)>
                                        {{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Project <span
                                    class="text-slate-500">(opsional)</span></label>
                            <select name="project_id" x-model="projectId"
                                class="min-h-11 w-full bg-slate-50 text-slate-700 rounded-md px-3 py-2 text-sm outline-none">
                                <option value="">-- Tidak terkait project --</option>
                                <template x-for="project in clientProjects" :key="project.id">
                                    <option :value="project.id" x-text="project.name"></option>
                                </template>
                            </select>
                            <p class="text-xs text-slate-500 mt-1" x-show="clientId && clientProjects.length === 0"
                                x-cloak>
                                Client ini belum punya project. Langganan tetap bisa dibuat tanpa project.
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Catatan</label>
                            <textarea name="description" rows="2"
                                class="min-h-11 w-full bg-slate-50 text-slate-700 rounded-md px-3 py-2 text-sm outline-none">{{ old('description', $subscription->description) }}</textarea>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <h2 class="font-semibold text-slate-800 mb-1">Penagihan</h2>
                    @if ($editing)
                        <p class="text-xs text-slate-500 mb-4">Mengubah nominal hanya berlaku untuk periode berikutnya.
                            Invoice yang sudah terbit tidak berubah.</p>
                    @else
                        <p class="text-xs text-slate-500 mb-4">Periode bergulir sendiri tiap siklus sampai tanggal
                            berakhir. Tidak ada yang perlu digeser setiap kali klien membayar.</p>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Siklus</label>
                            <p class="w-full bg-slate-50 text-slate-600 rounded-md px-3 py-2 text-sm" x-text="cycleHint"></p>
                            <p class="text-xs text-slate-500 mt-1">Siklus mengikuti layanan yang dipilih.</p>
                        </div>

                        {{-- Nominal stands alone on its own row: nothing pairs with
                             a price. The two dates below do pair, so they share a
                             row and read as one range, dari ke sampai. --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Nominal (Rp)</label>
                            <input type="number" name="amount" x-ref="amount" required min="0" step="1"
                                value="{{ old('amount', $subscription->amount ? (int) $subscription->amount : '') }}"
                                class="min-h-11 w-full bg-slate-50 text-slate-700 rounded-md px-3 py-2 text-sm outline-none">
                        </div>

                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Mulai</label>
                            <input type="date" name="start_date" required
                                value="{{ old('start_date', $subscription->start_date?->toDateString()) }}"
                                class="min-h-11 w-full bg-slate-50 text-slate-700 rounded-md px-3 py-2 text-sm outline-none">
                        </div>

                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Berakhir</label>
                            <input type="date" name="ends_on"
                                value="{{ old('ends_on', $subscription->ends_on?->toDateString()) }}"
                                class="min-h-11 w-full bg-slate-50 text-slate-700 rounded-md px-3 py-2 text-sm outline-none">
                        </div>

                        @if ($editing)
                            {{-- The date the engine is currently working from is no longer an input, but an
                                 operator editing a subscription still needs to see where it stands. --}}
                            <p class="text-xs text-slate-600 sm:col-span-2">
                                Periode berjalan dibuka
                                <span class="font-mono">{{ $subscription->next_renewal_date?->translatedFormat('d M Y') ?? '-' }}</span>
                                @if ($subscription->ends_on)
                                    · masa langganan sampai
                                    <span class="font-mono">{{ $subscription->ends_on->translatedFormat('d M Y') }}</span>
                                @endif
                            </p>
                        @endif

                        <input type="hidden" name="currency" value="{{ old('currency', $subscription->currency ?: 'IDR') }}">
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card>
                    <h2 class="font-semibold text-slate-800 mb-4">Status & Pengingat</h2>

                    <label class="block text-xs text-slate-500 mb-1">Status</label>
                    <select name="status"
                        class="min-h-11 w-full bg-slate-50 text-slate-700 rounded-md px-3 py-2 text-sm outline-none mb-4">
                        @foreach (['active' => 'Aktif', 'paused' => 'Dijeda', 'cancelled' => 'Dibatalkan', 'expired' => 'Berakhir'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $subscription->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="flex min-h-11 items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="auto_reminder" value="1"
                            class="h-5 w-5 flex-shrink-0 rounded-md border-slate-500 text-brand-600 focus:ring-brand-500"
                            @checked(old('auto_reminder', $subscription->auto_reminder ?? true))>
                        <span>
                            <span class="block text-sm text-slate-700">Reminder otomatis</span>
                            <span class="block text-xs text-slate-500">Pesan di kirim sesuai siklus. </span>
                        </span>
                    </label>
                </x-card>

                <div class="flex gap-2">
                    <button type="submit"
                        class="bg-brand-600 text-white text-sm px-4 py-3 rounded-md hover:bg-brand-700 transition flex-1">
                        {{ $editing ? 'Simpan Perubahan' : 'Buat Langganan' }}
                    </button>
                    <a href="{{ route('pages.finance', ['tab' => 'subscriptions']) }}"
                        class="bg-slate-100 text-slate-600 text-sm px-4 py-3 rounded-md hover:bg-slate-200 transition">
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </form>

@endsection
