<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequestOrderRequest;
use App\Models\Client;
use App\Models\FinanceSubscription;
use App\Models\Invoice;
use App\Services\Finance\FinanceCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class requestOrderController extends Controller
{
    public function create()
    {
        return view('pages.request', [
            'clients' => Client::orderBy('company_name')->get(),
        ]);
    }

    public function store(StoreRequestOrderRequest $request)
    {
        $validated = $request->validated();
        $serviceTypes = $request->serviceTypes();
        $wantsWebsite = $request->wantsWebsite();
        $wantsSeo = $request->wantsSeo();
        $wantsBacklink = $request->wantsBacklink();

        // 2. Transaksi Database
        DB::beginTransaction();

        try {
            // A. Pakai client lama atau buat client baru
            $client = !empty($validated['client_id'])
                ? Client::findOrFail($validated['client_id'])
                : Client::create([
                    'company_name' => $validated['company_name'],
                    'contact_name' => $validated['client_name'],
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'whatsapp' => $validated['phone'] ?? null,
                    'address' => $request->input('address'),
                    'created_by' => $this->userId(),
                ]);

            // B. Simpan Project
            // Nama project diketik tim di form. Kalau kosong (jalur lama/API),
            // diturunkan dari nama client + jenis layanan.
            $serviceLabels = ['website' => 'Website', 'seo' => 'SEO', 'backlink' => 'Backlink'];
            $projectName = trim((string) $validated['project_name']);
            if ($projectName === '') {
                $projectName = $client->company_name . ' - ' . implode(' & ', array_map(fn ($type) => $serviceLabels[$type] ?? ucfirst($type), $serviceTypes));
            }

            $project = $client->projects()->create([
                'name' => $projectName,
                'client_name' => $client->contact_name,
                'type' => $validated['website_type'] ?? null,
                'status' => 'request',
                'wants_seo' => $wantsSeo,
                'wants_backlink' => $wantsBacklink,
            ]);

            // C. Simpan Requirements Website (kalau dicentang)
            // FIX: sebelumnya field ini cuma digabung ke string
            // 'requirement_notes' yang BUKAN kolom asli di tabel projects
            // (tidak ada di migration maupun $fillable) — jadi selalu hilang
            // diam-diam. Sekarang disimpan sebagai kolom sendiri.
            if ($wantsWebsite) {
                $referenceType = $validated['design_reference_type'] ?? 'none';
                $referencePath = null;
                // Hanya disimpan kalau tipenya memang "upload": tanpa ini, file
                // yang masih tertinggal di input (type = none/url) ikut disimpan
                // dan jadi file yatim di storage.
                if (in_array($referenceType, ['image', 'zip'], true) && $request->hasFile('design_reference_file')) {
                    $refFile = $request->file('design_reference_file');
                    $referencePath = $refFile->store('design-references', 'public');
                }

                $project->update([
                    // Kolom nama website masih dibaca usulan domain, judul
                    // bundle, dan prompt AI. Field-nya sudah tidak ada di form,
                    // jadi diisi dari nama project yang diketik tim.
                    'website_name' => $projectName,
                    'target_market' => $validated['target_market'] ?? '',
                    'description' => $validated['user_story'],
                    'website_features' => $validated['website_features'],
                    'has_client_assets' => $request->boolean('has_client_assets'),
                    'design_reference_type' => $referenceType,
                    'design_reference_url' => $referenceType === 'url'
                        ? ($validated['design_reference_url'] ?? null)
                        : null,
                    'design_reference_path' => $referencePath,
                ]);

                // Simpan aset pendukung klien jika diunggah
                if ($request->hasFile('client_asset_files')) {
                    foreach ($request->file('client_asset_files') as $assetFile) {
                        $assetPath = $assetFile->store('mockup-assets/' . $project->id, 'public');
                        $mime = $assetFile->getMimeType() ?? '';
                        $cat = str_starts_with($mime, 'image/') ? 'foto' : (str_contains($mime, 'pdf') || str_contains($mime, 'word') || str_contains($mime, 'document') || str_contains($mime, 'text') ? 'dokumen' : 'pendukung');

                        $project->mockupAssets()->create([
                            'category' => $cat,
                            'original_name' => $assetFile->getClientOriginalName(),
                            'file_path' => $assetPath,
                            'file_size' => $assetFile->getSize(),
                            'mime_type' => $mime,
                        ]);
                    }
                }
            }

            // Brand asset is intentionally stored on Client, so every
            // website proposal for the same client uses their real logo.
            if ($request->hasFile('client_logo')) {
                $logoFile = $request->file('client_logo');
                $logoPath = $logoFile->store('client-logos', 'public');
                $client->update(['logo_path' => $logoPath]);

                $project->mockupAssets()->create([
                    'category' => 'logo',
                    'original_name' => $logoFile->getClientOriginalName(),
                    'file_path' => $logoPath,
                    'file_size' => $logoFile->getSize(),
                    'mime_type' => $logoFile->getMimeType(),
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

            DB::commit();

            // FIX (fitur SEO & Backlink otomatis): kalau tim sudah klik
            // "Analisis Sekarang" di form (mode PREVIEW, sebelum submit),
            // hasilnya sudah ada di Cache lewat token ini — tinggal
            // dipindahkan ke project, TIDAK perlu analisis ulang.
            $analysisToken = $request->input('analysis_token');
            $previewApplied = false;

            if ($wantsSeo && !empty($analysisToken)) {
                $preview = Cache::get(\App\Jobs\RunKeywordPreviewAnalysisJob::cacheKey($analysisToken));

                if (($preview['status'] ?? null) === 'done') {
                    $seo = $project->seo_requirements ?? [];
                    $seo['competitors'] = implode("\n", $preview['competitor_urls'] ?? []);
                    $seo['ai_recommendations'] = $preview['recommendations'] ?? null;
                    $seo['ai_identified_topics'] = $preview['topics'] ?? null;
                    $project->update(['seo_requirements' => $seo]);

                    $project->logActivity('SEO & Backlink analysis results (preview before submission) applied to project');
                    $previewApplied = true;
                }
            }

            // Kalau preview TIDAK dipakai (tombol Analisis dilewati, token
            // kosong/kedaluwarsa/gagal), tetap fallback ke auto-analisis
            // otomatis seperti sebelumnya — supaya tidak ada jalur buntu.
            if (!$previewApplied && ($wantsSeo || $wantsBacklink)) {
                $resolvableUrl = $validated['seo_target_url'] ?? $validated['backlink_target_url'] ?? null;

                if (!empty($resolvableUrl)) {
                    Cache::put(
                        \App\Jobs\GenerateKeywordRecommendationsJob::cacheKey($project->id),
                        ['status' => 'queued', 'progress' => 0, 'message' => 'Waiting to be processed...'],
                        now()->addMinutes(10)
                    );

                    \App\Jobs\GenerateKeywordRecommendationsJob::dispatch($project);
                }
            }

            return redirect()->route('pages.projects.show', $project)
                ->with('success', 'Project draft saved successfully!');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Failed to save data: ' . $e->getMessage());
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
        $client->load([
            'projects' => fn ($q) => $q->latest(),
        ]);

        $subscriptions = FinanceSubscription::where('client_id', $client->id)
            ->orWhereHas('project', fn ($p) => $p->where('client_id', $client->id))
            ->with(['project:id,name'])
            ->latest()
            ->get();

        $cycle = app(FinanceCycle::class);
        $subscriptions->each(function ($sub) use ($cycle) {
            if ($sub->next_renewal_date) {
                $sub->setAttribute('days_until', $cycle->daysUntil($sub->next_renewal_date));
            }
        });

        $invoices = Invoice::where('client_id', $client->id)
            ->orWhereHas('project', fn ($p) => $p->where('client_id', $client->id))
            ->with(['project:id,name', 'subscription:id,name'])
            ->latest()
            ->get();

        return view('pages.crm-view', compact('client', 'subscriptions', 'invoices'));
    }
}
