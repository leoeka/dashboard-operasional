@extends('layouts.app')
@section('title', $client->company_name)

@section('content')
@php
    $phoneRaw = $client->whatsapp ?: $client->phone;
    $phoneDigits = preg_replace('/[^0-9]/', '', (string) $phoneRaw);
    if (str_starts_with($phoneDigits, '0')) {
        $phoneDigits = '62' . substr($phoneDigits, 1);
    }
@endphp

{{-- Navigasi & Action Bar --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('pages.crm') }}"
            class="min-h-11 inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
            <i class='bx bx-arrow-back text-lg' aria-hidden="true"></i>
            <span>Kembali ke CRM</span>
        </a>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @if ($phoneDigits)
            <a href="https://wa.me/{{ $phoneDigits }}" target="_blank" rel="noopener noreferrer"
                class="min-h-11 inline-flex items-center gap-2 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold rounded-md transition shadow-sm">
                <i class='bx bxl-whatsapp text-lg' aria-hidden="true"></i>
                <span>Hubungi WhatsApp</span>
            </a>
        @endif

        @if ($client->billingEmail())
            <a href="mailto:{{ $client->billingEmail() }}"
                class="min-h-11 inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-md transition">
                <i class='bx bx-envelope text-lg' aria-hidden="true"></i>
                <span>Kirim Email</span>
            </a>
        @endif

        <a href="{{ route('pages.request') }}"
            class="min-h-11 inline-flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-md transition shadow-sm">
            <i class='bx bx-plus text-lg' aria-hidden="true"></i>
            <span>Request Project</span>
        </a>
    </div>
</div>

{{-- Kartu Profil Client --}}
<x-card class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-6 border-b border-slate-100">
        <div class="flex items-start gap-4 min-w-0">
            @if ($client->logo_path)
                <img src="{{ Storage::disk('public')->url($client->logo_path) }}" alt="{{ $client->company_name }}"
                    class="w-16 h-16 rounded-xl object-cover border border-slate-200 flex-shrink-0">
            @else
                <div class="w-16 h-16 rounded-xl bg-brand-600 flex items-center justify-center text-white font-bold text-xl flex-shrink-0 shadow-sm">
                    {{ strtoupper(substr($client->company_name, 0, 2)) }}
                </div>
            @endif

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-bold text-slate-900 truncate">{{ $client->company_name }}</h1>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                        Client ID #{{ $client->id }}
                    </span>
                </div>
                <p class="text-sm text-slate-600 mt-0.5">
                    Kontak: <span class="font-medium text-slate-800">{{ $client->contact_name ?: '-' }}</span>
                </p>
                <p class="text-xs text-slate-500 mt-1">
                    Terdaftar sejak <span class="font-mono">{{ $client->created_at?->format('d M Y') ?: '-' }}</span>
                </p>
            </div>
        </div>
    </div>

    {{-- Detail Kontak & Informasi --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 pt-5 text-sm">
        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Email Utama</p>
            @if ($client->email)
                <a href="mailto:{{ $client->email }}" class="font-medium text-brand-600 hover:underline break-words">
                    {{ $client->email }}
                </a>
            @else
                <p class="text-slate-600">-</p>
            @endif

            @if ($client->billing_email && $client->billing_email !== $client->email)
                <p class="text-xs text-slate-500 mt-1">
                    Billing: <a href="mailto:{{ $client->billing_email }}" class="text-brand-600 hover:underline break-words">{{ $client->billing_email }}</a>
                </p>
            @endif
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Telepon / WhatsApp</p>
            @if ($client->phone || $client->whatsapp)
                <p class="font-medium font-mono text-slate-800">{{ $client->phone ?: $client->whatsapp }}</p>
                @if ($client->billing_phone && $client->billing_phone !== $client->phone)
                    <p class="text-xs text-slate-500 mt-1">
                        Billing: <span class="font-mono text-slate-700">{{ $client->billing_phone }}</span>
                    </p>
                @endif
            @else
                <p class="text-slate-600">-</p>
            @endif
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Alamat</p>
            <p class="font-medium text-slate-700 break-words">{{ $client->address ?: '-' }}</p>
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Website & Sosial</p>
            <div class="space-y-1">
                @if ($client->website)
                    <div>
                        <a href="{{ str_starts_with($client->website, 'http') ? $client->website : 'https://' . $client->website }}"
                            target="_blank" rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 text-brand-600 hover:underline font-medium break-all">
                            <i class='bx bx-globe' aria-hidden="true"></i>
                            <span>{{ parse_url(str_starts_with($client->website, 'http') ? $client->website : 'https://' . $client->website, PHP_URL_HOST) ?: $client->website }}</span>
                        </a>
                    </div>
                @endif
                @if ($client->instagram)
                    <div>
                        <a href="https://instagram.com/{{ ltrim($client->instagram, '@') }}"
                            target="_blank" rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 text-slate-700 hover:text-slate-900 font-medium">
                            <i class='bx bxl-instagram text-slate-500' aria-hidden="true"></i>
                            <span>{{ '@' . ltrim($client->instagram, '@') }}</span>
                        </a>
                    </div>
                @endif
                @if (!$client->website && !$client->instagram)
                    <p class="text-slate-600">-</p>
                @endif
            </div>
        </div>
    </div>

    @if ($client->notes)
        <div class="mt-5 pt-4 border-t border-slate-100">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Catatan Klien</p>
            <p class="text-xs text-slate-700 whitespace-pre-line bg-slate-50 border border-slate-200 rounded-md p-3">{{ $client->notes }}</p>
        </div>
    @endif
</x-card>

{{-- Kartu Ringkasan Statistik --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl p-5 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Project</p>
            <p class="text-2xl font-bold font-mono text-slate-900 mt-1">{{ $client->projects->count() }}</p>
            <p class="text-xs text-slate-600 mt-0.5">Project terdaftar</p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-blue-50 text-brand-600 flex items-center justify-center text-2xl flex-shrink-0">
            <i class='bx bx-folder' aria-hidden="true"></i>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Langganan Aktif</p>
            <p class="text-2xl font-bold font-mono text-slate-900 mt-1">{{ $subscriptions->where('status', 'active')->count() }}</p>
            <p class="text-xs text-slate-600 mt-0.5">Hosting & Retainer</p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl flex-shrink-0">
            <i class='bx bx-refresh' aria-hidden="true"></i>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tagihan Terbuka</p>
            <p class="text-2xl font-bold font-mono text-slate-900 mt-1">{{ $invoices->where('status', 'unpaid')->count() }}</p>
            <p class="text-xs text-slate-600 mt-0.5">Menunggu pembayaran</p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-2xl flex-shrink-0">
            <i class='bx bx-receipt' aria-hidden="true"></i>
        </div>
    </div>
</div>

<div class="space-y-6">
    {{-- Section 1: Riwayat Project --}}
    <x-card padding="">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-slate-900 text-base">Riwayat Project</h2>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                    {{ $client->projects->count() }}
                </span>
            </div>
            <a href="{{ route('pages.request') }}"
                class="min-h-11 inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700 transition">
                <i class='bx bx-plus text-base' aria-hidden="true"></i>
                <span>Tambah Project</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-left text-xs text-slate-600 bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-3.5 font-semibold">Nama Project</th>
                        <th class="px-6 py-3.5 font-semibold">Tipe Layanan</th>
                        <th class="px-6 py-3.5 font-semibold">Tanggal Mulai</th>
                        <th class="px-6 py-3.5 font-semibold">Status</th>
                        <th class="px-6 py-3.5 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @forelse ($client->projects as $project)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <a href="{{ route('pages.projects.show', $project) }}"
                                    class="font-semibold text-slate-900 hover:text-brand-600 transition block">
                                    {{ $project->name }}
                                </a>
                                @if ($project->code)
                                    <span class="text-xs font-mono text-slate-500">{{ $project->code }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs text-slate-600">{{ $project->serviceTypeLabel() }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-mono text-slate-600">
                                    {{ $project->created_at?->format('d M Y') ?: '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <x-badge :color="$project->statusColor()">{{ $project->statusLabel() }}</x-badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('pages.projects.show', $project) }}"
                                    class="min-h-11 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700 transition">
                                    <span>Buka Workspace</span>
                                    <i class='bx bx-chevron-right text-base' aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-600">
                                Belum ada project terdaftar untuk client ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Section 2: Layanan Berlangganan & Perpanjangan --}}
    <x-card padding="">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-slate-900 text-base">Layanan Berlangganan (Hosting & Retainer)</h2>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                    {{ $subscriptions->count() }}
                </span>
            </div>
            <a href="{{ route('pages.finance.subscriptions.create') }}"
                class="min-h-11 inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700 transition">
                <i class='bx bx-plus text-base' aria-hidden="true"></i>
                <span>Tambah Langganan</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-left text-xs text-slate-600 bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-3.5 font-semibold">Nama Layanan</th>
                        <th class="px-6 py-3.5 font-semibold">Project</th>
                        <th class="px-6 py-3.5 font-semibold">Siklus</th>
                        <th class="px-6 py-3.5 font-semibold">Biaya</th>
                        <th class="px-6 py-3.5 font-semibold">Jatuh Tempo Berikutnya</th>
                        <th class="px-6 py-3.5 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @forelse ($subscriptions as $sub)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-900">{{ $sub->name }}</p>
                                <span class="text-xs text-slate-500 uppercase">{{ $sub->service_type }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @if ($sub->project)
                                    <a href="{{ route('pages.projects.show', $sub->project) }}"
                                        class="text-xs font-medium text-brand-600 hover:underline">
                                        {{ $sub->project->name }}
                                    </a>
                                @else
                                    <span class="text-xs text-slate-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs text-slate-700">
                                    {{ $sub->isYearly() ? 'Tahunan' : 'Bulanan' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-mono font-semibold text-slate-900">
                                    Rp {{ number_format($sub->amount, 0, ',', '.') }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-xs font-mono text-slate-800">
                                    {{ $sub->next_renewal_date?->format('d M Y') ?: '-' }}
                                </p>
                                @if ($sub->days_until !== null)
                                    <p class="text-xs font-mono mt-0.5 {{ $sub->days_until < 0 ? 'text-red-700 font-semibold' : ($sub->days_until <= 7 ? 'text-amber-700 font-semibold' : 'text-slate-500') }}">
                                        @if ($sub->days_until > 0)
                                            ({{ $sub->days_until }} hari lagi)
                                        @elseif ($sub->days_until === 0)
                                            (Hari ini)
                                        @else
                                            (Lewat {{ abs($sub->days_until) }} hari)
                                        @endif
                                    </p>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $subColor = match ($sub->status) {
                                        'active' => 'emerald',
                                        'paused' => 'amber',
                                        default => 'slate',
                                    };
                                @endphp
                                <x-badge :color="$subColor">{{ ucfirst($sub->status) }}</x-badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-600">
                                Belum ada layanan berlangganan untuk client ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Section 3: Riwayat Invoice & Tagihan --}}
    <x-card padding="">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-slate-900 text-base">Riwayat Tagihan & Invoice</h2>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                    {{ $invoices->count() }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-left text-xs text-slate-600 bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-3.5 font-semibold">No. Invoice</th>
                        <th class="px-6 py-3.5 font-semibold">Keperluan</th>
                        <th class="px-6 py-3.5 font-semibold">Jatuh Tempo</th>
                        <th class="px-6 py-3.5 font-semibold">Nominal</th>
                        <th class="px-6 py-3.5 font-semibold">Status</th>
                        <th class="px-6 py-3.5 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @forelse ($invoices as $inv)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 font-mono font-semibold text-slate-900">
                                <a href="{{ route('pages.finance.invoices.show', $inv) }}"
                                    class="hover:text-brand-600 transition">
                                    {{ $inv->invoice_number }}
                                </a>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-xs text-slate-800 font-medium">
                                    {{ $inv->isRenewal() ? 'Perpanjangan' : 'Pembayaran Project' }}
                                </p>
                                @if ($inv->project)
                                    <p class="text-xs text-slate-500 truncate max-w-xs">{{ $inv->project->name }}</p>
                                @elseif ($inv->subscription)
                                    <p class="text-xs text-slate-500 truncate max-w-xs">{{ $inv->subscription->name }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-600">
                                {{ $inv->due_date?->format('d M Y') ?: '-' }}
                            </td>
                            <td class="px-6 py-4 font-mono font-semibold text-slate-900">
                                Rp {{ number_format($inv->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4">
                                <x-badge :color="$inv->statusColor()">{{ $inv->statusLabel() }}</x-badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('pages.finance.invoices.show', $inv) }}"
                                    class="min-h-11 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700 transition">
                                    <span>Detail Invoice</span>
                                    <i class='bx bx-chevron-right text-base' aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-600">
                                Belum ada invoice tercatat untuk client ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>

@endsection
