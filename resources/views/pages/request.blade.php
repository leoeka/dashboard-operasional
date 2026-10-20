@extends('layouts.app')
@section('title', 'Request Order')

@section('content')
    <div class="max-w-4xl mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-slate-800">Add Client & New Draft Project</h2>

        @if (session('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-md">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-4 bg-red-50 border-l-4 border-l-red-700 text-red-700 rounded-md">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('clients.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6"
            id="requestForm">
            @csrf

            <div class="border-b pb-4">
                <h3 class="text-base font-semibold text-slate-800 mb-4">1. Client Information</h3>
                <div class="mb-4">
                    <label for="clientSelect" class="block text-sm font-semibold text-slate-800">Select Client</label>
                    <select name="client_id" id="clientSelect"
                        class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
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
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="newClientFields">
                    <div>
                        <label for="client_name" class="block text-sm font-semibold text-slate-800">Contact/Client Name <span
                                class="text-red-700">*</span></label>
                        <input type="text" id="client_name" name="client_name" value="{{ old('client_name') }}"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>

                    <div>
                        <label for="company_name" class="block text-sm font-semibold text-slate-800">Company Name <span
                                class="text-red-700">*</span></label>
                        <input type="text" id="company_name" name="company_name" value="{{ old('company_name') }}"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-800">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-semibold text-slate-800">Phone Number / WhatsApp</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>

                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-semibold text-slate-800">Address</label>
                        <textarea id="address" name="address" rows="2"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">{{ old('address') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="border-b pb-4">
                <h3 class="text-base font-semibold text-slate-800 mb-4">2. Project Request</h3>

                <div class="mb-4">
                    <label for="project_name" class="block text-sm font-semibold text-slate-800">Project Name <span
                            class="text-red-700">*</span></label>
                    <input type="text" id="project_name" name="project_name" value="{{ old('project_name') }}"
                        class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                </div>

                <label class="block text-sm font-semibold text-slate-800 mb-2">Service Requirements <span
                        class="text-red-700">*</span></label>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label
                        class="service-option flex items-center gap-3 border border-slate-500 rounded-md p-3 cursor-pointer hover:bg-brand-50 has-[:checked]:bg-brand-50 has-[:checked]:border-brand-600">
                        <input type="checkbox" name="service_type[]" value="website" id="chk-website"
                            class="service-checkbox h-5 w-5 text-brand-600 border-slate-500 rounded-md focus:ring-brand-500"
                            {{ in_array('website', old('service_type', [])) ? 'checked' : '' }}>
                        <span class="text-sm font-semibold text-slate-800">Create Website</span>
                    </label>

                    <label
                        class="service-option flex items-center gap-3 border border-slate-500 rounded-md p-3 cursor-pointer hover:bg-brand-50 has-[:checked]:bg-brand-50 has-[:checked]:border-brand-600">
                        <input type="checkbox" name="service_type[]" value="seo" id="chk-seo"
                            class="service-checkbox h-5 w-5 text-brand-600 border-slate-500 rounded-md focus:ring-brand-500"
                            {{ in_array('seo', old('service_type', [])) ? 'checked' : '' }}>
                        <span class="text-sm font-semibold text-slate-800">SEO Website</span>
                    </label>

                    <label
                        class="service-option flex items-center gap-3 border border-slate-500 rounded-md p-3 cursor-pointer hover:bg-brand-50 has-[:checked]:bg-brand-50 has-[:checked]:border-brand-600">
                        <input type="checkbox" name="service_type[]" value="backlink" id="chk-backlink"
                            class="service-checkbox h-5 w-5 text-brand-600 border-slate-500 rounded-md focus:ring-brand-500"
                            {{ in_array('backlink', old('service_type', [])) ? 'checked' : '' }}>
                        <span class="text-sm font-semibold text-slate-800">Backlink</span>
                    </label>
                </div>
                <p id="service-error" class="text-xs text-red-700 mt-2 hidden">Select at least one service requirement.</p>
            </div>

            <div class="border-b pb-4 service-section" id="section-website" style="display:none;">
                <h3 class="text-base font-semibold text-slate-800 mb-4">3. Website Project Requirements</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="website_type" class="block text-sm font-semibold text-slate-800">Website Type <span
                                class="text-red-700">*</span></label>
                        <input type="text" id="website_type" name="website_type" value="{{ old('website_type') }}"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500 conditional-required">
                    </div>

                    <div class="md:col-span-2">
                        <label for="user_story" class="block text-sm font-semibold text-slate-800">Main Goal of This Website <span
                                class="text-red-700">*</span></label>
                        <textarea id="user_story" name="user_story" rows="3"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500 conditional-required">{{ old('user_story') }}</textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label for="target_market" class="block text-sm font-semibold text-slate-800">Target Market / Audience</label>
                        <textarea id="target_market" name="target_market" rows="2"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">{{ old('target_market') }}</textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label for="website_features" class="block text-sm font-semibold text-slate-800">Content &amp; Features Needed <span
                                class="text-red-700">*</span></label>
                        <textarea id="website_features" name="website_features" rows="3"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500 conditional-required">{{ old('website_features') }}</textarea>
                    </div>

                    <div class="md:col-span-2 border-t border-slate-200 pt-4">
                        <h4 class="text-base font-semibold text-slate-800 mb-2">Brand &amp; Design Reference</h4>

                        <label for="client_logo" class="block text-sm font-semibold text-slate-800">Client Logo</label>
                        <input type="file" id="client_logo" name="client_logo" accept="image/png,image/jpeg,image/webp"
                            class="min-h-11 mt-1 block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
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
                                <label for="design_reference_file" class="block text-sm font-semibold text-slate-800">Reference File</label>
                                <input type="file" id="design_reference_file" name="design_reference_file" accept="image/png,image/jpeg,image/webp,.zip"
                                    class="min-h-11 mt-1 block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                            </div>
                            <div id="designReferenceUrlField">
                                <label for="design_reference_url" class="block text-sm font-semibold text-slate-800">Reference Website URL</label>
                                <input type="url" id="design_reference_url" name="design_reference_url" value="{{ old('design_reference_url') }}"
                                    class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                            </div>
                        </div>

                        <span class="block text-sm font-semibold text-slate-800 mt-4">Does the client have assets? (logo, photos, documents) <span
                                class="text-red-700">*</span></span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                            <label
                                class="flex items-center gap-3 border border-slate-500 rounded-md p-3 cursor-pointer hover:bg-brand-50 has-[:checked]:bg-brand-50 has-[:checked]:border-brand-600">
                                <input type="radio" name="has_client_assets" value="1" id="client_assets_yes"
                                    class="h-5 w-5 text-brand-600 border-slate-500 rounded-full focus:ring-brand-500"
                                    @checked(old('has_client_assets') === '1')>
                                <span class="text-sm font-semibold text-slate-800">Yes</span>
                            </label>

                            <label
                                class="flex items-center gap-3 border border-slate-500 rounded-md p-3 cursor-pointer hover:bg-brand-50 has-[:checked]:bg-brand-50 has-[:checked]:border-brand-600">
                                <input type="radio" name="has_client_assets" value="0" id="client_assets_no"
                                    class="h-5 w-5 text-brand-600 border-slate-500 rounded-full focus:ring-brand-500"
                                    @checked(old('has_client_assets') === '0')>
                                <span class="text-sm font-semibold text-slate-800">No</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-b pb-4 service-section" id="section-seo" style="display:none;">
                <h3 class="text-base font-semibold text-slate-800 mb-4">4. SEO Requirements</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2" id="seo-existing-url-wrapper">
                        <label for="seo_target_url" class="block text-sm font-semibold text-slate-800">Website URL to Optimize <span
                                class="text-red-700">*</span></label>
                        <input type="text" name="seo_target_url" id="seo_target_url"
                            value="{{ old('seo_target_url') }}"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500 conditional-required">

                        <button type="button" id="btnAnalyzePreview" disabled
                            class="min-h-11 mt-2 inline-flex items-center gap-2 text-xs font-semibold px-3 py-2 rounded-md bg-brand-600 text-white hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            Analyze Now
                        </button>

                        <div id="previewProgress" class="hidden mt-2">
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div id="previewProgressBar" class="bg-brand-600 h-2 rounded-full transition-all" style="width:0%"></div>
                            </div>
                            <p id="previewProgressMessage" class="text-xs text-slate-600 mt-1"></p>
                        </div>

                        <div id="previewError" class="hidden mt-2 text-sm text-red-700 bg-red-50 border-l-4 border-l-red-700 rounded-md p-4"></div>

                        <div id="previewResult" class="hidden mt-3 border-t pt-3 space-y-3"></div>

                        <input type="hidden" name="analysis_token" id="analysisToken" value="">
                    </div>

                    <div>
                        <label for="seo_location" class="block text-sm font-semibold text-slate-800">Target Location/Area (if any)</label>
                        <input type="text" id="seo_location" name="seo_location"
                            value="{{ old('seo_location') }}"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>

                    <div>
                        <label for="seo_cms_platform" class="block text-sm font-semibold text-slate-800">Client Website Platform <span
                                class="text-red-700">*</span></label>
                        <select id="seo_cms_platform" name="seo_cms_platform"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500 conditional-required">
                            <option value="">-- Select Platform --</option>
                            <option value="wordpress" {{ old('seo_cms_platform') == 'wordpress' ? 'selected' : '' }}>WordPress</option>
                            <option value="baru" {{ old('seo_cms_platform') == 'baru' ? 'selected' : '' }}>New Website (built by us)</option>
                            <option value="lainnya" {{ old('seo_cms_platform') == 'lainnya' ? 'selected' : '' }}>Other Platform (manual publish)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="border-b pb-4 service-section" id="section-backlink" style="display:none;">
                <h3 class="text-base font-semibold text-slate-800 mb-4">5. Backlink Requirements</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2" id="backlink-existing-url-wrapper">
                        <label for="backlink_target_url" class="block text-sm font-semibold text-slate-800">Backlink Target URL <span
                                class="text-red-700">*</span></label>
                        <input type="text" name="backlink_target_url" id="backlink_target_url"
                            value="{{ old('backlink_target_url') }}"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500 conditional-required">
                    </div>

                    <div>
                        <label for="backlink_quantity" class="block text-sm font-semibold text-slate-800">Number of Backlinks <span
                                class="text-red-700">*</span></label>
                        <input type="number" id="backlink_quantity" min="1" name="backlink_quantity"
                            value="{{ old('backlink_quantity') }}"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500 conditional-required">
                    </div>

                    <div>
                        <label for="backlink_anchor_text" class="block text-sm font-semibold text-slate-800">Anchor Text</label>
                        <input type="text" id="backlink_anchor_text" name="backlink_anchor_text" value="{{ old('backlink_anchor_text') }}"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>

                    <div class="md:col-span-2">
                        <label for="backlink_niche" class="block text-sm font-semibold text-slate-800">Desired Backlink Niche /
                            Category</label>
                        <input type="text" id="backlink_niche" name="backlink_niche"
                            value="{{ old('backlink_niche') }}"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>

                    <div>
                        <label for="backlink_anchor_type" class="block text-sm font-semibold text-slate-800">Desired Anchor Text Type</label>
                        <select id="backlink_anchor_type" name="backlink_anchor_type[]" multiple
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                            @php $anchorTypes = (array) old('backlink_anchor_type', []); @endphp
                            <option value="exact_match" @selected(in_array('exact_match', $anchorTypes))>Exact Match (exact keyword)</option>
                            <option value="partial_match" @selected(in_array('partial_match', $anchorTypes))>Partial Match</option>
                            <option value="branding" @selected(in_array('branding', $anchorTypes))>Branding (brand name)</option>
                            <option value="generic" @selected(in_array('generic', $anchorTypes))>Generic (click here, etc.)</option>
                        </select>
                    </div>

                    <div>
                        <label for="backlink_priority" class="block text-sm font-semibold text-slate-800">Priority</label>
                        <select id="backlink_priority" name="backlink_priority"
                            class="mt-1 block w-full min-h-11 border border-slate-500 rounded-md p-2 focus:ring-brand-500 focus:border-brand-500">
                            <option value="quality" @selected(old('backlink_priority', 'quality') === 'quality')>Quality (fewer, high-authority sites)</option>
                            <option value="quantity" @selected(old('backlink_priority') === 'quantity')>Quantity (more sites, varied authority)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-4">
                <a href="{{ route('pages.projects') }}"
                    class="min-h-11 px-4 py-2 border border-slate-300 bg-white text-slate-700 rounded-md hover:bg-slate-50 text-center">Cancel</a>
                <button type="submit"
                    class="min-h-11 px-4 py-2 bg-brand-600 text-white rounded-md hover:bg-brand-700 font-semibold">
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
                });

                ['client_name', 'company_name'].forEach(name => {
                    const field = document.querySelector(`#newClientFields [name="${name}"]`);
                    if (field) field.required = !isExistingClient;
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
                        if (field) {
                            field.value = value;
                            field.dataset.autofilled = '1';
                        }
                    });
                } else {
                    newClientFields?.querySelectorAll('input, textarea').forEach(field => {
                        if (field.dataset.autofilled === '1') {
                            field.value = '';
                            delete field.dataset.autofilled;
                        }
                    });
                }
            }

            clientSelect?.addEventListener('change', toggleClientMode);
            toggleClientMode();

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

                const websiteChecked = checkboxes.website.checked;
                const seoChecked = checkboxes.seo.checked;

                if (websiteChecked && seoChecked) {
                    seoUrlInput.required = false;
                } else if (seoChecked) {
                    seoUrlInput.required = true;
                }

                if (!seoChecked) {
                    analysisTokenInput.value = '';
                    previewResult.classList.add('hidden');
                    previewResult.innerHTML = '';
                    previewError.classList.add('hidden');
                }
            }

            function autoFillBacklinkUrl() {
                if (checkboxes.backlink.checked && backlinkUrlInput && !backlinkUrlInput.value && seoUrlInput && seoUrlInput.value) {
                    backlinkUrlInput.value = seoUrlInput.value;
                }
            }

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
                        business_type: document.querySelector('[name="website_type"]')?.value || '',
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
                    html += '<div><p class="text-xs text-slate-600 mb-1">Detected topics</p><div>' +
                        topics.core_topics.map(t => `<span class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-1 rounded-full mr-1 mb-1">${escapeHtml(t)}</span>`).join('') +
                        '</div></div>';
                }

                if (rec.main_keywords && rec.main_keywords.length) {
                    html += '<div><p class="text-xs text-slate-600 mb-2">Top 10 Keywords</p><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-xs text-slate-500 border-b"><th class="pb-1 pr-3">Keyword</th><th class="pb-1 pr-3">Volume/mo</th><th class="pb-1">Competition</th></tr></thead><tbody>';
                    rec.main_keywords.forEach(kw => {
                        html += `<tr class="border-b border-slate-200"><td class="py-1 pr-3 font-semibold text-slate-800">${escapeHtml(kw.keyword)}</td><td class="py-1 pr-3 text-slate-600">${escapeHtml(kw.avg_monthly_searches ?? '-')}</td><td class="py-1 text-slate-600">${escapeHtml(kw.competition ?? '-')}</td></tr>`;
                    });
                    html += '</tbody></table></div></div>';
                }

                if (competitors.length) {
                    html += '<div><p class="text-xs text-slate-600 mb-1">Competitors found</p><ul class="text-sm text-brand-600 space-y-1">' +
                        competitors.map(u => `<li><a href="${encodeURI(u)}" target="_blank" rel="noopener" class="hover:underline break-all">${escapeHtml(u)}</a></li>`).join('') +
                        '</ul></div>';
                }

                if (rec.summary) {
                    html += `<div><p class="text-xs text-slate-600 mb-1">Summary</p><p class="text-sm text-slate-600">${escapeHtml(rec.summary)}</p></div>`;
                }

                html += '<p class="text-xs text-emerald-700">&#10003; This result will be automatically saved once you click Save Draft below.</p>';

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
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    serviceError.classList.add('hidden');
                }
            });

            toggleSections();

            const designReferenceType = document.getElementById('designReferenceType');
            const designReferenceFileField = document.getElementById('designReferenceFileField');
            const designReferenceUrlField = document.getElementById('designReferenceUrlField');

            function toggleDesignReferenceFields() {
                const type = designReferenceType?.value || 'none';
                const showFile = ['image', 'zip'].includes(type);
                const showUrl = type === 'url';
                designReferenceFileField.style.display = showFile ? 'block' : 'none';
                designReferenceUrlField.style.display = showUrl ? 'block' : 'none';
                const fileInput = document.getElementById('design_reference_file');
                const urlInput = document.getElementById('design_reference_url');
                if (fileInput) fileInput.disabled = !showFile;
                if (urlInput) urlInput.disabled = !showUrl;
            }

            designReferenceType?.addEventListener('change', toggleDesignReferenceFields);
            toggleDesignReferenceFields();
        })();
    </script>
@endsection