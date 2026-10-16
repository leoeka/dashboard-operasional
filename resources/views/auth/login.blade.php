@extends('layouts.auth')
@section('title', 'Login')

@section('content')
    <div class="max-w-sm w-full bg-white rounded-xl border border-slate-200 overflow-hidden">

        <div class="bg-brand-600 p-6 text-center">
            <div
                class="w-12 h-12 rounded-lg bg-white/10 flex items-center justify-center mx-auto mb-3">
                <i class='bx bx-grid-alt text-3xl text-white' aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">SiteFlow</h1>
            <p class="text-xs font-semibold text-white mt-1">Masuk untuk mengelola project Anda</p>
        </div>

        <div class="p-6">
            @if (session('status'))
                <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 rounded-md border-l-4 border-l-emerald-700 p-4">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="username" class="block text-sm font-semibold text-slate-700 mb-2">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-500">
                            <i class='bx bx-user text-xl' aria-hidden="true"></i>
                        </span>
                        <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus
                            placeholder="Masukkan username"
                            class="w-full pl-12 pr-4 min-h-11 rounded-md border border-slate-500 text-slate-800 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 transition placeholder:text-slate-500" />
                    </div>
                    @error('username')
                        <p class="mt-2 text-xs text-red-700 flex items-center gap-2">
                            <i class='bx bx-error-circle' aria-hidden="true"></i> {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-500">
                            <i class='bx bx-lock-alt text-xl' aria-hidden="true"></i>
                        </span>
                        <input id="password" type="password" name="password" required
                            placeholder="Masukkan password"
                            class="w-full pl-12 pr-4 min-h-11 rounded-md border border-slate-500 text-slate-800 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 transition placeholder:text-slate-500" />
                    </div>
                    @error('password')
                        <p class="mt-2 text-xs text-red-700 flex items-center gap-2">
                            <i class='bx bx-error-circle' aria-hidden="true"></i> {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <label for="remember_me"
                        class="inline-flex items-center gap-2 cursor-pointer min-h-11 px-1 text-xs font-semibold text-slate-600">
                        <input id="remember_me" type="checkbox" name="remember"
                            class="w-5 h-5 rounded-md border-slate-500 text-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        Ingat saya
                    </label>

                    @if (Route::has('password.request'))
                        <a class="inline-flex items-center px-1 min-h-11 text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline"
                            href="{{ route('password.request') }}">
                            Lupa password?
                        </a>
                    @endif
                </div>

                <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                    <span>Masuk</span>
                    <i class='bx bx-right-arrow-alt text-xl' aria-hidden="true"></i>
                </button>
            </form>
        </div>

        <div class="bg-slate-50 border-t border-slate-100 py-4 text-center">
            <p class="text-xs text-slate-600">&copy; {{ date('Y') }} SiteFlow. Hak cipta dilindungi.</p>
        </div>
    </div>
@endsection
