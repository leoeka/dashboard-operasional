<aside class="w-64 bg-white border-r border-slate-100 flex-shrink-0 flex flex-col fixed md:static inset-y-0 left-0 z-50 transition-transform duration-200 -translate-x-full md:translate-x-0" :class="sidebarOpen && '!translate-x-0'">

    <div class="px-6 py-6 flex items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-brand-600"></div>
            <span class="text-base font-bold text-slate-800">SiteFlow</span>
        </div>
        <button @click="sidebarOpen = false" class="md:hidden inline-flex items-center justify-center w-11 h-11 -mr-2 text-slate-500" aria-label="Tutup menu">
            <i class='bx bx-x text-2xl' aria-hidden="true"></i>
        </button>
    </div>

    <nav class="flex-1 px-3 space-y-1 overflow-y-auto">
        @foreach (config('navigation.menu') as $item)
            <a href="{{ route($item['route']) }}" @click="sidebarOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-md text-sm hover:bg-slate-50 transition {{ request()->routeIs($item['route']) ? 'nav-active' : 'text-slate-500' }}">
                <i class='bx {{ $item['icon'] }} text-lg'></i>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</aside>
