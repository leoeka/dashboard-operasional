<?php

namespace App\Http\Controllers;

use App\Exceptions\ProviderException;
use App\Models\Project;
use App\Models\ProjectBundle;
use App\Services\BlueprintManifestService;
use App\Services\BundleBuilderService;
use App\Services\BundleExporterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BundleController extends Controller
{
    public function index(Project $project)
    {
        return view('bundles.index', compact('project'));
    }

    public function build(
        Project $project,
        Request $request,
        BlueprintManifestService $manifestService,
        BundleBuilderService $builder,
        BundleExporterService $exporter
    ) {
        $lockSeconds = max(
            900,
            (int) config('services.openai.wordpress_build_timeout', 600) + 300
        );
        $lock = Cache::lock('wordpress-bundle-build:'.$project->getKey(), $lockSeconds);

        if (! $lock->get()) {
            return redirect()
                ->route('pages.projects.bundle', $project)
                ->with('error', 'Build WordPress untuk proyek ini sedang berlangsung. Tunggu hingga selesai sebelum mencoba lagi.');
        }

        $currentBundle = null;
        $buildStarted = false;

        try {
            try {
                $proposal = $project->latestProposal;
                if (! $proposal) {
                    throw new \RuntimeException('Generate proposal dan mockup terlebih dahulu.');
                }

                $proposalData = json_decode((string) $proposal->ai_reasoning, true) ?: [];
                $candidates = $proposalData['mockup_candidates'] ?? [];
                $validated = $request->validate([
                    'mockup_index' => ['nullable', 'integer', 'min:0', 'max:2'],
                ]);
                $selectedIndex = (int) ($validated['mockup_index'] ?? $proposalData['selected_mockup_index'] ?? 0);

                $selectedMockup = is_array($candidates) && $candidates !== []
                    ? ($candidates[$selectedIndex] ?? null)
                    : ($selectedIndex === 0 ? ($proposalData['mockup'] ?? null) : null);
                if (! is_array($selectedMockup)) {
                    throw new \RuntimeException('Opsi mockup yang dipilih tidak ditemukan.');
                }

                $currentBundle = $project->bundles()
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->first();
                if ($currentBundle) {
                    $currentBundle->update([
                        'status' => 'building',
                        'exported_at' => null,
                    ]);
                }
                $buildStarted = true;

                $proposalData['selected_mockup_index'] = $selectedIndex;
                $proposalData['mockup'] = $selectedMockup;
                $proposalData['implementation_manifest'] = $manifestService->build($selectedMockup);
                $proposal->update([
                    'ai_reasoning' => json_encode($proposalData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                ]);

                $bundle = $builder->build($project);
            } catch (ProviderException $e) {
                // Classification and a scrubbed detail only — never a key.
                Log::error('WordPress build gagal di provider.', array_merge(['project_id' => $project->id], $e->context()));
                $this->markPreviousBundleFailed($currentBundle, $buildStarted);

                return redirect()
                    ->route('pages.projects.bundle', $project)
                    ->with('error', $e->getMessage());
            } catch (\Throwable $e) {
                $this->markPreviousBundleFailed($currentBundle, $buildStarted);

                return redirect()
                    ->route('pages.projects.bundle', $project)
                    ->with('error', ProviderException::sanitise($e->getMessage()));
            }

            $bundleDir = storage_path('app/bundles/'.$project->id);
            try {
                $zipPath = $exporter->export($bundle, $bundleDir);
            } catch (\Throwable $e) {
                $this->markPreviousBundleFailed($currentBundle, $buildStarted);

                Log::error('WordPress bundle export gagal.', [
                    'project_id' => $project->id,
                    'error' => ProviderException::sanitise($e->getMessage()),
                ]);

                return redirect()
                    ->route('pages.projects.bundle', $project)
                    ->with('error', 'ZIP WordPress gagal dibuat. Perbaiki hasil build GPT lalu coba lagi. ('.ProviderException::sanitise($e->getMessage()).')');
            }

            $exportedAt = now();
            $bundleAttributes = [
                'bundle_path' => $bundleDir,
                'zip_path' => $zipPath,
                'status' => 'exported',
                'built_at' => $exportedAt,
                'exported_at' => $exportedAt,
            ];
            if ($currentBundle) {
                $currentBundle->update($bundleAttributes);
            } else {
                $project->bundles()->create($bundleAttributes);
            }

            $project->update([
                'status' => 'in_progress',
            ]);

            return redirect()
                ->route('pages.projects.bundle', $project)
                ->with('success', 'WordPress berhasil dibuat. ZIP siap di-download.');
        } finally {
            $lock->release();
        }
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

    private function markPreviousBundleFailed(?ProjectBundle $bundle, bool $buildStarted): void
    {
        if ($bundle && $buildStarted) {
            $bundle->update([
                'status' => 'failed',
                'exported_at' => null,
            ]);
        }
    }
}
