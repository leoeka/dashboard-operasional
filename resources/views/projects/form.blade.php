@extends('layouts.app')
@section('title', 'Edit Project')

@section('content')

    @php
        // Old input hanya ada sesudah form ditolak validasi, jadi saat halaman
        // dibuka pertama kali checkbox diisi dari layanan yang sudah aktif di
        // proyek ini. Tanpa itu, menyimpan edit tanpa mencentang ulang membuat
        // wants_seo / wants_backlink mati diam-diam.
        $serviceTypes = session()->has('_old_input') ? (array) old('service_type', []) : $project->serviceTypes();

        // Satu definisi untuk semua kolom teks/pilihan. Kelasnya diulang di 15
        // tempat sebelumya, jadi tiap perbaikan tampilan harus diedit 15 kali.
        // Border slate-500 (4,76:1 di atas putih) dipakai supaya batas kolom
        // terbaca; bg putih supaya tidak terlihat seperti field yang dimatikan.
        $control =
            'block w-full min-h-11 rounded-md border border-slate-500 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 placeholder:text-slate-500';
    @endphp

    <x-card class="max-w-4xl mx-auto">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-slate-800">Edit Project</h2>
                <p class="text-sm text-slate-600 truncate">{{ $project->name }}</p>
            </div>

            <div class="w-full sm:w-56">
                <label for="statusSelect" class="text-xs font-semibold text-slate-600 mb-1 block">Status</label>
                {{-- Atribut form di bawah ini wajib: dropdown ini duduk di luar <form>,
                 jadi tanpa id form yang benar ia tidak ikut terkirim dan update()
                 menolak simpanan dengan "The status field is required." --}}
                <select id="statusSelect" name="status" form="projectForm" @class([$control, 'border-red-500' => $errors->has('status')])
                    @error('status') aria-invalid="true" aria-describedby="status-error" @enderror>
                    @foreach (['request' => 'Request', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('status')
                    <p id="status-error" class="mt-1 text-xs text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- grid + gap, bukan space-y: space-y ikut menghitung input tersembunyi
             di bawah ini, jadi field pertama dapat margin 24px yang tidak pernah diminta. --}}
        <form method="POST" id="projectForm" action="{{ route('pages.projects.update', $project) }}"
            enctype="multipart/form-data" class="grid gap-6">
            @csrf
            @method('PUT')

            {{-- Client tidak dipilih ulang di halaman ini, jadi identitasnya tetap
                 terkirim tersembunyi. Tanpa ini update() menuliskannya sebagai
                 null: client_name divalidasi required dan baris proyek jadi kosong. --}}
            <input type="hidden" name="client_id" value="{{ old('client_id', $project->client_id) }}">
            <input type="hidden" name="client_name" value="{{ old('client_name', $project->client_name) }}">

            {{-- Banner ini khusus field yang tidak tampil di layar. Field yang punya
                 tempat error sendiri, seperti status, sudah menampilkan pesannya di
                 bawah kontrolnya, jadi tidak perlu diulang di sini. --}}
            @php
                $hiddenErrors = [];

                foreach (['client_id', 'client_name'] as $hiddenField) {
                    $hiddenErrors = array_merge($hiddenErrors, $errors->get($hiddenField));
                }
            @endphp
            @if ($hiddenErrors !== [])
                <div class="text-sm text-red-700 bg-red-50 border-l-4 border-l-red-700 rounded-md p-4">
                    <ul class="list-disc space-y-1 pl-4">
                        @foreach ($hiddenErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label for="projectName" class="text-xs font-semibold text-slate-600 block mb-1">Project Name <span
                        class="text-red-700">*</span></label>
                <input type="text" id="projectName" name="name" value="{{ old('name', $project->name) }}"
                    class="{{ $control }}">
            </div>

            {{-- TILE LAYANAN: satu tile = satu panel di bawahnya. --}}
            <div class="space-y-2">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <label
                        class="flex items-center gap-3 min-h-11 border border-slate-500 rounded-md p-3 cursor-pointer hover:bg-brand-50 has-[:checked]:bg-brand-50 has-[:checked]:border-brand-600">
                        <input type="checkbox" name="service_type[]" value="website" id="chk-website"
                            class="service-checkbox h-5 w-5 text-brand-600 border-slate-500 rounded-md focus:ring-brand-500"
                            {{ in_array('website', $serviceTypes) ? 'checked' : '' }}>
                        <span class="text-sm font-semibold text-slate-800">Create Website</span>
                    </label>
                    <label
                        class="flex items-center gap-3 min-h-11 border border-slate-500 rounded-md p-3 cursor-pointer hover:bg-brand-50 has-[:checked]:bg-brand-50 has-[:checked]:border-brand-600">
                        <input type="checkbox" name="service_type[]" value="seo" id="chk-seo"
                            class="service-checkbox h-5 w-5 text-brand-600 border-slate-500 rounded-md focus:ring-brand-500"
                            {{ in_array('seo', $serviceTypes) ? 'checked' : '' }}>
                        <span class="text-sm font-semibold text-slate-800">SEO Website</span>
                    </label>
                    <label
                        class="flex items-center gap-3 min-h-11 border border-slate-500 rounded-md p-3 cursor-pointer hover:bg-brand-50 has-[:checked]:bg-brand-50 has-[:checked]:border-brand-600">
                        <input type="checkbox" name="service_type[]" value="backlink" id="chk-backlink"
                            class="service-checkbox h-5 w-5 text-brand-600 border-slate-500 rounded-md focus:ring-brand-500"
                            {{ in_array('backlink', $serviceTypes) ? 'checked' : '' }}>
                        <span class="text-sm font-semibold text-slate-800">Backlink</span>
                    </label>
                </div>
            </div>

            {{-- PANEL WEBSITE — isinya sama dengan bagian website di form Request. --}}
            <div class="service-section border border-slate-200 rounded-md p-4" id="section-website" style="display:none;">
                <h3 class="text-base font-semibold text-slate-800 mb-3">Website Project Requirements</h3>
                <div class="space-y-3">
                    <div>
                        <label for="websiteType" class="text-xs font-semibold text-slate-600 block mb-1">Website Type <span
                                class="text-red-700">*</span></label>
                        <input type="text" id="websiteType" name="type" value="{{ old('type', $project->type) }}"
                            class="{{ $control }} conditional-required">
                    </div>
                    <div>
                        <label for="userStory" class="text-xs font-semibold text-slate-600 block mb-1">Main Goal of This
                            Website <span class="text-red-700">*</span></label>
                        <textarea id="userStory" name="user_story" rows="3"
                            class="{{ $control }} conditional-required">{{ old('user_story', $project->description) }}</textarea>
                    </div>
                    <div>
                        <label for="targetMarket" class="text-xs font-semibold text-slate-600 block mb-1">Target Market /
                            Audience</label>
                        <textarea id="targetMarket" name="target_market" rows="2"
                            class="{{ $control }}">{{ old('target_market', $project->target_market) }}</textarea>
                    </div>
                    <div>
                        <label for="websiteFeatures" class="text-xs font-semibold text-slate-600 block mb-1">Content &amp;
                            Features Needed <span class="text-red-700">*</span></label>
                        <textarea id="websiteFeatures" name="website_features" rows="3"
                            class="{{ $control }} conditional-required">{{ old('website_features', $project->website_features) }}</textarea>
                    </div>

                    <div class="border-t border-slate-200 pt-3">
                        <h4 class="text-sm font-semibold text-slate-800">Brand &amp; Design Reference</h4>
                    </div>
                    <div>
                        <label for="clientLogo" class="text-xs font-semibold text-slate-600 block mb-1">Client logo</label>
                        <input type="file" id="clientLogo" name="client_logo" accept="image/png,image/jpeg,image/webp"
                            class="min-h-11 block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-50">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                                <label for="designReferenceType" class="block text-sm font-semibold text-slate-800">Design Reference Type</label>
                                <select name="design_reference_type" id="designReferenceType"
                                    class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                                    <option value="none" @selected(old('design_reference_type', 'none') === 'none')>No reference</option>
                                    <option value="image" @selected(old('design_reference_type') === 'image')>Upload image</option>
                                    <option value="zip" @selected(old('design_reference_type') === 'zip')>Upload ZIP template</option>
                                    <option value="url" @selected(old('design_reference_type') === 'url')>Website URL</option>
                                </select>
                            </div>
                        <div id="designReferenceFileField">
                            <label for="designReferenceFile" class="text-xs font-semibold text-slate-600 block mb-1">Reference
                                file</label>
                            <input type="file" id="designReferenceFile" name="design_reference_file"
                                accept="image/png,image/jpeg,image/webp,.zip"
                                class="min-h-11 block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-50">
                        </div>
                        <div id="designReferenceUrlField">
                            <label for="designReferenceUrl"
                                class="text-xs font-semibold text-slate-600 block mb-1">Reference website
                                URL</label>
                            <input type="url" id="designReferenceUrl" name="design_reference_url"
                                value="{{ old('design_reference_url', $project->design_reference_url) }}"
                                placeholder="https://" class="{{ $control }}">
                        </div>
                    </div>

                    @php
                        // Jawaban tersimpan bisa belum pernah diisi (null) pada proyek lama;
                        // radio tidak boleh memilih sendiri jawabannya kalau begitu.
                        $assetsAnswer = old('has_client_assets', is_null($project->has_client_assets) ? '' : (string) (int) $project->has_client_assets);
                    @endphp
                    <div>
                        <span class="text-xs font-semibold text-slate-600 block mb-1">Does the client have assets? (logo,
                            photos, documents) <span class="text-red-700">*</span></span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label
                                class="flex items-center gap-3 min-h-11 border border-slate-500 rounded-md p-3 cursor-pointer hover:bg-brand-50 has-[:checked]:bg-brand-50 has-[:checked]:border-brand-600">
                                <input type="radio" name="has_client_assets" value="1" id="clientAssetsYes"
                                    class="conditional-required h-5 w-5 text-brand-600 border-slate-500 rounded-full focus:ring-brand-500"
                                    @checked($assetsAnswer === '1')>
                                <span class="text-sm font-semibold text-slate-800">Yes</span>
                            </label>
                            <label
                                class="flex items-center gap-3 min-h-11 border border-slate-500 rounded-md p-3 cursor-pointer hover:bg-brand-50 has-[:checked]:bg-brand-50 has-[:checked]:border-brand-600">
                                <input type="radio" name="has_client_assets" value="0" id="clientAssetsNo"
                                    class="conditional-required h-5 w-5 text-brand-600 border-slate-500 rounded-full focus:ring-brand-500"
                                    @checked($assetsAnswer === '0')>
                                <span class="text-sm font-semibold text-slate-800">No</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION SEO — kondisional --}}
            <div class="service-section border border-slate-200 rounded-md p-4" id="section-seo" style="display:none;">
                <h3 class="text-base font-semibold text-slate-800 mb-2">SEO Requirements</h3>

                {{--
                AUTO-DETECT: kalau client yang dipilih sudah punya URL
                website dari project lain (seo_requirements.target_url),
                tampilkan sebagai saran — tim tinggal klik "Pakai URL ini",
                tidak perlu copy-paste manual. Tetap bisa diedit/diganti
                manual kalau perlu.
                --}}
                <div id="urlSuggestionBox"
                    class="hidden mb-3 p-4 bg-brand-50 border-l-4 border-l-brand-700 rounded-md text-sm">
                    <p class="text-brand-700 break-all">Website detected from this client's other project: <span
                            id="detectedUrlText" class="font-semibold"></span></p>
                    <button type="button" onclick="useDetectedUrl()"
                        class="min-h-11 mt-1 inline-flex items-center px-1 rounded-md text-xs font-semibold text-brand-600 hover:text-brand-700 outline-none focus:ring-2 focus:ring-brand-500">Use
                        this URL</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label for="seoTargetUrl" class="text-xs font-semibold text-slate-600 mb-1 block">Website
                            URL</label>
                        <input type="text" name="seo_target_url" id="seoTargetUrl"
                            value="{{ old('seo_target_url', $project->seo_requirements['target_url'] ?? '') }}"
                            class="{{ $control }}">
                        <p class="text-xs text-slate-600 mt-1">The system reads this URL for the keyword and competitor
                            analysis. When it was previously empty, filling it in starts that analysis automatically
                            after saving.</p>

                        <button type="button" id="btnAnalyzePreview" disabled
                            class="min-h-11 mt-2 inline-flex items-center gap-2 text-xs font-semibold px-4 py-2 rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            Analyze Now
                        </button>
                        <p class="text-xs text-slate-600 mt-1">Check keywords and competitors before saving (optional).</p>

                        <div id="previewProgress" class="hidden mt-2">
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div id="previewProgressBar" class="bg-brand-500 h-2 rounded-full transition-all"
                                    style="width:0%"></div>
                            </div>
                            <p id="previewProgressMessage" class="text-xs text-slate-600 mt-1"></p>
                        </div>

                        <div id="previewError"
                            class="hidden mt-2 text-sm text-red-700 bg-red-50 border-l-4 border-l-red-700 rounded-md p-4">
                        </div>

                        <div id="previewResult" class="hidden mt-3 border-t border-slate-200 pt-3 space-y-3"></div>

                        <input type="hidden" name="analysis_token" id="analysisToken" value="">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="seoLocation"
                                class="text-xs font-semibold text-slate-600 mb-1 block">Location/Area</label>
                            <input type="text" id="seoLocation" name="seo_location"
                                value="{{ old('seo_location', $project->seo_requirements['location'] ?? '') }}"
                                class="{{ $control }}">
                        </div>
                        <div>
                            <label for="seoCmsPlatform" class="text-xs font-semibold text-slate-600 mb-1 block">Client's
                                Website Platform <span class="text-red-700">*</span></label>
                            <select id="seoCmsPlatform" name="seo_cms_platform"
                                class="{{ $control }} conditional-required">
                                <option value="">-- Select Platform --</option>
                                <option value="wordpress"
                                    {{ old('seo_cms_platform', $project->seo_requirements['cms_platform'] ?? '') == 'wordpress' ? 'selected' : '' }}>
                                    WordPress</option>
                                <option value="baru"
                                    {{ old('seo_cms_platform', $project->seo_requirements['cms_platform'] ?? '') == 'baru' ? 'selected' : '' }}>
                                    New Website (built by us)</option>
                                <option value="lainnya"
                                    {{ old('seo_cms_platform', $project->seo_requirements['cms_platform'] ?? '') == 'lainnya' ? 'selected' : '' }}>
                                    Other Platform (manual publish)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION BACKLINK — kondisional --}}
            <div class="service-section border border-slate-200 rounded-md p-4" id="section-backlink"
                style="display:none;">
                <h3 class="text-base font-semibold text-slate-800 mb-2">Backlink Requirements</h3>
                <div class="space-y-3">
                    <div>
                        <label for="backlinkTargetUrl" class="text-xs font-semibold text-slate-600 mb-1 block">Backlink
                            Target URL <span class="text-red-700">*</span></label>
                        <input type="text" name="backlink_target_url" id="backlinkTargetUrl"
                            value="{{ old('backlink_target_url', $project->backlink_requirements['target_url'] ?? '') }}"
                            class="{{ $control }} conditional-required">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="backlinkQuantity" class="text-xs font-semibold text-slate-600 mb-1 block">Backlink
                                Quantity <span class="text-red-700">*</span></label>
                            <input type="number" min="1" id="backlinkQuantity" name="backlink_quantity"
                                value="{{ old('backlink_quantity', $project->backlink_requirements['quantity'] ?? '') }}"
                                class="{{ $control }} conditional-required">
                        </div>
                        <div>
                            <label for="backlinkPriority"
                                class="text-xs font-semibold text-slate-600 mb-1 block">Priority</label>
                            @php $priority = old('backlink_priority', $project->backlink_requirements['priority'] ?? 'quality'); @endphp
                            <select id="backlinkPriority" name="backlink_priority" class="{{ $control }}">
                                <option value="quality" @selected($priority === 'quality')>Quality</option>
                                <option value="quantity" @selected($priority === 'quantity')>Quantity</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="backlinkNiche" class="text-xs font-semibold text-slate-600 mb-1 block">Niche /
                            Category</label>
                        <input type="text" id="backlinkNiche" name="backlink_niche"
                            value="{{ old('backlink_niche', $project->backlink_requirements['niche'] ?? '') }}"
                            class="{{ $control }}">
                    </div>
                </div>
            </div>

            {{-- Status tetap ditampilkan: di seluruh aplikasi ini hanya dropdown ini
                 yang bisa memindahkan tahap sebuah project. Hilangkannya berarti
                 status tidak bisa diubah lagi dari mana pun. --}}
            <div class="flex flex-wrap items-end justify-between gap-4 border-t border-slate-200 pt-4">
                <button type="submit"
                    class="min-h-11 inline-flex items-center bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-md hover:bg-brand-700 outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition">
                    Save Changes
                </button>
            </div>
        </form>
    </x-card>

    <script>
        // Peta client_id => daftar URL website yang sudah diketahui dari
        // project lain milik client itu. Dikirim server-side sebagai JSON,
        // dipakai JS untuk auto-detect tanpa perlu AJAX call.
        const clientWebsiteUrls = @json($clientWebsiteUrls);

        function useDetectedUrl() {
            const box = document.getElementById('urlSuggestionBox');
            document.getElementById('seoTargetUrl').value = box.dataset.url || '';
        }

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
            const seoUrlInput = document.getElementById('seoTargetUrl');
            const backlinkUrlInput = document.getElementById('backlinkTargetUrl');

            function setConditionalRequired(section, isActive) {
                section.querySelectorAll('.conditional-required').forEach(field => {
                    field.required = isActive;
                });
            }

            function toggleSection(key, isActive) {
                sections[key].style.display = isActive ? 'block' : 'none';
                setConditionalRequired(sections[key], isActive);
            }

            function toggleSections() {
                toggleSection('website', checkboxes.website.checked);
                toggleSection('seo', checkboxes.seo.checked);
                toggleSection('backlink', checkboxes.backlink.checked);
            }

            // FIX (fitur SEO & Backlink otomatis): tim cukup isi 1 URL —
            // kalau kolom URL backlink masih kosong saat checkbox Backlink
            // dicentang / saat URL SEO diisi, salin otomatis dari URL SEO.
            function autoFillBacklinkUrl() {
                if (checkboxes.backlink.checked && backlinkUrlInput && !backlinkUrlInput.value && seoUrlInput &&
                    seoUrlInput.value) {
                    backlinkUrlInput.value = seoUrlInput.value;
                }
            }

            checkboxes.website.addEventListener('change', toggleSections);
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
            seoUrlInput?.addEventListener('input', function() {
                updateAnalyzeButtonState();
                if (analysisTokenInput && analysisTokenInput.value) {
                    analysisTokenInput.value = '';
                    previewResult.classList.add('hidden');
                    previewResult.innerHTML = '';
                }
            });
            updateAnalyzeButtonState();
            analyzeBtn?.addEventListener('click', function() {
                previewError.classList.add('hidden');
                previewResult.classList.add('hidden');
                previewResult.innerHTML = '';
                analysisTokenInput.value = '';

                fetch(PREVIEW_ANALYZE_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                                '',
                        },
                        body: JSON.stringify({
                            url: seoUrlInput.value.trim(),
                            location: document.querySelector('[name="seo_location"]')?.value || '',
                            business_name: document.querySelector('[name="client_name"]')?.value ||
                                '',
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
                        previewError.textContent = 'Failed to start analysis. Please try again.';
                        previewError.classList.remove('hidden');
                    });
            });

            function checkPreviewStatus(token) {
                fetch(`${PREVIEW_STATUS_URL}?token=${encodeURIComponent(token)}`, {
                        headers: {
                            Accept: 'application/json'
                        }
                    })
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
                            previewError.textContent = data.message || 'Analysis failed.';
                            previewError.classList.remove('hidden');
                            analysisTokenInput.value = '';
                        }
                    })
                    .catch(() => {});
            }

            function renderPreviewResult(data) {
                const rec = data.recommendations || {};
                const topics = data.topics || {};
                const competitors = data.competitor_urls || [];
                let html = '';

                if (topics.core_topics && topics.core_topics.length) {
                    html += '<div><p class="text-xs text-slate-600 mb-1">Detected topics</p><div>' +
                        topics.core_topics.map(t =>
                            `<span class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-1 rounded-md mr-1 mb-1">${escapeHtml(t)}</span>`
                        ).join('') +
                        '</div></div>';
                }

                if (rec.main_keywords && rec.main_keywords.length) {
                    html +=
                        '<div><p class="text-xs text-slate-600 mb-2">Top 10 Keywords</p><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-xs text-slate-600 border-b border-slate-200"><th class="pb-1 pr-3">Keyword</th><th class="pb-1 pr-3 text-right">Volume/mo</th><th class="pb-1">Competition</th></tr></thead><tbody>';
                    rec.main_keywords.forEach(kw => {
                        html +=
                            `<tr class="border-b border-slate-100"><td class="py-1 pr-3 font-semibold text-slate-700">${escapeHtml(kw.keyword)}</td><td class="py-1 pr-3 text-right font-mono text-slate-600">${escapeHtml(kw.avg_monthly_searches ?? '-')}</td><td class="py-1 text-slate-600">${escapeHtml(kw.competition ?? '-')}</td></tr>`;
                    });
                    html += '</tbody></table></div></div>';
                }

                if (competitors.length) {
                    html +=
                        '<div><p class="text-xs text-slate-600 mb-1">Competitors found</p><ul class="text-sm text-brand-600 space-y-1">' +
                        competitors.map(u =>
                            `<li><a href="${encodeURI(u)}" target="_blank" rel="noopener" class="hover:underline break-all">${escapeHtml(u)}</a></li>`
                        ).join('') +
                        '</ul></div>';
                }

                if (rec.summary) {
                    html +=
                        `<div><p class="text-xs text-slate-600 mb-1">Summary</p><p class="text-sm text-slate-600">${escapeHtml(rec.summary)}</p></div>`;
                }

                html +=
                    '<p class="text-xs text-slate-700 bg-slate-50 border-l-4 border-l-slate-700 rounded-md p-4">This result will be automatically saved once you click Save Changes.</p>';

                previewResult.innerHTML = html;
                previewResult.classList.remove('hidden');
            }

            toggleSections();

            // Field referensi mengikuti jenisnya, sama seperti form Request:
            // "No reference" tidak menyisakan kolom apa pun. Kontrol yang
            // disembunyikan ikut dimatikan supaya nilai lama tidak ikut terkirim.
            const designReferenceType = document.getElementById('designReferenceType');
            const designReferenceFileField = document.getElementById('designReferenceFileField');
            const designReferenceUrlField = document.getElementById('designReferenceUrlField');

            function toggleDesignReferenceFields() {
                const type = designReferenceType?.value || 'none';
                const showFile = ['image', 'zip'].includes(type);
                const showUrl = type === 'url';
                designReferenceFileField.style.display = showFile ? 'block' : 'none';
                designReferenceUrlField.style.display = showUrl ? 'block' : 'none';
                const fileInput = document.getElementById('designReferenceFile');
                const urlInput = document.getElementById('designReferenceUrl');
                if (fileInput) fileInput.disabled = !showFile;
                if (urlInput) urlInput.disabled = !showUrl;
            }

            designReferenceType?.addEventListener('change', toggleDesignReferenceFields);
            toggleDesignReferenceFields();

            // Client tidak dipilih lagi di halaman ini, jadi auto-detect URL
            // dijalankan sekali memakai client yang sudah melekat di proyek.
            const detectedUrls = clientWebsiteUrls["{{ $project->client_id }}"] || [];
            if (detectedUrls.length > 0) {
                const box = document.getElementById('urlSuggestionBox');
                document.getElementById('detectedUrlText').textContent = detectedUrls[0];
                box.dataset.url = detectedUrls[0];
                box.classList.remove('hidden');
            }
        })();
    </script>

@endsection
