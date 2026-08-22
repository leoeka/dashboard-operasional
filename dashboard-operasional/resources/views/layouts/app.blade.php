<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiteFlow — @yield('title', 'Dashboard')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    },
                    colors: {
                        brand: {
                            50: '#eef4ff',
                            500: '#3b6fe0',
                            600: '#2f5bc4',
                            700: '#25489c'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #F4F6FB;
        }

        .grad-purple {
            background: linear-gradient(135deg, #8B5CF6 0%, #4F7CF0 100%);
        }

        .grad-blue {
            background: linear-gradient(135deg, #4F7CF0 0%, #7C3AED 100%);
        }

        .grad-orange {
            background: linear-gradient(135deg, #FB923C 0%, #F472B6 100%);
        }

        .grad-teal {
            background: linear-gradient(135deg, #2DD4BF 0%, #3B82F6 100%);
        }

        .nav-active {
            color: #3b6fe0;
            background: #EEF4FF;
            font-weight: 600;
        }
    </style>
</head>

<body class="min-h-screen">
    @hasSection('auth')
        <main class="min-h-screen flex items-center justify-center p-4">
            @yield('content')
        </main>
    @else
        <div class="flex min-h-screen" x-data="{ sidebarOpen: false }">

            {{-- OVERLAY (mobile saja) --}}
            <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 bg-black/40 z-40 md:hidden">
            </div>

            {{-- SIDEBAR --}}
            <aside class="w-64 bg-white border-r border-slate-100 flex-shrink-0 flex flex-col
                           fixed md:static inset-y-0 left-0 z-50 transition-transform duration-200
                           -translate-x-full md:translate-x-0" :class="sidebarOpen && '!translate-x-0'">

                <div class="px-6 py-6 flex items-center justify-between gap-2">
                    <div>
                        {{-- <div class="w-8 h-8 rounded-lg grad-purple"></div> --}}
                        {{-- <span class="text-lg font-bold text-slate-800">SiteFlow</span> --}}
                    </div>
                    <button @click="sidebarOpen = false" class="md:hidden text-slate-400">
                        <i class='bx bx-x text-2xl'></i>
                    </button>
                </div>

                <nav class="flex-1 px-3 space-y-1 overflow-y-auto">
                    @php
                        $menu = [
                            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bx-grid-alt'],
                            ['label' => 'CRM', 'route' => 'pages.crm', 'icon' => 'bx-group'],
                            ['label' => 'Project', 'route' => 'pages.projects', 'icon' => 'bx-briefcase'],
                            ['label' => 'Request Order', 'route' => 'pages.request', 'icon' => 'bx-list-plus'],
                            ['label' => 'Seo & Backlink', 'route' => 'pages.seo-backlink', 'icon' => 'bx-cube'],
                            ['label' => 'Mockup', 'route' => 'pages.mockup', 'icon' => 'bx-shape-square'],
                            // ['label' => 'Website', 'route' => 'pages.website', 'icon' => 'bx-code-alt'],
                            ['label' => 'Finance', 'route' => 'pages.finance', 'icon' => 'bx-wallet'],
                            ['label' => 'Reports', 'route' => 'pages.reports', 'icon' => 'bx-bar-chart-alt-2'],
                            ['label' => 'Settings', 'route' => 'pages.settings', 'icon' => 'bx-cog'],
                        ];
                    @endphp

                    @foreach ($menu as $item)
                        <a href="{{ route($item['route']) }}" @click="sidebarOpen = false" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-500 hover:bg-slate-50 transition
                                          {{ request()->routeIs($item['route']) ? 'nav-active' : '' }}">
                            <i class='bx {{ $item['icon'] }} text-lg'></i>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>

            {{-- MAIN --}}
            <div class="flex-1 flex flex-col min-w-0">

                {{-- TOPBAR --}}
                <header class="h-16 bg-white border-b border-slate-100 flex items-center justify-between px-4 md:px-8">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <button @click="sidebarOpen = true" class="md:hidden text-slate-500 flex-shrink-0">
                            <i class='bx bx-menu text-2xl'></i>
                        </button>
                        <div
                            class="hidden sm:flex items-center gap-2 text-slate-400 bg-slate-50 rounded-lg px-3 py-2 w-full max-w-xs md:w-80">
                            <i class='bx bx-search'></i>
                            <input type="text" placeholder="Search projects or clients..."
                                class="bg-transparent outline-none text-sm w-full text-slate-600 placeholder:text-slate-400">
                        </div>
                    </div>
                    <div class="flex items-center gap-3 md:gap-5 text-slate-400 flex-shrink-0">
                        <i class='bx bx-bell text-xl hidden sm:inline'></i>
                        <i class='bx bx-envelope text-xl hidden sm:inline'></i>
                        <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="block">
                                <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Guest') }}&background=4F7CF0&color=fff"
                                    class="w-8 h-8 rounded-full" alt="avatar">
                            </button>

                            <div x-show="open" @click.outside="open = false" x-cloak x-transition
                                class="absolute right-0 mt-3 w-40 bg-white rounded-xl border border-slate-100 shadow-lg p-2 z-50">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold text-red-500 hover:bg-red-50 transition">
                                        <i class='bx bx-log-out text-lg'></i>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="flex-1 p-4 md:p-8">
                    @yield('content')
                </main>
            </div>
        </div>
    @endif
</body>

</html>