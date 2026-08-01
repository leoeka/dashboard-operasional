@props(['padding' => 'p-6'])

<div {{ $attributes->merge(['class' => "bg-white border border-slate-100 rounded-2xl {$padding}"]) }}>
    {{ $slot }}
</div>