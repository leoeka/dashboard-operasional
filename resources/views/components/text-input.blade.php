@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full min-h-11 rounded-md border border-slate-500 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-600 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500']) }}>
