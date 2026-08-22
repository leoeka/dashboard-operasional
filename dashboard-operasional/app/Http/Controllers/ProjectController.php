<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MockupTemplate;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Proposal;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\AiServices;
use App\Services\ZipWpMcpService;
use App\Services\ScreenshotService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private AiServices $aiServices;

    public function __construct(AiServices $aiServices)
    {
        $this->aiServices = $aiServices;
    }

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
        $project->load(['client', 'tasks', 'files', 'activityLogs', 'mockupTemplate',]);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        $clients = Client::orderBy('company_name')->get();

        return view('projects.form', [
            'project' => $project,
            'clients' => $clients,
            'clientWebsiteUrls' => $this->buildClientWebsiteUrlMap(),
        ]);
    }

    private function buildClientWebsiteUrlMap(): array
    {
        return Project::whereNotNull('client_id')
            ->with('mockupTemplate')
            ->get()
            ->groupBy('client_id')
            ->map(function ($projects) {
                return $projects
                    ->map(function ($p) {
                        // Ambil dari mockup_templates.source_url (kolom yang
                        // SUDAH ADA), fallback ke seo_requirements['target_url']
                        // kalau project itu tidak punya mockup (kasus client
                        // yang sudah punya website sendiri dari luar).
                        return $p->mockupTemplate->source_url
                            ?? ($p->seo_requirements['target_url'] ?? null);
                    })
                    ->filter()
                    ->unique()
                    ->values();
            })
            ->filter(fn($urls) => $urls->isNotEmpty())
            ->toArray();
    }

    // public function update(Request $request, Project $project)
    // {
    //     $data = $request->validate([
    //         'client_id' => 'nullable|exists:clients,id',
    //         'name' => 'required|string|max:255',
    //         'client_name' => 'required|string|max:255',
    //         'type' => 'nullable|string|max:255',
    //         'status' => 'required|in:request,proposal,mockup,development,qa,active,done',
    //     ]);

    //     $statusChanged = $project->status !== $data['status'];

    //     $project->update($data);

    //     if ($statusChanged && $data['status'] === 'done') {
    //         $project->logActivity('Project selesai');
    //     } else {
    //         $project->logActivity('Informasi project diperbarui');
    //     }

    //     return redirect()->route('pages.projects.show', $project)->with('success', 'Project berhasil diperbarui.');
    // }


    public function update(Request $request, Project $project)
    {
        $serviceTypes = $request->input('service_type', []);
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
            'type' => 'nullable|string|max:255',
            'status' => 'required|in:request,in_progress,completed',

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

        $statusChanged = $project->status !== $data['status'];

        $project->update($data);

        if ($wantsSeo) {
            $project->update([
                'seo_requirements' => [
                    'target_url' => $request->input('seo_target_url'),
                    'keywords' => $request->input('seo_keywords'),
                    'location' => $request->input('seo_location'),
                    'competitors' => $request->input('seo_competitors'),
                    'cms_platform' => $request->input('seo_cms_platform'),
                ],
            ]);
        }

        if ($wantsBacklink) {
            $project->update([
                'backlink_requirements' => [
                    'target_url' => $request->input('backlink_target_url'),
                    'quantity' => $request->input('backlink_quantity'),
                    'anchor_text' => $request->input('backlink_anchor_text'),
                    'niche' => $request->input('backlink_niche'),
                    'anchor_type' => $request->input('backlink_anchor_type', []),
                    'priority' => $request->input('backlink_priority'),
                ],
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

                $project->logActivity('Hasil analisis SEO & Backlink (preview sebelum submit) diterapkan ke project');
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
                    ['status' => 'queued', 'progress' => 0, 'message' => 'Menunggu diproses...'],
                    now()->addMinutes(10)
                );

                \App\Jobs\GenerateKeywordRecommendationsJob::dispatch($project->fresh());
            }
        }

        if ($statusChanged && $data['status'] === 'completed') {
            $project->logActivity('Project completed');
        } else {
            $project->logActivity('Informasi project diperbarui');
        }

        return redirect()->route('pages.projects.show', $project)->with('success', 'Project berhasil diperbarui.');
    }


    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('pages.projects')->with('success', 'Project berhasil dihapus.');
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
        $project->logActivity("File {$label} ditambahkan");

        return back()->with('success', 'File berhasil diunggah.');
    }


    public function addmockupTemplate(Request $request, Project $project)
    {
        $request->validate([
            'mockup_template_id' => 'required|exists:mockup_templates,id',
        ]);

        $project->update(['mockup_template_id' => $request->mockup_template_id]);

        $project->logActivity("Mockup template dipilih: {$request->mockup_template_id}");

        return back()->with('success');
    }

    public function mockupTemplates(Request $request, ZipWpMcpService $zipWp)
    {
        $search = $request->search ?: $request->industry;

        try {
            $data = $zipWp->listTemplates(
                search: $search,
                page: (int) $request->input('page', 1),
                perPage: 12
            );
        } catch (\Throwable $e) {
            report($e);
            return view('mockup.index', [
                'templates' => [],
                'currentPage' => 1,
                'lastPage' => 1,
                'totalItems' => 0,
            ])->with('error', 'Gagal terhubung ke ZipWP. Coba lagi beberapa saat.');
        }

        $templates = $data['templates'] ?? [];

        if ($request->filled('is_premium')) {
            $templates = array_values(array_filter($templates, function ($t) use ($request) {
                return ((bool) $t['is_premium']) === ($request->is_premium === 'premium');
            }));
        }

        // TAMBAHKAN INI
        if ($request->filled('page_builder')) {
            $templates = array_values(array_filter($templates, function ($t) use ($request) {
                return strtolower($t['page_builder']) === strtolower($request->page_builder);
            }));
        }

        return view('pages.mockup', [
            'templates' => $templates,
            'currentPage' => $data['currentPage'] ?? 1,
            'lastPage' => $data['lastPage'] ?? 1,
            'totalItems' => $data['totalItems'] ?? 0,
        ]);
    }

    private function safeText($value, string $fallback = ''): string
    {
        if (is_array($value)) {
            // Coba beberapa key umum yang sering muncul di struktur AI
            $candidates = [
                'ai_mockup_prompt_guide',
                'grid_system',
                'visual_identity_style',
                'description',
                'summary',
            ];

            foreach ($candidates as $key) {
                if (isset($value[$key]) && is_string($value[$key])) {
                    return $value[$key];
                }
            }

            // Kalau tidak ketemu field string yang cocok, fallback ke JSON penuh
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        if (is_null($value) || $value === '') {
            return $fallback;
        }

        return (string) $value;
    }

    public function generateProposal(Project $project)
    {
        $this->reportProgress($project, 'queued', 0, 'Menunggu diproses...');
        \App\Jobs\GenerateProposalJob::dispatch($project);
        return response()->json(['queued' => true]);
    }
    public function proposalStatus(Project $project)
    {
        return response()->json(
            Cache::get($this->progressCacheKey($project->id), [
                'status' => 'idle',
                'progress' => 0,
                'message' => '',
            ])
        );
    }


    /**
     * ============================================================
     * GANTI SELURUH METHOD generateProposal() DENGAN INI
     * ============================================================
     */
    /**
     * Jalankan proses pembuatan proposal & report progress ke Cache/DB.
     */
    public function runProposalGeneration(Project $project, AiServices $aiService, ZipWpMcpService $zipWp): void
    {
        @set_time_limit(180);

        // 1. UPDATE PROGRESS: Load Data
        $this->reportProgress($project, 'processing', 10, 'Mengambil data project dan client...');

        $project->load(['client', 'files']);
        $client = $project->client;

        if (!$client) {
            $this->reportProgress($project, 'failed', 0, 'Project ini belum terhubung dengan data client.');
            throw new \Exception('Project belum terhubung dengan data client.');
        }

        // 2. UPDATE PROGRESS: Ambil daftar template ZipWP dulu (kalau belum
        // ada site tersimpan), supaya bisa dikasih ke GPT sebagai konteks
        // saat analisis desain — GPT yang langsung memilih template-nya,
        // bukan proses terpisah setelah analisis selesai.
        $bestTemplate = null;
        $zipWpSiteUuid = null;
        $zipWpSiteUrl = null;
        $mockupFailReason = null;
        $candidates = [];
        $reuseExistingSite = !empty($project->zipwp_site_url) && !empty($project->zipwp_site_uuid);

        if ($reuseExistingSite) {
            $zipWpSiteUuid = $project->zipwp_site_uuid;
            $zipWpSiteUrl = $project->zipwp_site_url;
            $bestTemplate = [
                'uuid' => $project->zipwp_template_uuid,
                'name' => $project->zipwp_template_name,
                'preview_url' => $project->zipwp_template_preview_url,
            ];
        } else {
            $this->reportProgress($project, 'processing', 15, 'Mengambil daftar template ZipWP...');

            try {
                $candidates = $zipWp->listTemplates(
                search: $project->type ?? '',
                perPage: 30
                )['templates'] ?? [];

                Log::info('ZipWP listTemplates result', [
                    'project_id' => $project->id,
                    'search' => $project->type,
                    'candidate_count' => count($candidates),
                ]);

                // Fallback: kalau pencarian dengan search=project->type tidak
                // menghasilkan apa-apa (kemungkinan besar penamaan kategori
                // di ZipWP beda dengan project->type kita), coba ambil daftar
                // template tanpa filter search sama sekali.
                if (empty($candidates)) {
                    Log::info('ZipWP listTemplates: search kosong, mencoba tanpa filter search', [
                        'project_id' => $project->id,
                    ]);

                    $candidates = $zipWp->listTemplates(
                    search: null,
                    perPage: 50
                    )['templates'] ?? [];

                    Log::info('ZipWP listTemplates (tanpa search) result', [
                        'project_id' => $project->id,
                        'candidate_count' => count($candidates),
                    ]);
                }

                if (empty($candidates)) {
                    $mockupFailReason = "Tidak ada template ZipWP yang tersedia sama sekali (dicoba dengan & tanpa filter search).";
                    Log::warning($mockupFailReason, ['project_id' => $project->id]);
                }
            } catch (\Throwable $e) {
                Log::error('ZipWP listTemplates Error: ' . $e->getMessage(), ['project_id' => $project->id]);
                $mockupFailReason = 'Gagal mengambil daftar template ZipWP.';
            }
        }

        // 3. UPDATE PROGRESS: AI Analysis (Gemini bisnis + GPT desain & pilih template)
        $this->reportProgress($project, 'processing', 25, 'Menganalisis profil bisnis dengan AI...');

        try {
            $analysis = $aiService->analyzeProject($project, $client, $candidates);
        } catch (\Throwable $e) {
            $this->reportProgress($project, 'failed', 0, 'Gagal analisis AI: ' . $e->getMessage());
            throw $e;
        }

        if (!is_array($analysis)) {
            $analysis = [
                'business_analysis' => '',
                'market_analysis' => '',
                'target_market' => '',
                'website_objective' => '',
                'sitemap' => '',
                'content_strategy' => '',
                'design_direction' => '',
            ];
        }

        $analysisSummary = [
            'business_analysis' => $this->safeText($analysis['business_analysis'] ?? null),
            'target_market' => $this->safeText($analysis['target_market'] ?? null),
            'website_objective' => $this->safeText($analysis['website_objective'] ?? null),
            'sitemap' => $this->safeText($analysis['sitemap'] ?? null),
            'content_strategy' => $this->safeText($analysis['content_strategy'] ?? null),
        ];

        $designDirectionRaw = $analysis['design_direction'] ?? null;

        // 4. UPDATE PROGRESS: Bangun website di ZipWP pakai template pilihan GPT
        $this->reportProgress($project, 'processing', 50, 'Membuat website preview...');

        if (!$reuseExistingSite) {
            // Template sekarang dipilih langsung oleh GPT sebagai bagian dari
            // analisis desain (lihat template_selection di AiServices::analyzeDesignWithGpt),
            // bukan proses cocok-cocokan keyword terpisah setelah analisis.
            $gptSelection = $analysis['template_selection'] ?? null;

            if ($gptSelection && !empty($gptSelection['uuid'])) {
                $bestTemplate = collect($candidates)->firstWhere('uuid', $gptSelection['uuid']);
            }

            if (!$bestTemplate && !empty($candidates)) {
                // Fallback terakhir kalau GPT gagal / uuid tidak valid.
                $bestTemplate = $aiService->pickBestTemplate(
                    $candidates,
                    $project->type ?? 'Company Profile',
                    $project->description ?? ''
                );
            }

            if ($bestTemplate) {
                try {
                    $project->update([
                        'zipwp_template_uuid' => $bestTemplate['uuid'] ?? $bestTemplate['slug'] ?? $bestTemplate['id'] ?? null,
                        'zipwp_template_name' => $bestTemplate['name'] ?? 'Template',
                        'zipwp_template_preview_url' => $bestTemplate['preview_url'] ?? $bestTemplate['demo_url'] ?? null,
                    ]);

                    $enrichedDesc = $this->buildEnrichedBusinessDesc($project, $analysis);

                    $createResult = $zipWp->createAiSite([
                        'business_name' => $project->name,
                        'business_desc' => $enrichedDesc,
                        'business_category_name' => $project->type ?? 'Business',
                        'template' => $bestTemplate['uuid'] ?? '',
                        'title' => $project->name,
                        'business_email' => $client->email ?? null,
                        'business_phone' => $client->phone ?? null,
                        'language' => 'en',
                    ]);

                    $zipWpSiteUuid = $createResult['site_uuid'] ?? $createResult['uuid'] ?? null;

                    Log::info('ZipWP createAiSite result', [
                        'project_id' => $project->id,
                        'site_uuid' => $zipWpSiteUuid,
                        'raw_result' => $createResult,
                    ]);

                    if (!$zipWpSiteUuid) {
                        $mockupFailReason = 'ZipWP tidak mengembalikan site_uuid saat create-ai-site.';
                        Log::warning($mockupFailReason, ['project_id' => $project->id, 'raw_result' => $createResult]);
                    }

                    if ($zipWpSiteUuid) {
                        // UBAH JEDA POLLING ZIPWP DARI 10 DETIK MENJADI 5 DETIK
                        $maxAttempts = 20; // 20 attempt x 5 detik = 100 detik total max
                        $attempt = 0;

                        while ($attempt < $maxAttempts) {
                            sleep(5); // <-- Ganti 10 jadi 5
                            $attempt++;

                            // Update progress secara berkala saat polling
                            $currentProgress = 50 + ($attempt * 2);
                            $this->reportProgress($project, 'processing', $currentProgress, "Membangun website ZipWP (Langkah {$attempt}/20)...");

                            $progressStatus = $zipWp->getSiteProgress($zipWpSiteUuid);

                            if (($progressStatus['is_ready'] ?? false) === true) {
                                $zipWpSiteUrl = $progressStatus['site_url'] ?? null;
                                break;
                            }

                            if (($progressStatus['status'] ?? null) === 'failed') {
                                $mockupFailReason = 'ZipWP gagal membangun site.';
                                Log::warning($mockupFailReason, ['project_id' => $project->id, 'progress_status' => $progressStatus]);
                                break;
                            }
                        }

                        if (!$zipWpSiteUrl && !$mockupFailReason) {
                            $mockupFailReason = "Timeout: ZipWP belum selesai membangun site setelah {$maxAttempts}x percobaan (site_uuid: {$zipWpSiteUuid}).";
                            Log::warning($mockupFailReason, ['project_id' => $project->id]);
                        }

                        if ($zipWpSiteUrl) {
                            $project->update([
                                'zipwp_site_uuid' => $zipWpSiteUuid,
                                'zipwp_site_url' => $zipWpSiteUrl,
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('ZipWP Error: ' . $e->getMessage());
                    $mockupFailReason = 'Gagal generate website otomatis.';
                }
            }
        }

        // 4B. Mockup boleh gagal tanpa membatalkan proposal. Misalnya quota
        // ZipWP habis, PDF tetap berguna dan bagian mockup akan menampilkan
        // keterangan bahwa preview belum tersedia.
        if (!$reuseExistingSite && $mockupFailReason) {
            Log::warning('Mockup website tidak tersedia, proposal tetap dilanjutkan.', [
                'project_id' => $project->id,
                'reason' => $mockupFailReason,
            ]);
            $this->reportProgress($project, 'processing', 75, 'Mockup tidak tersedia, melanjutkan pembuatan PDF proposal...');
        }

        // 3B. Ambil screenshot dari situs ZipWP (kalau site_url tersedia) untuk ditempel di PDF
        $mockupScreenshotPath = null;
        if ($zipWpSiteUrl) {
            $this->reportProgress($project, 'processing', 75, 'Mengambil screenshot preview website...');

            $screenshotService = app(ScreenshotService::class);
            $savedPath = $screenshotService->capture($zipWpSiteUrl, "mockups/{$project->id}.png");

            if ($savedPath) {
                $mockupScreenshotPath = Storage::disk('public')->path($savedPath);
            } else {
                Log::warning('Screenshot mockup gagal/dilewati, PDF akan fallback ke link saja.', [
                    'project_id' => $project->id,
                    'site_url' => $zipWpSiteUrl,
                ]);
            }
        }

        // 4. UPDATE PROGRESS: Generate PDF Proposal
        $this->reportProgress($project, 'processing', 80, 'Menyusun dokumen PDF proposal...');

        if ($bestTemplate && !$project->mockup_template_id) {
            $mockupTemplate = MockupTemplate::create([
                'name' => ($bestTemplate['name'] ?? 'AI Generated') . ' (AI - ' . $project->client_name . ')',
                'category' => $project->type ?? 'company_profile',
                'preview_image' => null,
                'theme_slug' => 'ai:' . $project->id . ':' . ($bestTemplate['uuid'] ?? Str::random(8)),
                'source_url' => $zipWpSiteUrl ?? ($bestTemplate['preview_url'] ?? null),
                'site_uuid' => $zipWpSiteUuid,
                'description' => $this->safeText($designDirectionRaw, 'AI Generated Website'),
            ]);

            $project->update(['mockup_template_id' => $mockupTemplate->id]);
        }

        $mockup = [
            'title' => $project->name . ' — Website Preview',
            'site_url' => $zipWpSiteUrl,
            'wp_admin_url' => $zipWpSiteUrl ? rtrim($zipWpSiteUrl, '/') . '/wp-admin' : null,
            'template_name' => $bestTemplate['name'] ?? null,
            'design_direction' => $this->safeText($designDirectionRaw),
            'fail_reason' => $mockupFailReason,
            'screenshot_path' => $mockupScreenshotPath,
        ];

        $projectData = [
            'project_name' => $project->name,
            'client_name' => $project->client_name,
            'website_type' => $project->type ?? 'Company Profile',
            'project_code' => $project->code,
            'generated_at' => now()->format('d F Y H:i'),
        ];

        try {
            $pdf = Pdf::loadView('pdf.proposal', [
                'project' => $project,
                'projectData' => $projectData,
                'analysis' => $analysis,
                'analysisSummary' => $analysisSummary,
                'mockup' => $mockup,
            ]);

            $clientSlug = Str::slug($project->client_name);
            $fileName = "proposals/Proposal-Mockup-{$clientSlug}-{$project->code}.pdf";
            Storage::disk('public')->put($fileName, $pdf->output());

            Proposal::updateOrCreate(
                ['project_id' => $project->id],
                [
                    'client_name' => $project->client_name,
                    'status' => 'pending',
                    'pdf_path' => $fileName,
                    'version' => 1,
                    'ai_reasoning' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
                    'summary' => $this->safeText($designDirectionRaw),
                ]
            );

            // 5. UPDATE PROGRESS: Selesai!
            $completionMessage = $mockupFailReason
                ? 'Proposal berhasil dibuat; mockup belum tersedia karena layanan ZipWP gagal.'
                : 'Proposal dan Website berhasil dibuat!';
            $this->reportProgress($project, 'completed', 100, $completionMessage);

        } catch (\Throwable $e) {
            Log::error('PDF Error: ' . $e->getMessage());
            $this->reportProgress($project, 'failed', 0, 'PDF Proposal gagal dibuat: ' . $e->getMessage());
            throw $e;
        }
    }

    private function buildEnrichedBusinessDesc(Project $project, array $analysis): string
    {
        $parts = [];

        // Description dasar dari user (wajib ada)
        $parts[] = $project->description ?: ($project->name . ' - ' . ($project->type ?? 'Business'));

        // Value proposition dari business_analysis (kalau ada)
        $valueProposition = $analysis['business_analysis']['value_proposition']
            ?? $analysis['business_analysis']['brand_identity']
            ?? null;
        if ($valueProposition && is_string($valueProposition)) {
            $parts[] = "Value proposition: {$valueProposition}";
        }

        // Target market ringkas
        $targetDemo = $analysis['target_market']['demographics'] ?? null;
        if ($targetDemo && is_string($targetDemo)) {
            $parts[] = "Target market: {$targetDemo}";
        }

        // Tone of voice dari content_strategy
        $tone = $analysis['content_strategy']['tone_of_voice'] ?? null;
        if ($tone && is_string($tone)) {
            $parts[] = "Brand tone: {$tone}";
        }

        $combined = implode('. ', $parts);

        // Jaga-jaga kalau ZipWP ada limit panjang karakter (potong di ~500 karakter,
        // sesuaikan angka ini kalau ternyata limitnya beda setelah dites)
        return Str::limit($combined, 500, '');
    }

    public function previewProposal(Project $project)
    {
        $proposal = Proposal::where('project_id', $project->id)
            ->latest()
            ->first();

        if (!$proposal) {
            return redirect()
                ->route('pages.projects.show', $project)
                ->with('error', 'Proposal belum dibuat.');
        }

        return view('projects.proposal-preview', compact(
            'project',
            'proposal'
        ));
    }


    public function downloadProposal(Project $project)
    {
        $proposal = Proposal::where('project_id', $project->id)
            ->latest()
            ->first();

        if (!$proposal || !$proposal->pdf_path) {
            return back()->with('error', 'PDF proposal belum dibuat.');
        }

        if (!Storage::disk('public')->exists($proposal->pdf_path)) {
            return back()->with('error', 'File PDF tidak ditemukan.');
        }

        return response()->download(
            Storage::disk('public')->path($proposal->pdf_path),
            basename($proposal->pdf_path),
            ['Content-Type' => 'application/pdf']
        );
    }
    public function aiWorkspace(Request $request)
    {
        $projects = Project::orderBy('name')->get();

        $project = null;
        if ($request->project) {
            $project = Project::with('mockupTemplate')->find($request->project);
        }

        return view('pages.seo-backlink', compact('projects', 'project'));
    }

    private function progressCacheKey(int $projectId): string
    {
        return "proposal_progress:{$projectId}";
    }

    private function reportProgress(Project $project, string $status, int $progress, string $message): void
    {
        Cache::put(
            $this->progressCacheKey($project->id),
            ['status' => $status, 'progress' => $progress, 'message' => $message],
            now()->addMinutes(10)
        );
    }

    public function analyzeSeoBacklink(Project $project)
    {
        Cache::put(
            \App\Jobs\GenerateKeywordRecommendationsJob::cacheKey($project->id),
            ['status' => 'queued', 'progress' => 0, 'message' => 'Menunggu diproses...'],
            now()->addMinutes(10)
        );

        \App\Jobs\GenerateKeywordRecommendationsJob::dispatch($project);

        return response()->json(['queued' => true]);
    }

    public function seoBacklinkStatus(Project $project)
    {
        return response()->json(
            Cache::get(\App\Jobs\GenerateKeywordRecommendationsJob::cacheKey($project->id), [
                'status' => 'idle',
                'progress' => 0,
                'message' => '',
            ])
        );
    }

    /**
     * Mode PREVIEW: jalankan analisis SEO/Backlink TANPA project di
     * database — dipakai tombol "Analisis Sekarang" di form, sebelum
     * client/project benar-benar dibuat.
     */
    public function analyzeSeoBacklinkPreview(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
        ]);

        $token = (string) Str::uuid();

        Cache::put(
            $this->previewCacheKey($token),
            ['status' => 'queued', 'progress' => 0, 'message' => 'Menunggu diproses...'],
            now()->addMinutes(15)
        );

        \App\Jobs\RunKeywordPreviewAnalysisJob::dispatch(
            $token,
            $data['url'],
            $data['location'] ?? null,
            $data['business_name'] ?? null,
            $data['business_type'] ?? null,
        );

        return response()->json(['token' => $token]);
    }

    public function seoBacklinkPreviewStatus(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['status' => 'idle', 'progress' => 0, 'message' => '']);
        }

        return response()->json(
            Cache::get($this->previewCacheKey($token), [
                'status' => 'idle',
                'progress' => 0,
                'message' => '',
            ])
        );
    }

    private function previewCacheKey(string $token): string
    {
        return "keyword_preview:{$token}";
    }

    public function analyzePageSpeed(Project $project)
    {
        Cache::put(
            \App\Jobs\AnalyzePageSpeedJob::cacheKey($project->id),
            ['status' => 'queued', 'progress' => 0, 'message' => 'Menunggu diproses...'],
            now()->addMinutes(10)
        );

        \App\Jobs\AnalyzePageSpeedJob::dispatch($project);

        return response()->json(['queued' => true]);
    }

    public function pageSpeedStatus(Project $project)
    {
        return response()->json(
            Cache::get(\App\Jobs\AnalyzePageSpeedJob::cacheKey($project->id), [
                'status' => 'idle',
                'progress' => 0,
                'message' => '',
            ])
        );
    }

    public function analyzeSearchConsole(Project $project, \App\Services\SearchConsoleService $service)
    {
        $url = $project->seo_requirements['target_url']
            ?? $project->backlink_requirements['target_url']
            ?? $project->mockupTemplate?->source_url
            ?? null;

        if (!$url) {
            return response()->json(['success' => false, 'message' => 'URL website belum tersedia.'], 422);
        }

        $result = $service->getPerformance($url);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data — cek apakah website ini sudah terverifikasi di akun Search Console kantor, atau kredensial GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN belum diisi.',
            ], 422);
        }

        $seo = $project->seo_requirements ?? [];
        $seo['search_console'] = array_merge($result, ['analyzed_at' => now()->toDateTimeString()]);
        $project->update(['seo_requirements' => $seo]);

        $project->logActivity('Laporan Search Console diperbarui');

        return response()->json(['success' => true]);
    }

    public function analyzeGoogleAnalytics(Request $request, Project $project, \App\Services\GoogleAnalyticsService $service)
    {
        $url = $project->seo_requirements['target_url']
            ?? $project->backlink_requirements['target_url']
            ?? $project->mockupTemplate?->source_url
            ?? null;

        if (!$url) {
            return response()->json(['success' => false, 'message' => 'URL website belum tersedia.'], 422);
        }

        $accessToken = $service->getAccessToken();
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial GA4 belum lengkap — cek GOOGLE_ANALYTICS_REFRESH_TOKEN di .env.',
            ], 422);
        }

        $seo = $project->seo_requirements ?? [];
        $propertyId = $request->input('property_id') ?: ($seo['ga4_property_id'] ?? null);

        if (!$propertyId) {
            $resolved = $service->resolveProperty($accessToken, $url);

            if ($resolved['status'] === 'ambiguous') {
                return response()->json([
                    'success' => false,
                    'needs_selection' => true,
                    'candidates' => $resolved['candidates'],
                    'message' => 'Ketemu lebih dari 1 Property GA4 yang cocok.',
                ], 422);
            }

            if ($resolved['status'] === 'not_found') {
                return response()->json([
                    'success' => false,
                    'needs_manual_input' => true,
                    'message' => 'Tidak ketemu Property GA4 otomatis.',
                ], 422);
            }

            $propertyId = $resolved['property_id'];
        }

        $result = $service->getReport($accessToken, $propertyId);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dari GA4 — cek Property ID atau akses akun ke property ini.',
            ], 422);
        }

        $seo['ga4_property_id'] = $propertyId;
        $seo['google_analytics'] = array_merge($result, ['analyzed_at' => now()->toDateTimeString()]);
        $project->update(['seo_requirements' => $seo]);

        $project->logActivity('Laporan Google Analytics (GA4) diperbarui');

        return response()->json(['success' => true]);
    }

    public function downloadSeoBacklinkReport(Project $project)
    {
        $seo = $project->seo_requirements ?? [];
        $backlink = $project->backlink_requirements ?? [];

        $discoveredCompetitors = collect(explode("\n", $seo['competitors'] ?? ''))
            ->map(fn($u) => trim($u))
            ->filter()
            ->values();

        $pdf = Pdf::loadView('pdf.seo-backlink-report', [
            'project' => $project,
            'seo' => $seo,
            'backlink' => $backlink,
            'aiRecommendations' => $seo['ai_recommendations'] ?? null,
            'aiTopics' => $seo['ai_identified_topics'] ?? null,
            'discoveredCompetitors' => $discoveredCompetitors,
            'competitorPagespeed' => $seo['competitor_pagespeed'] ?? null,
            'pagespeed' => $seo['pagespeed'] ?? null,
            'searchConsole' => $seo['search_console'] ?? null,
            'ga4' => $seo['google_analytics'] ?? null,
            'generatedAt' => now()->format('d F Y H:i'),
        ]);

        $clientSlug = Str::slug($project->client_name);
        $fileName = "Laporan-SEO-Backlink-{$clientSlug}-{$project->code}.pdf";

        return $pdf->download($fileName);
    }

    public function analyzeCompetitorPageSpeed(Project $project)
    {
        \App\Jobs\AnalyzeCompetitorPageSpeedJob::dispatch($project->id);

        Cache::put(
            \App\Jobs\AnalyzeCompetitorPageSpeedJob::cacheKey($project->id),
            ['status' => 'running', 'progress' => 0, 'message' => 'Memulai...'],
            now()->addMinutes(10)
        );

        return response()->json(['success' => true]);
    }

    public function selectCompetitors(Request $request, Project $project)
    {
        $validated = $request->validate([
            'selected_urls' => ['nullable', 'array'],
            'selected_urls.*' => ['string'],
            'manual_urls' => ['nullable', 'string'],
        ]);

        $manualUrls = collect(explode("\n", $validated['manual_urls'] ?? ''))
            ->map(fn($u) => trim($u))
            ->filter()
            ->filter(fn($u) => filter_var($u, FILTER_VALIDATE_URL) !== false)
            ->values();

        $checkedUrls = collect($validated['selected_urls'] ?? []);

        $combined = $checkedUrls->merge($manualUrls)->unique()->values();

        if ($combined->count() > 3) {
            return response()->json([
                'success' => false,
                'message' => 'Total kompetitor (centang + manual) maksimal 3.',
            ], 422);
        }

        if ($combined->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal 1 kompetitor.',
            ], 422);
        }

        $seo = $project->seo_requirements ?? [];
        $seo['selected_competitors'] = $combined->all();
        $project->update(['seo_requirements' => $seo]);

        \App\Jobs\AnalyzeCompetitorPageSpeedJob::dispatch($project->id);

        Cache::put(
            \App\Jobs\AnalyzeCompetitorPageSpeedJob::cacheKey($project->id),
            ['status' => 'running', 'progress' => 0, 'message' => 'Memulai...'],
            now()->addMinutes(10)
        );

        return response()->json(['success' => true]);
    }

    public function selectKeywords(Request $request, Project $project)
    {
        $validated = $request->validate([
            'selected_keywords' => ['nullable', 'array'],
            'selected_keywords.*' => ['string'],
        ]);

        $selected = collect($validated['selected_keywords'] ?? []);

        $seo = $project->seo_requirements ?? [];
        $mainKeywords = $seo['ai_recommendations']['main_keywords'] ?? [];

        if (empty($mainKeywords)) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada hasil analisis keyword untuk project ini.',
            ], 422);
        }

        $mainKeywords = collect($mainKeywords)
            ->map(function ($kw) use ($selected) {
                $kw['selected'] = $selected->contains($kw['keyword'] ?? null);
                return $kw;
            })
            ->values()
            ->all();

        $seo['ai_recommendations']['main_keywords'] = $mainKeywords;
        $project->update(['seo_requirements' => $seo]);

        $project->logActivity('Pilihan keyword untuk proposal diperbarui (' . $selected->count() . ' dipilih)');

        return response()->json(['success' => true]);
    }


    public function uploadManualScreenshot(Request $request, Project $project)
    {
        $validated = $request->validate([
            'target' => ['required', 'string', 'regex:/^(own_pagespeed|own_semrush|competitor_pagespeed:.+|competitor_semrush:.+)$/'],
            'screenshot' => ['required', 'image', 'max:5120'],
            'return_tab' => ['nullable', 'in:performa'],
        ]);

        $seo = $project->seo_requirements ?? [];
        $manualScreenshots = $seo['manual_screenshots'] ?? [];

        [$slotKey, $competitorHost] = array_pad(explode(':', $validated['target'], 2), 2, null);
        $isCompetitorSlot = in_array($slotKey, ['competitor_pagespeed', 'competitor_semrush']);

        $existingPath = $isCompetitorSlot
            ? (is_array($manualScreenshots[$slotKey] ?? null) ? ($manualScreenshots[$slotKey][$competitorHost] ?? null) : null)
            : ($manualScreenshots[$slotKey] ?? null);

        if ($existingPath && Storage::disk('public')->exists($existingPath)) {
            Storage::disk('public')->delete($existingPath);
        }

        $newPath = $request->file('screenshot')->store('manual-screenshots/' . $project->id, 'public');

        if ($isCompetitorSlot) {
            if (!isset($manualScreenshots[$slotKey]) || !is_array($manualScreenshots[$slotKey])) {
                $manualScreenshots[$slotKey] = [];   // reset kalau data lama rusak (bukan array)
            }
            $manualScreenshots[$slotKey][$competitorHost] = $newPath;
        } else {
            $manualScreenshots[$slotKey] = $newPath;
        }

        $seo['manual_screenshots'] = $manualScreenshots;
        $project->update(['seo_requirements' => $seo]);

        return $this->manualScreenshotRedirect($request, $project)
            ->with('success', 'Screenshot berhasil diunggah.');
    }

    public function deleteManualScreenshot(Request $request, Project $project)
    {
        $validated = $request->validate([
            'target' => ['required', 'string', 'regex:/^(own_pagespeed|own_semrush|competitor_pagespeed:.+|competitor_semrush:.+)$/'],
            'return_tab' => ['nullable', 'in:performa'],
        ]);

        $seo = $project->seo_requirements ?? [];
        $manualScreenshots = $seo['manual_screenshots'] ?? [];

        [$slotKey, $competitorHost] = array_pad(explode(':', $validated['target'], 2), 2, null);
        $isCompetitorSlot = in_array($slotKey, ['competitor_pagespeed', 'competitor_semrush']);

        $path = $isCompetitorSlot
            ? (is_array($manualScreenshots[$slotKey] ?? null) ? ($manualScreenshots[$slotKey][$competitorHost] ?? null) : null)
            : ($manualScreenshots[$slotKey] ?? null);

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        if ($isCompetitorSlot) {
            unset($manualScreenshots[$slotKey][$competitorHost]);
        } else {
            unset($manualScreenshots[$slotKey]);
        }

        $seo['manual_screenshots'] = $manualScreenshots;
        $project->update(['seo_requirements' => $seo]);

        return $this->manualScreenshotRedirect($request, $project)
            ->with('success', 'Screenshot dihapus.');
    }

    /** Keep manually uploaded performance screenshots on the Performa tab. */
    private function manualScreenshotRedirect(Request $request, Project $project)
    {
        if ($request->input('return_tab') === 'performa') {
            return redirect()->to(route('pages.seo-backlink', ['project' => $project->id]) . '#performa');
        }

        return back();
    }

    public function downloadSeoProposal(Project $project)
    {
        $seo = $project->seo_requirements ?? [];
        $backlink = $project->backlink_requirements ?? [];

        $discoveredCompetitors = collect(explode("\n", $seo['competitors'] ?? ''))
            ->map(fn($u) => trim($u))
            ->filter()
            ->values();

        $pdf = Pdf::loadView('pdf.seo-proposal', [
            'project' => $project,
            'seo' => $seo,
            'backlink' => $backlink,
            'aiRecommendations' => $seo['ai_recommendations'] ?? null,
            'aiTopics' => $seo['ai_identified_topics'] ?? null,
            'discoveredCompetitors' => $discoveredCompetitors,
            'competitorPagespeed' => $seo['competitor_pagespeed'] ?? null,
            'pagespeed' => $seo['pagespeed'] ?? null,
            'generatedAt' => now()->format('d F Y H:i'),
        ]);

        $clientSlug = Str::slug($project->client_name);
        $fileName = "Proposal-SEO-{$clientSlug}-{$project->code}.pdf";

        return $pdf->download($fileName);
    }

    public function competitorPageSpeedStatus(Project $project)
    {
        return response()->json(
            Cache::get(
                \App\Jobs\AnalyzeCompetitorPageSpeedJob::cacheKey($project->id),
                ['status' => 'idle', 'progress' => 0, 'message' => '']
            )
        );
    }

}
