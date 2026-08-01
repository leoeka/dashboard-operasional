@props(['color' => 'slate'])

@php
    $colors = [
        'slate'   => 'bg-slate-100 text-slate-600',
        'purple'  => 'bg-purple-100 text-purple-600',
        'blue'    => 'bg-blue-100 text-blue-600',
        'amber'   => 'bg-amber-100 text-amber-600',
        'pink'    => 'bg-pink-100 text-pink-600',
        'emerald' => 'bg-emerald-100 text-emerald-600',
        'red'     => 'bg-red-100 text-red-600',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'text-xs px-3 py-1 rounded-full ' . ($colors[$color] ?? $colors['slate'])]) }}>
    {{ $slot }}
</span>