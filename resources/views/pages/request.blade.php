@extends('layouts.app')
@section('title', 'Request Order')

@section('content')
    <div class="max-w-4xl mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-gray-800">Add Client & New Draft Project</h2>

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
                <h3 class="text-lg font-semibold text-gray-700 mb-4">1. Client Information</h3>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Select Client</label>
                    <select name="client_id" id="clientSelect"
                        class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- New Client --</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}"
                                data-contact="{{ $client->contact_name }}"
                                data-company="{{ $client->company_name }}"
                                data-email="{{ $client->email }}"
                                data-phone="{{ $client->phone }}"
                                data-address="{{ $client->address }}"
                                @selected(old('client_id') == $client->id)>
                                {{ $client->company_name }} - {{ $client->contact_name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Select an existing client to create a new request without duplicating client data.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="newClientFields">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Contact/Client Name <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="client_name" value="{{ old('client_name') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Company Name <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="company_name" value="{{ old('company_name') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone Number / WhatsApp</label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea name="address" rows="2"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">{{ old('address') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: PILIH KEBUTUHAN LAYANAN -->
            <div class="border-b pb-4">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">2. Service Requirements <span
                        class="text-red-500">*</span></h3>
                <p class="text-xs text-gray-500 mb-3">Select one or more services the client needs (combinations allowed,
                    e.g. Web + SEO, or SEO + Backlink, or all three).</p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label
                        class="service-option flex items-center gap-3 border border-gray-300 rounded-md p-3 cursor-pointer hover:bg-blue-50 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500">
                        <input type="checkbox" name="service_type[]" value="website" id="chk-website"
                            class="service-checkbox h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            {{ in_array('website', old('service_type', [])) ? 'checked' : '' }}>
                        <span class="text-sm font-medium text-gray-700">Create Website</span>
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
                <p id="service-error" class="text-xs text-red-500 mt-2 hidden">Select at least one service requirement.</p>
            </div>

            <!-- SECTION 3: DATA PROJECT & REQUIREMENTS (WEBSITE) -->
            <div class="border-b pb-4 service-section" id="section-website" style="display:none;">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">3. Website Project Requirements</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Business Type <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="business_type" placeholder="e.g. E-commerce, F&B, Education"
                            value="{{ old('business_type') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Website Type <span
                                class="text-red-500">*</span></label>
                        <select name="website_type"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
                            <option value="">-- Select Website Type --</option>
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
                        <label class="block text-sm font-medium text-gray-700">Business Description <span
                                class="text-red-500">*</span></label>
                        <textarea name="business_description" rows="3"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">{{ old('business_description') }}</textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Business Target (Goal) <span
                                class="text-red-500">*</span></label>
                        <textarea name="business_goal" rows="3"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">{{ old('business_goal') }}</textarea>
                    </div>

                    <div class="md:col-span-2 border-t border-gray-200 pt-4">
                        <h4 class="text-base font-semibold text-gray-700 mb-2">Sample Mockup / Design Reference</h4>
                        <label class="block text-sm font-medium text-gray-700">Attachments from Client</label>
                        <input type="file" name="assets[]" multiple
                            class="website-asset-input mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-500 mt-1">Upload design samples, logo, brief, or other references (JPG, PNG, PDF, DOCX, ZIP; max. 10MB/file).</p>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: KEBUTUHAN SEO -->
            <div class="border-b pb-4 service-section" id="section-seo" style="display:none;">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">4. SEO Requirements</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                   <div class="md:col-span-2" id="seo-existing-url-wrapper">
                        <label class="block text-sm font-medium text-gray-700">Website URL to Optimize <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="seo_target_url" id="seo_target_url"
                            placeholder="https://examplewebsite.com" value="{{ old('seo_target_url') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
                        <p class="text-xs text-gray-500 mt-1" id="seo-note-baru" style="display:none;">
                            *Since the website is also being newly built, fill in this field once the final domain/URL is
                            available (you may leave it blank for now if not yet available — automatic SEO analysis will wait until this URL is filled in).
                        </p>

                        <button type="button" id="btnAnalyzePreview" disabled
                            class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            Analyze Now
                        </button>
                        <p class="text-xs text-gray-400 mt-1">Preview keywords & competitors before saving (optional).</p>

                        <div id="previewProgress" class="hidden mt-2">
                            <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                <div id="previewProgressBar" class="bg-blue-600 h-2 rounded-full transition-all" style="width:0%"></div>
                            </div>
                            <p id="previewProgressMessage" class="text-xs text-gray-500 mt-1"></p>
                        </div>

                        <div id="previewError" class="hidden mt-2 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3"></div>

                        <div id="previewResult" class="hidden mt-3 border-t pt-3 space-y-3"></div>

                        <input type="hidden" name="analysis_token" id="analysisToken" value="">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Target Location/Area (if any)</label>
                        <input type="text" name="seo_location" placeholder="e.g. Denpasar, Bali"
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
                        <label class="block text-sm font-medium text-gray-700">Client Website Platform <span
                                class="text-red-500">*</span></label>
                        <select name="seo_cms_platform"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
                            <option value="">-- Select Platform --</option>
                            <option value="wordpress" {{ old('seo_cms_platform') == 'wordpress' ? 'selected' : '' }}>WordPress</option>
                            <option value="baru" {{ old('seo_cms_platform') == 'baru' ? 'selected' : '' }}>New Website (built by us)</option>
                            <option value="lainnya" {{ old('seo_cms_platform') == 'lainnya' ? 'selected' : '' }}>Other Platform (manual publish)</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            WordPress & New Website: articles can be published automatically. Other platforms: articles are downloaded/sent manually.
                        </p>
                    </div>
                </div>
            </div>

            <!-- SECTION 5: KEBUTUHAN BACKLINK -->
            <div class="border-b pb-4 service-section" id="section-backlink" style="display:none;">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">5. Backlink Requirements</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2" id="backlink-existing-url-wrapper">
                        <label class="block text-sm font-medium text-gray-700">Backlink Target URL <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="backlink_target_url" id="backlink_target_url"
                            placeholder="https://examplewebsite.com" value="{{ old('backlink_target_url') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500 conditional-required">
                        <p class="text-xs text-gray-500 mt-1">Usually the same as the website URL above — automatically
                            copied if left blank.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Number of Backlinks <span
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
                        <label class="block text-sm font-medium text-gray-700">Desired Backlink Niche /
                            Category</label>
                        <input type="text" name="backlink_niche" placeholder="Optional — e.g. Travel, Business, Technology"
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
                        <label class="block text-sm font-medium text-gray-700">Desired Anchor Text Type</label>
                        <select name="backlink_anchor_type[]" multiple
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="exact_match">Exact Match (exact keyword)</option>
                            <option value="partial_match">Partial Match</option>
                            <option value="branding">Branding (brand name)</option>
                            <option value="generic">Generic (click here, etc.)</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Optional — can be left blank for now and adjusted later
                            after the team reviews the SEO analysis results.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Priority</label>
                        <select name="backlink_priority"
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="quality">Quality (fewer, high-authority sites)</option>
                            <option value="quantity">Quantity (more sites, varied authority)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- BUTTON SUBMIT -->
            <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-4">
                <a href="{{ route('pages.projects') }}"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 text-center">Cancel</a>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold">
                    Save Draft
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
            const seoUrlInput = document.getElementById('seo_target_url');
            const backlinkUrlInput = document.getElementById('backlink_target_url');
            const clientSelect = document.getElementById('clientSelect');
            const newClientFields = document.getElementById('newClientFields');

            function toggleClientMode() {
                const selected = clientSelect?.selectedOptions[0];
                const isExistingClient = Boolean(clientSelect?.value);

                newClientFields?.querySelectorAll('input, textarea').forEach(field => {
                    field.disabled = isExistingClient;
                    field.required = !isExistingClient;
                });

                if (isExistingClient && selected) {
                    const values = {
                        client_name: selected.dataset.contact || '',
                        company_name: selected.dataset.company || '',
                        email: selected.dataset.email || '',
                        phone: selected.dataset.phone || '',
                        address: selected.dataset.address || '',
                    };

                    Object.entries(values).forEach(([name, value]) => {
                        const field = document.querySelector(`[name="${name}"]`);
                        if (field) field.value = value;
                    });
                }
            }

            clientSelect?.addEventListener('change', toggleClientMode);
            toggleClientMode();

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

                document.querySelectorAll('.website-asset-input').forEach(input => {
                    input.disabled = !checkboxes.website.checked;
                });

                // Kondisi khusus kombinasi: Website + SEO dipilih bersamaan
                // -> URL SEO tidak wajib diisi manual (website belum jadi/URL belum final)
                const websiteChecked = checkboxes.website.checked;
                const seoChecked = checkboxes.seo.checked;

                if (websiteChecked && seoChecked) {
                    seoUrlInput.required = false;
                    seoNoteBaru.style.display = 'block';
                } else if (seoChecked) {
                    seoUrlInput.required = true;
                    seoNoteBaru.style.display = 'none';
                }
            }

            // FIX (fitur SEO & Backlink otomatis): tim cukup isi 1 URL —
            // kalau kolom URL backlink masih kosong saat checkbox Backlink
            // dicentang / saat URL SEO diisi, salin otomatis dari URL SEO.
            function autoFillBacklinkUrl() {
                if (checkboxes.backlink.checked && backlinkUrlInput && !backlinkUrlInput.value && seoUrlInput && seoUrlInput.value) {
                    backlinkUrlInput.value = seoUrlInput.value;
                }
            }

            // FIX (fitur SEO & Backlink otomatis, mode PREVIEW): tombol
            // "Analisis Sekarang" — jalankan AI SEBELUM form disubmit,
            // hasilnya ditampilkan di sini untuk direview, token-nya
            // dititip di hidden input supaya ikut kekirim saat Simpan.
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
                        business_name: document.querySelector('[name="company_name"]')?.value || '',
                        business_type: document.querySelector('[name="business_type"]')?.value || '',
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
                    html += '<div><p class="text-xs text-gray-500 mb-1">Detected topics</p><div>' +
                        topics.core_topics.map(t => `<span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full mr-1 mb-1">${escapeHtml(t)}</span>`).join('') +
                        '</div></div>';
                }

                if (rec.main_keywords && rec.main_keywords.length) {
                    html += '<div><p class="text-xs text-gray-500 mb-2">Top 10 Keywords</p><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-1 pr-3">Keyword</th><th class="pb-1 pr-3">Volume/mo</th><th class="pb-1">Competition</th></tr></thead><tbody>';
                    rec.main_keywords.forEach(kw => {
                        html += `<tr class="border-b border-gray-50"><td class="py-1 pr-3 font-medium text-gray-700">${escapeHtml(kw.keyword)}</td><td class="py-1 pr-3 text-gray-500">${escapeHtml(kw.avg_monthly_searches ?? '-')}</td><td class="py-1 text-gray-500">${escapeHtml(kw.competition ?? '-')}</td></tr>`;
                    });
                    html += '</tbody></table></div></div>';
                }

                if (competitors.length) {
                    html += '<div><p class="text-xs text-gray-500 mb-1">Competitors found</p><ul class="text-sm text-blue-600 space-y-0.5">' +
                        competitors.map(u => `<li><a href="${encodeURI(u)}" target="_blank" rel="noopener" class="hover:underline break-all">${escapeHtml(u)}</a></li>`).join('') +
                        '</ul></div>';
                }

                if (rec.summary) {
                    html += `<div><p class="text-xs text-gray-500 mb-1">Summary</p><p class="text-sm text-gray-600">${escapeHtml(rec.summary)}</p></div>`;
                }

                html += '<p class="text-xs text-emerald-600">&#10003; This result will be automatically saved once you click Save Draft below.</p>';

                previewResult.innerHTML = html;
                previewResult.classList.remove('hidden');
            }

            Object.values(checkboxes).forEach(chk => {
                chk.addEventListener('change', toggleSections);
            });

            checkboxes.backlink.addEventListener('change', autoFillBacklinkUrl);
            seoUrlInput?.addEventListener('blur', autoFillBacklinkUrl);

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