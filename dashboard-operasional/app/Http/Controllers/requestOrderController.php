<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class requestOrderController extends Controller
{
    public function create()
    {
        return view('pages.request');
    }

    public function store(Request $request)
    {
        // FIX: service_type[] sekarang divalidasi & dibaca — sebelumnya
        // checkbox ini cuma dipakai buat show/hide section di JS, nilainya
        // tidak pernah divalidasi atau disimpan sama sekali.
        $serviceTypes = $request->input('service_type', []);
        $wantsWebsite = in_array('website', $serviceTypes);
        $wantsSeo = in_array('seo', $serviceTypes);
        $wantsBacklink = in_array('backlink', $serviceTypes);

        // 1. Validasi Input Data
        $validated = $request->validate([
            // Data Klien
            'client_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],

            'service_type' => ['required', 'array', 'min:1'],
            'service_type.*' => ['in:website,seo,backlink'],

            // FIX: field Website sebelumnya SELALU required di server,
            // walau JS menandainya opsional saat checkbox "website" tidak
            // dicentang — bikin submit gagal untuk client yang cuma mau
            // SEO/Backlink saja. Sekarang wajib CUMA kalau service_type
            // mengandung 'website'.
            'business_type' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string', 'max:255'],
            'business_description' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string'],
            'business_goal' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string'],
            'website_type' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string', 'max:255'],

            // Field SEO — wajib cuma kalau service_type mengandung 'seo'.
            // seo_target_url TIDAK wajib kalau website+seo dicentang bareng
            // (situs belum jadi, URL belum final) — logic ini sudah ada di
            // JS (toggleSections), di server kita longgarkan jadi nullable
            // saja supaya tidak bentrok dengan kondisi kombinasi itu.
            'seo_target_url' => ['nullable', 'string', 'max:255'],
            'seo_keywords' => [Rule::requiredIf($wantsSeo), 'nullable', 'string'],
            'seo_location' => ['nullable', 'string', 'max:255'],
            'seo_competitors' => ['nullable', 'string'],
            'seo_cms_platform' => [Rule::requiredIf($wantsSeo), 'nullable', 'in:wordpress,shopify,wix,lainnya,baru'],

            // Field Backlink — wajib cuma kalau service_type mengandung 'backlink'.
            'backlink_target_url' => [Rule::requiredIf($wantsBacklink), 'nullable', 'string', 'max:255'],
            'backlink_quantity' => [Rule::requiredIf($wantsBacklink), 'nullable', 'integer', 'min:1'],
            'backlink_anchor_text' => ['nullable', 'string', 'max:255'],
            'backlink_niche' => ['nullable', 'string', 'max:255'],
            'backlink_anchor_type' => ['nullable', 'array'],
            'backlink_anchor_type.*' => ['in:exact_match,partial_match,branding,generic'],
            'backlink_priority' => ['nullable', 'in:quality,quantity'],

            // Data Assets
            'assets' => ['nullable', 'array'],
            'assets.*' => ['file', 'mimes:jpg,jpeg,png,pdf,docx,zip', 'max:10240'],
        ]);

        // 2. Transaksi Database
        DB::beginTransaction();

        try {
            // A. Simpan Data Client
            $client = Client::create([
                'company_name' => $validated['company_name'],
                'contact_name' => $validated['client_name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'whatsapp' => $validated['phone'] ?? null,
                'address' => $request->input('address'),
                'created_by' => Auth::id(),
            ]);

            // B. Simpan Project
            // FIX: nama project sebelumnya selalu "Website {company_name}",
            // tidak masuk akal untuk client yang cuma minta SEO/Backlink
            // tanpa website baru.
            $projectName = $wantsWebsite
                ? "Website {$validated['company_name']}"
                : $validated['company_name'] . ' - ' . implode(' & ', array_map('ucfirst', $serviceTypes));

            $project = $client->projects()->create([
                'name' => $projectName,
                'client_name' => $validated['client_name'],
                'type' => $validated['website_type'] ?? null,
                'status' => 'request',
                'progress' => 0,
                'wants_seo' => $wantsSeo,
                'wants_backlink' => $wantsBacklink,
            ]);

            // C. Simpan Requirements Website (kalau dicentang)
            if ($wantsWebsite) {
                $project->update([
                    'requirement_notes' => "Jenis usaha: {$validated['business_type']}\nDeskripsi: {$validated['business_description']}\nTujuan: {$validated['business_goal']}",
                    'description' => $validated['business_description'],
                ]);
            }

            // D. Simpan Requirements SEO (kalau dicentang) — sebagai JSON
            // terstruktur, supaya gampang dibaca ulang oleh AI service nanti
            // (generate keyword/artikel) tanpa perlu parsing teks bebas.
            if ($wantsSeo) {
                $project->update([
                    'seo_requirements' => [
                        'target_url' => $validated['seo_target_url'] ?? null,
                        'keywords' => $validated['seo_keywords'] ?? null,
                        'location' => $validated['seo_location'] ?? null,
                        'competitors' => $validated['seo_competitors'] ?? null,
                        'cms_platform' => $validated['seo_cms_platform'] ?? null,
                    ],
                ]);
            }

            // E. Simpan Requirements Backlink (kalau dicentang)
            if ($wantsBacklink) {
                $project->update([
                    'backlink_requirements' => [
                        'target_url' => $validated['backlink_target_url'] ?? null,
                        'quantity' => $validated['backlink_quantity'] ?? null,
                        'anchor_text' => $validated['backlink_anchor_text'] ?? null,
                        'niche' => $validated['backlink_niche'] ?? null,
                        'anchor_type' => $validated['backlink_anchor_type'] ?? [],
                        'priority' => $validated['backlink_priority'] ?? null,
                    ],
                ]);
            }

            // F. Simpan Assets (Jika ada lampiran file)
            if ($request->hasFile('assets')) {
                $logoPath = null;

                foreach ($request->file('assets') as $file) {
                    $path = $file->store('project-files', 'public');

                    $project->files()->create([
                        'original_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'category' => 'pendukung',
                    ]);

                    if (!$logoPath && str_starts_with($file->getClientMimeType(), 'image/')) {
                        $logoPath = $path;
                    }
                }

                if ($logoPath) {
                    $client->update(['logo_path' => $logoPath]);
                }
            }

            DB::commit();

            return redirect()->route('pages.projects.show', $project)
                ->with('success', 'Draft proyek berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    public function clients(Request $request)
    {
        $search = $request->input('search');

        $clients = Client::with(['projects'])
            ->when($search, function ($query, $search) {
                return $query->where('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('pages.crm', compact('clients'));
    }

    public function clientView(Client $client)
    {
        $client->load(['projects']);

        return view('pages.crm-view', compact('client'));
    }
}