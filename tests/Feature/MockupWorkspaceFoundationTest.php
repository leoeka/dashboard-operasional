<?php

use App\Models\ClientProposal;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Models\WpBuild;
use App\Support\MockupWorkspace;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const MOCKUP_MIGRATIONS = [
    '2026_10_21_200000_create_mockup_candidates_table',
    '2026_10_21_200001_create_client_proposals_table',
    '2026_10_21_200002_create_wp_builds_table',
];

function mockupProject(): Project
{
    static $sequence = 0;

    return Project::create([
        'code' => 'MWK-' . str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT),
        'name' => 'Website Klien Uji',
        'client_name' => 'Klien Uji',
        'type' => 'Company',
        'status' => 'request',
    ]);
}

function mockupCandidate(Project $project, int $number = 1): MockupCandidate
{
    $candidate = MockupCandidate::create([
        'project_id' => $project->id,
        'candidate_number' => $number,
    ]);

    $candidate->update(['folder' => (new MockupWorkspace())->relativeFolder($candidate)]);

    return $candidate;
}

function mockupRollbackConnection(): string
{
    $file = tempnam(sys_get_temp_dir(), 'mockup-rollback-') . '.sqlite';
    touch($file);

    config(['database.connections.mockup_rollback' => [
        'driver' => 'sqlite',
        'database' => $file,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    Artisan::call('migrate', ['--database' => 'mockup_rollback', '--force' => true]);

    return 'mockup_rollback';
}

it('creates the three tables the mockup loop works on', function () {
    foreach (['mockup_candidates', 'client_proposals', 'wp_builds'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasColumn('mockup_candidates', 'idea_sentence'))->toBeTrue();
    expect(Schema::hasColumn('mockup_candidates', 'critique_rounds'))->toBeTrue();
    expect(Schema::hasColumn('mockup_candidates', 'failure_reason'))->toBeTrue();
    expect(Schema::hasColumn('client_proposals', 'pdf_path'))->toBeTrue();
    expect(Schema::hasColumn('wp_builds', 'inspection_results'))->toBeTrue();
});

it('claims a candidate as draft and a build as pending', function () {
    $project = mockupProject();
    $candidate = mockupCandidate($project);

    expect($candidate->status)->toBe(MockupCandidate::STATUS_DRAFT);
    expect($candidate->critique_rounds)->toBe(0);
    expect($candidate->failure_reason)->toBeNull();

    $build = WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    expect($build->status)->toBe(WpBuild::STATUS_PENDING);
    expect($build->inspection_results)->toBeNull();
});

it('lets one project hold several generations of three candidates', function () {
    $project = mockupProject();

    foreach ([1, 2, 3] as $number) {
        mockupCandidate($project, $number);
    }
    foreach ([1, 2, 3] as $number) {
        mockupCandidate($project, $number);
    }

    expect($project->mockupCandidates()->count())->toBe(6);
});

it('records the proposal with its pdf and, later, the chosen candidate', function () {
    $project = mockupProject();
    $candidate = mockupCandidate($project);

    $proposal = ClientProposal::create([
        'project_id' => $project->id,
        'pdf_path' => 'mockup-work/' . $project->id . '/proposal.pdf',
        'sent_at' => now(),
    ]);

    $proposal->update([
        'chosen_candidate_id' => $candidate->id,
        'chosen_at' => now(),
    ]);

    expect($proposal->fresh()->chosenCandidate->is($candidate))->toBeTrue();
});

it('deletes mockup history with the project, not before it', function () {
    $project = mockupProject();
    $candidate = mockupCandidate($project);

    ClientProposal::create([
        'project_id' => $project->id,
        'chosen_candidate_id' => $candidate->id,
    ]);
    WpBuild::create([
        'project_id' => $project->id,
        'mockup_candidate_id' => $candidate->id,
    ]);

    $project->delete();

    expect(MockupCandidate::count())->toBe(0);
    expect(ClientProposal::count())->toBe(0);
    expect(WpBuild::count())->toBe(0);
});

it('keeps the proposal when its chosen candidate disappears, without a dangling choice', function () {
    $project = mockupProject();
    $candidate = mockupCandidate($project);

    $proposal = ClientProposal::create([
        'project_id' => $project->id,
        'chosen_candidate_id' => $candidate->id,
        'chosen_at' => now(),
    ]);

    $candidate->delete();

    // chosen_at sengaja tidak ikut dikosongkan basis data; yang jadi sumber
    // kebenaran pilihan adalah chosen_candidate_id.
    expect($proposal->fresh()->chosen_candidate_id)->toBeNull();
});

it('gives every candidate its own work folder under storage/app/mockup-work', function () {
    $workspace = new MockupWorkspace();
    $project = mockupProject();
    $candidate = mockupCandidate($project, 2);

    $path = $workspace->candidatePath($candidate);

    expect(is_dir($path))->toBeTrue();
    expect($path)->toBe($workspace->basePath() . '/' . $project->id . '/candidate-' . $candidate->id);
    expect($workspace->resolve($workspace->relativeFolder($candidate)))->toBe($path);

    $workspace->deleteProject($project->id);

    expect(is_dir($workspace->basePath() . '/' . $project->id))->toBeFalse();
});

it('never reuses a work folder when a later generation repeats a candidate number', function () {
    $workspace = new MockupWorkspace();
    $project = mockupProject();

    $first = mockupCandidate($project, 1);
    $regenerated = mockupCandidate($project, 1);

    expect($workspace->relativeFolder($regenerated))
        ->not->toBe($workspace->relativeFolder($first));

    expect($workspace->candidatePath($regenerated))
        ->not->toBe($workspace->candidatePath($first));
});

it('rolls its three migrations back and forward cleanly', function () {
    $connection = mockupRollbackConnection();

    $step = DB::connection($connection)->table('migrations')
        ->whereIn('migration', MOCKUP_MIGRATIONS)
        ->count();
    expect($step)->toBe(3);

    Artisan::call('migrate:rollback', [
        '--database' => $connection,
        '--step' => $step,
        '--force' => true,
    ]);

    foreach (['mockup_candidates', 'client_proposals', 'wp_builds'] as $table) {
        expect(Schema::connection($connection)->hasTable($table))->toBeFalse();
    }

    Artisan::call('migrate', ['--database' => $connection, '--force' => true]);

    foreach (['mockup_candidates', 'client_proposals', 'wp_builds'] as $table) {
        expect(Schema::connection($connection)->hasTable($table))->toBeTrue();
    }
});
