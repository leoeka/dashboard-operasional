@props(['color' => 'slate'])

@php
    $colors = [
        'slate'   => 'bg-slate-100 text-slate-600',
        'purple'  => 'bg-purple-100 text-purple-700',
        'blue'    => 'bg-blue-100 text-blue-700',
        'amber'   => 'bg-amber-100 text-amber-700',
        'pink'    => 'bg-pink-100 text-pink-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'red'     => 'bg-red-100 text-red-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'text-xs px-2 py-1 rounded-md ' . ($colors[$color] ?? $colors['slate'])]) }}>
    {{ $slot }}
</span>