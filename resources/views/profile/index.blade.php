@extends('layouts.app')
@section('title', 'Profile')

@section('content')

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-slate-800">Profile</h1>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="min-h-11 inline-flex items-center gap-2 px-4 py-2 rounded-md border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                <i class='bx bx-log-out text-lg'></i>
                Logout
            </button>
        </form>
    </div>

    <x-card padding="p-0">
        <div class="bg-brand-600 h-20 relative"></div>

        <div class="px-8 pb-8">
            <div class="flex items-center gap-4 pt-6 mb-6">
                <img src="https://ui-avatars.com/api/?name={{ urlencode($user['name']) }}&background=4F7CF0&color=fff&size=128"
                     class="w-20 h-20 rounded-full border-4 border-white flex-shrink-0" alt="avatar">
                <div class="pt-8">
                    <p class="text-base font-bold text-slate-800">{{ $user['name'] }}</p>
                    <p class="text-sm text-slate-600">{{ $user['role'] ?? 'Not set' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm mb-8">
                <div>
                    <p class="text-slate-600 mb-1">Email</p>
                    <p class="font-semibold text-slate-700">{{ $user['email'] }}</p>
                </div>
                <div>
                    <p class="text-slate-600 mb-1">Phone Number</p>
                    <p class="font-semibold text-slate-700">{{ $user['phone'] ?? 'Not set' }}</p>
                </div>
                <div>
                    <p class="text-slate-600 mb-1">Member Since</p>
                    <p class="font-semibold text-slate-700">{{ $user['joined'] ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-slate-600 mb-1">Role</p>
                    <p class="font-semibold text-slate-700">{{ $user['role'] ?? 'Not set' }}</p>
                </div>
            </div>

            <a href="{{ route('profile.edit') }}" class="inline-block px-4 py-3 rounded-md text-white text-sm font-semibold bg-brand-600 hover:bg-brand-700 transition">
                Edit Profile
            </a>
        </div>
    </x-card>

@endsection
