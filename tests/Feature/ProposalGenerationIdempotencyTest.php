<?php

use App\Jobs\GenerateProposalJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

it('queues only one proposal generation job per project', function () {
    $project = Project::create([
        'name' => 'Website Kopi Nusantara',
        'client_name' => 'Kopi Nusantara',
        'status' => 'request',
    ]);
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.proposal.generate', $project))
        ->assertOk()
        ->assertJson(['queued' => true]);

    $this->post(route('pages.projects.proposal.generate', $project))
        ->assertOk()
        ->assertJson(['queued' => true]);

    Queue::assertPushed(GenerateProposalJob::class, 1);
});

it('preserves active proposal progress when another request arrives', function () {
    $project = Project::create([
        'name' => 'Website Kopi Nusantara',
        'client_name' => 'Kopi Nusantara',
        'status' => 'request',
    ]);
    $activeProgress = [
        'status' => 'processing',
        'progress' => 55,
        'message' => 'GPT is designing three website options...',
    ];
    Cache::put('proposal_progress:'.$project->id, $activeProgress, now()->addMinutes(20));
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('pages.projects.proposal.generate', $project))
        ->assertOk();

    expect(Cache::get('proposal_progress:'.$project->id))->toBe($activeProgress);
});
