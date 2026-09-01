<?php

namespace App\Http\Controllers;

use App\Jobs\BuildProjectBundleJob;
use App\Models\Project;
use App\Models\ProjectBundle;
use App\Models\TemplateBundle;
use App\Services\BundleBuilderService;
use App\Services\BundleExporterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BundleController extends Controller
{
    public function index(Project $project)
    {
        $templates = TemplateBundle::query()->where('is_active', true)->get();

        return view('bundles.index', [
            'project' => $project,
            'templates' => $templates,
        ]);
    }

    public function build(Project $project, BundleBuilderService $builder, BundleExporterService $exporter)
    {
        $bundle = $builder->build($project);

        $bundleDir = storage_path('app/bundles/' . $project->id);
        $zipPath = $exporter->export($bundle, $bundleDir);

        ProjectBundle::updateOrCreate(
            ['project_id' => $project->id],
            [
                'template_bundle_id' => $project->bundles()->latest()->value('template_bundle_id'),
                'bundle_path' => $bundleDir,
                'zip_path' => $zipPath,
                'status' => 'exported',
                'built_at' => now(),
                'exported_at' => now(),
            ]
        );

        $project->update([
            'status' => 'in_progress',
        ]);

        return redirect()
            ->route('pages.projects.bundle', $project)
            ->with('success', 'WordPress berhasil dibuat. ZIP siap di-download.');
    }

    public function download(Project $project)
    {
        $zipFile = storage_path('app/bundles/' . $project->id . '/bundle-export.zip');

        if (!file_exists($zipFile)) {
            return back()->with('error', 'Bundle belum dibuat.');
        }

        return response()->download($zipFile, 'project-' . $project->id . '-bundle.zip');
    }

    public function storeTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'slug' => 'required|string|unique:template_bundles,slug',
            'category' => 'required|string',
            'description' => 'nullable|string',
            'preview_url' => 'nullable|url',
            'is_active' => 'nullable|boolean',
            'settings' => 'nullable|array',
        ]);

        TemplateBundle::create($data);

        return back()->with('success', 'Template bundle saved successfully.');
    }
}
