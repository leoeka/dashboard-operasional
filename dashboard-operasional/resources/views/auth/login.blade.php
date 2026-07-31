@extends('layouts.app')
@section('title', 'Login')
@section('auth', true)

@section('content')
<div class="max-w-md w-full bg-white rounded-2xl border border-slate-100 shadow-xl overflow-hidden">
    
    {{-- Header Card --}}
    <div class="grad-purple p-8 text-white relative text-center">
        <div class="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center mx-auto mb-3 shadow-inner">
            <i class='bx bx-grid-alt text-3xl text-white'></i>
        </div>
        <h2 class="text-2xl font-bold">SiteFlow</h2>
        <p class="text-xs font-medium opacity-90 mt-1">Masuk untuk mengelola proyek kamu</p>
    </div>

    <div class="p-8">
        {{-- Session Status --}}
        @if (session('status'))
            <div class="mb-4 text-sm font-medium text-emerald-600 bg-emerald-50 p-3 rounded-lg border border-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <!-- Username -->
            <div>
                <label for="username" class="block text-sm font-semibold text-slate-700 mb-2">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <i class='bx bx-user text-xl'></i>
                    </span>
                    <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus
                           placeholder="Masukkan username"
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all placeholder:text-slate-400" />
                </div>
                @error('username')
                    <p class="mt-2 text-xs text-rose-500 flex items-center gap-1">
                        <i class='bx bx-error-circle'></i> {{ $message }}
                    </p>
                @enderror
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <i class='bx bx-lock-alt text-xl'></i>
                    </span>
                    <input id="password" type="password" name="password" required
                           placeholder="Masukkan Password"
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all placeholder:text-slate-400" />
                </div>
                @error('password')
                    <p class="mt-2 text-xs text-rose-500 flex items-center gap-1">
                        <i class='bx bx-error-circle'></i> {{ $message }}
                    </p>
                @enderror
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="flex items-center justify-between pt-1">
                <label for="remember_me" class="inline-flex items-center cursor-pointer">
                    <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate-300 text-brand-500 shadow-sm focus:ring-brand-500">
                    <span class="ms-2 text-xs text-slate-600 font-medium">Ingat Saya</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="text-xs text-brand-500 hover:text-brand-700 font-semibold transition-colors" href="{{ route('password.request') }}">
                        Lupa password?
                    </a>
                @endif
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button type="submit" class="w-full grad-blue text-white font-semibold py-3 px-4 rounded-xl shadow-md hover:opacity-95 transition-all flex items-center justify-center gap-2">
                    <span>Masuk Sekarang</span>
                    <i class='bx bx-right-arrow-alt text-xl'></i>
                </button>
            </div>
        </form>
    </div>

    <div class="bg-slate-50 border-t border-slate-100 py-4 text-center">
        <p class="text-xs text-slate-400">&copy; {{ date('Y') }} SiteFlow. All rights reserved.</p>
    </div>
</div>
@endsection