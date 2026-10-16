@extends('layouts.auth')
@section('title', 'Konfirmasi Password')

@section('content')
    <div class="max-w-sm w-full bg-white rounded-xl border border-slate-200 overflow-hidden">

        <div class="bg-brand-600 p-6 text-center">
            <h1 class="text-2xl font-bold text-white">Konfirmasi password</h1>
            <p class="text-xs font-semibold text-white mt-1">Area ini aman. Masukan password Anda lagi untuk lanjut.</p>
        </div>

        <div class="p-6">
            <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                    <x-text-input id="password" name="password" type="password" required
                        autocomplete="current-password" placeholder="Password akun Anda" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between">
                    <a class="inline-flex items-center px-1 min-h-11 text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline"
                        href="{{ route('login') }}">Batal</a>

                    <x-primary-button>Konfirmasi</x-primary-button>
                </div>
            </form>
        </div>
    </div>
@endsection
