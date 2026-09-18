<?php

use App\Models\BillingReminderLog;
use App\Models\BillingSubscription;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ServicePackage;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of Billing & Finance: the schema, and nothing but the schema.
 *
 * The rule these tests protect is that recurring billing was added ALONGSIDE
 * the existing project invoices, not in place of them. There is one invoices
 * table, and a DP invoice for a project must keep behaving exactly as it did
 * while a hosting renewal — which may have no project at all — now fits in the
 * same table.
 */
function billingClient(array $overrides = []): Client
{
    return Client::create(array_merge([
        'company_name' => 'PT ABC',
        'contact_name' => 'Budi',
        'email' => 'budi@abc.test',
        'phone' => '08111',
    ], $overrides));
}

function billingProject(Client $client): Project
{
    return Project::create([
        'client_id' => $client->id,
        'name' => 'Website ABC',
        'client_name' => $client->company_name,
        'status' => 'request',
    ]);
}

function hostingSubscription(Client $client, array $overrides = []): BillingSubscription
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

it('creates the service_packages table the existing model always assumed', function () {
    expect(Schema::hasTable('service_packages'))->toBeTrue();

    // Fields follow ServicePackageController::validated(), so the page that was
    // returning a 500 now works unchanged.
    $package = ServicePackage::create([
        'name' => 'SEO Basic',
        'category' => 'paket_utama',
        'price' => 3500000,
        'unit' => 'per bulan',
        'features' => "Riset keyword\nOptimasi on-page",
    ]);

    expect($package->fresh()->price)->toBe('3500000.00')
        ->and($package->featureList())->toHaveCount(2);
});

it('lets a subscription exist without any service package or project', function () {
    $client = billingClient();
    $subscription = hostingSubscription($client);

    expect($subscription->service_package_id)->toBeNull()
        ->and($subscription->project_id)->toBeNull()
        ->and($subscription->client->company_name)->toBe('PT ABC')
        ->and($subscription->isYearly())->toBeTrue()
        ->and($subscription->isActive())->toBeTrue();
});

it('links a subscription to a package and a project when they exist', function () {
    $client = billingClient();
    $project = billingProject($client);
    $package = ServicePackage::create([
        'name' => 'SEO Basic', 'category' => 'paket_utama', 'price' => 3500000, 'features' => 'x',
    ]);

    $seo = hostingSubscription($client, [
        'project_id' => $project->id,
        'service_package_id' => $package->id,
        'name' => 'SEO Basic',
        'service_type' => 'seo',
        'billing_cycle' => BillingSubscription::CYCLE_MONTHLY,
        'amount' => 3500000,
    ]);

    expect($seo->project->name)->toBe('Website ABC')
        ->and($seo->servicePackage->name)->toBe('SEO Basic')
        ->and($seo->isYearly())->toBeFalse()
        ->and($package->billingSubscriptions)->toHaveCount(1);
});

it('keeps the existing project invoice working exactly as before', function () {
    $client = billingClient();
    $project = billingProject($client);

    $invoice = Invoice::create([
        'project_id' => $project->id,
        'invoice_number' => 'INV-ABC-1001',
        'type' => 'dp',
        'amount' => 5000000,
        'due_date' => today()->addDays(7),
    ]);

    $invoice->refresh();

    expect($invoice->project->name)->toBe('Website ABC')
        ->and($invoice->typeLabel())->toBe('DP')
        ->and($invoice->status)->toBe('unpaid')
        // Untouched legacy rows get the purpose they have always had.
        ->and($invoice->purpose)->toBe(Invoice::PURPOSE_PROJECT)
        ->and($invoice->isRenewal())->toBeFalse()
        // and the client is still reachable through the project.
        ->and($invoice->billableClient()->id)->toBe($client->id);
});

it('accepts a renewal invoice that has no project at all', function () {
    $client = billingClient();
    $subscription = hostingSubscription($client);

    $invoice = Invoice::create([
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'invoice_number' => 'INV-2026-000123',
        'type' => 'full',
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'issue_date' => today(),
        'amount' => 2500000,
        'subtotal' => 2500000,
        'due_date' => '2026-10-30',
        'billing_period_start' => '2026-10-30',
        'billing_period_end' => '2027-10-29',
    ]);

    $invoice->refresh();

    expect($invoice->project_id)->toBeNull()
        ->and($invoice->isRenewal())->toBeTrue()
        ->and($invoice->subscription->name)->toBe('Hosting + Domain')
        ->and($invoice->billableClient()->id)->toBe($client->id)
        ->and($invoice->currency)->toBe('IDR');
});

it('tells a renewal from a project payment by purpose, not by a missing project', function () {
    $client = billingClient();
    $project = billingProject($client);
    $subscription = hostingSubscription($client, ['project_id' => $project->id]);

    // A renewal CAN belong to a project — hosting for a site we built — so
    // project_id being null is not what makes an invoice a renewal.
    $renewal = Invoice::create([
        'project_id' => $project->id,
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'invoice_number' => 'INV-2026-000124',
        'type' => 'full',
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'amount' => 1500000,
        'due_date' => today()->addDays(30),
    ]);

    expect($renewal->fresh()->isRenewal())->toBeTrue()
        ->and($renewal->project_id)->not->toBeNull();

    expect(Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->count())->toBe(1);
});

it('adds up line items to the invoice total', function () {
    $client = billingClient();
    $invoice = Invoice::create([
        'client_id' => $client->id,
        'invoice_number' => 'INV-2026-000125',
        'type' => 'full',
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'amount' => 2800000,
        'due_date' => today()->addDays(30),
    ]);

    foreach ([
        ['Hosting Business', 1500000],
        ['Domain abc.com', 300000],
        ['Website Maintenance', 1000000],
    ] as $position => [$description, $price]) {
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $price,
            'total' => $price,
            'position' => $position,
        ]);
    }

    $invoice->load('items');

    expect($invoice->items)->toHaveCount(3)
        ->and($invoice->itemsTotal())->toBe('2800000.00')
        ->and($invoice->itemsTotal())->toBe($invoice->amount)
        ->and($invoice->items->first()->description)->toBe('Hosting Business');
});

it('records a payment against an invoice with its own detail', function () {
    $client = billingClient();
    $invoice = Invoice::create([
        'client_id' => $client->id,
        'invoice_number' => 'INV-2026-000126',
        'type' => 'full',
        'amount' => 2500000,
        'due_date' => today(),
    ]);

    $payment = Payment::create([
        'invoice_id' => $invoice->id,
        'paid_on' => today(),
        'amount' => 2500000,
        'method' => 'bank_transfer',
        'reference' => 'TRF/2026/0099',
    ]);

    expect($invoice->fresh()->payments)->toHaveCount(1)
        ->and($payment->methodLabel())->toBe('Bank Transfer')
        ->and($payment->invoice->invoice_number)->toBe('INV-2026-000126');
});

it('refuses a second reminder log for the same threshold on the same invoice', function () {
    $client = billingClient();
    $subscription = hostingSubscription($client);
    $invoice = Invoice::create([
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'invoice_number' => 'INV-2026-000127',
        'type' => 'full',
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'amount' => 2500000,
        'due_date' => '2026-10-30',
    ]);

    $log = fn (int $days) => BillingReminderLog::create([
        'invoice_id' => $invoice->id,
        'billing_subscription_id' => $subscription->id,
        'days_before' => $days,
        'scheduled_for' => today(),
        'status' => BillingReminderLog::SENT,
        'sent_at' => now(),
        'recipient' => 'budi@abc.test',
    ]);

    $log(30);
    $log(7); // a different threshold is fine

    // The same threshold twice is what must be impossible — this is the
    // database-level guarantee the scheduler relies on, not a timestamp check.
    expect(fn () => $log(30))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);

    expect($invoice->fresh()->reminderLogs)->toHaveCount(2);
});

it('describes a reminder the way the schedule is written', function () {
    $log = new BillingReminderLog(['days_before' => 30, 'status' => BillingReminderLog::FAILED, 'attempts' => 1]);

    expect($log->label())->toBe('H-30')
        ->and($log->wasSent())->toBeFalse()
        ->and($log->canRetry())->toBeTrue();

    $exhausted = new BillingReminderLog([
        'days_before' => 7,
        'status' => BillingReminderLog::FAILED,
        'attempts' => BillingReminderLog::MAX_ATTEMPTS,
    ]);

    expect($exhausted->canRetry())->toBeFalse();
});

it('falls back to the client main contact when no billing contact is set', function () {
    $client = billingClient();

    expect($client->billingEmail())->toBe('budi@abc.test')
        ->and($client->billingName())->toBe('Budi')
        ->and($client->billingPhone())->toBe('08111');

    $client->update([
        'billing_name' => 'Finance ABC',
        'billing_email' => 'finance@abc.test',
        'billing_phone' => '08222',
    ]);

    expect($client->fresh()->billingEmail())->toBe('finance@abc.test')
        ->and($client->fresh()->billingName())->toBe('Finance ABC')
        ->and($client->fresh()->billingPhone())->toBe('08222');
});

it('stores money as numbers, never as formatted strings', function () {
    $client = billingClient();
    $subscription = hostingSubscription($client, ['amount' => 2500000.50]);

    $invoice = Invoice::create([
        'client_id' => $client->id,
        'invoice_number' => 'INV-2026-000128',
        'type' => 'full',
        'amount' => 2500000.50,
        'subtotal' => 2500000.50,
        'discount' => 0,
        'tax' => 0,
        'due_date' => today(),
    ]);

    // Decimal casts, so arithmetic stays exact and nothing carries an "Rp".
    expect($subscription->fresh()->amount)->toBe('2500000.50')
        ->and($invoice->fresh()->amount)->toBe('2500000.50')
        ->and($invoice->fresh()->subtotal)->toBe('2500000.50')
        ->and($invoice->fresh()->amount)->not->toContain('Rp');
});

it('leaves overdue derived rather than stored', function () {
    $client = billingClient();
    $invoice = Invoice::create([
        'client_id' => $client->id,
        'invoice_number' => 'INV-2026-000129',
        'type' => 'full',
        'amount' => 1000000,
        'due_date' => today()->subDay(),
    ]);

    // No 'overdue' value is ever written; the status stays what it is and the
    // condition is computed, so a status can never drift out of step with the
    // due date.
    expect($invoice->status)->toBe('unpaid')
        ->and($invoice->isOverdue())->toBeTrue()
        ->and($invoice->statusLabel())->toBe('Terlambat');

    $invoice->update(['status' => 'paid', 'paid_at' => today()]);

    expect($invoice->fresh()->isOverdue())->toBeFalse();
});

it('keeps a subscription countdown to its renewal date', function () {
    $client = billingClient();
    $subscription = hostingSubscription($client, ['next_renewal_date' => today()->addDays(12)]);

    expect($subscription->daysUntilRenewal())->toBe(12)
        ->and($subscription->periodEndFrom($subscription->next_renewal_date)->toDateString())
        ->toBe($subscription->next_renewal_date->copy()->addYear()->subDay()->toDateString());
});

it('keeps the invoice when the project it was raised for is deleted', function () {
    $client = billingClient();
    $project = billingProject($client);

    $invoice = Invoice::create([
        'project_id' => $project->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-ABC-2001',
        'type' => 'pelunasan',
        'amount' => 5000000,
        'due_date' => today(),
    ]);
    Payment::create(['invoice_id' => $invoice->id, 'paid_on' => today(), 'amount' => 5000000]);

    $project->delete();

    // An invoice is a finance record. It outlives the project it was raised
    // for — this used to cascade, taking the invoice and its payment with it.
    $invoice->refresh();

    expect(Invoice::whereKey($invoice->id)->exists())->toBeTrue()
        ->and($invoice->project_id)->toBeNull()
        ->and($invoice->client_id)->toBe($client->id)
        ->and($invoice->payments)->toHaveCount(1)
        ->and($invoice->amount)->toBe('5000000.00');
});

it('keeps the renewal invoice when its subscription is deleted', function () {
    $client = billingClient();
    $subscription = hostingSubscription($client);

    $invoice = Invoice::create([
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'invoice_number' => 'INV-2026-002002',
        'type' => 'full',
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'amount' => 2500000,
        'due_date' => today(),
    ]);

    $subscription->delete();
    $invoice->refresh();

    expect(Invoice::whereKey($invoice->id)->exists())->toBeTrue()
        ->and($invoice->billing_subscription_id)->toBeNull()
        ->and($invoice->isRenewal())->toBeTrue()
        ->and($invoice->client_id)->toBe($client->id);
});

it('keeps invoices when the client record itself is deleted', function () {
    $client = billingClient();
    $subscription = hostingSubscription($client);

    $invoice = Invoice::create([
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'invoice_number' => 'INV-2026-002003',
        'type' => 'full',
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'amount' => 2500000,
        'due_date' => today(),
    ]);

    $client->delete();
    $invoice->refresh();

    // The subscription is not history and goes with the client; the invoice is
    // history and stays, with its references simply emptied.
    expect(Invoice::whereKey($invoice->id)->exists())->toBeTrue()
        ->and($invoice->client_id)->toBeNull()
        ->and(BillingSubscription::whereKey($subscription->id)->exists())->toBeFalse();
});

it('removes an invoice own children when the invoice itself is deleted', function () {
    $client = billingClient();
    $subscription = hostingSubscription($client);

    $invoice = Invoice::create([
        'client_id' => $client->id,
        'billing_subscription_id' => $subscription->id,
        'invoice_number' => 'INV-2026-002004',
        'type' => 'full',
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'amount' => 2500000,
        'due_date' => today(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'description' => 'Hosting',
        'quantity' => 1, 'unit_price' => 2500000, 'total' => 2500000,
    ]);
    Payment::create(['invoice_id' => $invoice->id, 'paid_on' => today(), 'amount' => 2500000]);
    BillingReminderLog::create([
        'invoice_id' => $invoice->id,
        'billing_subscription_id' => $subscription->id,
        'days_before' => 30,
        'scheduled_for' => today(),
    ]);

    $invoice->delete();

    // These belong TO the invoice, so they go with it — unlike the client,
    // project and subscription it merely refers to.
    expect(InvoiceItem::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(BillingReminderLog::count())->toBe(0);
});
