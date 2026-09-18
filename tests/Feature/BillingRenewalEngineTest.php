<?php

use App\Models\BillingReminderLog;
use App\Models\BillingSubscription;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Project;
use App\Services\Billing\BillingClock;
use App\Services\Billing\BillingCycle;
use App\Services\Billing\BillingPaymentService;
use App\Services\Billing\BillingRenewalService;
use App\Services\Billing\LegacyInvoiceReminderService;
use App\Services\Billing\ReminderDispatcher;
use App\Services\Billing\ReminderPolicy;
use App\Services\WhatsAppService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

/**
 * Phase 2: the recurring billing engine.
 *
 * Nothing here sends anything. Delivery lives behind ReminderDispatcher, which
 * these tests replace with a recorder — so the schedule, the invoice and the
 * once-per-threshold guarantee can be exercised with no SMTP, no WhatsApp and
 * no network.
 *
 * The behaviour worth protecting is what happens when the scheduler runs twice:
 * a daily cron that is retried, or an operator running the command by hand,
 * must not produce a second invoice, a second reminder or a renewal date that
 * has jumped two periods.
 */
class RecordingDispatcher implements ReminderDispatcher
{
    /** @var array<int, array{invoice: int, days_before: int, recipient: string}> */
    public array $sent = [];

    public function __construct(public bool $delivers = true, public ?string $throwWith = null)
    {
    }

    public function send(Invoice $invoice, BillingReminderLog $log, string $recipient): bool
    {
        $this->sent[] = ['invoice' => $invoice->id, 'days_before' => $log->days_before, 'recipient' => $recipient];

        if ($this->throwWith) {
            throw new RuntimeException($this->throwWith);
        }

        return $this->delivers;
    }
}

function dispatcher(bool $delivers = true, ?string $throwWith = null): RecordingDispatcher
{
    $recorder = new RecordingDispatcher($delivers, $throwWith);
    app()->instance(ReminderDispatcher::class, $recorder);

    return $recorder;
}

function engine(): BillingRenewalService
{
    return app(BillingRenewalService::class);
}

function renewalClient(array $overrides = []): Client
{
    return Client::create(array_merge([
        'company_name' => 'PT ABC',
        'contact_name' => 'Budi',
        'email' => 'budi@abc.test',
    ], $overrides));
}

function subscription(Client $client, array $overrides = []): BillingSubscription
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

/** Runs the daily engine as if today were $date. */
function runOn(string $date): array
{
    Carbon::setTestNow(Carbon::parse($date . ' 09:00:00'));

    return engine()->processDue();
}

afterEach(fn () => Carbon::setTestNow());

// ---------------------------------------------------------------- policy ----

it('gives a yearly subscription a month of warning', function () {
    $policy = new ReminderPolicy();
    $yearly = new BillingSubscription(['billing_cycle' => BillingSubscription::CYCLE_YEARLY]);

    expect($policy->thresholdsFor($yearly))->toBe([30, 7, 3])
        ->and($policy->invoiceCreationThreshold($yearly))->toBe(30);
});

it('gives a monthly subscription a shorter run-up', function () {
    $policy = new ReminderPolicy();
    $monthly = new BillingSubscription(['billing_cycle' => BillingSubscription::CYCLE_MONTHLY]);

    // A 30-day notice on a 30-day cycle would arrive before the previous period
    // had finished.
    expect($policy->thresholdsFor($monthly))->toBe([7, 3, 1])
        ->and($policy->invoiceCreationThreshold($monthly))->toBe(7);
});

it('only acts on an exact threshold day', function () {
    $policy = new ReminderPolicy();
    $yearly = new BillingSubscription(['billing_cycle' => BillingSubscription::CYCLE_YEARLY]);

    expect($policy->thresholdDueOn($yearly, 30))->toBe(30)
        ->and($policy->thresholdDueOn($yearly, 29))->toBeNull()
        ->and($policy->thresholdDueOn($yearly, 8))->toBeNull()
        ->and($policy->thresholdDueOn($yearly, 3))->toBe(3);
});

// ----------------------------------------------------------------- yearly ---

it('raises one invoice at H-30 for a yearly service', function () {
    $recorder = dispatcher();
    subscription(renewalClient());

    $summary = runOn('2026-09-30'); // 30 days before 2026-10-30

    $invoice = Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->sole();

    expect($summary['invoices_created'])->toBe(1)
        ->and($invoice->invoice_number)->toBe('INV-2026-000001')
        ->and($invoice->amount)->toBe('2500000.00')
        ->and($invoice->due_date->toDateString())->toBe('2026-10-30')
        ->and($invoice->billing_period_start->toDateString())->toBe('2026-10-30')
        ->and($invoice->billing_period_end->toDateString())->toBe('2027-10-29')
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->itemsTotal())->toBe($invoice->amount)
        ->and($recorder->sent)->toHaveCount(1)
        ->and($recorder->sent[0]['days_before'])->toBe(30);
});

it('reuses the same invoice at H-7 and H-3 instead of raising another', function () {
    $recorder = dispatcher();
    subscription(renewalClient());

    runOn('2026-09-30'); // H-30
    runOn('2026-10-23'); // H-7
    runOn('2026-10-27'); // H-3

    expect(Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->count())->toBe(1);

    $invoice = Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->sole();

    expect($invoice->reminderLogs->pluck('days_before')->sort()->values()->all())->toBe([3, 7, 30])
        ->and($recorder->sent)->toHaveCount(3)
        // every reminder pointed at the same bill
        ->and(collect($recorder->sent)->pluck('invoice')->unique())->toHaveCount(1);
});

it('does nothing extra when the same day is processed twice', function () {
    $recorder = dispatcher();
    subscription(renewalClient());

    runOn('2026-09-30');
    $second = runOn('2026-09-30'); // cron retried, or someone ran it by hand

    expect(Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->count())->toBe(1)
        ->and(BillingReminderLog::count())->toBe(1)
        ->and($second['invoices_created'])->toBe(0)
        // the dispatcher was not asked to send a second time either
        ->and($recorder->sent)->toHaveCount(1);
});

// ---------------------------------------------------------------- monthly ---

it('raises a monthly invoice at H-7 and reuses it at H-3 and H-1', function () {
    $recorder = dispatcher();
    subscription(renewalClient(), [
        'name' => 'SEO Retainer',
        'service_type' => 'seo',
        'billing_cycle' => BillingSubscription::CYCLE_MONTHLY,
        'amount' => 3500000,
        'start_date' => '2026-09-30',
        'next_renewal_date' => '2026-10-30',
    ]);

    runOn('2026-10-23'); // H-7
    runOn('2026-10-27'); // H-3
    runOn('2026-10-29'); // H-1

    $invoice = Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->sole();

    expect(Invoice::count())->toBe(1)
        ->and($invoice->billing_period_end->toDateString())->toBe('2026-11-29')
        ->and($invoice->reminderLogs->pluck('days_before')->sort()->values()->all())->toBe([1, 3, 7])
        ->and($recorder->sent)->toHaveCount(3);
});

// ---------------------------------------------------------------- payment ---

it('records a payment, closes the invoice and advances a yearly renewal', function () {
    dispatcher();
    $subscription = subscription(renewalClient());
    runOn('2026-09-30');

    $invoice = Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->sole();
    $result = app(BillingPaymentService::class)->markPaid($invoice, ['method' => 'bank_transfer', 'reference' => 'TRF-1']);

    expect($result['already_paid'])->toBeFalse()
        ->and($result['renewal_advanced'])->toBeTrue()
        ->and(Payment::count())->toBe(1)
        ->and(Payment::first()->amount)->toBe('2500000.00')
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and($invoice->fresh()->paid_at)->not->toBeNull()
        ->and($subscription->fresh()->next_renewal_date->toDateString())->toBe('2027-10-30');
});

it('advances a monthly renewal by exactly one month', function () {
    dispatcher();
    $subscription = subscription(renewalClient(), [
        'billing_cycle' => BillingSubscription::CYCLE_MONTHLY,
        'start_date' => '2026-09-30',
        'next_renewal_date' => '2026-10-30',
    ]);
    runOn('2026-10-23');

    app(BillingPaymentService::class)->markPaid(Invoice::sole());

    expect($subscription->fresh()->next_renewal_date->toDateString())->toBe('2026-11-30');
});

it('does not advance the renewal twice when mark-paid is submitted twice', function () {
    dispatcher();
    $subscription = subscription(renewalClient());
    runOn('2026-09-30');

    $invoice = Invoice::sole();
    $payments = app(BillingPaymentService::class);

    $payments->markPaid($invoice);
    $second = $payments->markPaid($invoice->fresh());

    // A double-clicked button must not skip a whole year of billing.
    expect($second['already_paid'])->toBeTrue()
        ->and($second['renewal_advanced'])->toBeFalse()
        ->and(Payment::count())->toBe(1)
        ->and($subscription->fresh()->next_renewal_date->toDateString())->toBe('2027-10-30');
});

it('stops reminding once the invoice is paid', function () {
    $recorder = dispatcher();
    $sub = subscription(renewalClient());

    $summary30 = runOn('2026-09-30'); // H-30 raises and reminds
    app(BillingPaymentService::class)->markPaid(Invoice::sole()); // client pays at H-15

    runOn('2026-10-23'); // would have been H-7
    runOn('2026-10-27'); // would have been H-3

    // Paying advanced the cycle to 2027-10-30, so those dates are no longer
    // near a renewal at all — the old thresholds simply stop matching. The
    // client hears nothing more about a bill they have already settled.
    expect($summary30['reminders_sent'])->toBe(1)
        ->and($sub->fresh()->next_renewal_date->toDateString())->toBe('2027-10-30')
        ->and(BillingReminderLog::count())->toBe(1)
        ->and($recorder->sent)->toHaveCount(1)
        ->and(Invoice::count())->toBe(1);
});

it('skips a threshold whose invoice is already settled', function () {
    $recorder = dispatcher();
    $sub = subscription(renewalClient());

    runOn('2026-09-30'); // H-30 raises and reminds

    // Settled outside the payment service — an import, a correction, or an
    // operator editing the row. The renewal date has NOT moved, so the later
    // thresholds still match and the engine has to refuse them on its own.
    Invoice::sole()->update(['status' => 'paid', 'paid_at' => today()]);

    $summary7 = runOn('2026-10-23');
    $summary3 = runOn('2026-10-27');

    expect($summary7['skipped_paid'])->toBe(1)
        ->and($summary3['skipped_paid'])->toBe(1)
        // no H-7 or H-3 log was written, and nothing was dispatched
        ->and(BillingReminderLog::count())->toBe(1)
        ->and($recorder->sent)->toHaveCount(1)
        ->and($sub->fresh()->next_renewal_date->toDateString())->toBe('2026-10-30');
});

it('refuses a payment of zero', function () {
    dispatcher();
    subscription(renewalClient());
    runOn('2026-09-30');

    expect(fn () => app(BillingPaymentService::class)->markPaid(Invoice::sole(), ['amount' => 0]))
        ->toThrow(InvalidArgumentException::class);

    expect(Invoice::sole()->status)->toBe('unpaid')
        ->and(Payment::count())->toBe(0);
});

// ----------------------------------------------------------------- legacy ---

it('leaves project invoices to the legacy path and renewals to the engine', function () {
    Mail::fake();
    dispatcher();

    $client = renewalClient(['whatsapp' => null]);
    $project = Project::create(['client_id' => $client->id, 'name' => 'Web ABC', 'client_name' => 'PT ABC', 'status' => 'request']);

    Carbon::setTestNow(Carbon::parse('2026-09-30 09:00:00'));

    $projectInvoice = Invoice::create([
        'project_id' => $project->id,
        'invoice_number' => 'INV-ABC-1001',
        'type' => 'dp',
        'amount' => 5000000,
        'due_date' => '2026-10-01', // within the legacy 3-day window
    ]);

    subscription($client);
    engine()->processDue(); // raises the renewal invoice at H-30

    $renewal = Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->sole();
    $sent = app(LegacyInvoiceReminderService::class)->sendDue();

    // The legacy path picked up the project invoice and ignored the renewal —
    // without that filter the client would get two reminders for one bill.
    expect($sent['legacy_reminders_sent'])->toBe(1)
        ->and($projectInvoice->fresh()->last_reminder_sent_at)->not->toBeNull()
        ->and($renewal->fresh()->last_reminder_sent_at)->toBeNull();

    Mail::assertSentCount(1);
});

it('keeps a project invoice working exactly as before', function () {
    Mail::fake();
    $client = renewalClient();
    $project = Project::create(['client_id' => $client->id, 'name' => 'Web ABC', 'client_name' => 'PT ABC', 'status' => 'request']);

    $invoice = Invoice::create([
        'project_id' => $project->id,
        'invoice_number' => 'INV-ABC-1002',
        'type' => 'pelunasan',
        'amount' => 5000000,
        'due_date' => today(),
    ]);

    expect($invoice->purpose)->toBe(Invoice::PURPOSE_PROJECT)
        ->and(app(LegacyInvoiceReminderService::class)->remind($invoice))->toBeTrue();

    // Marking a project invoice paid records the money but moves no renewal
    // date, because there is no subscription behind it.
    $result = app(BillingPaymentService::class)->markPaid($invoice->fresh());

    expect($result['renewal_advanced'])->toBeFalse()
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and(Payment::count())->toBe(1);
});

// ------------------------------------------------------------------ flags ---

it('ignores a subscription that is not active', function () {
    dispatcher();
    subscription(renewalClient(), ['status' => 'paused']);
    subscription(renewalClient(['company_name' => 'PT Batal', 'email' => 'b@b.test']), ['status' => 'cancelled']);
    subscription(renewalClient(['company_name' => 'PT Habis', 'email' => 'c@c.test']), ['status' => 'expired']);

    $summary = runOn('2026-09-30');

    expect($summary['subscriptions_checked'])->toBe(0)
        ->and(Invoice::count())->toBe(0)
        ->and(BillingReminderLog::count())->toBe(0);
});

it('raises no invoice when auto_invoice is off', function () {
    $recorder = dispatcher();
    subscription(renewalClient(), ['auto_invoice' => false]);

    $summary = runOn('2026-09-30');

    // A reminder must never be the reason an invoice appears.
    expect($summary['invoices_created'])->toBe(0)
        ->and(Invoice::count())->toBe(0)
        ->and($recorder->sent)->toBeEmpty();
});

it('still reminds about a manually raised invoice when auto_invoice is off', function () {
    $recorder = dispatcher();
    $client = renewalClient();
    $sub = subscription($client, ['auto_invoice' => false]);

    // Someone raised this by hand for the same period.
    Invoice::create([
        'client_id' => $client->id,
        'billing_subscription_id' => $sub->id,
        'invoice_number' => 'INV-2026-000900',
        'type' => 'full',
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'amount' => 2500000,
        'due_date' => '2026-10-30',
        'billing_period_start' => '2026-10-30',
        'billing_period_end' => '2027-10-29',
    ]);

    runOn('2026-09-30');

    expect(Invoice::count())->toBe(1)
        ->and($recorder->sent)->toHaveCount(1);
});

it('raises the invoice but sends nothing when auto_reminder is off', function () {
    $recorder = dispatcher();
    subscription(renewalClient(), ['auto_reminder' => false]);

    $summary = runOn('2026-09-30');

    expect($summary['invoices_created'])->toBe(1)
        ->and(BillingReminderLog::count())->toBe(0)
        ->and($recorder->sent)->toBeEmpty();
});

// ------------------------------------------------------------ idempotency ---

it('keeps one invoice per billing period however often it runs', function () {
    dispatcher();
    subscription(renewalClient());

    foreach (['2026-09-30', '2026-09-30', '2026-10-23', '2026-10-23', '2026-10-27'] as $day) {
        runOn($day);
    }

    expect(Invoice::count())->toBe(1)
        ->and(BillingReminderLog::count())->toBe(3);
});

it('records a failed delivery without losing the claim on that threshold', function () {
    $recorder = dispatcher(throwWith: 'SMTP refused the recipient');
    subscription(renewalClient());

    runOn('2026-09-30');

    $log = BillingReminderLog::sole();

    expect($log->status)->toBe(BillingReminderLog::FAILED)
        ->and($log->attempts)->toBe(1)
        ->and($log->error_message)->toContain('SMTP refused')
        ->and($log->canRetry())->toBeTrue();

    // A retry updates the same row rather than creating a second one.
    runOn('2026-09-30');

    expect(BillingReminderLog::count())->toBe(1)
        ->and(BillingReminderLog::sole()->attempts)->toBe(2)
        ->and($recorder->sent)->toHaveCount(2);
});

it('stops retrying a reminder that keeps failing', function () {
    dispatcher(throwWith: 'permanently bad address');
    subscription(renewalClient());

    for ($i = 0; $i < 5; $i++) {
        runOn('2026-09-30');
    }

    $log = BillingReminderLog::sole();

    expect($log->attempts)->toBe(BillingReminderLog::MAX_ATTEMPTS)
        ->and($log->canRetry())->toBeFalse();
});

it('marks a reminder failed when the client has no address at all', function () {
    dispatcher();
    $client = renewalClient(['email' => null]);
    subscription($client);

    runOn('2026-09-30');

    $log = BillingReminderLog::sole();

    expect($log->status)->toBe(BillingReminderLog::FAILED)
        ->and($log->error_message)->toContain('email');
});

it('sends the reminder to the billing address when one is set', function () {
    $recorder = dispatcher();
    subscription(renewalClient(['billing_email' => 'finance@abc.test']));

    runOn('2026-09-30');

    expect($recorder->sent[0]['recipient'])->toBe('finance@abc.test')
        ->and(BillingReminderLog::sole()->recipient)->toBe('finance@abc.test');
});

// ------------------------------------------------------------------ dates ---

it('does not let a month-end monthly subscription drift', function () {
    $cycle = new BillingCycle();
    $sub = new BillingSubscription([
        'billing_cycle' => BillingSubscription::CYCLE_MONTHLY,
        'start_date' => '2026-01-31',
        'next_renewal_date' => '2026-01-31',
    ]);

    // January 31 -> February 28 (not March 3, which a naive +1 month gives)
    $sub->next_renewal_date = Carbon::parse('2026-01-31');
    expect($cycle->nextRenewalAfter($sub)->toDateString())->toBe('2026-02-28');

    // and then back to the 31st, rather than staying on the 28th forever
    $sub->next_renewal_date = Carbon::parse('2026-02-28');
    expect($cycle->nextRenewalAfter($sub)->toDateString())->toBe('2026-03-31');

    $sub->next_renewal_date = Carbon::parse('2026-03-31');
    expect($cycle->nextRenewalAfter($sub)->toDateString())->toBe('2026-04-30');
});

it('handles a yearly subscription that started on a leap day', function () {
    $cycle = new BillingCycle();
    $sub = new BillingSubscription([
        'billing_cycle' => BillingSubscription::CYCLE_YEARLY,
        'start_date' => '2024-02-29',
        'next_renewal_date' => '2024-02-29',
    ]);

    $sub->next_renewal_date = Carbon::parse('2024-02-29');
    expect($cycle->nextRenewalAfter($sub)->toDateString())->toBe('2025-02-28');

    // 2028 is a leap year again, and the anchor brings the 29th back
    $sub->next_renewal_date = Carbon::parse('2027-02-28');
    expect($cycle->nextRenewalAfter($sub)->toDateString())->toBe('2028-02-29');
});

it('counts thresholds in calendar days, not hours', function () {
    $recorder = dispatcher();
    subscription(renewalClient());

    // Late in the evening of H-7 — stated in the business timezone, which is
    // what a calendar day means here — is still H-7. An hours-based comparison
    // would read this as 6.4 days and skip the reminder entirely.
    Carbon::setTestNow(Carbon::parse('2026-10-23 23:50:00', config('billing.timezone')));
    engine()->processDue();

    expect($recorder->sent)->toHaveCount(1)
        ->and($recorder->sent[0]['days_before'])->toBe(7);
});

// ------------------------------------------------------------------ batch ---

it('keeps processing the batch when one subscription fails', function () {
    dispatcher();

    $good = subscription(renewalClient(['company_name' => 'PT Baik', 'email' => 'baik@abc.test']));
    // A subscription whose client row is gone: reaching for a billing address
    // is where this one breaks.
    $broken = subscription(renewalClient(['company_name' => 'PT Rusak', 'email' => 'rusak@abc.test']));
    $alsoGood = subscription(renewalClient(['company_name' => 'PT Juga', 'email' => 'juga@abc.test']));

    Client::whereKey($broken->client_id)->delete();

    $summary = runOn('2026-09-30');

    // The deleted client took its own subscription with it (cascade), so two
    // remain and both were billed — the batch did not stop.
    expect($summary['invoices_created'])->toBe(2)
        ->and(Invoice::count())->toBe(2)
        ->and(Invoice::pluck('client_id')->sort()->values()->all())
        ->toBe([$good->client_id, $alsoGood->client_id]);
});

it('numbers invoices sequentially and readably', function () {
    dispatcher();
    subscription(renewalClient(['company_name' => 'A', 'email' => 'a@a.test']));
    subscription(renewalClient(['company_name' => 'B', 'email' => 'b@b.test']));
    subscription(renewalClient(['company_name' => 'C', 'email' => 'c@c.test']));

    runOn('2026-09-30');

    expect(Invoice::orderBy('id')->pluck('invoice_number')->all())
        ->toBe(['INV-2026-000001', 'INV-2026-000002', 'INV-2026-000003']);
});

it('leaves legacy invoice numbers alone when numbering a renewal', function () {
    dispatcher();
    $client = renewalClient();
    $project = Project::create(['client_id' => $client->id, 'name' => 'Web', 'client_name' => 'PT ABC', 'status' => 'request']);

    // The old random format must not confuse the sequence.
    Invoice::create([
        'project_id' => $project->id,
        'invoice_number' => 'INV-XYZ-4821',
        'type' => 'dp',
        'amount' => 1000000,
        'due_date' => '2026-12-01',
    ]);

    subscription($client);
    runOn('2026-09-30');

    expect(Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->value('invoice_number'))
        ->toBe('INV-2026-000001');
});

it('snapshots the price so a later increase does not rewrite history', function () {
    dispatcher();
    $sub = subscription(renewalClient());
    runOn('2026-09-30');

    $invoice = Invoice::sole();
    $sub->update(['amount' => 3000000]); // price rises for the next period

    expect($invoice->fresh()->amount)->toBe('2500000.00')
        ->and(InvoiceItem::sole()->unit_price)->toBe('2500000.00');
});

// --------------------------------------------------------------- catch-up ---

it('still raises the yearly invoice when the H-30 run was missed', function () {
    $recorder = dispatcher();
    subscription(renewalClient());

    // The server was down on H-30; the scheduler comes back on H-29.
    $summary = runOn('2026-10-01');

    $invoice = Invoice::where('purpose', Invoice::PURPOSE_RENEWAL)->sole();

    expect($summary['invoices_created'])->toBe(1)
        ->and($invoice->billing_period_start->toDateString())->toBe('2026-10-30')
        // The invoice is owed from H-30 onward, so a late run still raises it.
        ->and($invoice->due_date->toDateString())->toBe('2026-10-30');

    // But the reminder is a promise about a day. H-30 did not go out and is not
    // backfilled — no log claims it was sent, and nothing was dispatched.
    expect(BillingReminderLog::count())->toBe(0)
        ->and($recorder->sent)->toBeEmpty();
});

it('resumes the normal reminder schedule after a catch-up', function () {
    $recorder = dispatcher();
    subscription(renewalClient());

    runOn('2026-10-01'); // H-29 catch-up: invoice only
    runOn('2026-10-23'); // H-7
    runOn('2026-10-27'); // H-3

    $invoice = Invoice::sole();

    expect(Invoice::count())->toBe(1)
        // H-30 is absent, the later two are present and point at the same bill.
        ->and($invoice->reminderLogs->pluck('days_before')->sort()->values()->all())->toBe([3, 7])
        ->and($recorder->sent)->toHaveCount(2);
});

it('still raises the monthly invoice when the H-7 run was missed', function () {
    $recorder = dispatcher();
    subscription(renewalClient(), [
        'billing_cycle' => BillingSubscription::CYCLE_MONTHLY,
        'amount' => 3500000,
        'start_date' => '2026-09-30',
        'next_renewal_date' => '2026-10-30',
    ]);

    $summary = runOn('2026-10-24'); // H-6

    expect($summary['invoices_created'])->toBe(1)
        ->and(BillingReminderLog::count())->toBe(0)
        ->and($recorder->sent)->toBeEmpty();

    runOn('2026-10-27'); // H-3
    runOn('2026-10-29'); // H-1

    expect(Invoice::count())->toBe(1)
        ->and(Invoice::sole()->reminderLogs->pluck('days_before')->sort()->values()->all())->toBe([1, 3])
        ->and($recorder->sent)->toHaveCount(2);
});

it('raises only one invoice however many catch-up runs happen', function () {
    dispatcher();
    subscription(renewalClient());

    foreach (['2026-10-01', '2026-10-05', '2026-10-12', '2026-10-20'] as $day) {
        runOn($day);
    }

    expect(Invoice::count())->toBe(1)
        ->and(BillingReminderLog::count())->toBe(0);
});

it('raises the invoice even after the renewal date has passed unpaid', function () {
    dispatcher();
    subscription(renewalClient());

    // Nobody ran the scheduler for weeks and the renewal date came and went.
    // The debt is still owed, so the invoice appears — visibly overdue.
    $summary = runOn('2026-11-05');

    $invoice = Invoice::sole();

    expect($summary['invoices_created'])->toBe(1)
        ->and($invoice->isOverdue())->toBeTrue()
        ->and(BillingReminderLog::count())->toBe(0);
});

it('does nothing at all before the creation threshold', function () {
    dispatcher();
    subscription(renewalClient());

    $summary = runOn('2026-09-15'); // H-45, still too early

    expect($summary['invoices_created'])->toBe(0)
        ->and(Invoice::count())->toBe(0)
        ->and(BillingReminderLog::count())->toBe(0);
});

it('does not catch up an invoice when auto_invoice is off', function () {
    dispatcher();
    subscription(renewalClient(), ['auto_invoice' => false]);

    runOn('2026-10-01');

    expect(Invoice::count())->toBe(0);
});

// -------------------------------------------------------------- timezone ---

it('uses the billing timezone for calendar days while the app stays on UTC', function () {
    $recorder = dispatcher();
    subscription(renewalClient());

    // The application itself is UTC — created_at, queues and logs stay
    // comparable. Only billing's own date arithmetic moves.
    config(['app.timezone' => 'UTC', 'billing.timezone' => 'Asia/Makassar']);

    // 16:30 UTC is already 00:30 the next day in Makassar. Read as a UTC
    // instant this is H-8 and nothing fires; read as a business date it is
    // H-7 and the reminder is due.
    Carbon::setTestNow(Carbon::parse('2026-10-22 16:30:00', 'UTC'));

    $clock = app(BillingClock::class);

    expect(config('app.timezone'))->toBe('UTC')
        ->and($clock->timezone())->toBe('Asia/Makassar')
        ->and($clock->today()->toDateString())->toBe('2026-10-23')
        // and the UTC view of the very same instant is still the day before
        ->and(Carbon::now('UTC')->toDateString())->toBe('2026-10-22');

    engine()->processDue();

    expect($recorder->sent)->toHaveCount(1)
        ->and($recorder->sent[0]['days_before'])->toBe(7)
        ->and(BillingReminderLog::sole()->days_before)->toBe(7)
        // the log is dated by the business day too
        ->and(BillingReminderLog::sole()->scheduled_for->toDateString())->toBe('2026-10-23');
});

it('reads the same instant as a different day when billing runs on UTC', function () {
    $recorder = dispatcher();
    subscription(renewalClient());

    // The mirror image: with billing on UTC the same instant is H-8, so the
    // H-7 reminder does not fire. This is what the separate zone prevents.
    config(['billing.timezone' => 'UTC']);
    Carbon::setTestNow(Carbon::parse('2026-10-22 16:30:00', 'UTC'));

    engine()->processDue();

    expect($recorder->sent)->toBeEmpty()
        ->and(BillingReminderLog::count())->toBe(0);
});

it('schedules the daily billing run in the billing timezone', function () {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    // 09:00 in Asia/Makassar is 01:00 UTC; the scheduler normalises the
    // expression, which is exactly the proof that the timezone was applied.
    expect($output)->toContain('billing:process-renewals')
        ->toContain('0 1 * * *')
        // and nothing else is scheduled that could touch the same invoices
        ->not->toContain('invoices:send-reminders');
});

it('reads its timezone from configuration rather than hardcoding one', function () {
    // Billing dates are calendar dates, so the zone the business operates in is
    // deployment configuration, not something a service should decide.
    expect(file_get_contents(config_path('app.php')))
        ->toContain("env('APP_TIMEZONE'")
        ->and(file_get_contents(config_path('billing.php')))
        ->toContain("env('BILLING_TIMEZONE'");

    foreach (['ReminderPolicy', 'BillingRenewalService', 'BillingCycle', 'BillingPaymentService', 'BillingClock'] as $service) {
        expect(file_get_contents(app_path("Services/Billing/{$service}.php")))
            ->not->toContain('setTimezone(')
            ->not->toContain('Asia/');
    }
});
