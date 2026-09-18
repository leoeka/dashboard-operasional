@extends('layouts.app')
@section('title', $subscription->exists ? 'Edit Langganan' : 'Langganan Baru')

@section('content')

    @php($editing = $subscription->exists)

    <x-page-header :title="$editing ? 'Edit Langganan' : 'Langganan Baru'" />

    @if ($errors->any())
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-600 text-sm">
            <ul class="list-disc list-inside space-y-0.5">
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
                const unit = (p.unit || '').toLowerCase();
                if (unit.includes('bulan') || unit.includes('month')) this.$refs.cycle.value = 'monthly';
                if (unit.includes('tahun') || unit.includes('year')) this.$refs.cycle.value = 'yearly';
            }
        }">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            <div class="lg:col-span-2 space-y-5">
                <x-card>
                    <h2 class="font-semibold text-slate-800 mb-4">Layanan</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Client <span
                                    class="text-red-500">*</span></label>
                            <select name="client_id" required x-model="clientId" @change="onClientChange()"
                                class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
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
                                <span class="text-slate-400">(opsional — hanya untuk mengisi form)</span>
                            </label>
                            <select name="service_package_id" @change="applyPackage($event.target.value)"
                                class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                                <option value="">-- Tanpa paket / custom --</option>
                                @foreach ($packages as $package)
                                    <option value="{{ $package->id }}" @selected(old('service_package_id', $subscription->service_package_id) == $package->id)>
                                        {{ $package->name }} — Rp{{ number_format((float) $package->price, 0, ',', '.') }}
                                        {{ $package->unit ? '/ ' . $package->unit : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Nama Layanan <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="name" x-ref="name" required
                                value="{{ old('name', $subscription->name) }}" placeholder="Hosting + Domain"
                                class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                        </div>

                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Jenis <span
                                    class="text-red-500">*</span></label>
                            <select name="service_type" required
                                class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                                @foreach (\App\Models\BillingSubscription::SERVICE_TYPES as $type)
                                    <option value="{{ $type }}" @selected(old('service_type', $subscription->service_type) === $type)>
                                        {{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Project <span
                                    class="text-slate-400">(opsional)</span></label>
                            <select name="project_id" x-model="projectId"
                                class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                                <option value="">-- Tidak terkait project --</option>
                                <template x-for="project in clientProjects" :key="project.id">
                                    <option :value="project.id" x-text="project.name"></option>
                                </template>
                            </select>
                            <p class="text-xs text-slate-400 mt-1" x-show="clientId && clientProjects.length === 0"
                                x-cloak>
                                Client ini belum punya project. Langganan tetap bisa dibuat tanpa project.
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Catatan</label>
                            <textarea name="description" rows="2"
                                class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">{{ old('description', $subscription->description) }}</textarea>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <h2 class="font-semibold text-slate-800 mb-1">Penagihan</h2>
                    @if ($editing)
                        <p class="text-xs text-slate-400 mb-4">Mengubah nominal hanya berlaku untuk periode berikutnya.
                            Invoice yang sudah terbit tidak berubah.</p>
                    @else
                        <p class="text-xs text-slate-400 mb-4">Invoice perpanjangan dibuat otomatis H-30 (tahunan) atau
                            H-7 (bulanan) sebelum tanggal perpanjangan.</p>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Siklus <span
                                    class="text-red-500">*</span></label>
                            <select name="billing_cycle" x-ref="cycle" required
                                class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                                <option value="yearly" @selected(old('billing_cycle', $subscription->billing_cycle) === 'yearly')>Tahunan</option>
                                <option value="monthly" @selected(old('billing_cycle', $subscription->billing_cycle) === 'monthly')>Bulanan</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Nominal (Rp) <span
                                    class="text-red-500">*</span></label>
                            <input type="number" name="amount" x-ref="amount" required min="0" step="1"
                                value="{{ old('amount', $subscription->amount ? (int) $subscription->amount : '') }}"
                                class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                        </div>

                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Mulai <span
                                    class="text-red-500">*</span></label>
                            <input type="date" name="start_date" required
                                value="{{ old('start_date', $subscription->start_date?->toDateString()) }}"
                                class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                            <p class="text-xs text-slate-400 mt-1">Tanggal ini menjadi patokan hari perpanjangan tiap
                                periode.</p>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Perpanjangan Berikutnya <span
                                    class="text-red-500">*</span></label>
                            <input type="date" name="next_renewal_date" required
                                value="{{ old('next_renewal_date', $subscription->next_renewal_date?->toDateString()) }}"
                                class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none">
                        </div>

                        <input type="hidden" name="currency" value="{{ old('currency', $subscription->currency ?: 'IDR') }}">
                    </div>
                </x-card>
            </div>

            <div class="space-y-5">
                <x-card>
                    <h2 class="font-semibold text-slate-800 mb-4">Status & Otomatisasi</h2>

                    <label class="block text-xs text-slate-500 mb-1">Status</label>
                    <select name="status"
                        class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none mb-4">
                        @foreach (['active' => 'Aktif', 'paused' => 'Dijeda', 'cancelled' => 'Dibatalkan', 'expired' => 'Berakhir'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $subscription->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <label class="flex items-start gap-3 mb-3 cursor-pointer">
                        <input type="checkbox" name="auto_invoice" value="1" class="mt-0.5"
                            @checked(old('auto_invoice', $subscription->auto_invoice ?? true))>
                        <span>
                            <span class="block text-sm text-slate-700">Invoice otomatis</span>
                            <span class="block text-xs text-slate-400">Sistem menerbitkan invoice perpanjangan sendiri.
                                Jika dimatikan, invoice harus dibuat manual.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="auto_reminder" value="1" class="mt-0.5"
                            @checked(old('auto_reminder', $subscription->auto_reminder ?? true))>
                        <span>
                            <span class="block text-sm text-slate-700">Reminder otomatis</span>
                            <span class="block text-xs text-slate-400">Email pengingat dikirim sesuai jadwal H-30/H-7/H-3
                                atau H-7/H-3/H-1.</span>
                        </span>
                    </label>
                </x-card>

                <div class="flex gap-2">
                    <button type="submit"
                        class="grad-blue text-white text-sm px-5 py-2.5 rounded-lg hover:opacity-90 transition flex-1">
                        {{ $editing ? 'Simpan Perubahan' : 'Buat Langganan' }}
                    </button>
                    <a href="{{ route('pages.finance', ['tab' => 'subscriptions']) }}"
                        class="bg-slate-100 text-slate-600 text-sm px-5 py-2.5 rounded-lg hover:bg-slate-200 transition">
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </form>

@endsection
