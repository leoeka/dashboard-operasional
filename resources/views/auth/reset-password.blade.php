@extends('layouts.auth')
@section('title', 'Password Baru')

@section('content')
    <div class="max-w-sm w-full bg-white rounded-xl border border-slate-200 overflow-hidden">

        <div class="bg-brand-600 p-6 text-center">
            <h1 class="text-2xl font-bold text-white">Password baru</h1>
            <p class="text-xs font-semibold text-white mt-1">Pakai password yang belum pernah Anda gunakan</p>
        </div>

        <div class="p-6">
            <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-2">Email</label>
                    <x-text-input id="email" name="email" type="email" :value="old('email', $request->email)" required
                        autofocus autocomplete="username" />
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

                <div class="flex items-center justify-end">
                    <x-primary-button>Simpan password</x-primary-button>
                </div>
            </form>
        </div>
    </div>
@endsection
