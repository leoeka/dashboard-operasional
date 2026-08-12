@extends('layouts.app')
@section('title', $project->exists ? 'Edit Project' : 'Tambah Project')

@section('content')

    <a href="{{ $project->exists ? route('pages.projects.show', $project) : route('pages.projects') }}"
        class="text-sm text-slate-400 flex items-center gap-1 mb-4 hover:text-slate-600">
        <i class='bx bx-arrow-back'></i> Kembali
    </a>

    <x-page-header :title="$project->exists ? 'Edit Project' : 'Tambah Project'" />

    <x-card class="max-w-2xl">
        <form method="POST" id="projectForm"
            action="{{ $project->exists ? route('pages.projects.update', $project) : route('pages.projects.store') }}"
            class="space-y-5">
            @csrf
            @if ($project->exists) @method('PUT') @endif

            <div>
                <label class="text-sm font-medium text-slate-600 mb-1 block">Client (dari CRM)</label>
                <select name="client_id" id="clientSelect"
                    onchange="document.querySelector('[name=client_name]').value = this.options[this.selectedIndex].dataset.name || ''; onClientChange(this.value);"
                    class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">-- Tanpa data client di CRM --</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}" data-name="{{ $c->company_name }}" @selected(old('client_id', $project->client_id) == $c->id)>
                            {{ $c->company_name }} ({{ $c->contact_name }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-600 mb-1 block">Nama Project</label>
                <input type="text" name="name" value="{{ old('name', $project->name) }}" placeholder="mis. Website PT ABC"
                    class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                @error('name')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-600 mb-1 block">Nama Client (tampil di tabel)</label>
                <input type="text" name="client_name" value="{{ old('client_name', $project->client_name) }}"
                    class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                @error('client_name')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-slate-400 mt-1">Otomatis terisi kalau pilih Client di atas. Bisa diedit manual kalau
                    perlu.</p>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-600 mb-1 block">Jenis Website</label>
                <input type="text" name="type" value="{{ old('type', $project->type) }}"
                    placeholder="mis. Company Profile, E-commerce"
                    class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            {{--
            KEBUTUHAN LAYANAN — checkbox tetap 3 opsi (Website/SEO/Backlink)
            supaya form ini tetap fleksibel dipakai untuk berbagai skenario
            (client baru sekalian bikin website, atau client LAMA yang cuma
            mau tambah SEO/Backlink tanpa website baru — tinggal tidak
            dicentang "Website"-nya).
            --}}
            <div class="border border-slate-200 rounded-lg p-4">
                <label class="text-sm font-medium text-slate-600 mb-2 block">Kebutuhan Layanan</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <label
                        class="flex items-center gap-2 border border-slate-200 rounded-lg p-2.5 cursor-pointer hover:bg-slate-50">
                        <input type="checkbox" name="service_type[]" value="website" id="chk-website"
                            class="service-checkbox" {{ in_array('website', old('service_type', [])) ? 'checked' : '' }}>
                        <span class="text-sm text-slate-700">Website</span>
                    </label>
                    <label
                        class="flex items-center gap-2 border border-slate-200 rounded-lg p-2.5 cursor-pointer hover:bg-slate-50">
                        <input type="checkbox" name="service_type[]" value="seo" id="chk-seo" class="service-checkbox" {{ in_array('seo', old('service_type', [])) ? 'checked' : '' }}>
                        <span class="text-sm text-slate-700">SEO</span>
                    </label>
                    <label
                        class="flex items-center gap-2 border border-slate-200 rounded-lg p-2.5 cursor-pointer hover:bg-slate-50">
                        <input type="checkbox" name="service_type[]" value="backlink" id="chk-backlink"
                            class="service-checkbox" {{ in_array('backlink', old('service_type', [])) ? 'checked' : '' }}>
                        <span class="text-sm text-slate-700">Backlink</span>
                    </label>
                </div>
                <p class="text-xs text-slate-400 mt-2">
                    Untuk client yang sudah punya project Website sebelumnya, biarkan "Website" TIDAK dicentang kalau
                    project ini khusus untuk SEO/Backlink saja.
                </p>
            </div>

            {{-- SECTION SEO — kondisional --}}
            <div class="service-section border border-slate-200 rounded-lg p-4" id="section-seo" style="display:none;">
                <label class="text-sm font-medium text-slate-600 mb-2 block">Kebutuhan SEO</label>

                {{--
                AUTO-DETECT: kalau client yang dipilih sudah punya URL
                website dari project lain (zipwp_site_url ATAU
                seo_requirements sebelumnya), tampilkan sebagai saran —
                tim tinggal klik "Pakai URL ini", tidak perlu copy-paste
                manual. Tetap bisa diedit/diganti manual kalau perlu.
                --}}
                <div id="urlSuggestionBox" class="hidden mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm">
                    <p class="text-blue-700">Website terdeteksi dari project lain client ini: <span id="detectedUrlText"
                            class="font-medium"></span></p>
                    <button type="button" onclick="useDetectedUrl()"
                        class="mt-1 text-xs font-semibold text-blue-600 hover:text-blue-800">Pakai URL ini</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-slate-500 mb-1 block">URL Website</label>
                        <input type="text" name="seo_target_url" id="seoTargetUrl" placeholder="https://contohwebsite.com"
                            value="{{ old('seo_target_url', $project->seo_requirements['target_url'] ?? '') }}"
                            class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 mb-1 block">Target Keyword</label>
                        <input type="text" name="seo_keywords"
                            placeholder="Pisahkan dengan koma (opsional, boleh dikosongkan)"
                            value="{{ old('seo_keywords', $project->seo_requirements['keywords'] ?? '') }}"
                            class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 mb-1 block">Lokasi/Area</label>
                        <input type="text" name="seo_location" placeholder="Contoh: Denpasar, Bali"
                            value="{{ old('seo_location', $project->seo_requirements['location'] ?? '') }}"
                            class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 mb-1 block">Kompetitor (URL, opsional — boleh dikosongkan,
                            sistem bisa cari otomatis)</label>
                        <textarea name="seo_competitors" rows="2" placeholder="Satu URL per baris"
                            class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">{{ old('seo_competitors', $project->seo_requirements['competitors'] ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 mb-1 block">Platform Website Client <span
                                class="text-red-500">*</span></label>
                        <select name="seo_cms_platform"
                            class="conditional-required w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="">-- Pilih Platform --</option>
                            <option value="wordpress" {{ old('seo_cms_platform', $project->seo_requirements['cms_platform'] ?? '') == 'wordpress' ? 'selected' : '' }}>WordPress</option>
                            <option value="baru" {{ old('seo_cms_platform', $project->seo_requirements['cms_platform'] ?? '') == 'baru' ? 'selected' : '' }}>Website Baru (dibuat oleh kami)</option>
                            <option value="lainnya" {{ old('seo_cms_platform', $project->seo_requirements['cms_platform'] ?? '') == 'lainnya' ? 'selected' : '' }}>Platform Lain (publish manual)</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- SECTION BACKLINK — kondisional --}}
            <div class="service-section border border-slate-200 rounded-lg p-4" id="section-backlink" style="display:none;">
                <label class="text-sm font-medium text-slate-600 mb-2 block">Kebutuhan Backlink</label>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-slate-500 mb-1 block">URL Tujuan Backlink <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="backlink_target_url" placeholder="https://contohwebsite.com"
                            value="{{ old('backlink_target_url', $project->backlink_requirements['target_url'] ?? '') }}"
                            class="conditional-required w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 mb-1 block">Jumlah Backlink <span
                                class="text-red-500">*</span></label>
                        <input type="number" min="1" name="backlink_quantity"
                            value="{{ old('backlink_quantity', $project->backlink_requirements['quantity'] ?? '') }}"
                            class="conditional-required w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 mb-1 block">Niche / Kategori</label>
                        <input type="text" name="backlink_niche" placeholder="Contoh: Travel, Bisnis, Teknologi"
                            value="{{ old('backlink_niche', $project->backlink_requirements['niche'] ?? '') }}"
                            class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 mb-1 block">Prioritas</label>
                        <select name="backlink_priority"
                            class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="quality">Kualitas</option>
                            <option value="quantity">Kuantitas</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-600 mb-1 block">Status</label>
                    <select name="status"
                        class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                        @foreach (['request' => 'Request', 'proposal' => 'Proposal', 'mockup' => 'Mockup', 'development' => 'Development', 'qa' => 'QA', 'done' => 'Selesai'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 mb-1 block">Deadline</label>
                    <input type="date" name="deadline" value="{{ old('deadline', $project->deadline?->format('Y-m-d')) }}"
                        class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>

            <button type="submit"
                class="grad-blue text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                {{ $project->exists ? 'Simpan Perubahan' : 'Buat Project' }}
            </button>
        </form>
    </x-card>

    <script>
        // Peta client_id => daftar URL website yang sudah diketahui dari
        // project lain milik client itu. Dikirim server-side sebagai JSON,
        // dipakai JS untuk auto-detect tanpa perlu AJAX call.
        const clientWebsiteUrls = @json($clientWebsiteUrls);

        function onClientChange(clientId) {
            const box = document.getElementById('urlSuggestionBox');
            const text = document.getElementById('detectedUrlText');
            const urls = clientWebsiteUrls[clientId];

            if (urls && urls.length > 0) {
                text.textContent = urls[0];
                box.dataset.url = urls[0];
                box.classList.remove('hidden');
            } else {
                box.classList.add('hidden');
            }
        }

        function useDetectedUrl() {
            const box = document.getElementById('urlSuggestionBox');
            document.getElementById('seoTargetUrl').value = box.dataset.url || '';
        }

        (function () {
            const checkboxes = {
                website: document.getElementById('chk-website'),
                seo: document.getElementById('chk-seo'),
                backlink: document.getElementById('chk-backlink'),
            };
            const sections = {
                seo: document.getElementById('section-seo'),
                backlink: document.getElementById('section-backlink'),
            };

            function setConditionalRequired(section, isActive) {
                section.querySelectorAll('.conditional-required').forEach(field => {
                    field.required = isActive;
                });
            }

            function toggleSections() {
                sections.seo.style.display = checkboxes.seo.checked ? 'block' : 'none';
                setConditionalRequired(sections.seo, checkboxes.seo.checked);

                sections.backlink.style.display = checkboxes.backlink.checked ? 'block' : 'none';
                setConditionalRequired(sections.backlink, checkboxes.backlink.checked);
            }

            checkboxes.seo.addEventListener('change', toggleSections);
            checkboxes.backlink.addEventListener('change', toggleSections);

            toggleSections();

            // Kalau form ini dibuka dalam mode EDIT dan client sudah
            // terpilih dari awal, jalankan auto-detect sekali saat load.
            const clientSelect = document.getElementById('clientSelect');
            if (clientSelect.value) {
                onClientChange(clientSelect.value);
            }
        })();
    </script>

@endsection