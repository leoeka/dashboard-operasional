<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\BundleBuilderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildProjectBundleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public Project $project,
    ) {
    }

    public function handle(BundleBuilderService $builder): void
    {
        $bundle = $builder->build($this->project);

        // Hook ini bisa dikembangkan ke export ZIP dan penyimpanan file.
        // Untuk tahap awal, hanya menyiapkan payload build.
        // Contoh:
        // $zipPath = app(BundleExporterService::class)->export($bundle, storage_path('app/bundles'));

        $this->project->update([
            'status' => 'in_progress',
        ]);
    }
}
