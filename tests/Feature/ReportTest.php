<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Reports is the only screen where the operator reads the business in
 * aggregate, so a filter that matches nothing shows up as a confident wrong
 * number instead of an error. These tests pin the two shapes that bug takes:
 * a status value no row has, and a zero presented as if it were a measurement.
 */
function rpUser(): User
{
    return User::factory()->create();
}

function rpProject(string $status, int $daysFromCreateToFinish = 10): Project
{
    $client = Client::create(['company_name' => 'PT ' . fake()->unique()->word()]);

    $project = Project::create([
        'client_id' => $client->id,
        'name' => 'Project ' . ucfirst($status),
        'client_name' => $client->company_name,
        'status' => $status,
    ]);

    // created_at is set by the insert, so move only the finish date.
    $project->forceFill([
        'updated_at' => $project->created_at->copy()->addDays($daysFromCreateToFinish),
    ])->saveQuietly();

    return $project;
}

it('sends a guest to login', function () {
    $this->get(route('pages.reports'))->assertRedirect(route('login'));
});

it('refuses to average a status the pipeline never writes', function () {
    // 'done' is the legacy spelling; the normalize migration mapped it to
    // 'completed'. Counting it here is what used to print "0 days" as fact.
    rpProject('completed', 12);

    $this->actingAs(rpUser())
        ->get(route('pages.reports'))
        ->assertOk()
        ->assertSee('12 hari');
});

it('says the average is not measurable instead of showing zero', function () {
    rpProject('request');
    rpProject('in_progress');

    $this->actingAs(rpUser())
        ->get(route('pages.reports'))
        ->assertOk()
        ->assertSee('belum bisa dihitung')
        ->assertDontSee('0 hari');
});

it('shows the paid months as numbers, not just bar heights', function () {
    $client = Client::create(['company_name' => 'PT Bayar Tepat']);
    Invoice::create([
        'invoice_number' => 'INV-RP-' . fake()->unique()->numberBetween(10000, 99999),
        'client_id' => $client->id,
        'type' => 'full',
        'amount' => 5_000_000,
        'status' => 'paid',
        'due_date' => today(),
        'paid_at' => Carbon::now(),
    ]);

    $this->actingAs(rpUser())
        ->get(route('pages.reports'))
        ->assertOk()
        ->assertSee('Total Rp5.000.000')
        ->assertSee('Rp5,0jt');
});

it('names an empty income chart and where to look instead', function () {
    $this->actingAs(rpUser())
        ->get(route('pages.reports'))
        ->assertOk()
        ->assertSee('Belum ada invoice berstatus dibayar dalam 6 bulan terakhir.');
});

it('exports the same figures without a bare zero in the duration row', function () {
    $csv = $this->actingAs(rpUser())
        ->get(route('pages.reports.download-excel'))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Average Completed Project Duration (days)')
        // With nothing completed the cell must not read as an empty number.
        ->and($csv)->toContain('no completed projects yet');
});
