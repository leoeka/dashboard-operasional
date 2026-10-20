<?php

use App\Jobs\BuildProjectBundleJob;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Services\BundleBuilderService;
use App\Services\BundleExporterService;
use Illuminate\Support\Facades\Cache;

/**
 * A build only runs on the option the client approved, and the approval is
 * recorded by the button on the mockup card (see ApproveProposalTest). The
 * form therefore no longer carries a mockup_index: nothing a request names can
 * point the build at a different option than the recorded one.
 *
 * The work itself runs in the queue now, so two of these read the result off
 * the bundle row and the progress cache instead of the flash message: the
 * click only starts a build, and the phases the worker reports are what the
 * workspace popup shows. phpunit.xml puts the queue on "sync", so the job body
 * still executes inside these requests, with the builder and exporter mocked.
 */
function buildableProject(string $status = 'in_progress'): Project
{
    $project = Project::create([
        'name' => 'Bali Explore Tour',
        'client_name' => 'Bali Explore Tour',
        'status' => $status,
    ]);

    Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => 'pending',
        'ai_reasoning' => json_encode([
            'mockup_candidates' => [
                ['website_concept' => 'Option one', 'design' => ['primary_color' => '#111111'], 'pages' => []],
                ['website_concept' => 'Option two', 'design' => ['primary_color' => '#123456'], 'pages' => []],
            ],
            'selected_mockup_index' => null,
        ]),
    ]);

    return $project;
}

function recordClientChoice(Project $project, int $index): void
{
    test()->actingAs(User::factory()->create())
        ->post(route('pages.projects.proposal.approve', $project), ['mockup_index' => $index])
        ->assertRedirect();
}

it('builds the option the client approved even when the request names another', function () {
    $project = buildableProject();
    recordClientChoice($project, 1);

    app()->instance(BundleBuilderService::class, Mockery::mock(BundleBuilderService::class, function ($mock) use ($project) {
        $mock->shouldReceive('build')->once()->withArgs(function (Project $project) {
            $data = json_decode((string) $project->latestProposal->ai_reasoning, true);

            expect($data['selected_mockup_index'])->toBe(1)
                ->and($data['mockup']['website_concept'])->toBe('Option two')
                ->and($data['implementation_manifest']['design_system']['colors']['primary'])->toBe('#123456');

            return true;
        })->andReturn([]);
    }));
    app()->instance(BundleExporterService::class, Mockery::mock(BundleExporterService::class, function ($mock) {
        $mock->shouldReceive('export')->once()->andReturn(storage_path('app/bundles/test/theme-install.zip'));
    }));

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.bundle.build', $project), ['mockup_index' => 0])
        ->assertRedirect(route('pages.projects.bundle', $project))
        ->assertSessionHas('success');

    expect($project->latestProposal->fresh()->status)->toBe('approved');
});

it('refuses to build before the client choice is recorded', function () {
    $project = buildableProject('request');

    app()->instance(BundleBuilderService::class, Mockery::mock(BundleBuilderService::class, function ($mock) {
        $mock->shouldNotReceive('build');
    }));
    app()->instance(BundleExporterService::class, Mockery::mock(BundleExporterService::class, function ($mock) {
        $mock->shouldNotReceive('export');
    }));

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.bundle.build', $project), ['mockup_index' => 1])
        ->assertRedirect(route('pages.projects.bundle', $project))
        ->assertSessionHas('error', 'Build WordPress terkunci sampai pilihan klien dicatat. Tekan "Klien pilih opsi ini" pada mockup yang dipilih klien.');

    $data = json_decode((string) $project->latestProposal->fresh()->ai_reasoning, true);
    expect($project->latestProposal->fresh()->status)->toBe('pending')
        ->and($data['selected_mockup_index'])->toBeNull()
        ->and($data['implementation_manifest'] ?? null)->toBeNull();
});

it('rejects a second build while the same project is already building', function () {
    $project = buildableProject();
    recordClientChoice($project, 0);

    app()->instance(BundleBuilderService::class, Mockery::mock(BundleBuilderService::class, function ($mock) {
        $mock->shouldNotReceive('build');
    }));
    app()->instance(BundleExporterService::class, Mockery::mock(BundleExporterService::class, function ($mock) {
        $mock->shouldNotReceive('export');
    }));

    // "In flight" is the worker's own report, not a lock the controller holds:
    // this is exactly the cache state a second click meets while a build runs.
    Cache::put(
        BuildProjectBundleJob::cacheKey($project->id),
        ['status' => 'processing', 'progress' => 35, 'message' => 'Merender halaman sesuai mockup yang dipilih...'],
        now()->addMinutes(20)
    );

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.bundle.build', $project))
        ->assertRedirect(route('pages.projects.bundle', $project))
        ->assertSessionHas('error', 'Build WordPress untuk proyek ini sedang berlangsung. Tunggu hingga selesai sebelum mencoba lagi.');

    expect(json_decode((string) $project->latestProposal->fresh()->ai_reasoning, true)['selected_mockup_index'])->toBe(0);
});

it('blocks an older bundle download after a replacement build fails', function () {
    $project = buildableProject();
    $bundle = $project->bundles()->create([
        'bundle_path' => storage_path('app/bundles/previous'),
        'zip_path' => storage_path('app/bundles/previous/theme-install.zip'),
        'status' => 'exported',
        'built_at' => now()->subDay(),
        'exported_at' => now()->subDay(),
    ]);
    recordClientChoice($project, 0);

    app()->instance(BundleBuilderService::class, Mockery::mock(BundleBuilderService::class, function ($mock) {
        $mock->shouldReceive('build')->once()->andThrow(new RuntimeException('Builder failed.'));
    }));
    app()->instance(BundleExporterService::class, Mockery::mock(BundleExporterService::class, function ($mock) {
        $mock->shouldNotReceive('export');
    }));

    $user = User::factory()->create();
    $this->actingAs($user)
        ->post(route('pages.projects.bundle.build', $project))
        ->assertRedirect(route('pages.projects.bundle', $project))
        ->assertSessionHas('success');

    // The failure is no longer a flash message: the click only queued the
    // work. It surfaces where the workspace reads it, and the bundle row is
    // what keeps the old ZIP from being offered as the newest result.
    expect(Cache::get(BuildProjectBundleJob::cacheKey($project->id)))
        ->toMatchArray(['status' => 'failed', 'message' => 'Builder failed.'])
        ->and($bundle->fresh()->status)->toBe('failed')
        ->and($bundle->fresh()->exported_at)->toBeNull()
        ->and($bundle->fresh()->zip_path)->toBe(storage_path('app/bundles/previous/theme-install.zip'))
        ->and($project->fresh()->status)->toBe('in_progress');

    $this->actingAs($user)
        ->from(route('pages.projects.bundle', $project))
        ->get(route('pages.projects.bundle.download', $project))
        ->assertRedirect(route('pages.projects.bundle', $project))
        ->assertSessionHas('error', 'Build terbaru belum berhasil. ZIP sebelumnya tidak tersedia sebagai hasil terbaru.');

    $this->actingAs($user)
        ->get(route('pages.projects.bundle', $project))
        ->assertOk()
        ->assertSee('Build terakhir gagal.')
        ->assertSee('pointer-events-none opacity-40', false);
});

it('marks the previous bundle failed when zip export fails', function () {
    $project = buildableProject();
    $bundle = $project->bundles()->create([
        'bundle_path' => storage_path('app/bundles/previous'),
        'zip_path' => storage_path('app/bundles/previous/theme-install.zip'),
        'status' => 'exported',
        'built_at' => now()->subDay(),
        'exported_at' => now()->subDay(),
    ]);
    recordClientChoice($project, 0);

    app()->instance(BundleBuilderService::class, Mockery::mock(BundleBuilderService::class, function ($mock) {
        $mock->shouldReceive('build')->once()->andReturn([]);
    }));
    app()->instance(BundleExporterService::class, Mockery::mock(BundleExporterService::class, function ($mock) {
        $mock->shouldReceive('export')->once()->andThrow(new RuntimeException('ZIP export failed.'));
    }));

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.bundle.build', $project))
        ->assertRedirect(route('pages.projects.bundle', $project))
        ->assertSessionHas('success');

    expect($bundle->fresh()->status)->toBe('failed')
        ->and($bundle->fresh()->exported_at)->toBeNull()
        ->and(Cache::get(BuildProjectBundleJob::cacheKey($project->id)))
        ->toMatchArray(['status' => 'failed', 'message' => 'ZIP export failed.']);
});

it('reports the build phase so the workspace popup can follow the queue', function () {
    $project = buildableProject();

    // Nothing dispatched yet: an idle answer, not a stale one.
    $this->actingAs(User::factory()->create())
        ->getJson(route('pages.projects.bundle.status', $project))
        ->assertOk()
        ->assertJson(['status' => 'idle', 'progress' => 0, 'message' => '']);

    Cache::put(
        BuildProjectBundleJob::cacheKey($project->id),
        ['status' => 'processing', 'progress' => 70, 'message' => 'Mengemas theme menjadi ZIP siap install...'],
        now()->addMinutes(20)
    );

    $this->getJson(route('pages.projects.bundle.status', $project))
        ->assertOk()
        ->assertJson(['status' => 'processing', 'progress' => 70]);
});

it('closes the project only when the ZIP is written', function () {
    $project = buildableProject();
    recordClientChoice($project, 0);

    app()->instance(BundleBuilderService::class, Mockery::mock(BundleBuilderService::class, function ($mock) {
        $mock->shouldReceive('build')->once()->andReturn([]);
    }));
    app()->instance(BundleExporterService::class, Mockery::mock(BundleExporterService::class, function ($mock) {
        $mock->shouldReceive('export')->once()->andReturn(storage_path('app/bundles/test/theme-install.zip'));
    }));

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.bundle.build', $project))
        ->assertRedirect(route('pages.projects.bundle', $project));

    expect($project->bundles()->latest('id')->first()->status)->toBe('exported')
        ->and($project->fresh()->status)->toBe('completed')
        ->and(Cache::get(BuildProjectBundleJob::cacheKey($project->id)))
        ->toMatchArray(['status' => 'done', 'progress' => 100]);
});

it('reopens a finished project while a replacement build is running', function () {
    $project = buildableProject('completed');
    recordClientChoice($project, 0);

    app()->instance(BundleBuilderService::class, Mockery::mock(BundleBuilderService::class, function ($mock) {
        $mock->shouldReceive('build')->once()->andThrow(new RuntimeException('Builder failed.'));
    }));
    app()->instance(BundleExporterService::class, Mockery::mock(BundleExporterService::class, function ($mock) {
        $mock->shouldNotReceive('export');
    }));

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.bundle.build', $project))
        ->assertRedirect(route('pages.projects.bundle', $project));

    // Delivered status belongs to the newest ZIP: a project that is mid-way
    // through being replaced, and whose replacement then failed, cannot stay
    // counted as finished work.
    expect($project->fresh()->status)->toBe('in_progress');
});
