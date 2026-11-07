<?php

use App\Jobs\AnalyzeAiCrawlerAccessJob;
use App\Jobs\AnalyzeContentExtractabilityJob;
use App\Jobs\AnalyzeOnPageJob;
use App\Jobs\AnalyzePageSpeedJob;
use App\Jobs\AnalyzeStructuredDataJob;
use App\Jobs\AnalyzeTechnicalSeoJob;
use App\Jobs\GenerateKeywordRecommendationsJob;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('shows the standalone URL-only SEO analysis form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('pages.seo-analysis'))
        ->assertOk()
        ->assertSee('URL website client')
        ->assertSee('Mulai Analisa');
});

it('creates a client project from a public website URL and queues its analysis', function () {
    Queue::fake();

    $response = $this->actingAs(User::factory()->create())
        ->post(route('pages.seo-analysis.analyze'), ['url' => 'client-example.test']);

    $client = Client::sole();
    $project = Project::sole();

    $response->assertRedirect(route('pages.project-workspace', ['project' => $project->id]));

    expect($client->website)->toBe('https://client-example.test')
        ->and($project->client_id)->toBe($client->id)
        ->and($project->wants_seo)->toBeTrue()
        ->and($project->wants_backlink)->toBeFalse()
        ->and($project->seo_requirements['target_url'])->toBe('https://client-example.test');

    Queue::assertPushed(GenerateKeywordRecommendationsJob::class);
    Queue::assertPushed(AnalyzePageSpeedJob::class);
    Queue::assertPushed(AnalyzeAiCrawlerAccessJob::class);
    Queue::assertPushed(AnalyzeStructuredDataJob::class);
    Queue::assertPushed(AnalyzeOnPageJob::class);
    Queue::assertPushed(AnalyzeTechnicalSeoJob::class);
    Queue::assertPushed(AnalyzeContentExtractabilityJob::class);

    $this->get(route('pages.project-workspace', ['project' => $project->id]))
        ->assertOk()
        ->assertSee('https://client-example.test')
        ->assertSee($project->name)
        ->assertSee('SEO Requirements')
        ->assertSee('Location');
});

it('rejects private and malformed website URLs', function (string $url) {
    $this->actingAs(User::factory()->create())
        ->from(route('pages.seo-analysis'))
        ->post(route('pages.seo-analysis.analyze'), ['url' => $url])
        ->assertRedirect(route('pages.seo-analysis'))
        ->assertSessionHasErrors('url');

    expect(Project::count())->toBe(0);
})->with([
    'private IP' => 'http://127.0.0.1/admin',
    'malformed URL' => 'not a website',
]);
