<?php

use App\Models\BillingReminderLog;
use App\Models\BillingSubscription;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\BillingRenewalService;
use App\Services\Billing\ReminderDispatcher;
use Illuminate\Support\Carbon;

/**
 * The settlement marker.
 *
 * Money is settled outside this system, so the only thing Finance writes about
 * a payment is "this bill was paid, on this date". These tests protect the ways
 * that can go wrong: a date the UTC clock calls future when the business does
 * not, a marker that leaves the reminder schedule running anyway, and a button
 * hidden in Blade while the route stays wide open.
 */
class MarkPaidRecorder implements ReminderDispatcher
{
    /** @var array<int, array{invoice: int, days_before: int}> */
    public array $sent = [];

    public function send(Invoice $invoice, BillingReminderLog $log, string $recipient): bool
    {
        $this->sent[] = ['invoice' => $invoice->id, 'days_before' => $log->days_before];

        return true;
    }
}

function markRecorder(): MarkPaidRecorder
{
    $recorder = new MarkPaidRecorder();
    app()->instance(ReminderDispatcher::class, $recorder);

    return $recorder;
}

function markUser(): User
{
    return User::factory()->create();
}

function markClient(): Client
{
    return Client::create([
        'company_name' => 'PT ABC',
        'contact_name' => 'Budi',
        'email' => 'budi@abc.test',
    ]);
}

function markInvoice(Client $client, array $overrides = []): Invoice
{
    return Invoice::create(array_merge([
        'client_id' => $client->id,
        'invoice_number' => 'INV-MARK-001',
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'amount' => 2500000,
        'issue_date' => '2026-09-30',
        'due_date' => '2026-10-30',
    ], $overrides));
}

function markSubscription(Client $client): BillingSubscription
{
    return BillingSubscription::create([
        'client_id' => $client->id,
        'name' => 'Hosting + Domain',
        'service_type' => 'hosting',
        'billing_cycle' => BillingSubscription::CYCLE_YEARLY,
        'amount' => 2500000,
        'start_date' => '2025-10-30',
        'next_renewal_date' => '2026-10-30',
    ]);
}

afterEach(fn () => Carbon::setTestNow());

// ------------------------------------------------------------ marking ----

it('marks an invoice paid on the date the operator chose', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-17 01:00:00'));
    $client = markClient();
    $invoice = markInvoice($client);

    $this->actingAs(markUser())
        ->patch(route('pages.finance.invoices.paid', $invoice), ['paid_on' => '2026-10-17'])
        ->assertSessionHas('success');

    $invoice->refresh();

    expect($invoice->status)->toBe('paid')
        ->and($invoice->paid_at->format('Y-m-d'))->toBe('2026-10-17');
});

it('accepts a date that is tomorrow to the business but still today in UTC', function () {
    // 2026-10-16 23:30 UTC is 2026-17 07:30 in Asia/Makassar. A check written
    // against today() would reject the date the operator is looking at.
    Carbon::setTestNow(Carbon::parse('2026-10-16 23:30:00'));
    $invoice = markInvoice(markClient());

    $this->actingAs(markUser())
        ->patch(route('pages.finance.invoices.paid', $invoice), ['paid_on' => '2026-10-17'])
        ->assertSessionHas('success');

    expect($invoice->refresh()->status)->toBe('paid');
});

it('refuses a settlement date in the future', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-17 01:00:00'));
    $invoice = markInvoice(markClient());

    $this->actingAs(markUser())
        ->patch(route('pages.finance.invoices.paid', $invoice), ['paid_on' => '2026-10-18'])
        ->assertSessionHas('error');

    $invoice->refresh();

    expect($invoice->status)->toBe('unpaid')
        ->and($invoice->paid_at)->toBeNull();
});

it('refuses to mark a cancelled invoice paid', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-17 01:00:00'));
    $invoice = markInvoice(markClient(), ['status' => 'cancelled']);

    $this->actingAs(markUser())
        ->patch(route('pages.finance.invoices.paid', $invoice), ['paid_on' => '2026-10-17'])
        ->assertSessionHas('error');

    expect($invoice->refresh()->status)->toBe('cancelled');
});

it('does not overwrite a settlement date by marking the same invoice twice', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-17 01:00:00'));
    $invoice = markInvoice(markClient());

    $this->actingAs(markUser())->patch(route('pages.finance.invoices.paid', $invoice), ['paid_on' => '2026-10-17']);

    Carbon::setTestNow(Carbon::parse('2026-10-19 01:00:00'));
    $this->actingAs(markUser())
        ->patch(route('pages.finance.invoices.paid', $invoice), ['paid_on' => '2026-10-19'])
        ->assertSessionHas('error');

    expect($invoice->refresh()->paid_at->format('Y-m-d'))->toBe('2026-10-17');
});

it('clears both the status and the date when the marker is undone', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-17 01:00:00'));
    $invoice = markInvoice(markClient(), ['status' => 'paid', 'paid_at' => '2026-10-17']);

    $this->actingAs(markUser())
        ->patch(route('pages.finance.invoices.unpaid', $invoice))
        ->assertSessionHas('success');

    $invoice->refresh();

    expect($invoice->status)->toBe('unpaid')
        ->and($invoice->paid_at)->toBeNull();
});

it('refuses to undo an invoice that was never marked paid', function () {
    $invoice = markInvoice(markClient());

    $this->actingAs(markUser())
        ->patch(route('pages.finance.invoices.unpaid', $invoice))
        ->assertSessionHas('error');

    expect($invoice->refresh()->status)->toBe('unpaid');
});

it('requires a signed-in operator', function () {
    $invoice = markInvoice(markClient());

    $this->patch(route('pages.finance.invoices.paid', $invoice), ['paid_on' => '2026-10-17'])
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------- what the page shows --

it('offers the marker on an open invoice, with the date already filled in', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-17 01:00:00'));
    $invoice = markInvoice(markClient());

    $this->actingAs(markUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertSee('Tandai lunas')
        ->assertSee('name="paid_on"', false)
        ->assertSee('value="2026-10-17"', false)
        ->assertDontSee('Batalkan lunas');
});

it('offers the way back instead of the marker once it is settled', function () {
    $invoice = markInvoice(markClient(), ['status' => 'paid', 'paid_at' => '2026-10-17']);

    $this->actingAs(markUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertSee('Batalkan lunas')
        ->assertDontSee('Tandai lunas');
});

it('offers neither on a cancelled invoice', function () {
    $invoice = markInvoice(markClient(), ['status' => 'cancelled']);

    $this->actingAs(markUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertDontSee('Tandai lunas')
        ->assertDontSee('Batalkan lunas');
});

// ------------------------------------------------- what the marker stops --

it('stops the remaining thresholds of a paid invoice from sending', function () {
    $recorder = markRecorder();
    $client = markClient();
    markSubscription($client);

    // H-30 for a renewal due 2026-10-30: the invoice is raised and reminded.
    Carbon::setTestNow(Carbon::parse('2026-09-30 09:00:00'));
    app(BillingRenewalService::class)->processDue();

    $invoice = Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->sole();

    expect(BillingReminderLog::count())->toBe(1)
        ->and($recorder->sent)->toHaveCount(1);

    // The operator sees the money arrive off-system and marks it here.
    $this->actingAs(markUser())->patch(route('pages.finance.invoices.paid', $invoice), ['paid_on' => '2026-09-30']);

    // H-7 and H-3 would both be reminder days if the bill were still open.
    Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00'));
    app(BillingRenewalService::class)->processDue();
    Carbon::setTestNow(Carbon::parse('2026-10-27 09:00:00'));
    app(BillingRenewalService::class)->processDue();

    expect(BillingReminderLog::count())->toBe(1)
        ->and($recorder->sent)->toHaveCount(1);
});

it('keeps rolling the subscription period after an invoice is marked paid', function () {
    markRecorder();
    $subscription = markSubscription(markClient());

    Carbon::setTestNow(Carbon::parse('2026-09-30 09:00:00'));
    app(BillingRenewalService::class)->processDue();

    $invoice = Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->sole();
    $this->actingAs(markUser())->patch(route('pages.finance.invoices.paid', $invoice), ['paid_on' => '2026-09-30']);

    // Periods follow the calendar, not the money: the roll happens on schedule
    // and the next year's invoice is raised on its own H-30.
    Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00'));
    app(BillingRenewalService::class)->processDue();

    expect($subscription->refresh()->next_renewal_date->format('Y-m-d'))->toBe('2027-10-30')
        ->and(Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->count())->toBe(1);
});
