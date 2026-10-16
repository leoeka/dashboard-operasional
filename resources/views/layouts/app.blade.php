<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <x-app.head />
</head>

<body class="min-h-screen">
    <div class="flex min-h-screen" x-data="{ sidebarOpen: false }">

        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 bg-black/40 z-40 md:hidden">
        </div>

        <x-app.sidebar />

        <div class="flex-1 flex flex-col min-w-0">
            <x-app.topbar />

            <main class="flex-1 px-6 py-6">
                <div class="mx-auto max-w-[1400px]">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>

</html>
