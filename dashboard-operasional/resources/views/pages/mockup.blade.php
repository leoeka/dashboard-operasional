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
            <div class="flex flex-col md:flex-row gap-3 items-center">

                <!-- Input Search -->
                <div class="relative flex-1 w-full">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" placeholder="Search mockup..."
                        class="w-full pl-9 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                </div>

                <!-- Dropdown Filter Horizontal -->
                <div class="flex flex-wrap md:flex-nowrap gap-3 w-full md:w-auto">
                    <select
                        class="w-full md:w-40 py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                        <option value="">Industry ▼</option>
                        <option value="spa">Spa</option>
                        <option value="restaurant">Restaurant</option>
                        <option value="hotel">Hotel</option>
                    </select>

                    <select
                        class="w-full md:w-40 py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                        <option value="">Builder ▼</option>
                        <option value="elementor">Elementor</option>
                        <option value="betheme">BeTheme</option>
                        <option value="divi">Divi</option>
                    </select>

                    <select
                        class="w-full md:w-36 py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                        <option value="">Status ▼</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>

            </div>
        </div>

        <!-- MOCKUP GRID CARDS (STATIS TEMPORARY) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            <!-- Card 1 -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition">
                <div
                    class="h-48 bg-gray-200 flex items-center justify-center text-gray-400 font-semibold uppercase tracking-wider">
                    Preview Image
                </div>
                <div class="p-5">
                    <h3 class="font-bold text-gray-800 text-lg">Luxury Spa</h3>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="bg-blue-50 text-blue-600 text-xs px-2.5 py-1 rounded-md font-medium">Spa</span>
                        <span class="text-xs text-gray-500 font-medium">• Elementor</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-5 pt-4 border-t border-gray-50">
                        <button
                            class="w-full py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition">Preview</button>
                        <button
                            class="w-full py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-semibold rounded-lg transition">Edit</button>
                    </div>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition">
                <div
                    class="h-48 bg-gray-200 flex items-center justify-center text-gray-400 font-semibold uppercase tracking-wider">
                    Preview Image
                </div>
                <div class="p-5">
                    <h3 class="font-bold text-gray-800 text-lg">Modern Restaurant</h3>
                    <div class="flex items-center gap-2 mt-2">
                        <span
                            class="bg-green-50 text-green-600 text-xs px-2.5 py-1 rounded-md font-medium">Restaurant</span>
                        <span class="text-xs text-gray-500 font-medium">• Elementor</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-5 pt-4 border-t border-gray-50">
                        <button
                            class="w-full py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition">Preview</button>
                        <button
                            class="w-full py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-semibold rounded-lg transition">Edit</button>
                    </div>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition">
                <div
                    class="h-48 bg-gray-200 flex items-center justify-center text-gray-400 font-semibold uppercase tracking-wider">
                    Preview Image
                </div>
                <div class="p-5">
                    <h3 class="font-bold text-gray-800 text-lg">Bali Hotel</h3>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="bg-purple-50 text-purple-600 text-xs px-2.5 py-1 rounded-md font-medium">Hotel</span>
                        <span class="text-xs text-gray-500 font-medium">• BeTheme</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-5 pt-4 border-t border-gray-50">
                        <button
                            class="w-full py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition">Preview</button>
                        <button
                            class="w-full py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-semibold rounded-lg transition">Edit</button>
                    </div>
                </div>
            </div>

        </div>

    </div>
@endsection