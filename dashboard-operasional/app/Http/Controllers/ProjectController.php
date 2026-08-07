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


    /**
     * ============================================================
     * GANTI SELURUH METHOD generateProposal() DENGAN INI
     * ============================================================
     */
    public function generateProposal(Project $project, AiServices $aiService, ZipWpMcpService $zipWp)
    {
        // Proses ini bisa memakan waktu sampai ~2 menit (create-ai-site + polling),
        // jadi kasih waktu eksekusi lebih panjang dari default.
        set_time_limit(180);

        // =====================================================
        // 1. LOAD DATA PROJECT
        // =====================================================
        $project->load(['client', 'files']);
        $client = $project->client;

        if (!$client) {
            return redirect()
                ->back()
                ->with('error', 'Project ini belum terhubung dengan data client.');
        }

        // =====================================================
        // 2. AI ANALYSIS (Gemini: bisnis & pasar, GPT: struktur & desain)
        // =====================================================
        try {
            $analysis = $aiService->analyzeProject($project, $client);
        } catch (\RuntimeException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        // Kalau AI gagal balikin array yang benar, pakai struktur kosong sebagai fallback
        if (!is_array($analysis)) {
            $analysis = [
                'business_analysis' => '',
                'market_analysis' => '',
                'target_market' => '',
                'competitor_analysis' => '',
                'website_objective' => '',
                'sitemap' => '',
                'page_structure' => '',
                'content_strategy' => '',
                'cta_strategy' => '',
                'design_direction' => '',
            ];
        }

        // Siapkan versi STRING dari field yang dipakai di halaman "Strategy Summary"
        // PDF — field ini sekarang nested array (hasil Gemini+GPT), jadi wajib
        // di-convert ke string dulu di sini. INI SELALU DIJALANKAN, tidak
        // digantungkan pada kondisi apapun, supaya $analysisSummary selalu ada.
        $analysisSummary = [
            'business_analysis' => $this->safeText($analysis['business_analysis'] ?? null),
            'target_market' => $this->safeText($analysis['target_market'] ?? null),
            'website_objective' => $this->safeText($analysis['website_objective'] ?? null),
            'sitemap' => $this->safeText($analysis['sitemap'] ?? null),
            'content_strategy' => $this->safeText($analysis['content_strategy'] ?? null),
        ];

        $designDirectionRaw = $analysis['design_direction'] ?? null;

        // =====================================================
        // 3. PILIH TEMPLATE ZIPWP & GENERATE WEBSITE BENERAN
        // =====================================================
        $bestTemplate = null;
        $zipWpSiteUuid = null;
        $zipWpSiteUrl = null;
        $mockupFailReason = null;

        // GUARD: kalau project ini SUDAH punya website ZipWP aktif dari
        // percobaan sebelumnya (misal request sebelumnya gagal di step PDF,
        // TAPI site-nya sudah kadung jadi), JANGAN generate site baru lagi.
        // Pakai yang sudah ada saja, supaya tidak numpuk site percobaan
        // dan tidak buang-buang kuota ZipWP tiap kali retry.
        if (!empty($project->zipwp_site_url) && !empty($project->zipwp_site_uuid)) {

            Log::info('Generate Proposal - Pakai ZipWP site yang sudah ada (skip create baru)', [
                'project_id' => $project->id,
                'existing_site_url' => $project->zipwp_site_url,
            ]);

            $zipWpSiteUuid = $project->zipwp_site_uuid;
            $zipWpSiteUrl = $project->zipwp_site_url;
            $bestTemplate = [
                'uuid' => $project->zipwp_template_uuid,
                'name' => $project->zipwp_template_name,
                'preview_url' => $project->zipwp_template_preview_url,
            ];

        } else {

            try {
                // 3a. Cari kandidat template berdasarkan kategori bisnis
                $candidates = $zipWp->listTemplates(
                search: $project->type,
                perPage: 30
                )['templates'] ?? [];

                if (empty($candidates)) {
                    $candidates = $zipWp->listTemplates(perPage: 30)['templates'] ?? [];
                }

                if (empty($candidates)) {
                    $mockupFailReason = 'Tidak ada template ZipWP yang tersedia dari API.';
                } else {
                    // 3b. Pilih template terbaik berdasarkan hasil analisis Gemini+GPT
                    $bestTemplate = $aiService->pickBestTemplate($project, $candidates, $analysis);
                }

                if ($bestTemplate) {

                    $project->update([
                        'zipwp_template_uuid' => $bestTemplate['uuid'],
                        'zipwp_template_name' => $bestTemplate['name'],
                        'zipwp_template_preview_url' => $bestTemplate['preview_url'] ?? null,
                    ]);

                    // 3c. Generate website WordPress beneran dari template + data bisnis
                    $createResult = $zipWp->createAiSite([
                        'business_name' => $project->name,
                        'business_desc' => $project->description ?: ($project->name . ' - ' . ($project->type ?? 'Business')),
                        'business_category_name' => $project->type ?? 'Business',
                        'template' => $bestTemplate['uuid'],
                        'title' => $project->name,
                        'business_email' => $client->email ?? null,
                        'business_phone' => $client->phone ?? null,
                        'language' => 'en',
                    ]);

                    $zipWpSiteUuid = $createResult['site_uuid'] ?? $createResult['uuid'] ?? null;

                    if (!$zipWpSiteUuid) {
                        Log::error('ZipWP create-ai-site tidak mengembalikan site_uuid', [
                            'project_id' => $project->id,
                            'response' => $createResult,
                        ]);
                        $mockupFailReason = 'ZipWP gagal memulai proses pembuatan site.';
                    } else {

                        // 3d. Polling progress sampai selesai (max ~2 menit, cek tiap 10 detik)
                        $maxAttempts = 12;
                        $attempt = 0;
                        $siteActive = false;

                        while ($attempt < $maxAttempts) {
                            sleep(10);
                            $attempt++;

                            $progress = $zipWp->getSiteProgress($zipWpSiteUuid);
                            $status = $progress['status'] ?? null;

                            Log::info('ZipWP site progress', [
                                'project_id' => $project->id,
                                'attempt' => $attempt,
                                'status' => $status,
                            ]);

                            if (($progress['is_ready'] ?? false) === true) {
                                $zipWpSiteUrl = $progress['site_url'] ?? null;
                                $siteActive = true;
                                break;
                            }

                            if ($status === 'failed' || $status === 'error') {
                                $mockupFailReason = 'ZipWP gagal membangun site: ' . ($progress['message'] ?? 'unknown error');
                                break;
                            }
                        }

                        if (!$siteActive && !$mockupFailReason) {
                            $mockupFailReason = 'Pembuatan site ZipWP memakan waktu lebih lama dari perkiraan. Cek status manual nanti di halaman project.';
                        }

                        if ($zipWpSiteUrl) {
                            $project->update([
                                'zipwp_site_uuid' => $zipWpSiteUuid,
                                'zipwp_site_url' => $zipWpSiteUrl,
                            ]);
                        }
                    }

                } elseif (!$mockupFailReason) {
                    $mockupFailReason = 'AI tidak berhasil memilih template yang cocok.';
                }

            } catch (\Throwable $e) {
                Log::error('Generate Proposal - ZipWP site gagal: ' . $e->getMessage(), [
                    'project_id' => $project->id,
                ]);
                $mockupFailReason = 'Terjadi error teknis saat proses generate website.';
            }

        } // <-- penutup blok "else" dari guard idempotency di atas

        // =====================================================
        // 4. SIMPAN MOCKUP TEMPLATE (referensi template + site yang dipakai)
        // =====================================================
        // Kalau project sudah punya mockup_template_id (dari percobaan
        // sebelumnya yang berhasil bikin site tapi gagal di PDF), tidak
        // perlu bikin row MockupTemplate baru lagi — pakai yang sudah ada.
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

            $project->update([
                'mockup_template_id' => $mockupTemplate->id,
            ]);

        } elseif (!$bestTemplate) {
            Log::warning('Generate Proposal - ZipWP site gagal dibuat', [
                'project_id' => $project->id,
                'reason' => $mockupFailReason,
            ]);
        }

        // =====================================================
        // 5. DATA MOCKUP UNTUK PDF (berisi URL live site, bukan gambar statis)
        // =====================================================
        $mockup = [
            'title' => $project->name . ' — Website Preview',
            'site_url' => $zipWpSiteUrl,
            'template_name' => $bestTemplate['name'] ?? null,
            'design_direction' => $this->safeText($designDirectionRaw),
            'fail_reason' => $mockupFailReason,
        ];

        // =====================================================
        // 6. DATA PROJECT UNTUK PDF
        // =====================================================
        $projectData = [
            'project_name' => $project->name,
            'client_name' => $project->client_name,
            'website_type' => $project->type ?? 'Company Profile',
            'project_code' => $project->code,
            'generated_at' => now()->format('d F Y H:i'),
        ];

        // =====================================================
        // 7. GENERATE PDF
        // =====================================================
        // Dibungkus try-catch: kalau PDF gagal (misal error di blade),
        // website ZipWP yang SUDAH TERLANJUR dibuat di step 3 tidak hilang
        // percuma — project tetap tersimpan dengan zipwp_site_url terisi,
        // dan idempotency guard di atas akan pakai ulang site ini di
        // percobaan berikutnya (tidak generate site baru lagi).
        try {
            $pdf = Pdf::loadView('pdf.proposal', [
                'project' => $project,
                'projectData' => $projectData,
                'analysis' => $analysis,
                'analysisSummary' => $analysisSummary,
                'mockup' => $mockup,
            ]);
        } catch (\Throwable $e) {
            Log::error('Generate Proposal - PDF gagal dibuat: ' . $e->getMessage(), [
                'project_id' => $project->id,
            ]);

            return redirect()
                ->back()
                ->with('error', 'Website berhasil dibuat, tapi PDF proposal gagal digenerate. Silakan coba lagi — sistem akan memakai website yang sudah ada, tidak generate ulang.');
        }

        // =====================================================
        // 8. SIMPAN PDF
        // =====================================================
        $clientSlug = Str::slug($project->client_name);
        $fileName = "proposals/Proposal-Mockup-{$clientSlug}-{$project->code}.pdf";
        Storage::disk('public')->put($fileName, $pdf->output());

        // =====================================================
        // 9. SIMPAN PROPOSAL
        // =====================================================
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

        // =====================================================
        // 10. LOG ACTIVITY
        // =====================================================
        $project->logActivity(
            $zipWpSiteUrl
            ? 'AI Analysis dan Website berhasil dibuat: ' . $zipWpSiteUrl
            : 'AI Analysis berhasil dibuat, website ZipWP gagal digenerate (' . ($mockupFailReason ?? 'unknown') . ')'
        );

        // =====================================================
        // 11. KE PREVIEW
        // =====================================================
        return redirect()->route(
            'pages.projects.proposal.preview',
            $project
        )->with(
                'success',
                $zipWpSiteUrl
                ? 'Proposal berhasil dibuat, website preview sudah siap.'
                : 'Proposal berhasil dibuat, tapi website ZipWP gagal digenerate otomatis.'
            );
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

}