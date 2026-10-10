<?php

use App\Models\Project;
use App\Models\ProjectBundle;
use App\Models\Proposal;
use App\Models\User;
use App\Services\BundleBuilderService;
use App\Services\BundleExporterService;
use Illuminate\Support\Facades\Cache;

it('builds the mockup option clicked by the operator without a separate approval record', function () {
    $project = Project::create([
        'name' => 'Bali Explore Tour',
        'client_name' => 'Bali Explore Tour',
        'status' => 'request',
    ]);

    $proposal = Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => 'pending',
        'ai_reasoning' => json_encode([
            'mockup_candidates' => [
                ['website_concept' => 'Option one', 'design' => [], 'pages' => []],
                ['website_concept' => 'Option two', 'design' => ['primary_color' => '#123456'], 'pages' => []],
            ],
            'selected_mockup_index' => 0,
        ]),
    ]);
    $existingBundle = $project->bundles()->create([
        'bundle_path' => storage_path('app/bundles/'.$project->id),
        'zip_path' => storage_path('app/bundles/'.$project->id.'/old-theme.zip'),
        'status' => 'exported',
        'built_at' => now()->subDay(),
        'exported_at' => now()->subDay(),
    ]);

    app()->instance(BundleBuilderService::class, Mockery::mock(BundleBuilderService::class, function ($mock) use ($proposal) {
        $mock->shouldReceive('build')->once()->withArgs(function (Project $project) use ($proposal) {
            $data = json_decode((string) $proposal->fresh()->ai_reasoning, true);

            expect($data['selected_mockup_index'])->toBe(1)
                ->and($data['mockup']['website_concept'])->toBe('Option two')
                ->and($data['implementation_manifest']['design_system']['colors']['primary'])->toBe('#123456');

            return true;
        })->andReturn(['built_with' => 'openai']);
    }));
    app()->instance(BundleExporterService::class, Mockery::mock(BundleExporterService::class, function ($mock) {
        $mock->shouldReceive('export')->once()->andReturn(storage_path('app/bundles/test/theme-install.zip'));
    }));

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.bundle.build', $project), ['mockup_index' => 1])
        ->assertRedirect(route('pages.projects.bundle', $project))
        ->assertSessionHas('success');

    expect($proposal->fresh()->status)->toBe('pending');
    expect($existingBundle->fresh()->status)->toBe('exported')
        ->and($existingBundle->fresh()->zip_path)->toBe(storage_path('app/bundles/test/theme-install.zip'))
        ->and($project->bundles()->count())->toBe(1);

    $lock = Cache::lock('wordpress-bundle-build:'.$project->id, 900);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

it('rejects a second build while the same project is already building', function () {
    $project = Project::create([
        'name' => 'Bali Explore Tour',
        'client_name' => 'Bali Explore Tour',
        'status' => 'request',
    ]);

    $proposal = Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => 'pending',
        'ai_reasoning' => json_encode([
            'mockup_candidates' => [
                ['website_concept' => 'Option one', 'design' => [], 'pages' => []],
                ['website_concept' => 'Option two', 'design' => ['primary_color' => '#123456'], 'pages' => []],
            ],
            'selected_mockup_index' => 0,
        ]),
    ]);

    app()->instance(BundleBuilderService::class, Mockery::mock(BundleBuilderService::class, function ($mock) {
        $mock->shouldNotReceive('build');
    }));
    app()->instance(BundleExporterService::class, Mockery::mock(BundleExporterService::class, function ($mock) {
        $mock->shouldNotReceive('export');
    }));

    $lock = Cache::lock('wordpress-bundle-build:'.$project->id, 900);
    expect($lock->get())->toBeTrue();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.bundle.build', $project), ['mockup_index' => 1])
        ->assertRedirect(route('pages.projects.bundle', $project))
        ->assertSessionHas('error', 'Build WordPress untuk proyek ini sedang berlangsung. Tunggu hingga selesai sebelum mencoba lagi.');

    expect(json_decode((string) $proposal->fresh()->ai_reasoning, true)['selected_mockup_index'])->toBe(0);
    $lock->release();
});

it('blocks an older bundle download after a replacement build fails', function () {
    $project = Project::create([
        'name' => 'Bali Explore Tour',
        'client_name' => 'Bali Explore Tour',
        'status' => 'in_progress',
    ]);
    Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => 'pending',
        'ai_reasoning' => json_encode([
            'mockup_candidates' => [
                ['website_concept' => 'Option one', 'design' => [], 'pages' => []],
            ],
            'selected_mockup_index' => 0,
        ]),
    ]);
    $bundle = $project->bundles()->create([
        'bundle_path' => storage_path('app/bundles/previous'),
        'zip_path' => storage_path('app/bundles/previous/theme-install.zip'),
        'status' => 'exported',
        'built_at' => now()->subDay(),
        'exported_at' => now()->subDay(),
    ]);

    app()->instance(BundleBuilderService::class, Mockery::mock(BundleBuilderService::class, function ($mock) {
        $mock->shouldReceive('build')->once()->andThrow(new RuntimeException('Builder failed.'));
    }));
    app()->instance(BundleExporterService::class, Mockery::mock(BundleExporterService::class, function ($mock) {
        $mock->shouldNotReceive('export');
    }));

    $user = User::factory()->create();
    $this->actingAs($user)
        ->post(route('pages.projects.bundle.build', $project), ['mockup_index' => 0])
        ->assertRedirect(route('pages.projects.bundle', $project))
        ->assertSessionHas('error', 'Builder failed.');

    expect($bundle->fresh()->status)->toBe('failed')
        ->and($bundle->fresh()->exported_at)->toBeNull()
        ->and($bundle->fresh()->zip_path)->toBe(storage_path('app/bundles/previous/theme-install.zip'));

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
    $project = Project::create([
        'name' => 'Bali Explore Tour',
        'client_name' => 'Bali Explore Tour',
        'status' => 'in_progress',
    ]);
    Proposal::create([
        'project_id' => $project->id,
        'client_name' => $project->client_name,
        'version' => 1,
        'status' => 'pending',
        'ai_reasoning' => json_encode([
            'mockup_candidates' => [
                ['website_concept' => 'Option one', 'design' => [], 'pages' => []],
            ],
            'selected_mockup_index' => 0,
        ]),
    ]);
    $bundle = $project->bundles()->create([
        'bundle_path' => storage_path('app/bundles/previous'),
        'zip_path' => storage_path('app/bundles/previous/theme-install.zip'),
        'status' => 'exported',
        'built_at' => now()->subDay(),
        'exported_at' => now()->subDay(),
    ]);

    app()->instance(BundleBuilderService::class, Mockery::mock(BundleBuilderService::class, function ($mock) {
        $mock->shouldReceive('build')->once()->andReturn(['built_with' => 'openai']);
    }));
    app()->instance(BundleExporterService::class, Mockery::mock(BundleExporterService::class, function ($mock) {
        $mock->shouldReceive('export')->once()->andThrow(new RuntimeException('ZIP export failed.'));
    }));

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.bundle.build', $project), ['mockup_index' => 0])
        ->assertRedirect(route('pages.projects.bundle', $project))
        ->assertSessionHas('error');

    expect($bundle->fresh()->status)->toBe('failed')
        ->and($bundle->fresh()->exported_at)->toBeNull();
});
