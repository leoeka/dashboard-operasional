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
        // 1. Cek apakah proposal untuk proyek ini sudah pernah digenerate
        $proposal = Proposal::where('project_id', $project->id)->latest()->first();

        // JIKA SUDAH ADA: Langsung arahkan ke halaman edit proposal
        if ($proposal) {
            return redirect()->route('pages.projects.proposal.edit', $project);
        }

        // JIKA BELUM ADA: Jalankan proses pembuatan pertama kali
        $templates = MockupTemplate::all(['id', 'name', 'category']);

        // --- MODE TESTING AI (Nanti bisa diaktifkan kembali AiMockupRecommender) ---
        $aiReasoning = '[MODE TESTING] Fitur AI dinonaktifkan sementara.';
        $recommendedTemplate = $project->mockupTemplate ?? $templates->first();

        if (!$project->mockup_template_id && $recommendedTemplate) {
            $project->update(['mockup_template_id' => $recommendedTemplate->id]);
        }

        $project->load('mockupTemplate');

        // 2. Generate PDF & simpan file fisik ke Storage
        $pdf = Pdf::loadView('pdf.proposal', compact('project', 'recommendedTemplate', 'aiReasoning'));

        $clientSlug = Str::slug($project->client_name, '-');
        $fileName = "proposals/Proposal-{$clientSlug}-v1.pdf";

        Storage::disk('public')->put($fileName, $pdf->output());

        // 3. Simpan record proposal baru ke database
        Proposal::create([
            'project_id' => $project->id,
            'mockup_template_id' => $project->mockup_template_id,
            'client_name' => $project->client_name,
            'status' => 'pending',
            'pdf_path' => $fileName,
            'version' => 1,
            'ai_reasoning' => $aiReasoning,
        ]);

        // 4. Setelah selesai generate pertama kali, langsung redirect ke halaman edit
        return redirect()->route('projects.proposal-edit', $project)
            ->with('success', 'Proposal berhasil digenerate pertama kali!');
    }

    /**
     * Halaman Editor Proposal
     */
    public function editProposal(Project $project)
    {
        $templates = MockupTemplate::orderBy('name')->get();
        $proposal = Proposal::where('project_id', $project->id)->latest()->first();

        $project->load('mockupTemplate');

        return view('projects.proposal-edit', compact('project', 'templates', 'proposal'));
    }

    /**
     * Simpan Perubahan Proposal & Re-generate PDF Baru
     */
    public function updateProposal(Request $request, Project $project)
    {
        $validated = $request->validate([
            'mockup_template_id' => 'required|exists:mockup_templates,id',
            'summary' => 'nullable|string',
        ]);

        // Update pilihan template di project
        $project->update(['mockup_template_id' => $validated['mockup_template_id']]);
        $project->load('mockupTemplate');

        // Ambil proposal terkini atau buat jika belum ada
        $proposal = Proposal::where('project_id', $project->id)->latest()->first();
        $newVersion = $proposal ? $proposal->version + 1 : 1;

        // Re-generate PDF fisik yang baru
        $aiReasoning = $proposal->ai_reasoning ?? '[MODE TESTING] Fitur AI dinonaktifkan sementara.';
        $pdf = Pdf::loadView('pdf.proposal', compact('project'));

        $clientSlug = Str::slug($project->client_name, '-');
        $fileName = "proposals/Proposal-{$clientSlug}-v{$newVersion}.pdf";

        Storage::disk('public')->put($fileName, $pdf->output());

        // Update / Buat record proposal baru dengan versi yang di-increment
        Proposal::create([
            'project_id' => $project->id,
            'mockup_template_id' => $validated['mockup_template_id'],
            'client_name' => $project->client_name,
            'status' => 'pending',
            'pdf_path' => $fileName,
            'version' => $newVersion,
            'summary' => $validated['summary'] ?? null,
            'ai_reasoning' => $aiReasoning,
        ]);

        return back()->with('success', "Proposal versi {$newVersion} berhasil diperbarui!");
    }

    /**
     * Stream file PDF fisik dari Storage ke Iframe
     */
    public function streamPdf(Project $project)
    {
        $proposal = Proposal::where('project_id', $project->id)->latest()->first();

        if ($proposal && $proposal->pdf_path && Storage::disk('public')->exists($proposal->pdf_path)) {
            return response()->file(storage_path('app/public/' . $proposal->pdf_path));
        }

        // Fallback jika file fisik belum ada
        $pdf = Pdf::loadView('pdf.proposal', compact('project'));
        return $pdf->stream("Proposal-{$project->client_name}.pdf");
    }
}