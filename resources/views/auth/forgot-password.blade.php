@extends('layouts.auth')
@section('title', 'Lupa Password')

@section('content')
    <div class="max-w-sm w-full bg-white rounded-xl border border-slate-200 overflow-hidden">

        <div class="bg-brand-600 p-6 text-center">
            <h1 class="text-2xl font-bold text-white">Atur ulang password</h1>
            <p class="text-xs font-semibold text-white mt-1">Tautan reset dikirim ke email akun Anda</p>
        </div>

        <div class="p-6">
            @if (session('status'))
                <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 rounded-md border-l-4 border-l-emerald-700 p-4">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-2">Email</label>
                    <x-text-input id="email" name="email" type="email" :value="old('email')" required autofocus
                        placeholder="nama@perusahaan.com" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between">
                    <a class="inline-flex items-center px-1 min-h-11 text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline"
                        href="{{ route('login') }}">Kembali ke login</a>

                    <x-primary-button>Kirim tautan</x-primary-button>
                </div>
            </form>
        </div>
    </div>
@endsection
