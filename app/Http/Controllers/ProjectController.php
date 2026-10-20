<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Project CRUD only. See WebsiteBuilderController for the AI proposal/
 * mockup/build pipeline and SeoBacklinkController for SEO/backlink
 * tooling — both used to live in this class.
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

    public function update(Request $request, Project $project)
    {
        $serviceTypes = (array) $request->input('service_type', []);
        $wantsWebsite = in_array('website', $serviceTypes);
        $wantsSeo = in_array('seo', $serviceTypes);
        $wantsBacklink = in_array('backlink', $serviceTypes);

        // FIX (fitur SEO & Backlink otomatis): tangkap URL LAMA sebelum
        // di-update, dipakai untuk deteksi "URL baru pertama kali terisi".
        $previousUrl = $project->seo_requirements['target_url']
            ?? $project->backlink_requirements['target_url']
            ?? null;

        $data = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'name' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'status' => 'required|in:request,in_progress,completed',

            // Bagian website meniru aturan form Request: wajib cuma kalau
            // tile "Create Website" dicentang.
            'type' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string', 'max:255'],
            'user_story' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string'],
            'target_market' => ['nullable', 'string'],
            'website_features' => [Rule::requiredIf($wantsWebsite), 'nullable', 'string'],
            'has_client_assets' => [Rule::requiredIf($wantsWebsite), 'nullable', 'boolean'],

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
        ]);

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
            $project->update([
                'design_reference_path' => $request->file('design_reference_file')->store('design-references', 'public'),
            ]);
        }

        if ($request->hasFile('client_logo') && $project->client) {
            $project->client->update([
                'logo_path' => $request->file('client_logo')->store('client-logos', 'public'),
            ]);
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

    public function storeFile(Request $request, Project $project)
    {
        $request->validate([
            'file' => 'required|file|max:20480',
            'category' => 'required|in:logo,company_profile,foto,dokumen,pendukung',
        ]);

        $path = $request->file('file')->store('project-files', 'public');

        ProjectFile::create([
            'project_id' => $project->id,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $path,
            'category' => $request->category,
        ]);

        $label = ProjectFile::categoryLabels()[$request->category] ?? 'File';
        $project->logActivity("File {$label} added");

        return back()->with('success', 'File uploaded successfully.');
    }
}
