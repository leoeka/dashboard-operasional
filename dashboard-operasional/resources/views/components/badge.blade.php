@props(['color' => 'slate'])

@php
    $colors = [
        'slate'   => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'purple'  => 'bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-300',
        'blue'    => 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300',
        'amber'   => 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300',
        'pink'    => 'bg-pink-100 text-pink-600 dark:bg-pink-900/40 dark:text-pink-300',
        'emerald' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300',
        'red'     => 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-300',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'text-xs px-3 py-1 rounded-full ' . ($colors[$color] ?? $colors['slate'])]) }}>
    {{ $slot }}
</span>