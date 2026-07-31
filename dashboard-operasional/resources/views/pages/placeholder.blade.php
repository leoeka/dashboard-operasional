@extends('layouts.app')
@section('title', $title)

@section('content')

    <div class="flex items-center gap-2 mb-6">
        <h1 class="text-xl font-bold text-slate-800">{{ $title }}</h1>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 p-16 flex flex-col items-center text-center">
        <div class="w-16 h-16 rounded-2xl grad-purple flex items-center justify-center mb-4">
            <i class='bx bx-wrench text-2xl text-white'></i>
        </div>
        <p class="font-semibold text-slate-700 mb-1">Halaman ini sedang dikembangkan</p>
        <p class="text-sm text-slate-400 max-w-md">{{ $description }}</p>
    </div>

@endsection
