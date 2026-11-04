<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestOrderRequest extends FormRequest
{
    /**
     * Tentukan apakah user diizinkan membuat request ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mendapatkan daftar service_type yang dipilih.
     *
     * @return array<int, string>
     */
    public function serviceTypes(): array
    {
        return (array) $this->input('service_type', []);
    }

    public function wantsWebsite(): bool
    {
        return in_array('website', $this->serviceTypes(), true);
    }

    public function wantsSeo(): bool
    {
        return in_array('seo', $this->serviceTypes(), true);
    }

    public function wantsBacklink(): bool
    {
        return in_array('backlink', $this->serviceTypes(), true);
    }

    public function isExistingClient(): bool
    {
        return $this->filled('client_id');
    }

    /**
     * Aturan validasi input untuk pembuatan permintaan proyek baru (CRM Request).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $wantsWebsite = $this->wantsWebsite();
        $wantsSeo = $this->wantsSeo();
        $wantsBacklink = $this->wantsBacklink();
        $existingClient = $this->isExistingClient();

        return [
            // Data Klien
            'client_id' => ['nullable', 'exists:clients,id'],
            'client_name' => [Rule::requiredIf(!$existingClient), 'nullable', 'string', 'max:255'],
            'company_name' => [Rule::requiredIf(!$existingClient), 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'client_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'service_type' => ['required', 'array', 'min:1'],
            'service_type.*' => ['in:website,seo,backlink'],

            'project_name' => ['required', 'string', 'max:255'],

            // Bagian website: wajib jika service_type mengandung 'website'
            'user_story' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string'],
            'website_features' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string'],
            'has_client_assets' => [Rule::requiredIf($wantsWebsite), 'nullable', 'boolean'],
            'client_asset_files' => ['nullable', 'array'],
            'client_asset_files.*' => ['file', 'max:20480'],
            'website_type' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string', 'max:255'],
            'target_market' => ['nullable', 'string'],
            'design_reference_type' => ['nullable', Rule::in(['none', 'image', 'zip', 'url'])],
            'design_reference_url' => ['nullable', 'url', 'max:2048'],
            'design_reference_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,zip', 'max:10240'],

            // Bagian SEO: URL wajib jika SEO dipilih tanpa website baru
            'seo_target_url' => [Rule::requiredIf($wantsSeo && !$wantsWebsite), 'nullable', 'string', 'max:255'],
            'seo_keywords' => ['nullable', 'string'],
            'seo_location' => ['nullable', 'string', 'max:255'],
            'seo_competitors' => ['nullable', 'string'],
            'seo_cms_platform' => [Rule::requiredIf($wantsSeo), 'nullable', 'in:wordpress,shopify,wix,lainnya,baru'],

            // Bagian Backlink: wajib jika service_type mengandung 'backlink'
            'backlink_target_url' => [Rule::requiredIf($wantsBacklink), 'nullable', 'string', 'max:255'],
            'backlink_quantity' => [Rule::requiredIf($wantsBacklink), 'nullable', 'integer', 'min:1'],
            'backlink_anchor_text' => ['nullable', 'string', 'max:255'],
            'backlink_niche' => ['nullable', 'string', 'max:255'],
            'backlink_anchor_type' => ['nullable', 'array'],
            'backlink_anchor_type.*' => ['in:exact_match,partial_match,branding,generic'],
            'backlink_priority' => ['nullable', 'in:quality,quantity'],
        ];
    }
}

