<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiteFlow — @yield('title', 'Dashboard')</title>

    {{-- Tailwind via CDN agar bisa langsung jalan tanpa build step.
    Nanti bisa dipindah ke Vite + Tailwind resmi kalau proyek sudah stabil. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen">
    <div class="flex min-h-screen">

        {{-- SIDEBAR --}}
        <aside class="w-64 bg-white border-r border-slate-100 flex-shrink-0 flex flex-col">
            <div class="px-6 py-6 flex items-center gap-2">
                {{-- <div class="w-8 h-8 rounded-lg grad-purple"></div> --}}
                {{-- <span class="text-lg font-bold text-slate-800">SiteFlow</span> --}}
            </div>

            <div class="px-6 pb-6 flex items-center gap-3">
                <img src="https://ui-avatars.com/api/?name=Dimas&background=4F7CF0&color=fff"
                    class="w-9 h-9 rounded-full" alt="avatar">
                <div>
                    <p class="text-sm font-semibold text-slate-800">Dimas Prakoso</p>
                    <p class="text-xs text-slate-400">Project Manager</p>
                </div>
            </div>

            <nav class="flex-1 px-3 space-y-1 overflow-y-auto">
                @php
                    $menu = [
                        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bx-grid-alt'],
                        ['label' => 'Request Project', 'route' => 'request-project', 'icon' => 'bx-file-plus'],
                        ['label' => 'Proposal AI', 'route' => 'proposal-ai', 'icon' => 'bx-bulb'],
                        ['label' => 'Mockup AI', 'route' => 'mockup-ai', 'icon' => 'bx-shape-square'],
                        ['label' => 'Website Generator', 'route' => 'website-generator', 'icon' => 'bx-code-alt'],
                        ['label' => 'Projects', 'route' => 'projects.index', 'icon' => 'bx-briefcase'],
                        ['label' => 'AI Workspace', 'route' => 'ai-workspace', 'icon' => 'bx-cube'],
                        ['label' => 'QA', 'route' => 'qa', 'icon' => 'bx-check-shield'],
                        ['label' => 'Reports', 'route' => 'reports', 'icon' => 'bx-bar-chart-alt-2'],
                        ['label' => 'Settings', 'route' => 'settings', 'icon' => 'bx-cog'],
                    ];
                @endphp

                @foreach ($menu as $item)
                    <a href="{{ route($item['route']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-500 hover:bg-slate-50 transition
                              {{ request()->routeIs($item['route']) ? 'nav-active' : '' }}">
                        <i class='bx {{ $item['icon'] }} text-lg'></i>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="p-4">
                <a href="{{ route('request-project') }}"
                    class="flex items-center justify-center gap-2 w-full py-2.5 rounded-lg text-white text-sm font-semibold grad-blue hover:opacity-90 transition">
                    <i class='bx bx-plus'></i> Proyek Baru
                </a>
            </div>
        </aside>

        {{-- MAIN --}}
        <div class="flex-1 flex flex-col min-w-0">

            {{-- TOPBAR --}}
            <header class="h-16 bg-white border-b border-slate-100 flex items-center justify-between px-8">
                <div class="flex items-center gap-2 text-slate-400 bg-slate-50 rounded-lg px-3 py-2 w-80">
                    <i class='bx bx-search'></i>
                    <input type="text" placeholder="Cari proyek atau klien..."
                        class="bg-transparent outline-none text-sm w-full text-slate-600 placeholder:text-slate-400">
                </div>
                <div class="flex items-center gap-5 text-slate-400">
                    <i class='bx bx-bell text-xl'></i>
                    <i class='bx bx-envelope text-xl'></i>
                    <div class="w-px h-6 bg-slate-200"></div>
                    <img src="https://ui-avatars.com/api/?name=Dimas&background=4F7CF0&color=fff"
                        class="w-8 h-8 rounded-full" alt="avatar">
                </div>
            </header>

            <main class="flex-1 p-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>

</html>