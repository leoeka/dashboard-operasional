@props(['icon' => 'bx-inbox', 'title' => 'Belum ada data', 'description' => ''])

<div class="flex flex-col items-center text-center py-16">
    <div class="w-16 h-16 rounded-2xl grad-purple flex items-center justify-center mb-4">
        <i class='bx {{ $icon }} text-2xl text-white'></i>
    </div>
    <p class="font-semibold text-slate-700 mb-1">{{ $title }}</p>
    @if ($description)
        <p class="text-sm text-slate-400 max-w-md">{{ $description }}</p>
    @endif
</div>