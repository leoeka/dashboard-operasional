@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')

    <div class="max-w-7xl mx-auto">
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Daftar Client</h1>
                <p class="text-sm text-gray-600">Kelola data klien dan project terkait.</p>
            </div>
            <div>
                <a href="{{ route('pages.request') }}"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition shadow">
                    + Tambah Client Baru
                </a>
            </div>
        </div>

        <!-- ALERT NOTIFIKASI -->
        @if (session('success'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <!-- FITUR PENCARIAN -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <form action="{{ route('pages.crm') }}" method="GET" class="flex gap-2">
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Cari berdasarkan perusahaan, nama, email, atau HP..."
                    class="w-full md:w-1/3 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit"
                    class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-900">
                    Cari
                </button>
                @if (request('search'))
                    <a href="{{ route('pages.crm') }}"
                        class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-300">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <!-- TABEL DATA CLIENT -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            <th class="px-6 py-3">Perusahaan</th>
                            <th class="px-6 py-3">Nama Kontak</th>
                            <th class="px-6 py-3">Kontak Info</th>
                            <th class="px-6 py-3">Jumlah Project</th>
                            <th class="px-6 py-3">Tanggal Dibuat</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($clients as $client)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 font-semibold text-gray-900">
                                    {{ $client->company_name }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ $client->contact_name ?? '-' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        @if ($client->email)
                                            <span class="text-xs text-gray-600">✉ {{ $client->email }}</span>
                                        @endif
                                        @if ($client->phone)
                                            <span class="text-xs text-gray-600">📞 {{ $client->phone }}</span>
                                        @endif
                                        @if (!$client->email && !$client->phone)
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">
                                        {{ $client->projects->count() }} Project
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500">
                                    {{ $client->created_at ? $client->created_at->format('d M Y') : '-' }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <a href="{{ route('pages.crm-view', $client->id) }}"
                                        class="inline-block bg-blue-50 text-blue-600 hover:bg-blue-100 px-3 py-1 rounded text-xs font-medium border border-blue-200">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    Belum ada data client.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div class="p-4 border-t border-gray-200">
                {{ $clients->links() }}
            </div>
        </div>
    </div>
@endsection
