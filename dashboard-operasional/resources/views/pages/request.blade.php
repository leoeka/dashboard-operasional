@extends('layouts.app')
@section('title', 'Request Order')

@section('content')
    <div class="max-w-4xl mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-md">
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

        <form action="{{ route('clients.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6"
            id="requestForm">
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

            <!-- SECTION 2: PILIH KEBUTUHAN LAYANAN -->
            <div class="border-b pb-4">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">2. Kebutuhan Layanan <span
                        class="text-red-500">*</span></h3>
                <p class="text-xs text-gray-500 mb-3">Pilih satu atau lebih layanan yang dibutuhkan client (bisa kombinasi,
                    misal: Web + SEO, atau SEO + Backlink, atau ketiganya).</p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label
                        class="service-option flex items-center gap-3 border border-gray-300 rounded-md p-3 cursor-pointer hover:bg-blue-50 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500">
                        <input type="checkbox" name="service_type[]" value="website" id="chk-website"
                            class="service-checkbox h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            {{ in_array('website', old('service_type', [])) ? 'checked' : '' }}>
                        <span class="text-sm font-medium text-gray-700">Buat Website</span>
                    </label>

                    <label
                        class="service-option flex items-center gap-3 border border-gray-300 rounded-md p-3 cursor-pointer hover:bg-blue-50 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500">
                        <input type="checkbox" name="service_type[]" value="seo" id="chk-seo"
                            class="service-checkbox h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            {{ in_array('seo', old('service_type', [])) ? 'checked' : '' }}>
                        <span class="text-sm font-medium text-gray-700">SEO Website</span>
                    </label>

                    <label
                        class="service-option flex items-center gap-3 border border-gray-300 rounded-md p-3 cursor-pointer hover:bg-blue-50 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500">
                        <input type="checkbox" name="service_type[]" value="backlink" id="chk-backlink"
                            class="service-checkbox h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            {{ in_array('backlink', old('service_type', [])) ? 'checked' : '' }}>
                        <span class="text-sm font-medium text-gray-700">Backlink</span>
                    </label>
                </div>
                <p id="service-error" class="text-xs text-red-500 mt-2 hidden">Pilih minimal satu kebutuhan layanan.</p>
            </div>

            <!-- SECTION 3: DATA PROJECT & REQUIREMENTS (WEBSITE) -->
            <div class="border-b pb-4 service-section" id="section-website" style="display:none;">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">3. Kebutuhan Project Website</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipe Bisnis <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="business_type" placeholder="Contoh: E-commerce, F&B, Edukasi"
                            value="{{ old('business_type') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipe Website <span
                                class="text-red-500">*</span></label>
                        <select name="website_type"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
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
                        <textarea name="business_description" rows="3"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">{{ old('business_description') }}</textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Target Bisnis (Goal) <span
                                class="text-red-500">*</span></label>
                        <textarea name="business_goal" rows="3"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">{{ old('business_goal') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: KEBUTUHAN SEO -->
            <div class="border-b pb-4 service-section" id="section-seo" style="display:none;">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">4. Kebutuhan SEO</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2" id="seo-existing-url-wrapper">
                        <label class="block text-sm font-medium text-gray-700">URL Website yang Akan Dioptimasi <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="seo_target_url" placeholder="https://contohwebsite.com"
                            value="{{ old('seo_target_url') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
                        <p class="text-xs text-gray-500 mt-1" id="seo-note-baru" style="display:none;">
                            *Karena website juga dibuat baru, isi kolom ini setelah domain/URL final tersedia (boleh
                            dikosongkan dulu jika belum ada).
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Target Keyword <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="seo_keywords" placeholder="Pisahkan dengan koma"
                            value="{{ old('seo_keywords') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Target Lokasi/Area (jika ada)</label>
                        <input type="text" name="seo_location" placeholder="Contoh: Denpasar, Bali"
                            value="{{ old('seo_location') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    {{--
                        FIX: field ini sebelumnya ke-nested di dalam div
                        "Kompetitor" (md:col-span-2), bukan jadi grid item
                        sejajar — sekarang dipindah jadi sibling normal biar
                        tampil rapi 2 kolom sesuai grid section ini.
                    --}}
                   <!-- GANTI blok <select name="seo_cms_platform">...</select> yang lama
     (di dalam <div id="section-seo">) dengan ini: -->

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Platform Website Client <span
                                class="text-red-500">*</span></label>
                        <select name="seo_cms_platform"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
                            <option value="">-- Pilih Platform --</option>
                            <option value="wordpress" {{ old('seo_cms_platform') == 'wordpress' ? 'selected' : '' }}>WordPress</option>
                            <option value="baru" {{ old('seo_cms_platform') == 'baru' ? 'selected' : '' }}>Website Baru (dibuat oleh kami)</option>
                            <option value="lainnya" {{ old('seo_cms_platform') == 'lainnya' ? 'selected' : '' }}>Platform Lain (publish manual)</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            WordPress & Website Baru: artikel bisa dipublish otomatis. Platform lain: artikel diunduh/dikirim manual.
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Kompetitor (URL, opsional)</label>
                        <textarea name="seo_competitors" rows="2" placeholder="Satu URL per baris"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">{{ old('seo_competitors') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- SECTION 5: KEBUTUHAN BACKLINK -->
            <div class="border-b pb-4 service-section" id="section-backlink" style="display:none;">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">5. Kebutuhan Backlink</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2" id="backlink-existing-url-wrapper">
                        <label class="block text-sm font-medium text-gray-700">URL Tujuan Backlink <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="backlink_target_url" placeholder="https://contohwebsite.com"
                            value="{{ old('backlink_target_url') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jumlah Backlink <span
                                class="text-red-500">*</span></label>
                        <input type="number" min="1" name="backlink_quantity"
                            value="{{ old('backlink_quantity') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Anchor Text</label>
                        <input type="text" name="backlink_anchor_text" value="{{ old('backlink_anchor_text') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Niche / Kategori Backlink yang
                            Diinginkan</label>
                        <input type="text" name="backlink_niche" placeholder="Contoh: Travel, Bisnis, Teknologi"
                            value="{{ old('backlink_niche') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    {{--
                        FIX: 2 field ini sebelumnya ke-taruh di dalam
                        section-seo (salah tempat) — jadi cuma muncul kalau
                        centang SEO, padahal ini soal Backlink. Dipindah ke
                        sini, section-backlink, tempat yang benar.
                    --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jenis Anchor Text yang Diinginkan</label>
                        <select name="backlink_anchor_type[]" multiple
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="exact_match">Exact Match (persis keyword)</option>
                            <option value="partial_match">Partial Match</option>
                            <option value="branding">Branding (nama brand)</option>
                            <option value="generic">Generic (klik di sini, dst)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Prioritas</label>
                        <select name="backlink_priority"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="quality">Kualitas (situs otoritas tinggi, lebih sedikit)</option>
                            <option value="quantity">Kuantitas (lebih banyak, otoritas beragam)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECTION 6: ASSETS -->
            <div>
                <h3 class="text-lg font-semibold text-gray-700 mb-4">6. Upload Assets / Lampiran</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700">File Pendukung (Logo, Brief, Doc,
                        Gambar)</label>
                    <input type="file" name="assets[]" multiple
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-1">Bisa upload lebih dari 1 file (Format: JPG, PNG, PDF, DOCX, ZIP.
                        Maks: 10MB/file)</p>
                </div>
            </div>

            <!-- BUTTON SUBMIT -->
            <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-4">
                <a href="{{ route('pages.projects') }}"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 text-center">Batal</a>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold">
                    Simpan Draft
                </button>
            </div>
        </form>
    </div>

    <script>
        (function() {
            const checkboxes = {
                website: document.getElementById('chk-website'),
                seo: document.getElementById('chk-seo'),
                backlink: document.getElementById('chk-backlink'),
            };

            const sections = {
                website: document.getElementById('section-website'),
                seo: document.getElementById('section-seo'),
                backlink: document.getElementById('section-backlink'),
            };

            const seoNoteBaru = document.getElementById('seo-note-baru');
            const serviceError = document.getElementById('service-error');

            // Field wajib di tiap section, hanya "required" saat section-nya aktif
            function setConditionalRequired(section, isActive) {
                section.querySelectorAll('.conditional-required').forEach(field => {
                    field.required = isActive;
                });
            }

            function toggleSections() {
                Object.keys(checkboxes).forEach(key => {
                    const isChecked = checkboxes[key].checked;
                    sections[key].style.display = isChecked ? 'block' : 'none';
                    setConditionalRequired(sections[key], isChecked);
                });

                // Kondisi khusus kombinasi: Website + SEO dipilih bersamaan
                // -> URL SEO tidak wajib diisi manual (website belum jadi/URL belum final)
                const websiteChecked = checkboxes.website.checked;
                const seoChecked = checkboxes.seo.checked;
                const seoUrlInput = document.querySelector('input[name="seo_target_url"]');

                if (websiteChecked && seoChecked) {
                    seoUrlInput.required = false;
                    seoNoteBaru.style.display = 'block';
                } else if (seoChecked) {
                    seoUrlInput.required = true;
                    seoNoteBaru.style.display = 'none';
                }
            }

            Object.values(checkboxes).forEach(chk => {
                chk.addEventListener('change', toggleSections);
            });

            document.getElementById('requestForm').addEventListener('submit', function(e) {
                const anyChecked = Object.values(checkboxes).some(chk => chk.checked);
                if (!anyChecked) {
                    e.preventDefault();
                    serviceError.classList.remove('hidden');
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                } else {
                    serviceError.classList.add('hidden');
                }
            });

            // Jalankan sekali saat load (misal setelah validasi gagal & old() terisi kembali)
            toggleSections();
        })();
    </script>
@endsection