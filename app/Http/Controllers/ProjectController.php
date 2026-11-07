<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProjectRequest;
use App\Models\MockupAsset;
use App\Models\Project;
use App\Support\MockupAssetZip;
use App\Support\PageSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Project CRUD only. See SeoBacklinkController for SEO/backlink
 * tooling — it used to live in this class.
 */
class ProjectController extends Controller
{

    public function index(Request $request)
    {
        $projects = Project::query()
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('client_name', 'like', "%{$request->search}%");
            })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('projects.index', compact('projects'));
    }

    public function show(Project $project)
    {
        return redirect()->route('pages.project-workspace', ['project' => $project->id]);
    }

    public function edit(Project $project)
    {
        return view('projects.form', [
            'project' => $project,
            'clientWebsiteUrls' => $this->buildClientWebsiteUrlMap(),
        ]);
    }

    private function buildClientWebsiteUrlMap(): array
    {
        return Project::whereNotNull('client_id')
            ->get()
            ->groupBy('client_id')
            ->map(function ($projects) {
                return $projects
                    ->map(fn ($p) => $p->seo_requirements['target_url'] ?? null)
                    ->filter()
                    ->unique()
                    ->values();
            })
            ->filter(fn($urls) => $urls->isNotEmpty())
            ->toArray();
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $wantsWebsite = $request->wantsWebsite();
        $wantsSeo = $request->wantsSeo();
        $wantsBacklink = $request->wantsBacklink();

        // FIX (fitur SEO & Backlink otomatis): tangkap URL LAMA sebelum
        // di-update, dipakai untuk deteksi "URL baru pertama kali terisi".
        $previousUrl = $project->seo_requirements['target_url']
            ?? $project->backlink_requirements['target_url']
            ?? null;

        $data = $request->validated();

        $data['wants_seo'] = $wantsSeo;
        $data['wants_backlink'] = $wantsBacklink;
        unset($data['service_type']);

        // User story bukan nama kolom; kolomnya 'description'. Jawaban aset
        // ('1'/'0' dari radio) dinormalkan di bawah jadi boolean.
        $userStory = $data['user_story'] ?? null;
        unset($data['user_story'], $data['has_client_assets']);

        $statusChanged = $project->status !== $data['status'];

        if (($data['design_reference_type'] ?? null) !== 'url') {
            $data['design_reference_url'] = null;
        }

        // Generator mockup membaca design_reference_path untuk menyebut file
        // acuan di prompt, tanpa memedulikan type. Maka path lama harus ikut
        // hilang begitu type berpindah ke "tidak ada file".
        if (in_array($data['design_reference_type'] ?? null, ['none', 'url'], true)) {
            $data['design_reference_path'] = null;
        }

        unset($data['client_logo'], $data['design_reference_file']);

        $project->update($data);

        // Bagian website ditulis sama seperti di form Request: user story masuk
        // kolom description, dan nama website ikut nama project yang baru supaya
        // usulan domain dan judul bundle tidak tertinggal satu edit.
        if ($wantsWebsite) {
            $project->update([
                'description' => $userStory,
                'website_name' => $data['name'],
                'has_client_assets' => $request->boolean('has_client_assets'),
            ]);
        }

        if ($request->hasFile('design_reference_file') && in_array($project->design_reference_type, ['image', 'zip'], true)) {
            $refFile = $request->file('design_reference_file');
            $refPath = $refFile->store('design-references', 'public');
            $project->update([
                'design_reference_path' => $refPath,
            ]);
        }

        if ($request->hasFile('client_logo') && $project->client) {
            $logoFile = $request->file('client_logo');
            $logoPath = $logoFile->store('client-logos', 'public');
            $project->client->update([
                'logo_path' => $logoPath,
            ]);
            $project->mockupAssets()->create([
                'category' => 'logo',
                'original_name' => $logoFile->getClientOriginalName(),
                'file_path' => $logoPath,
                'file_size' => $logoFile->getSize(),
                'mime_type' => $logoFile->getMimeType(),
            ]);
        }

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

        if ($wantsSeo) {
            $project->update([
                'seo_requirements' => array_merge($project->seo_requirements ?? [], $this->requirementsFromRequest($request, [
                    'target_url' => 'seo_target_url',
                    'keywords' => 'seo_keywords',
                    'location' => 'seo_location',
                    'competitors' => 'seo_competitors',
                    'cms_platform' => 'seo_cms_platform',
                ])),
            ]);
        }

        if ($wantsBacklink) {
            $project->update([
                'backlink_requirements' => array_merge($project->backlink_requirements ?? [], $this->requirementsFromRequest($request, [
                    'target_url' => 'backlink_target_url',
                    'quantity' => 'backlink_quantity',
                    'anchor_text' => 'backlink_anchor_text',
                    'niche' => 'backlink_niche',
                    'anchor_type' => 'backlink_anchor_type',
                    'priority' => 'backlink_priority',
                ])),
            ]);
        }

        // FIX (fitur SEO & Backlink otomatis): pakai hasil preview dulu
        // kalau ada (tim klik "Analisis Sekarang" sebelum submit).
        $analysisToken = $request->input('analysis_token');
        $previewApplied = false;

        if (!empty($analysisToken)) {
            $preview = Cache::get(\App\Jobs\RunKeywordPreviewAnalysisJob::cacheKey($analysisToken));

            if (($preview['status'] ?? null) === 'done') {
                $seo = $project->fresh()->seo_requirements ?? [];
                $seo['competitors'] = implode("\n", $preview['competitor_urls'] ?? []);
                $seo['ai_recommendations'] = $preview['recommendations'] ?? null;
                $seo['ai_identified_topics'] = $preview['topics'] ?? null;
                $project->update(['seo_requirements' => $seo]);

                $project->logActivity('SEO & Backlink analysis result (preview before submit) applied to project');
                $previewApplied = true;
            }
        }

        // FIX (fitur SEO & Backlink otomatis): auto-jalankan analisis AI
        // HANYA saat URL baru pertama kali terisi (sebelumnya kosong) DAN
        // belum pernah dianalisis DAN hasil preview tidak dipakai di atas.
        // Perubahan status tidak memicu analisis ulang otomatis.
        // re-analisis otomatis — biar kuota AI tidak boros. Re-run manual
        // tersedia di halaman SEO & Backlink Workspace.
        if (!$previewApplied && ($wantsSeo || $wantsBacklink)) {
            $newUrl = $request->input('seo_target_url') ?: $request->input('backlink_target_url');
            $alreadyAnalyzed = !empty($project->fresh()->seo_requirements['ai_recommendations'] ?? null);

            if (!empty($newUrl) && empty($previousUrl) && !$alreadyAnalyzed) {
                Cache::put(
                    \App\Jobs\GenerateKeywordRecommendationsJob::cacheKey($project->id),
                    ['status' => 'queued', 'progress' => 0, 'message' => 'Waiting to be processed...'],
                    now()->addMinutes(10)
                );

                \App\Jobs\GenerateKeywordRecommendationsJob::dispatch($project->fresh());
            }
        }

        if ($statusChanged && $data['status'] === 'completed') {
            $project->logActivity('Project completed');
        } else {
            $project->logActivity('Project information updated');
        }

        return redirect()->route('pages.projects.show', $project)->with('success', 'Project updated successfully.');
    }


    // Hanya field yang benar-benar ada di request yang ikut ditulis. Blob
    // requirements ini juga ditulisi job SEO (pagespeed, ai_recommendations,
    // manual_screenshots) yang tidak muncul di form ini, dan middleware sudah
    // mengubah string kosong jadi null, jadi kehadiran key satu-satunya
    // pembeda antara "dikosongkan" dan "tidak ditampilkan".
    private function requirementsFromRequest(Request $request, array $map): array
    {
        $values = [];

        foreach ($map as $stored => $field) {
            if ($request->has($field)) {
                $values[$stored] = $request->input($field);
            }
        }

        return $values;
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('pages.projects')->with('success', 'Project deleted successfully.');
    }

    public function storeFile(Request $request, Project $project, MockupAssetZip $zip)
    {
        $request->validate([
            'file' => 'required|file|max:2097152',
            'category' => 'required|in:logo,company_profile,foto,dokumen,pendukung',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        if (strtolower((string) $file->getClientOriginalExtension()) === 'zip') {
            try {
                $hasil = $zip->masukkan($project, $file, $request->category);
            } catch (RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }

            $pesan = $zip->ringkas($hasil);
            $project->logActivity('Aset mockup dari ZIP: ' . $pesan);

            return back()->with($hasil['masuk'] > 0 ? 'success' : 'error', $pesan);
        }

        $path = $file->store('mockup-assets/' . $project->id, 'public');

        $project->mockupAssets()->create([
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'category' => $request->category,
        ]);

        $label = MockupAsset::categoryLabels()[$request->category] ?? 'Aset';
        $project->logActivity("Aset mockup ({$label}) berhasil diunggah");

        return back()->with('success', 'Aset mockup berhasil diunggah.');
    }

    public function destroyFile(Project $project, MockupAsset $file)
    {
        if ($file->project_id !== $project->id) {
            abort(404);
        }

        Storage::disk('public')->delete($file->file_path);
        $file->delete();
        $project->logActivity("Aset {$file->original_name} dihapus");

        return back()->with('success', 'Aset mockup berhasil dihapus.');
    }

    /**
     * Acuan desain dari klien: gambar, ZIP template, atau tautan situs/web.
     * Khusus acuan, dipisah dari daftar bahan klien karena acuan tidak pernah
     * dipasang ke website.
     */
    public function storeReference(Request $request, Project $project, PageSnapshot $potret)
    {
        $data = $request->validate([
            'type' => 'required|in:none,image,zip,url',
            'file' => 'nullable|required_if:type,image,zip|file|max:2097152',
            'url' => 'nullable|required_if:type,url|url:http,https|max:2048',
        ]);

        if ($data['type'] === 'none') {
            $project->update([
                'design_reference_type' => 'none',
                'design_reference_url' => null,
                'design_reference_path' => null,
            ]);
            $project->logActivity('Referensi desain dihapus');

            return back()->with('success', 'Referensi desain dikosongkan.');
        }

        if ($data['type'] === 'url') {
            // Codex tidak punya akses internet, jadi tautan harus jadi gambar
            // lebih dulu. Tautannya tetap disimpan walaupun potret gagal,
            // supaya pemilik tinggal menekan "Ambil ulang potret".
            $relatif = 'design-references/' . $project->id . '/potret-' . substr(uniqid(), -6) . '.png';
            $gagal = null;

            try {
                $potret->captureUrl($data['url'], Storage::disk('public')->path($relatif));
            } catch (RuntimeException $e) {
                $gagal = $e->getMessage();
                $relatif = null;
            }

            $project->update([
                'design_reference_type' => 'url',
                'design_reference_url' => $data['url'],
                'design_reference_path' => $relatif,
            ]);
            $project->logActivity('Referensi desain: tautan ' . $data['url']);

            return $gagal === null
                ? back()->with('success', 'Situs berhasil dipotret dan dipakai sebagai acuan.')
                : back()->with('error', 'Tautan tersimpan, tapi situsnya gagal dipotret: ' . $gagal);
        }

        /** @var UploadedFile $file */
        $file = $data['file'];
        $boleh = $data['type'] === 'zip' ? ['zip'] : ['png', 'jpg', 'jpeg', 'webp', 'svg'];
        $ekstensi = strtolower((string) $file->getClientOriginalExtension());

        if (!in_array($ekstensi, $boleh, true)) {
            return back()->with('error', 'Berkas referensi harus berformat ' . implode(', ', $boleh) . '.');
        }

        $path = $file->store('design-references/' . $project->id, 'public');

        $project->update([
            'design_reference_type' => $data['type'],
            'design_reference_url' => null,
            'design_reference_path' => $path,
        ]);
        $project->logActivity('Referensi desain: berkas ' . $file->getClientOriginalName());

        return back()->with('success', 'Berkas referensi tersimpan.');
    }
}
