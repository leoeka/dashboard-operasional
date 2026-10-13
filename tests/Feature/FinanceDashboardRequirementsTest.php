<?php

use App\Models\BillingReminderLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\Billing\BillingRenewalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function financeRequirementClient(array $attributes = []): Client
{
    static $sequence = 0;
    $sequence++;

    return Client::create(array_merge([
        'company_name' => "Klien Uji {$sequence}",
        'contact_name' => 'Kontak Uji',
        'email' => "kontak{$sequence}@example.test",
    ], $attributes));
}

function financeRequirementInvoice(Client $client, array $attributes = []): Invoice
{
    static $sequence = 0;
    $sequence++;

    return Invoice::create(array_merge([
        'client_id' => $client->id,
        'invoice_number' => "INV-REQ-{$sequence}",
        'type' => 'full',
        'amount' => 100000,
        'due_date' => today()->addDays(10),
        'status' => 'unpaid',
    ], $attributes));
}

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00')));
afterEach(fn () => Carbon::setTestNow());

it('shows the client total without invoice payment status counts', function () {
    $overdue = financeRequirementClient();
    financeRequirementInvoice($overdue, ['due_date' => today()->subDay()]);
    financeRequirementInvoice($overdue, ['due_date' => today()->subDays(2)]);

    $dueSoon = financeRequirementClient();
    financeRequirementInvoice($dueSoon, ['due_date' => today()->addDays(7)]);

    $projectClient = financeRequirementClient();
    $project = Project::create([
        'client_id' => $projectClient->id,
        'name' => 'Project Klien Uji',
        'client_name' => $projectClient->company_name,
        'status' => 'request',
    ]);
    Invoice::create([
        'project_id' => $project->id,
        'invoice_number' => 'INV-REQ-PROJECT',
        'type' => 'full',
        'amount' => 100000,
        'due_date' => today()->addDays(3),
        'status' => 'unpaid',
    ]);
    financeRequirementClient();

    $summary = $this->actingAs(User::factory()->create())
        ->get(route('pages.finance'))
        ->assertOk()
        ->viewData('summary');

    expect($summary['total_clients'])->toBe(4)
        ->and(array_key_exists('unpaid_clients', $summary))->toBeFalse()
        ->and(array_key_exists('overdue_clients', $summary))->toBeFalse()
        ->and(array_key_exists('due_soon_clients', $summary))->toBeFalse()
        ->and(array_key_exists('paid_this_month_clients', $summary))->toBeFalse();
});

it('creates the client and project through the CRM request workflow', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('clients.store'), [
            'client_name' => 'Rina Uji',
            'company_name' => 'PT Klien SQL',
            'email' => 'rina@sql.test',
            'phone' => '0812345678',
            'service_type' => ['website'],
            'website_name' => 'Website PT Klien SQL',
            'user_story' => 'Profil perusahaan.',
            'target_market' => 'Perusahaan lokal.',
            'website_type' => 'Company Profile',
        ])
        ->assertRedirect();

    $client = Client::where('company_name', 'PT Klien SQL')->sole();
    $project = $client->projects()->sole();

    expect(DB::table('clients')->where('id', $client->id)->value('email'))->toBe('rina@sql.test')
        ->and(DB::table('projects')->where('id', $project->id)->value('client_id'))->toBe($client->id)
        ->and($project->name)->toBe('Website PT Klien SQL');
});

it('hides payment and manual invoice entry points from finance', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('pages.finance'))
        ->assertOk()
        ->assertDontSee('Pembayaran')
        ->assertDontSee('Membayar Bulan Ini')
        ->assertDontSee('Invoice Manual');

    expect(\Illuminate\Support\Facades\Route::has('pages.finance.store'))->toBeFalse();
});

it('shows resend only when a failed reminder remains retryable', function () {
    $client = financeRequirementClient();
    $subscription = \App\Models\BillingSubscription::create([
        'client_id' => $client->id,
        'name' => 'Hosting',
        'service_type' => 'hosting',
        'billing_cycle' => 'yearly',
        'amount' => 100000,
        'start_date' => today(),
        'next_renewal_date' => today()->addDays(30),
    ]);
    $invoice = financeRequirementInvoice($client, [
        'billing_subscription_id' => $subscription->id,
        'purpose' => Invoice::PURPOSE_RENEWAL,
    ]);
    $log = BillingReminderLog::create([
        'invoice_id' => $invoice->id,
        'billing_subscription_id' => $subscription->id,
        'days_before' => 30,
        'scheduled_for' => today(),
        'status' => BillingReminderLog::FAILED,
        'attempts' => 1,
        'error_message' => 'SMTP timeout',
    ]);

    $service = Mockery::mock(BillingRenewalService::class);
    $service->shouldReceive('retryFailedDeliveries')->once()->with([$log->id])->andReturn(1);
    $this->app->instance(BillingRenewalService::class, $service);

    $this->actingAs(User::factory()->create())
        ->get(route('pages.finance', ['tab' => 'reminders']))
        ->assertOk()
        ->assertSee('Kirim ulang');

    $this->post(route('pages.finance.reminders.retry', $log))
        ->assertRedirect()
        ->assertSessionHas('success');

    $log->update(['attempts' => BillingReminderLog::MAX_ATTEMPTS]);
    $this->get(route('pages.finance', ['tab' => 'reminders']))
        ->assertOk()
        ->assertSee('Batas percobaan tercapai')
        ->assertDontSee('Kirim ulang');
});
