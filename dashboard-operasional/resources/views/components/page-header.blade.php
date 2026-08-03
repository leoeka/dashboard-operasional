@props(['title'])

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <h1 class="text-xl font-bold text-slate-800">{{ $title }}</h1>

    @isset($actions)
        <div>{{ $actions }}</div>
    @endisset
</div>