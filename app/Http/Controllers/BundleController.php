<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectBundle;
use App\Services\BundleBuilderService;
use App\Services\BundleExporterService;
use Illuminate\Http\Request;

class BundleController extends Controller
{
    public function index(Project $project)
    {
        return view('bundles.index', compact('project'));
    }

    public function build(Project $project, BundleBuilderService $builder, BundleExporterService $exporter)
    {
        try {
            $bundle = $builder->build($project);
        } catch (\Throwable $e) {
            return redirect()
                ->route('pages.projects.bundle', $project)
                ->with('error', $e->getMessage());
        }

        $bundleDir = storage_path('app/bundles/' . $project->id);
        try {
            $zipPath = $exporter->export($bundle, $bundleDir);
        } catch (\Throwable $e) {
            return redirect()
                ->route('pages.projects.bundle', $project)
                ->with('error', 'ZIP WordPress gagal dibuat. Perbaiki hasil build Claude lalu coba lagi.');
        }

        ProjectBundle::updateOrCreate(
            ['project_id' => $project->id],
            [
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
        $zipFile = storage_path('app/bundles/' . $project->id . '/theme-install.zip');

        if (!file_exists($zipFile)) {
            return back()->with('error', 'Bundle belum dibuat.');
        }

        return response()->download($zipFile, 'project-' . $project->id . '-wordpress-theme.zip');
    }

}
