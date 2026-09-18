@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');

    // Outstanding and overdue lead because they are the two numbers somebody
    // opens this page to check.
    $cards = [
        ['Belum Dibayar', $rp($summary['outstanding_total']), $summary['outstanding_count'] . ' invoice', 'bx-time-five', 'text-slate-700', 'bg-slate-100 text-slate-500'],
        ['Terlambat', $rp($summary['overdue_total']), $summary['overdue_count'] . ' invoice', 'bx-error-circle', 'text-red-600', 'bg-red-50 text-red-500'],
        ['Jatuh Tempo 7 Hari', $rp($summary['due_soon_total']), $summary['due_soon_count'] . ' invoice', 'bx-calendar-exclamation', 'text-amber-600', 'bg-amber-50 text-amber-500'],
        ['Diterima Bulan Ini', $rp($summary['paid_this_month']), $summary['paid_this_month_count'] . ' pembayaran', 'bx-wallet', 'text-emerald-600', 'bg-emerald-50 text-emerald-500'],
        ['Langganan Aktif', $summary['active_subscriptions'], 'layanan berjalan', 'bx-repost', 'text-slate-700', 'bg-blue-50 text-blue-500'],
        ['Perpanjangan 30 Hari', $summary['upcoming_renewals'], 'akan jatuh tempo', 'bx-refresh', 'text-slate-700', 'bg-purple-50 text-purple-500'],
    ];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
    @foreach ($cards as [$label, $value, $meta, $icon, $valueClass, $iconClass])
        <x-card padding="p-4">
            <div class="flex items-start justify-between gap-2 mb-2">
                <p class="text-xs text-slate-400 leading-tight">{{ $label }}</p>
                <span class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 {{ $iconClass }}">
                    <i class='bx {{ $icon }}'></i>
                </span>
            </div>
            <p class="font-bold {{ $valueClass }} text-base leading-tight break-words">{{ $value }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $meta }}</p>
        </x-card>
    @endforeach
</div>
