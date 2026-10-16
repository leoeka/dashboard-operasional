@extends('layouts.auth')
@section('title', 'Daftar')

@section('content')
    <div class="max-w-sm w-full bg-white rounded-xl border border-slate-200 overflow-hidden">

        <div class="bg-brand-600 p-6 text-center">
            <h1 class="text-2xl font-bold text-white">Buat akun SiteFlow</h1>
            <p class="text-xs font-semibold text-white mt-1">Isi data berikut untuk masuk pertama kali</p>
        </div>

        <div class="p-6">
            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-semibold text-slate-700 mb-2">Nama</label>
                    <x-text-input id="name" name="name" type="text" :value="old('name')" required autofocus
                        autocomplete="name" placeholder="Nama lengkap" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <label for="username" class="block text-sm font-semibold text-slate-700 mb-2">Username</label>
                    <x-text-input id="username" name="username" type="text" :value="old('username')" required
                        autocomplete="username" placeholder="Nama pengguna untuk masuk" />
                    <x-input-error :messages="$errors->get('username')" class="mt-2" />
                </div>

                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-2">Email</label>
                    <x-text-input id="email" name="email" type="email" :value="old('email')" required
                        autocomplete="username" placeholder="nama@perusahaan.com" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                    <x-text-input id="password" name="password" type="password" required autocomplete="new-password"
                        placeholder="Minimal 8 karakter" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-slate-700 mb-2">
                        Ulangi password
                    </label>
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" required
                        autocomplete="new-password" placeholder="Tulis ulang password yang sama" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between">
                    <a class="inline-flex items-center px-1 min-h-11 text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline"
                        href="{{ route('login') }}">Sudah punya akun?</a>

                    <x-primary-button>Daftar</x-primary-button>
                </div>
            </form>
        </div>
    </div>
@endsection
