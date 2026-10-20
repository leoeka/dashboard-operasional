<?php

namespace App\Http\Controllers;

use App\Jobs\BuildProjectBundleJob;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BundleController extends Controller
{
    public function index(Project $project)
    {
        return view('bundles.index', compact('project'));
    }

    public function build(Request $request, Project $project)
    {
        // The client's recorded answer is the only thing that can start a
        // build. Reading the option from the proposal instead of the form means
        // the site that gets built is the option that was approved; a request
        // naming a different card cannot slip past it.
        $gate = $project->approvedBuildOption();
        if ($gate['mockup'] === null) {
            return $this->refuseBuild($request, $project, $gate['message']);
        }

        $progress = Cache::get(BuildProjectBundleJob::cacheKey($project->id), []);
        if (in_array($progress['status'] ?? null, ['queued', 'processing'], true)) {
            return $this->refuseBuild($request, $project, 'Build WordPress untuk proyek ini sedang berlangsung. Tunggu hingga selesai sebelum mencoba lagi.');
        }

        // The row goes to "building" before the job is queued: it is what the
        // step rail, the panel and the download guard all read, and a click
        // that never reached a worker must still be visible as unfinished
        // work rather than disappear.
        $bundle = $project->bundles()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
        if ($bundle) {
            $bundle->update(['status' => 'building', 'exported_at' => null]);
        }

        // Rebuilding reopens the work: a project whose ZIP was already done can
        // end up here again, and a failed replacement must not leave it looking
        // finished while its newest build is broken.
        $project->update(['status' => 'in_progress']);

        $this->reportProgress($project, 'queued', 0, 'Build masuk antrean...');

        BuildProjectBundleJob::dispatch($project);

        // The workspace button keeps the page and follows the queue through
        // /bundle/status, so it gets JSON and no reload. The plain form post is
        // the no-JS path and still lands back on the page with a flash message.
        if ($request->expectsJson()) {
            return response()->json(['started' => true]);
        }

        return redirect()
            ->route('pages.projects.bundle', $project)
            ->with('success', 'Build WordPress dimulai. ZIP siap diunduh setelah selesai.');
    }

    /**
     * A build that never started, answered the way the caller asked: the
     * progress popup reads the message off a 422, a form post sees it as a
     * flash error. Refusing quietly is not an option, because the click looked
     * like it did something either way.
     */
    private function refuseBuild(Request $request, Project $project, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 422);
        }

        return redirect()->route('pages.projects.bundle', $project)->with('error', $message);
    }

    public function status(Project $project)
    {
        return response()->json(Cache::get(
            BuildProjectBundleJob::cacheKey($project->id),
            ['status' => 'idle', 'progress' => 0, 'message' => '']
        ));
    }

    private function reportProgress(Project $project, string $status, int $progress, string $message): void
    {
        Cache::put(
            BuildProjectBundleJob::cacheKey($project->id),
            ['status' => $status, 'progress' => $progress, 'message' => $message],
            now()->addMinutes(20)
        );
    }

    public function download(Project $project)
    {
        // theme-install.zip is the real deliverable: a single, self-
        // sufficient WordPress theme with the approved pages, content, and
        // generated photos already baked in (see BundleExporterService::
        // injectPageImporterIntoTheme()) — install & activate this one
        // file, nothing else to upload, no separate plugin step. It used
        // to be bundle-export.zip (theme + a required "exito-core" plugin
        // as two separate uploads), which was both an extra step for the
        // client and an extra upload that could hit a host's upload-size
        // limit and fail with WordPress's misleading "The plugin does not
        // have a valid header".
        $bundle = $project->bundles()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
        if (! $bundle) {
            return back()->with('error', 'Bundle belum dibuat.');
        }
        if ($bundle->status !== 'exported') {
            return back()->with('error', 'Build terbaru belum berhasil. ZIP sebelumnya tidak tersedia sebagai hasil terbaru.');
        }

        $zipFile = $bundle->zip_path ?: storage_path('app/bundles/'.$project->id.'/theme-install.zip');

        if (! file_exists($zipFile)) {
            return back()->with('error', 'Bundle belum dibuat.');
        }

        return response()->download($zipFile, 'project-'.$project->id.'-wordpress-theme.zip');
    }
}
