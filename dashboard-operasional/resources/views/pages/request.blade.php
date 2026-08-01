@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')
    <div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-gray-800">Tambah Client & Draft Project Baru</h2>

        {{-- Pesan Alert Error --}}
        @if (session('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                {{ session('error') }}
            </div>
        @endif

        {{-- Alert Kesalahan Validasi Input --}}
        @if ($errors->any())
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-600 rounded">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('clients.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- SECTION 1: DATA CLIENT -->
            <div class="border-b pb-4">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">1. Informasi Client</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nama Contact/Client <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="client_name" value="{{ old('client_name') }}" required
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nama Perusahaan <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="company_name" value="{{ old('company_name') }}" required
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nomor Telepon / WhatsApp</label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Alamat</label>
                        <textarea name="address" rows="2"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">{{ old('address') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: DATA PROJECT & REQUIREMENTS -->
            <div class="border-b pb-4">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">2. Kebutuhan Project</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipe Bisnis <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="business_type" placeholder="Contoh: E-commerce, F&B, Edukasi"
                            value="{{ old('business_type') }}" required
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipe Website <span
                                class="text-red-500">*</span></label>
                        <select name="website_type" required
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Pilih Tipe Website --</option>
                            <option value="Company Profile"
                                {{ old('website_type') == 'Company Profile' ? 'selected' : '' }}>Company Profile</option>
                            <option value="Online Shop / E-Commerce"
                                {{ old('website_type') == 'Online Shop / E-Commerce' ? 'selected' : '' }}>Online Shop /
                                E-Commerce</option>
                            <option value="Landing Page" {{ old('website_type') == 'Landing Page' ? 'selected' : '' }}>
                                Landing Page</option>
                            <option value="Custom Web Application"
                                {{ old('website_type') == 'Custom Web Application' ? 'selected' : '' }}>Custom Web
                                Application</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Deskripsi Bisnis <span
                                class="text-red-500">*</span></label>
                        <textarea name="business_description" rows="3" required
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">{{ old('business_description') }}</textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Tujuan Bisnis (Goal) <span
                                class="text-red-500">*</span></label>
                        <textarea name="business_goal" rows="3" required
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">{{ old('business_goal') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: ASSETS -->
            <div>
                <h3 class="text-lg font-semibold text-gray-700 mb-4">3. Upload Assets / Lampiran</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700">File Pendukung (Logo, Brief, Doc, Gambar)</label>
                    <input type="file" name="assets[]" multiple
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-1">Bisa upload lebih dari 1 file (Format: JPG, PNG, PDF, DOCX, ZIP.
                        Maks: 10MB/file)</p>
                </div>
            </div>

            <!-- BUTTON SUBMIT -->
            <div class="flex justify-end gap-3 pt-4">
                <a href="{{ route('pages.projects') }}"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">Batal</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold">
                    Simpan Draft
                </button>
            </div>
        </form>
    </div>
@endsection
