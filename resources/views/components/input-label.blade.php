@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs font-semibold text-slate-600']) }}>
    {{ $value ?? $slot }}
</label>
