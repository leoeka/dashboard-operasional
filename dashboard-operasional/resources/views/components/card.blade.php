@props(['padding' => 'p-6'])

<div {{ $attributes->merge(['class' => "bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl {$padding}"]) }}>
    {{ $slot }}
</div>