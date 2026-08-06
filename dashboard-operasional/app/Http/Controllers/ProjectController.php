<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Proposal;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\AiServices;
use App\Services\ZipWpMcpService;
use Illuminate\Support\Facades\Log;


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
    public function generateProposal(Project $project, AiServices $aiService)
    {
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
        // 2. AI ANALYSIS
        // =====================================================
        try {
            $analysis = $aiService->analyzeProject($project, $client);
        } catch (\RuntimeException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        if (!is_array($analysis)) {
            $analysis = [
                'business_overview' => '',
                'target_market' => '',
                'website_goal' => '',
                'recommended_structure' => [],
                'recommended_features' => [],
                'seo_strategy' => '',
                'design_direction' => '',
                'recommended_cta' => '',
            ];
        }

        // =====================================================
        // 3. GENERATE MOCKUP
        // =====================================================
        try {
            $mockupResult = $aiService->generateMockup($project, $analysis);
        } catch (\Exception $e) {
            Log::error('Mockup Generation Error: ' . $e->getMessage(), ['project_id' => $project->id]);
            return redirect()
                ->back()
                ->with('error', 'Gagal membuat mockup. Silakan coba lagi.');
        }

        $mockupImagePath = $mockupResult['merged'] ?? null;   // 1 gambar panjang, buat thumbnail/preview
        $mockupSections = $mockupResult['sections'] ?? [];     // 3 gambar terpisah, buat ditaruh di PDF

        $designDirectionRaw = $analysis['design_direction'] ?? null;

        $mockupTemplate = \App\Models\MockupTemplate::create([
            'name' => 'AI Generated - ' . $project->name,
            'image_path' => $mockupImagePath,
            'category' => $project->type ?? 'company_profile',
            'description' => $this->safeText($designDirectionRaw, 'AI Generated Mockup'),
        ]);

        $project->update([
            'mockup_template_id' => $mockupTemplate->id,
        ]);

        $mockup = [
            'title' => $project->name . ' — Website Mockup',
            'content_notes' => $this->safeText($designDirectionRaw),
            'sections' => $mockupSections,   // <-- dipakai di blade PDF, 3 gambar terpisah
            'design_direction' => $this->safeText($designDirectionRaw),
            'image_path' => $mockupImagePath, // <-- tetap ada, buat keperluan lain (thumbnail dsb)
        ];

        // =====================================================
        // 4. DATA PROJECT UNTUK PDF
        // =====================================================
        $projectData = [
            'project_name' => $project->name,
            'client_name' => $project->client_name,
            'website_type' => $project->type ?? 'Company Profile',
            'project_code' => $project->code,
            'generated_at' => now()->format('d F Y H:i'),
        ];

        // =====================================================
        // 5. GENERATE PDF
        // =====================================================
        $pdf = Pdf::loadView('pdf.proposal', [
            'project' => $project,
            'projectData' => $projectData,
            'analysis' => $analysis,
            'mockup' => $mockup,
        ]);

        // =====================================================
        // 6. SIMPAN PDF
        // =====================================================
        $clientSlug = Str::slug($project->client_name);
        $fileName = "proposals/Proposal-Mockup-{$clientSlug}-{$project->code}.pdf";
        Storage::disk('public')->put($fileName, $pdf->output());

        // =====================================================
        // 7. SIMPAN PROPOSAL
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
        // 8. LOG ACTIVITY
        // =====================================================
        $project->logActivity('AI Analysis dan Mockup berhasil dibuat');

        // =====================================================
        // 9. KE PREVIEW
        // =====================================================
        return redirect()
            ->route('pages.projects.proposal.preview', $project)
            ->with('success', 'Proposal berhasil dibuat.');
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

    public function generateAiContent(Project $project)
    {
        $mockupName = $project->mockupTemplate->name ?? 'mockup yang dipilih';

        $content = "Desain website untuk {$project->client_name} dibuat mengikuti struktur \"{$mockupName}\".\n\n"
            . "Jenis website: {$project->type}\n"
            . "Ringkasan kebutuhan: " . ($project->requirement_notes ?? '-') . "\n\n"
            . "[Hasil generate otomatis pada " . now()->translatedFormat('d M Y, H:i') . "]";

        $project->update(['ai_generated_content' => $content]);
        $project->logActivity('AI Workspace: konten digenerate');

        return back()->with('success', 'Konten berhasil digenerate.');
    }


}