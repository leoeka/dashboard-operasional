@props(['title'])

<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-slate-800">{{ $title }}</h1>

    @isset($actions)
        <div>{{ $actions }}</div>
    @endisset
</div>