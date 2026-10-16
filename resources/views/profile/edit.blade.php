@extends('layouts.app')
@section('title', 'Profile')

@section('content')

    <x-page-header title="Profile" />

    <div class="space-y-6 max-w-xl">
        <x-card>
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card>
            @include('profile.partials.update-password-form')
        </x-card>

        <x-card>
            @include('profile.partials.delete-user-form')
        </x-card>
    </div>

@endsection
