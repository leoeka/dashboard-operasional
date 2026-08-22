@extends('layouts.app')
@section('title', 'Profile')

@section('content')

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-slate-800">Profile</h1>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-red-500 bg-red-50 hover:bg-red-100 transition">
                <i class='bx bx-log-out text-lg'></i>
                Logout
            </button>
        </form>
    </div>

    <x-card padding="p-0">
        <div class="grad-purple h-20 relative"></div>

        <div class="px-8 pb-8">
            <div class="flex items-center gap-4 pt-6 mb-6">
                <img src="https://ui-avatars.com/api/?name={{ urlencode($user['name']) }}&background=4F7CF0&color=fff&size=128"
                     class="w-20 h-20 rounded-full border-4 border-white flex-shrink-0" alt="avatar">
                <div class="pt-8">
                    <p class="text-lg font-bold text-slate-800">{{ $user['name'] }}</p>
                    <p class="text-sm text-slate-400">{{ $user['role'] ?? 'Not set' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm mb-8">
                <div>
                    <p class="text-slate-400 mb-1">Email</p>
                    <p class="font-medium text-slate-700">{{ $user['email'] }}</p>
                </div>
                <div>
                    <p class="text-slate-400 mb-1">Phone Number</p>
                    <p class="font-medium text-slate-700">{{ $user['phone'] ?? 'Not set' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 mb-1">Member Since</p>
                    <p class="font-medium text-slate-700">{{ $user['joined'] ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 mb-1">Role</p>
                    <p class="font-medium text-slate-700">{{ $user['role'] ?? 'Not set' }}</p>
                </div>
            </div>

            <button class="px-5 py-2.5 rounded-lg text-white text-sm font-semibold grad-blue hover:opacity-90 transition">
                Edit Profile
            </button>
        </div>
    </x-card>

@endsection