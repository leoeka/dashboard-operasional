<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Tentukan apakah user diizinkan memperbarui data project ini.
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

    /**
     * Aturan validasi pembaruan data proyek.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $wantsWebsite = $this->wantsWebsite();
        $wantsSeo = $this->wantsSeo();
        $wantsBacklink = $this->wantsBacklink();

        return [
            'client_id' => ['nullable', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:request,in_progress,completed'],

            // Bagian website: wajib jika tile "Create Website" dicentang
            'type' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string', 'max:255'],
            'user_story' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string'],
            'target_market' => ['nullable', 'string'],
            'website_features' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string'],
            'has_client_assets' => [Rule::requiredIf($wantsWebsite), 'nullable', 'boolean'],
            'client_asset_files' => ['nullable', 'array'],
            'client_asset_files.*' => ['file', 'max:20480'],

            'client_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'design_reference_type' => ['nullable', Rule::in(['none', 'image', 'zip', 'url'])],
            'design_reference_url' => ['nullable', 'url', 'max:2048'],
            'design_reference_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,zip', 'max:10240'],

            'service_type' => ['nullable', 'array'],
            'service_type.*' => ['in:website,seo,backlink'],

            'seo_target_url' => ['nullable', 'string', 'max:255'],
            'seo_keywords' => ['nullable', 'string'],
            'seo_location' => ['nullable', 'string', 'max:255'],
            'seo_competitors' => ['nullable', 'string'],
            'seo_cms_platform' => [Rule::requiredIf($wantsSeo), 'nullable', 'in:wordpress,lainnya,baru'],

            'backlink_target_url' => [Rule::requiredIf($wantsBacklink), 'nullable', 'string', 'max:255'],
            'backlink_quantity' => [Rule::requiredIf($wantsBacklink), 'nullable', 'integer', 'min:1'],
            'backlink_anchor_text' => ['nullable', 'string', 'max:255'],
            'backlink_niche' => ['nullable', 'string', 'max:255'],
            'backlink_anchor_type' => ['nullable', 'array'],
            'backlink_priority' => ['nullable', 'in:quality,quantity'],
        ];
    }
}

