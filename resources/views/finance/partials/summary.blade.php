@php
    $cards = [
        ['Total Klien', $summary['total_clients'], 'klien terdaftar', 'bx-group', 'text-emerald-600', 'bg-emerald-50 text-emerald-500'],
        ['Langganan Aktif', $summary['active_subscriptions'], 'layanan berjalan', 'bx-repost', 'text-slate-700', 'bg-blue-50 text-blue-500'],
        ['Perpanjangan 30 Hari', $summary['upcoming_renewals'], 'akan jatuh tempo', 'bx-refresh', 'text-slate-700', 'bg-purple-50 text-purple-500'],
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 mb-6">
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
