@extends('layouts.auth')
@section('title', 'Verifikasi Email')

@section('content')
    <div class="max-w-sm w-full bg-white rounded-xl border border-slate-200 overflow-hidden">

        <div class="bg-brand-600 p-6 text-center">
            <h1 class="text-2xl font-bold text-white">Verifikasi email Anda</h1>
            <p class="text-xs font-semibold text-white mt-1">Klik tautan di email yang baru kami kirim</p>
        </div>

        <div class="p-6 space-y-4">
            @if (session('status') == 'verification-link-sent')
                <div class="text-sm text-emerald-700 bg-emerald-50 rounded-md border-l-4 border-l-emerald-700 p-4">
                    Tautan verifikasi baru sudah dikirim ke email yang Anda daftarkan.
                </div>
            @endif

            <p class="text-sm text-slate-600">
                Belum menerima emailnya? Kirim ulang lewat tombol di bawah, atau keluar dan masuk dengan akun lain.
            </p>

            <div class="flex items-center justify-between gap-3">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <x-primary-button>Kirim ulang email</x-primary-button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center px-1 min-h-11 text-xs font-semibold text-slate-700 hover:text-slate-900 hover:underline">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
