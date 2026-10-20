<?php

namespace App\Jobs;

use App\Exceptions\ProviderException;
use App\Models\Project;
use App\Models\ProjectBundle;
use App\Services\BlueprintManifestService;
use App\Services\BundleBuilderService;
use App\Services\BundleExporterService;
use App\Support\MockupSite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds the WordPress theme for the option the client approved, then packs it
 * into the installable ZIP.
 *
 * This used to run inside the HTTP request. The build no longer calls any AI
 * model (see BundleBuilderService), so it is fast — but it still reads every
 * approved photo off disk, renders a page per slug, and writes a ZIP, which on
 * a shared host is more than a request should carry, and a request that dies
 * half-way leaves a theme folder on the server and a build nobody can see.
 * Running here means the workspace can show which phase is on screen while it
 * happens, the same way proposal generation already does.
 */
class BuildProjectBundleJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const OVERLAP_SECONDS = 900;

    private const PROGRESS_TTL_MINUTES = 20;

    public int $timeout = 600;

    // One attempt on purpose. A build writes into the same folder every time;
    // a silent second attempt would double the ZIP work and hide the first
    // failure from the person who has to read it.
    public int $tries = 1;

    public int $uniqueFor = self::OVERLAP_SECONDS;

    public function __construct(public Project $project) {}

    public static function cacheKey(int $projectId): string
    {
        return "bundle_progress:{$projectId}";
    }

    public function uniqueId(): string
    {
        return (string) $this->project->getKey();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('wordpress-bundle-build:'.$this->uniqueId()))
                ->dontRelease()
                ->expireAfter(self::OVERLAP_SECONDS),
        ];
    }

    public function handle(
        BlueprintManifestService $manifestService,
        BundleBuilderService $builder,
        BundleExporterService $exporter
    ): void {
        set_time_limit(0);

        $project = $this->project;
        $bundle = $this->currentBundle($project);

        // The click was already gated in the controller, but a click and this
        // worker are not one moment: the team can record a different option or
        // answer "nothing chosen" in between. The recorded approval is still
        // the only thing that may be built.
        $gate = $project->approvedBuildOption();
        if ($gate['mockup'] === null) {
            $this->failBuild($bundle, $gate['message']);

            return;
        }

        $this->report('processing', 10, 'Membekukan blueprint opsi yang disetujui...');

        // Re-frozen here so a build always carries the manifest of the option
        // being built, even if the proposal changed shape after approval.
        $proposal = $project->latestProposal;
        $proposalData = $gate['data'];
        $proposalData['mockup'] = $gate['mockup'];
        $proposalData['implementation_manifest'] = $manifestService->build($gate['mockup']);
        $proposal->update([
            'ai_reasoning' => json_encode($proposalData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        try {
            $this->report('processing', 35, 'Merender halaman sesuai mockup yang dipilih...');
            $built = $builder->build($project);

            $this->report('processing', 70, 'Mengemas theme menjadi ZIP siap install...');
            $zipPath = $exporter->export($built, storage_path('app/bundles/'.$project->id));
        } catch (ProviderException $e) {
            // Classification and a scrubbed detail only — never a key.
            Log::error('WordPress build gagal di provider.', array_merge(['project_id' => $project->id], $e->context()));
            $this->failBuild($bundle, $e->getMessage());

            return;
        } catch (\Throwable $e) {
            Log::error('WordPress build gagal.', [
                'project_id' => $project->id,
                'error' => ProviderException::sanitise($e->getMessage()),
            ]);
            $this->failBuild($bundle, $e->getMessage());

            return;
        }

        $exportedAt = now();
        $attributes = [
            'bundle_path' => storage_path('app/bundles/'.$project->id),
            'zip_path' => $zipPath,
            'status' => 'exported',
            'built_at' => $exportedAt,
            'exported_at' => $exportedAt,
        ];
        if ($bundle) {
            $bundle->update($attributes);
        } else {
            $project->bundles()->create($attributes);
        }

        $project->update(['status' => 'completed']);

        $this->report('done', 100, 'ZIP WordPress siap diunduh.');
    }

    public function failed(Throwable $exception): void
    {
        // Reached only when the worker itself died (timeout, fatal), because
        // handle() catches its own failures. Without this the row stays
        // "building" and the workspace offers a ZIP that was never written.
        $this->failBuild($this->currentBundle($this->project), $exception->getMessage());
    }

    private function currentBundle(Project $project): ?ProjectBundle
    {
        return $project->bundles()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    private function failBuild(?ProjectBundle $bundle, string $error): void
    {
        if ($bundle) {
            $bundle->update(['status' => 'failed', 'exported_at' => null]);
        }

        $this->report('failed', 0, ProviderException::sanitise($error));
    }

    private function report(string $status, int $progress, string $message): void
    {
        Cache::put(
            self::cacheKey($this->project->id),
            ['status' => $status, 'progress' => $progress, 'message' => $message],
            now()->addMinutes(self::PROGRESS_TTL_MINUTES)
        );
    }
}
