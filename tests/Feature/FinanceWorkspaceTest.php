<?php

use App\Models\BillingSubscription;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\Billing\BillingPaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Phase 4A: the Billing & Finance workspace.
 *
 * The page is read-only — every action still runs through the controllers and
 * services that Phases 1–3 built and tested. So what these tests protect is
 * that the page shows the truth (the same truth the scheduler works from), and
 * that nothing the Finance page did before has been lost along the way.
 */
function financeUser(): User
{
    return User::factory()->create();
}

function financeClient(array $overrides = []): Client
{
    return Client::create(array_merge([
        'company_name' => 'PT ABC',
        'contact_name' => 'Budi',
        'email' => 'budi@abc.test',
    ], $overrides));
}

function financeProject(Client $client, string $name = 'Website ABC'): Project
{
    return Project::create([
        'client_id' => $client->id,
        'name' => $name,
        'client_name' => $client->company_name,
        'status' => 'request',
    ]);
}

function financeSubscription(Client $client, array $overrides = []): BillingSubscription
{
    return BillingSubscription::create(array_merge([
        'client_id' => $client->id,
        'name' => 'Hosting + Domain',
        'service_type' => 'hosting',
        'billing_cycle' => BillingSubscription::CYCLE_YEARLY,
        'amount' => 2500000,
        'start_date' => '2025-10-30',
        'next_renewal_date' => '2026-10-30',
    ], $overrides));
}

function financeInvoice(array $attributes): Invoice
{
    return Invoice::create(array_merge([
        'invoice_number' => 'INV-TEST-' . fake()->unique()->numberBetween(1000, 9999),
        'type' => 'full',
        'amount' => 1000000,
        'due_date' => today(),
    ], $attributes));
}

afterEach(fn () => Carbon::setTestNow());

// --------------------------------------------------------------- overview ---

it('loads the finance workspace', function () {
    $this->actingAs(financeUser())
        ->get(route('pages.finance'))
        ->assertOk()
        ->assertSee('Billing &amp; Finance', false)
        ->assertSee('Perpanjangan Mendatang');
});

it('keeps the sidebar route working', function () {
    // The menu links to pages.finance; renaming or moving it would break the
    // sidebar and every bookmark.
    expect(route('pages.finance'))->toEndWith('/finance');

    $this->actingAs(financeUser())->get('/finance')->assertOk();
});

it('adds up what is still owed', function () {
    $client = financeClient();
    financeInvoice(['client_id' => $client->id, 'amount' => 1500000, 'status' => 'unpaid', 'due_date' => today()->addDays(10)]);
    financeInvoice(['client_id' => $client->id, 'amount' => 500000, 'status' => 'unpaid', 'due_date' => today()->addDays(20)]);
    financeInvoice(['client_id' => $client->id, 'amount' => 900000, 'status' => 'paid', 'paid_at' => today()]);

    $summary = $this->actingAs(financeUser())->get(route('pages.finance'))->viewData('summary');

    // Paid invoices are not outstanding.
    expect($summary['outstanding_total'])->toBe(2000000.0)
        ->and($summary['outstanding_count'])->toBe(2);
});

it('derives overdue from the due date rather than a stored status', function () {
    $client = financeClient();
    financeInvoice(['client_id' => $client->id, 'amount' => 700000, 'status' => 'unpaid', 'due_date' => today()->subDays(3)]);
    financeInvoice(['client_id' => $client->id, 'amount' => 300000, 'status' => 'unpaid', 'due_date' => today()->addDays(3)]);
    // Past its date but settled — not overdue.
    financeInvoice(['client_id' => $client->id, 'amount' => 999000, 'status' => 'paid', 'due_date' => today()->subDays(9), 'paid_at' => today()]);

    $summary = $this->actingAs(financeUser())->get(route('pages.finance'))->viewData('summary');

    expect($summary['overdue_count'])->toBe(1)
        ->and($summary['overdue_total'])->toBe(700000.0);
});

it('counts what falls due within the next week', function () {
    $client = financeClient();
    financeInvoice(['client_id' => $client->id, 'amount' => 100000, 'status' => 'unpaid', 'due_date' => today()->addDays(2)]);
    financeInvoice(['client_id' => $client->id, 'amount' => 200000, 'status' => 'unpaid', 'due_date' => today()->addDays(7)]);
    financeInvoice(['client_id' => $client->id, 'amount' => 400000, 'status' => 'unpaid', 'due_date' => today()->addDays(8)]);

    $summary = $this->actingAs(financeUser())->get(route('pages.finance'))->viewData('summary');

    expect($summary['due_soon_count'])->toBe(2)
        ->and($summary['due_soon_total'])->toBe(300000.0);
});

it('totals the money actually received this month', function () {
    $client = financeClient();
    $invoice = financeInvoice(['client_id' => $client->id, 'amount' => 2500000]);

    Payment::create(['invoice_id' => $invoice->id, 'paid_on' => today(), 'amount' => 2500000]);
    Payment::create(['invoice_id' => $invoice->id, 'paid_on' => today()->subMonth(), 'amount' => 9000000]);

    $summary = $this->actingAs(financeUser())->get(route('pages.finance'))->viewData('summary');

    expect($summary['paid_this_month'])->toBe(2500000.0)
        ->and($summary['paid_this_month_count'])->toBe(1);
});

it('counts only active subscriptions', function () {
    $client = financeClient();
    financeSubscription($client);
    financeSubscription($client, ['name' => 'SEO', 'status' => 'paused']);
    financeSubscription($client, ['name' => 'Maintenance', 'status' => 'cancelled']);

    $summary = $this->actingAs(financeUser())->get(route('pages.finance'))->viewData('summary');

    expect($summary['active_subscriptions'])->toBe(1);
});

it('counts renewals falling inside the next thirty days', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00'));
    $client = financeClient();

    financeSubscription($client, ['next_renewal_date' => '2026-10-20']); // inside
    financeSubscription($client, ['name' => 'B', 'next_renewal_date' => '2026-10-31']); // inside
    financeSubscription($client, ['name' => 'C', 'next_renewal_date' => '2026-12-01']); // outside
    financeSubscription($client, ['name' => 'D', 'next_renewal_date' => '2026-10-15', 'status' => 'paused']);

    $summary = $this->actingAs(financeUser())->get(route('pages.finance'))->viewData('summary');

    expect($summary['upcoming_renewals'])->toBe(2);
});

it('shows a countdown on the upcoming renewals list', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00'));
    $client = financeClient();
    financeSubscription($client); // renews 2026-10-30 → 7 days

    $renewals = $this->actingAs(financeUser())->get(route('pages.finance'))->viewData('renewals');

    expect($renewals)->toHaveCount(1)
        ->and($renewals[0]['days'])->toBe(7)
        ->and($renewals[0]['countdown'])->toBe('7 hari lagi')
        // close enough to warrant attention, not alarm
        ->and($renewals[0]['tone'])->toBe('amber');
});

it('explains an empty overview instead of showing a bare table', function () {
    $this->actingAs(financeUser())
        ->get(route('pages.finance'))
        ->assertSee('Belum ada perpanjangan mendekat');
});

// ---------------------------------------------------------- subscriptions ---

it('lists subscriptions on their own tab', function () {
    $client = financeClient();
    financeSubscription($client);

    $this->actingAs(financeUser())
        ->get(route('pages.finance', ['tab' => 'subscriptions']))
        ->assertOk()
        ->assertSee('Hosting + Domain')
        ->assertSee('PT ABC');
});

it('filters subscriptions by status, cycle and search', function () {
    $client = financeClient();
    financeSubscription($client, ['name' => 'Hosting Aktif']);
    financeSubscription($client, ['name' => 'SEO Dijeda', 'billing_cycle' => 'monthly', 'status' => 'paused']);

    $user = financeUser();

    $this->actingAs($user)->get(route('pages.finance', ['tab' => 'subscriptions', 'status' => 'paused']))
        ->assertSee('SEO Dijeda')->assertDontSee('Hosting Aktif');

    $this->actingAs($user)->get(route('pages.finance', ['tab' => 'subscriptions', 'cycle' => 'monthly']))
        ->assertSee('SEO Dijeda')->assertDontSee('Hosting Aktif');

    $this->actingAs($user)->get(route('pages.finance', ['tab' => 'subscriptions', 'q' => 'Hosting']))
        ->assertSee('Hosting Aktif')->assertDontSee('SEO Dijeda');
});

it('creates a custom subscription with no package at all', function () {
    $client = financeClient();

    $this->actingAs(financeUser())
        ->post(route('pages.finance.subscriptions.store'), [
            'client_id' => $client->id,
            'name' => 'Maintenance Khusus',
            'service_type' => 'maintenance',
            'billing_cycle' => 'yearly',
            'amount' => 1000000,
            'start_date' => '2026-01-15',
            'next_renewal_date' => '2027-01-15',
            'status' => 'active',
            'auto_invoice' => 1,
            'auto_reminder' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $subscription = BillingSubscription::sole();

    expect($subscription->name)->toBe('Maintenance Khusus')
        ->and($subscription->service_package_id)->toBeNull()
        ->and($subscription->project_id)->toBeNull()
        ->and($subscription->amount)->toBe('1000000.00')
        ->and($subscription->auto_invoice)->toBeTrue();
});

it('creates a subscription from an optional service package', function () {
    $client = financeClient();
    $package = ServicePackage::create([
        'name' => 'SEO Basic', 'category' => 'paket_utama', 'price' => 3500000, 'unit' => 'per bulan', 'features' => 'x',
    ]);

    $this->actingAs(financeUser())
        ->post(route('pages.finance.subscriptions.store'), [
            'client_id' => $client->id,
            'service_package_id' => $package->id,
            'name' => 'SEO Basic',
            'service_type' => 'seo',
            'billing_cycle' => 'monthly',
            'amount' => 3500000,
            'start_date' => '2026-09-30',
            'next_renewal_date' => '2026-10-30',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect(BillingSubscription::sole()->servicePackage->name)->toBe('SEO Basic');
});

it('rejects a subscription without the fields it cannot work without', function () {
    $this->actingAs(financeUser())
        ->post(route('pages.finance.subscriptions.store'), ['name' => 'Tanpa Client'])
        ->assertSessionHasErrors(['client_id', 'service_type', 'billing_cycle', 'amount', 'start_date', 'next_renewal_date']);

    expect(BillingSubscription::count())->toBe(0);
});

it('updates a subscription', function () {
    $client = financeClient();
    $subscription = financeSubscription($client);

    $this->actingAs(financeUser())
        ->put(route('pages.finance.subscriptions.update', $subscription), [
            'client_id' => $client->id,
            'name' => 'Hosting Business',
            'service_type' => 'hosting',
            'billing_cycle' => 'yearly',
            'amount' => 3000000,
            'start_date' => '2025-10-30',
            'next_renewal_date' => '2026-10-30',
            'status' => 'active',
            'auto_invoice' => 1,
            'auto_reminder' => 1,
        ])
        ->assertRedirect();

    expect($subscription->fresh()->name)->toBe('Hosting Business')
        ->and($subscription->fresh()->amount)->toBe('3000000.00');
});

it('leaves already-issued invoices alone when the price changes', function () {
    $client = financeClient();
    $subscription = financeSubscription($client);

    $invoice = financeInvoice([
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'amount' => 2500000,
    ]);
    $invoice->items()->create(['description' => 'Hosting + Domain', 'quantity' => 1, 'unit_price' => 2500000, 'total' => 2500000]);

    $this->actingAs(financeUser())->put(route('pages.finance.subscriptions.update', $subscription), [
        'client_id' => $client->id,
        'name' => 'Hosting + Domain',
        'service_type' => 'hosting',
        'billing_cycle' => 'yearly',
        'amount' => 5000000, // price doubles for the next period
        'start_date' => '2025-10-30',
        'next_renewal_date' => '2026-10-30',
        'status' => 'active',
    ]);

    // An invoice is a historical record of what was billed, not a live view of
    // the current price.
    expect($invoice->fresh()->amount)->toBe('2500000.00')
        ->and($invoice->items()->sole()->unit_price)->toBe('2500000.00')
        ->and($subscription->fresh()->amount)->toBe('5000000.00');
});

it('pauses, resumes and cancels a subscription without deleting it', function () {
    $client = financeClient();
    $subscription = financeSubscription($client);
    $user = financeUser();

    $patch = fn (string $status) => $this->actingAs($user)
        ->patch(route('pages.finance.subscriptions.status', $subscription), ['status' => $status]);

    $patch('paused')->assertRedirect();
    expect($subscription->fresh()->status)->toBe('paused');

    $patch('active');
    expect($subscription->fresh()->status)->toBe('active');

    $patch('cancelled');
    expect($subscription->fresh()->status)->toBe('cancelled')
        // still there, because invoices point at it
        ->and(BillingSubscription::whereKey($subscription->id)->exists())->toBeTrue();
});

it('refuses a status the model does not recognise', function () {
    $subscription = financeSubscription(financeClient());

    $this->actingAs(financeUser())
        ->patch(route('pages.finance.subscriptions.status', $subscription), ['status' => 'deleted'])
        ->assertSessionHasErrors('status');

    expect($subscription->fresh()->status)->toBe('active');
});

// --------------------------------------------------------------- invoices ---

it('shows project invoices and renewal invoices in one list', function () {
    $client = financeClient();
    $project = financeProject($client);
    $subscription = financeSubscription($client);

    financeInvoice(['project_id' => $project->id, 'invoice_number' => 'INV-ABC-1001', 'type' => 'dp']);
    financeInvoice([
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'invoice_number' => 'INV-2026-000001',
        'purpose' => Invoice::PURPOSE_RENEWAL,
    ]);

    $this->actingAs(financeUser())
        ->get(route('pages.finance', ['tab' => 'invoices']))
        ->assertOk()
        ->assertSee('INV-ABC-1001')
        ->assertSee('INV-2026-000001')
        ->assertSee('Website ABC')
        ->assertSee('Hosting + Domain')
        // each labelled for what it is
        ->assertSee('Perpanjangan')
        ->assertSee('Project');
});

it('filters invoices by purpose', function () {
    $client = financeClient();
    $project = financeProject($client);

    financeInvoice(['project_id' => $project->id, 'invoice_number' => 'INV-ABC-2001', 'type' => 'dp']);
    financeInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-2026-000002', 'purpose' => Invoice::PURPOSE_RENEWAL]);

    $user = financeUser();

    $this->actingAs($user)->get(route('pages.finance', ['tab' => 'invoices', 'purpose' => 'renewal']))
        ->assertSee('INV-2026-000002')->assertDontSee('INV-ABC-2001');

    $this->actingAs($user)->get(route('pages.finance', ['tab' => 'invoices', 'purpose' => 'project_payment']))
        ->assertSee('INV-ABC-2001')->assertDontSee('INV-2026-000002');
});

it('filters invoices by status and by overdue', function () {
    $client = financeClient();

    financeInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-UNPAID', 'status' => 'unpaid', 'due_date' => today()->addDays(5)]);
    financeInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-LATE', 'status' => 'unpaid', 'due_date' => today()->subDays(5)]);
    financeInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-PAID', 'status' => 'paid', 'paid_at' => today()]);

    $user = financeUser();

    $this->actingAs($user)->get(route('pages.finance', ['tab' => 'invoices', 'status' => 'paid']))
        ->assertSee('INV-PAID')->assertDontSee('INV-LATE');

    $this->actingAs($user)->get(route('pages.finance', ['tab' => 'invoices', 'status' => 'overdue']))
        ->assertSee('INV-LATE')->assertDontSee('INV-UNPAID');

    $this->actingAs($user)->get(route('pages.finance', ['tab' => 'invoices', 'status' => 'unpaid']))
        ->assertSee('INV-UNPAID')->assertSee('INV-LATE')->assertDontSee('INV-PAID');
});

it('keeps filters attached to the pagination links', function () {
    $client = financeClient();

    foreach (range(1, 18) as $i) {
        financeInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-P-' . $i, 'purpose' => Invoice::PURPOSE_RENEWAL]);
    }

    $this->actingAs(financeUser())
        ->get(route('pages.finance', ['tab' => 'invoices', 'purpose' => 'renewal']))
        ->assertSee('purpose=renewal', false);
});

// ---------------------------------------------------------- compatibility ---

it('still creates a manual project invoice from the finance page', function () {
    $client = financeClient();
    $project = financeProject($client);

    $this->actingAs(financeUser())
        ->post(route('pages.finance.store'), [
            'project_id' => $project->id,
            'type' => 'dp',
            'amount' => 5000000,
            'due_date' => today()->addDays(7)->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $invoice = Invoice::sole();

    // Unchanged legacy behaviour: a project invoice, defaulting to the project
    // purpose, with the old invoice-number format.
    expect($invoice->project_id)->toBe($project->id)
        ->and($invoice->type)->toBe('dp')
        ->and($invoice->purpose)->toBe(Invoice::PURPOSE_PROJECT);
});

it('still marks an invoice paid through the existing payment service', function () {
    $client = financeClient();
    $project = financeProject($client);
    $invoice = financeInvoice(['project_id' => $project->id, 'amount' => 5000000]);

    $this->actingAs(financeUser())
        ->patch(route('pages.finance.paid', $invoice))
        ->assertRedirect()
        ->assertSessionHas('success');

    // The service recorded a payment, not just a status flip.
    expect($invoice->fresh()->status)->toBe('paid')
        ->and(Payment::where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('advances the renewal date when a renewal invoice is marked paid from the page', function () {
    $client = financeClient();
    $subscription = financeSubscription($client);

    $invoice = financeInvoice([
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'amount' => 2500000,
        'due_date' => '2026-10-30',
        'billing_period_start' => '2026-10-30',
        'billing_period_end' => '2027-10-29',
    ]);

    $this->actingAs(financeUser())->patch(route('pages.finance.paid', $invoice))->assertRedirect();

    // The page did not reimplement any of this — BillingPaymentService did it.
    expect($subscription->fresh()->next_renewal_date->toDateString())->toBe('2027-10-30');
});

it('still sends a manual reminder for a project invoice', function () {
    Mail::fake();
    $client = financeClient(['whatsapp' => null]);
    $project = financeProject($client);
    $invoice = financeInvoice(['project_id' => $project->id]);

    $this->actingAs(financeUser())
        ->post(route('pages.finance.remind', $invoice))
        ->assertRedirect()
        ->assertSessionHas('success');

    Mail::assertSent(App\Mail\InvoiceReminderMail::class, 1);
    expect($invoice->fresh()->last_reminder_sent_at)->not->toBeNull();
});

it('refuses a manual reminder for a renewal invoice', function () {
    Mail::fake();
    $client = financeClient();
    $invoice = financeInvoice(['client_id' => $client->id, 'purpose' => Invoice::PURPOSE_RENEWAL]);

    $this->actingAs(financeUser())
        ->post(route('pages.finance.remind', $invoice))
        ->assertSessionHas('error');

    // Renewals follow the logged H-30/H-7/H-3 schedule; a manual send would
    // bypass that record entirely.
    Mail::assertNothingSent();
});

it('still deletes a legacy invoice', function () {
    $client = financeClient();
    $project = financeProject($client);
    $invoice = financeInvoice(['project_id' => $project->id]);

    $this->actingAs(financeUser())
        ->delete(route('pages.finance.destroy', $invoice))
        ->assertRedirect();

    expect(Invoice::count())->toBe(0);
});

it('does not run a heavy query for the overview', function () {
    $client = financeClient();

    foreach (range(1, 25) as $i) {
        financeInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-Q-' . $i, 'status' => 'unpaid']);
        financeSubscription($client, ['name' => 'Svc ' . $i]);
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->actingAs(financeUser())->get(route('pages.finance'))->assertOk();

    // Summary figures are aggregates and the renewal list is capped, so the
    // page cost does not grow with the size of the book.
    expect($queries)->toBeLessThan(30);
});

// ------------------------------------------------------- data integrity ----

/** A complete, valid subscription payload, so each test only varies one thing. */
function subscriptionPayload(Client $client, array $overrides = []): array
{
    return array_merge([
        'client_id' => $client->id,
        'name' => 'Hosting + Domain',
        'service_type' => 'hosting',
        'billing_cycle' => 'yearly',
        'amount' => 2500000,
        'start_date' => '2026-01-15',
        'next_renewal_date' => '2027-01-15',
        'status' => 'active',
    ], $overrides);
}

it('accepts a project that belongs to the chosen client', function () {
    $client = financeClient();
    $project = financeProject($client);

    $this->actingAs(financeUser())
        ->post(route('pages.finance.subscriptions.store'), subscriptionPayload($client, ['project_id' => $project->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(BillingSubscription::sole()->project_id)->toBe($project->id);
});

it('refuses a project that belongs to a different client', function () {
    $clientA = financeClient(['company_name' => 'PT A', 'email' => 'a@a.test']);
    $clientB = financeClient(['company_name' => 'PT B', 'email' => 'b@b.test']);
    $projectOfB = financeProject($clientB, 'Website B');

    // Billing PT A for work recorded under PT B is an inconsistency no later
    // screen could make sense of.
    $this->actingAs(financeUser())
        ->post(route('pages.finance.subscriptions.store'), subscriptionPayload($clientA, ['project_id' => $projectOfB->id]))
        ->assertSessionHasErrors('project_id');

    expect(BillingSubscription::count())->toBe(0);
});

it('refuses to move a subscription onto another client project', function () {
    $clientA = financeClient(['company_name' => 'PT A', 'email' => 'a@a.test']);
    $clientB = financeClient(['company_name' => 'PT B', 'email' => 'b@b.test']);
    $projectOfB = financeProject($clientB, 'Website B');

    $subscription = financeSubscription($clientA);

    $this->actingAs(financeUser())
        ->put(
            route('pages.finance.subscriptions.update', $subscription),
            subscriptionPayload($clientA, ['project_id' => $projectOfB->id, 'name' => 'Diubah'])
        )
        ->assertSessionHasErrors('project_id');

    expect($subscription->fresh()->project_id)->toBeNull()
        ->and($subscription->fresh()->name)->toBe('Hosting + Domain');
});

it('still allows a subscription with no project at all', function () {
    $client = financeClient();

    $this->actingAs(financeUser())
        ->post(route('pages.finance.subscriptions.store'), subscriptionPayload($client, ['project_id' => null]))
        ->assertSessionHasNoErrors();

    expect(BillingSubscription::sole()->project_id)->toBeNull();
});

it('refuses a renewal date earlier than the start date', function () {
    $client = financeClient();

    $this->actingAs(financeUser())
        ->post(route('pages.finance.subscriptions.store'), subscriptionPayload($client, [
            'start_date' => '2026-10-30',
            'next_renewal_date' => '2025-01-01',
        ]))
        ->assertSessionHasErrors('next_renewal_date');

    expect(BillingSubscription::count())->toBe(0);
});

it('allows a renewal date equal to the start date', function () {
    $client = financeClient();

    // A subscription can begin and bill on the same day.
    $this->actingAs(financeUser())
        ->post(route('pages.finance.subscriptions.store'), subscriptionPayload($client, [
            'start_date' => '2026-10-30',
            'next_renewal_date' => '2026-10-30',
        ]))
        ->assertSessionHasNoErrors();

    expect(BillingSubscription::sole()->next_renewal_date->toDateString())->toBe('2026-10-30');
});

it('refuses an earlier renewal date on update too', function () {
    $client = financeClient();
    $subscription = financeSubscription($client);

    $this->actingAs(financeUser())
        ->put(route('pages.finance.subscriptions.update', $subscription), subscriptionPayload($client, [
            'start_date' => '2026-10-30',
            'next_renewal_date' => '2026-10-29',
        ]))
        ->assertSessionHasErrors('next_renewal_date');

    expect($subscription->fresh()->next_renewal_date->toDateString())->toBe('2026-10-30');
});

// ------------------------------------------------- invoice delete guard ----

it('still deletes an unpaid project invoice', function () {
    $client = financeClient();
    $project = financeProject($client);
    $invoice = financeInvoice(['project_id' => $project->id, 'status' => 'unpaid']);

    $this->actingAs(financeUser())
        ->delete(route('pages.finance.destroy', $invoice))
        ->assertSessionHas('success');

    expect(Invoice::count())->toBe(0);
});

it('refuses to delete a paid project invoice', function () {
    $client = financeClient();
    $project = financeProject($client);
    $invoice = financeInvoice(['project_id' => $project->id, 'status' => 'paid', 'paid_at' => today()]);

    // Settled money is financial history.
    $this->actingAs(financeUser())
        ->delete(route('pages.finance.destroy', $invoice))
        ->assertSessionHas('error');

    expect(Invoice::whereKey($invoice->id)->exists())->toBeTrue();
});

it('refuses to delete an unpaid renewal invoice', function () {
    $client = financeClient();
    $subscription = financeSubscription($client);
    $invoice = financeInvoice([
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'status' => 'unpaid',
    ]);

    $this->actingAs(financeUser())
        ->delete(route('pages.finance.destroy', $invoice))
        ->assertSessionHas('error');

    expect(Invoice::whereKey($invoice->id)->exists())->toBeTrue();
});

it('refuses to delete a paid renewal invoice', function () {
    $client = financeClient();
    $subscription = financeSubscription($client);
    $invoice = financeInvoice([
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'status' => 'paid',
        'paid_at' => today(),
    ]);

    $this->actingAs(financeUser())
        ->delete(route('pages.finance.destroy', $invoice))
        ->assertSessionHas('error');

    expect(Invoice::whereKey($invoice->id)->exists())->toBeTrue();
});

it('keeps the payments, items and reminder logs of a refused delete', function () {
    $client = financeClient();
    $subscription = financeSubscription($client);
    $invoice = financeInvoice([
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'amount' => 2500000,
    ]);

    $invoice->items()->create(['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 2500000, 'total' => 2500000]);
    Payment::create(['invoice_id' => $invoice->id, 'paid_on' => today(), 'amount' => 2500000]);
    App\Models\BillingReminderLog::create([
        'invoice_id' => $invoice->id,
        'billing_subscription_id' => $subscription->id,
        'days_before' => 30,
        'scheduled_for' => today(),
        'status' => App\Models\BillingReminderLog::SENT,
        'sent_at' => now(),
    ]);

    $this->actingAs(financeUser())->delete(route('pages.finance.destroy', $invoice));

    // All three cascade from the invoice, which is exactly why the delete is
    // refused rather than merely hidden.
    expect(App\Models\InvoiceItem::count())->toBe(1)
        ->and(Payment::count())->toBe(1)
        ->and(App\Models\BillingReminderLog::count())->toBe(1);
});

it('blocks the delete even without the button', function () {
    $client = financeClient();
    $invoice = financeInvoice(['client_id' => $client->id, 'purpose' => Invoice::PURPOSE_RENEWAL]);

    // Hiding a button is not a rule; this is the rule.
    $this->actingAs(financeUser())
        ->delete(route('pages.finance.destroy', $invoice))
        ->assertSessionHas('error');

    expect(Invoice::count())->toBe(1);
});

// --------------------------------------------------------- frontend setup ---

it('only uses Alpine directives the runtime actually loads', function () {
    // The layout pulls Alpine core from a CDN with no plugins, so x-collapse
    // would be an unknown directive that silently does nothing.
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($layout)->toContain('alpinejs')
        ->not->toContain('collapse');

    foreach (['pages/finance.blade.php', 'finance/subscription-form.blade.php'] as $view) {
        // Blade comments stripped first: the comment explaining why x-collapse
        // is NOT used would otherwise trip this check.
        $markup = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents(resource_path('views/' . $view)));

        expect($markup)->not->toContain('x-collapse');
    }
});
