@extends('layouts.app')
@section('title', 'Mockup Website')

@section('content')
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- HEADER SECTION -->
        <div
            class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Mockup Website</h1>
                <p class="text-sm text-gray-500 mt-1">Manage website mockup templates</p>
            </div>
            <div>
                <button
                    class="grad-blue text-white text-sm font-semibold px-4 py-2.5 rounded-lg flex items-center gap-2 hover:opacity-90 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Mockup
                </button>
            </div>
        </div>

        <!-- FILTER & SEARCH BAR SECTION (RAPI HARIZONTAL) -->
        <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
            <form action="{{ route('pages.mockup') }}" method="GET" class="flex flex-col md:flex-row gap-3 items-center">

                <!-- Input Search -->
                <div class="relative flex-1 w-full">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search mockup..."
                        class="w-full pl-9 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                </div>

                <!-- Dropdown Filter Horizontal -->
                <div class="flex flex-wrap md:flex-nowrap gap-3 w-full md:w-auto">
                    <select name="is_premium" onchange="this.form.submit()"
                        class="w-full md:w-36 py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                        <option value="">Status</option>
                        <option value="free" {{ request('is_premium') == 'free' ? 'selected' : '' }}>Free</option>
                        <option value="premium" {{ request('is_premium') == 'premium' ? 'selected' : '' }}>Premium</option>
                    </select>

                    <select name="page_builder" onchange="this.form.submit()"
                        class="w-full md:w-40 py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                        <option value="">Builder</option>
                        <option value="elementor" {{ request('page_builder') == 'elementor' ? 'selected' : '' }}>Elementor</option>
                        <option value="betheme" {{ request('page_builder') == 'betheme' ? 'selected' : '' }}>BeTheme</option>
                        <option value="divi" {{ request('page_builder') == 'divi' ? 'selected' : '' }}>Divi</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- MOCKUP GRID CARDS (STATIS TEMPORARY) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            @forelse($templates as $template)
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition">
                    <div class="h-48 bg-gray-200 overflow-hidden relative">
                        <iframe src="{{ $template['preview_url'] }}" class="pointer-events-none"
                            style="width: 400%; height: 400%; transform: scale(0.25); transform-origin: 0 0; border: 0;"
                            loading="lazy" sandbox="allow-same-origin">
                        </iframe>
                    </div>
                    <div class="p-5">
                        <h3 class="font-bold text-gray-800 text-lg">{{ $template['name'] }}</h3>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="bg-blue-50 text-blue-600 text-xs px-2.5 py-1 rounded-md font-medium">
                                {{ $template['categories'][0] ?? '-' }}
                            </span>
                            <span class="text-xs text-gray-500 font-medium">• {{ ucfirst($template['page_builder']) }}</span>
                            @if($template['is_premium'])
                                <span class="text-xs text-amber-600 font-semibold">★ Premium</span>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-5 pt-4 border-t border-gray-50">
                            <a href="{{ $template['preview_url'] }}" target="_blank"
                                class="w-full py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition text-center">Preview</a>
                            <button
                                class="w-full py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-semibold rounded-lg transition">Edit</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="col-span-full text-center text-gray-400 py-10">No mockups found.</p>
            @endforelse

        </div>

        @if($lastPage > 1)
            <div class="flex justify-center gap-2 mt-6">
                @for($i = 1; $i <= $lastPage; $i++)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $i]) }}"
                        class="px-3 py-1.5 rounded-lg text-sm {{ $currentPage == $i ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600' }}">
                        {{ $i }}
                    </a>
                @endfor
            </div>
        @endif

    </div>
@endsection