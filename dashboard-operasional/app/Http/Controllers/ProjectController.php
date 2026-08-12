<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MockupTemplate;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectTask;
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
use Illuminate\Http\RedirectResponse; // <-- Tambahkan ini




class ProjectController extends Controller
{

    private AiServices $aiServices;

    public function __construct(AiServices $aiServices)
    {
        $this->aiServices = $aiServices;
    }
    private array $defaultTasks = [
        'Homepage',
        'About',
        'Services',
        'Gallery',
        'Contact',
        'Blog',
        'SEO',
        'QA',
    ];

    public function index(Request $request)
    {
        $projects = Project::query()
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('client_name', 'like', "%{$request->search}%");
            })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->deadline, function ($q) use ($request) {
                match ($request->deadline) {
                    'week' => $q->whereBetween('deadline', [now()->startOfWeek(), now()->endOfWeek()]),
                    'month' => $q->whereBetween('deadline', [now()->startOfMonth(), now()->endOfMonth()]),
                    'overdue' => $q->whereDate('deadline', '<', now())->whereNotIn('status', ['done']),
                    default => null,
                };
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        $clients = Client::orderBy('company_name')->get();

        return view('projects.form', ['project' => new Project(), 'clients' => $clients]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'name' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'status' => 'required|in:request,proposal,mockup,development,qa,active,done',
            'deadline' => 'nullable|date',
        ]);

        $data['code'] = strtoupper(Str::random(3)) . '-' . random_int(1000, 9999);
        $data['progress'] = 0;

        $project = Project::create($data);

        foreach ($this->defaultTasks as $i => $title) {
            ProjectTask::create([
                'project_id' => $project->id,
                'title' => $title,
                'position' => $i,
            ]);
        }

        $project->logActivity('Project dibuat');

        return redirect()->route('pages.projects.show', $project)->with('success', 'Project berhasil dibuat.');
    }

    public function show(Project $project)
    {
        $project->load(['client', 'tasks', 'files', 'activityLogs', 'mockupTemplate',]);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        $clients = Client::orderBy('company_name')->get();

        return view('projects.form', compact('project', 'clients'));
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'name' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'status' => 'required|in:request,proposal,mockup,development,qa,active,done',
            'deadline' => 'nullable|date',
        ]);

        $statusChanged = $project->status !== $data['status'];

        $project->update($data);

        if ($statusChanged && $data['status'] === 'done') {
            $project->logActivity('Project selesai');
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

    public function storeTask(Request $request, Project $project)
    {
        $request->validate(['title' => 'required|string|max:255']);

        ProjectTask::create([
            'project_id' => $project->id,
            'title' => $request->title,
            'position' => $project->tasks()->count(),
        ]);

        $project->logActivity("Task ditambahkan: {$request->title}");

        return back()->with('success', 'Task ditambahkan.');
    }

    public function toggleTask(Project $project, ProjectTask $task)
    {
        $task->update(['is_done' => !$task->is_done]);

        $project->logActivity($task->is_done ? "{$task->title} selesai" : "{$task->title} dibuka lagi");

        $progress = $project->recalculateProgress();
        $project->logActivity("Progress menjadi {$progress}%");

        return back();
    }

    public function destroyTask(Project $project, ProjectTask $task)
    {
        $task->delete();
        $project->recalculateProgress();

        return back()->with('success', 'Task dihapus.');
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
                page: (int) $request->get('page', 1),
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

        // 4B. FAIL-FAST: kalau mockup ZipWP gagal di titik manapun (gagal ambil
        // daftar template, gagal create-ai-site, timeout polling, atau exception
        // lain), STOP TOTAL di sini. Job ditandai failed, PDF proposal TIDAK
        // dibuat sama sekali — daripada lanjut dengan status "berhasil" yang
        // menyesatkan padahal mockup situsnya gagal.
        if (!$reuseExistingSite && $mockupFailReason) {
            $failMessage = 'Gagal membuat mockup website (ZipWP): ' . $mockupFailReason;
            Log::error($failMessage, ['project_id' => $project->id]);
            $this->reportProgress($project, 'failed', 0, $failMessage);
            throw new \RuntimeException($failMessage);
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
            $this->reportProgress($project, 'completed', 100, 'Proposal dan Website berhasil dibuat!');

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

        return Storage::download(
            $proposal->pdf_path,
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

        return view('pages.ai-workspace', compact('projects', 'project'));
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

}