@extends('layouts.app')
@section('title', 'Edit Project')

@section('content')

    <a href="{{ route('pages.projects.show', $project) }}"
        class="text-sm text-slate-400 flex items-center gap-1 mb-4 hover:text-slate-600">
        <i class='bx bx-arrow-back'></i> Kembali
    </a>

    <x-page-header title="Edit Project" />

    <x-card class="max-w-2xl">
        <form method="POST" id="projectForm" action="{{ route('pages.projects.update', $project) }}"
            class="space-y-5">
            @csrf
            @method('PUT')

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
                        <p class="text-xs text-slate-400 mt-1">Ini yang dipakai sistem untuk analisis AI (keyword &
                            kompetitor) begitu disimpan — kalau sebelumnya kosong, analisis akan otomatis jalan sekali
                            setelah URL ini diisi.</p>

                        <button type="button" id="btnAnalyzePreview" disabled
                            class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg bg-brand-500 text-white hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                            Analisis Sekarang
                        </button>
                        <p class="text-xs text-slate-400 mt-1">Cek preview keyword & kompetitor sebelum disimpan (opsional).
                        </p>

                        <div id="previewProgress" class="hidden mt-2">
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div id="previewProgressBar" class="bg-brand-500 h-2 rounded-full transition-all"
                                    style="width:0%"></div>
                            </div>
                            <p id="previewProgressMessage" class="text-xs text-slate-500 mt-1"></p>
                        </div>

                        <div id="previewError"
                            class="hidden mt-2 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3"></div>

                        <div id="previewResult" class="hidden mt-3 border-t pt-3 space-y-3"></div>

                        <input type="hidden" name="analysis_token" id="analysisToken" value="">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 mb-1 block">Lokasi/Area</label>
                        <input type="text" name="seo_location" placeholder="Contoh: Denpasar, Bali"
                            value="{{ old('seo_location', $project->seo_requirements['location'] ?? '') }}"
                            class="w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
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
                        <input type="text" name="backlink_target_url" id="backlinkTargetUrl"
                            placeholder="https://contohwebsite.com"
                            value="{{ old('backlink_target_url', $project->backlink_requirements['target_url'] ?? '') }}"
                            class="conditional-required w-full bg-slate-50 text-slate-700 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                        <p class="text-xs text-slate-400 mt-1">Biasanya sama dengan URL website di section SEO —
                            otomatis disalin kalau dikosongkan.</p>
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

            <div>
                <div>
                    <label class="text-sm font-medium text-slate-600 mb-1 block">Status</label>
                    <select name="status"
                        class="w-full bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                        @foreach (['request' => 'Request', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button type="submit"
                class="grad-blue text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan Perubahan
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
            const seoUrlInput = document.getElementById('seoTargetUrl');
            const backlinkUrlInput = document.getElementById('backlinkTargetUrl');

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

            // FIX (fitur SEO & Backlink otomatis): tim cukup isi 1 URL —
            // kalau kolom URL backlink masih kosong saat checkbox Backlink
            // dicentang / saat URL SEO diisi, salin otomatis dari URL SEO.
            function autoFillBacklinkUrl() {
                if (checkboxes.backlink.checked && backlinkUrlInput && !backlinkUrlInput.value && seoUrlInput && seoUrlInput.value) {
                    backlinkUrlInput.value = seoUrlInput.value;
                }
            }

            checkboxes.seo.addEventListener('change', toggleSections);
            checkboxes.backlink.addEventListener('change', toggleSections);
            checkboxes.backlink.addEventListener('change', autoFillBacklinkUrl);
            seoUrlInput?.addEventListener('blur', autoFillBacklinkUrl);

            // FIX (fitur SEO & Backlink otomatis, mode PREVIEW): sama
            // seperti di request.blade.php — analisis AI sebelum submit.
            const analyzeBtn = document.getElementById('btnAnalyzePreview');
            const previewProgress = document.getElementById('previewProgress');
            const previewProgressBar = document.getElementById('previewProgressBar');
            const previewProgressMessage = document.getElementById('previewProgressMessage');
            const previewError = document.getElementById('previewError');
            const previewResult = document.getElementById('previewResult');
            const analysisTokenInput = document.getElementById('analysisToken');
            const PREVIEW_ANALYZE_URL = "{{ route('pages.seo-backlink.preview.analyze') }}";
            const PREVIEW_STATUS_URL = "{{ route('pages.seo-backlink.preview.status') }}";
            let previewPollTimer = null;

            function escapeHtml(str) {
                const div = document.createElement('div');
                div.textContent = str ?? '';
                return div.innerHTML;
            }

            function updateAnalyzeButtonState() {
                if (analyzeBtn) analyzeBtn.disabled = !seoUrlInput || !seoUrlInput.value.trim();
            }
            seoUrlInput?.addEventListener('input', function () {
                updateAnalyzeButtonState();
                if (analysisTokenInput && analysisTokenInput.value) {
                    analysisTokenInput.value = '';
                    previewResult.classList.add('hidden');
                    previewResult.innerHTML = '';
                }
            });
            updateAnalyzeButtonState();
            analyzeBtn?.addEventListener('click', function () {
                previewError.classList.add('hidden');
                previewResult.classList.add('hidden');
                previewResult.innerHTML = '';
                analysisTokenInput.value = '';

                fetch(PREVIEW_ANALYZE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        url: seoUrlInput.value.trim(),
                        location: document.querySelector('[name="seo_location"]')?.value || '',
                        business_name: document.querySelector('[name="client_name"]')?.value || '',
                        business_type: document.querySelector('[name="type"]')?.value || '',
                    }),
                })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.token) throw new Error('no token');
                        analysisTokenInput.value = data.token;
                        previewProgress.classList.remove('hidden');
                        clearInterval(previewPollTimer);
                        previewPollTimer = setInterval(() => checkPreviewStatus(data.token), 2500);
                        checkPreviewStatus(data.token);
                    })
                    .catch(() => {
                        previewError.textContent = 'Gagal memulai analisis. Coba lagi.';
                        previewError.classList.remove('hidden');
                    });
            });

            function checkPreviewStatus(token) {
                fetch(`${PREVIEW_STATUS_URL}?token=${encodeURIComponent(token)}`, { headers: { Accept: 'application/json' } })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'queued' || data.status === 'running') {
                            previewProgressBar.style.width = (data.progress || 0) + '%';
                            previewProgressMessage.textContent = data.message || '';
                        } else if (data.status === 'done') {
                            clearInterval(previewPollTimer);
                            previewProgress.classList.add('hidden');
                            renderPreviewResult(data);
                        } else if (data.status === 'failed') {
                            clearInterval(previewPollTimer);
                            previewProgress.classList.add('hidden');
                            previewError.textContent = data.message || 'Analisis gagal.';
                            previewError.classList.remove('hidden');
                            analysisTokenInput.value = '';
                        }
                    })
                    .catch(() => { });
            }

            function renderPreviewResult(data) {
                const rec = data.recommendations || {};
                const topics = data.topics || {};
                const competitors = data.competitor_urls || [];
                let html = '';

                if (topics.core_topics && topics.core_topics.length) {
                    html += '<div><p class="text-xs text-slate-400 mb-1">Topik terdeteksi</p><div>' +
                        topics.core_topics.map(t => `<span class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-full mr-1 mb-1">${escapeHtml(t)}</span>`).join('') +
                        '</div></div>';
                }

                if (rec.main_keywords && rec.main_keywords.length) {
                    html += '<div><p class="text-xs text-slate-400 mb-2">10 Keyword Utama</p><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-xs text-slate-400 border-b"><th class="pb-1 pr-3">Keyword</th><th class="pb-1 pr-3">Volume/bln</th><th class="pb-1">Persaingan</th></tr></thead><tbody>';
                    rec.main_keywords.forEach(kw => {
                        html += `<tr class="border-b border-slate-50"><td class="py-1 pr-3 font-medium text-slate-700">${escapeHtml(kw.keyword)}</td><td class="py-1 pr-3 text-slate-500">${escapeHtml(kw.avg_monthly_searches ?? '-')}</td><td class="py-1 text-slate-500">${escapeHtml(kw.competition ?? '-')}</td></tr>`;
                    });
                    html += '</tbody></table></div></div>';
                }

                if (competitors.length) {
                    html += '<div><p class="text-xs text-slate-400 mb-1">Kompetitor ditemukan</p><ul class="text-sm text-blue-600 space-y-0.5">' +
                        competitors.map(u => `<li><a href="${encodeURI(u)}" target="_blank" rel="noopener" class="hover:underline break-all">${escapeHtml(u)}</a></li>`).join('') +
                        '</ul></div>';
                }

                if (rec.summary) {
                    html += `<div><p class="text-xs text-slate-400 mb-1">Ringkasan</p><p class="text-sm text-slate-600">${escapeHtml(rec.summary)}</p></div>`;
                }

                html += '<p class="text-xs text-emerald-600">&#10003; Hasil ini akan otomatis tersimpan begitu Anda klik Simpan Perubahan.</p>';

                previewResult.innerHTML = html;
                previewResult.classList.remove('hidden');
            }

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