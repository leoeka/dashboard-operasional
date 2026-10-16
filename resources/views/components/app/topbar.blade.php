<header class="h-16 bg-white border-b border-slate-100 flex items-center justify-between px-4 md:px-8">
    <button @click="sidebarOpen = true"
        class="md:hidden inline-flex items-center justify-center gap-2 min-h-11 min-w-11 px-1 text-slate-600 flex-shrink-0" aria-label="Buka menu">
        <i class='bx bx-menu text-2xl' aria-hidden="true"></i>
        <span class="text-sm font-semibold">Menu</span>
    </button>
    <div class="flex-1"></div>
    <div class="flex items-center gap-3 md:gap-6 text-slate-500 flex-shrink-0">
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" :aria-expanded="open" aria-haspopup="true"
                aria-label="Menu akun" class="inline-flex items-center justify-center w-11 h-11">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Guest') }}&background=2f5bc4&color=fff"
                    class="w-8 h-8 rounded-full" alt="Foto profil {{ auth()->user()->name ?? 'Guest' }}">
            </button>

            <div x-show="open" @click.outside="open = false" x-cloak x-transition
                class="absolute right-0 mt-3 w-40 bg-white rounded-xl border border-slate-100 shadow-lg p-2 z-50">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full flex items-center gap-2 px-3 min-h-11 rounded-md text-sm font-semibold text-red-700 hover:bg-red-50 transition">
                        <i class='bx bx-log-out text-lg'></i>
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
