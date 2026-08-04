<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\MockupTemplate;
use App\Models\Proposal;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
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

    public function generateProposal(Project $project)
    {
        // Ambil proposal terakhir jika sudah pernah dibuat
        $proposal = Proposal::where('project_id', $project->id)
            ->latest()
            ->first();

        // Jika sudah ada, jangan membuat proposal baru otomatis
        if ($proposal) {
            return redirect()
                ->route('pages.projects.show', $project)
                ->with('info', 'Proposal untuk project ini sudah tersedia.');
        }

        /*
        |--------------------------------------------------------------------------
        | DATA REQUEST
        |--------------------------------------------------------------------------
        | Untuk sementara kita gunakan data project/request yang sudah tersedia.
        | AI belum diaktifkan.
        */

        $analysis = [
            'business_analysis' => 'Analisis bisnis akan dihasilkan oleh AI.',
            'market_analysis' => 'Analisis pasar akan dihasilkan oleh AI.',
            'target_market' => 'Target market akan dianalisis oleh AI.',
            'competitor_analysis' => 'Analisis kompetitor akan dihasilkan oleh AI.',
            'website_objective' => 'Tujuan website akan dianalisis berdasarkan request client.',
            'sitemap' => 'Sitemap akan dihasilkan berdasarkan kebutuhan website.',
            'page_structure' => 'Struktur halaman akan dihasilkan berdasarkan hasil analisis.',
            'content_strategy' => 'Strategi konten akan dihasilkan oleh AI.',
            'cta_strategy' => 'Strategi CTA akan dihasilkan oleh AI.',
            'design_direction' => 'Arah desain akan ditentukan pada tahap mockup.',
        ];

        /*
        |--------------------------------------------------------------------------
        | SIMPAN PROPOSAL
        |--------------------------------------------------------------------------
        */

        $proposal = Proposal::create([
            'project_id' => $project->id,
            'client_name' => $project->client_name,
            'status' => 'pending',
            'version' => 1,

            // Sementara menggunakan placeholder.
            // Nanti bagian ini diisi hasil AI.
            'ai_reasoning' => json_encode($analysis),
        ]);

        $project->logActivity('Proposal berhasil dibuat.');

        /*
        |--------------------------------------------------------------------------
        | REDIRECT KE PREVIEW PROPOSAL
        |--------------------------------------------------------------------------
        */

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

        return Storage::disk('public')->download(
            $proposal->pdf_path,
            basename($proposal->pdf_path)
        );
    }


}