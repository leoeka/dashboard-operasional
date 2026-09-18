<?php

use App\Jobs\SendBillingReminderEmail;
use App\Mail\BillingRenewalReminderMail;
use App\Mail\InvoiceReminderMail;
use App\Models\BillingReminderLog;
use App\Models\BillingSubscription;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\Billing\BillingRenewalService;
use App\Services\Billing\MailReminderDispatcher;
use App\Services\Billing\ReminderDispatcher;
use App\Services\Billing\LegacyInvoiceReminderService;
use App\Services\WhatsAppService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Phase 3: turning a claimed reminder into an actual email.
 *
 * Phase 2 decided WHICH reminders are due and claimed each one exactly once;
 * none of that is retested here. What matters now is the delivery half: that
 * the scheduler hands off to the queue instead of waiting on SMTP, that the
 * worker re-checks the world before sending, and that a job delivered twice —
 * which queues do — sends one email.
 *
 * Nothing reaches a real mail host: Mail::fake() and Queue::fake() throughout.
 */
function emailClient(array $overrides = []): Client
{
    return Client::create(array_merge([
        'company_name' => 'PT ABC',
        'contact_name' => 'Budi',
        'email' => 'budi@abc.test',
    ], $overrides));
}

function emailSubscription(Client $client, array $overrides = []): BillingSubscription
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

/** Runs the daily billing engine as if today were $date. */
function billingRunOn(string $date): void
{
    Carbon::setTestNow(Carbon::parse($date . ' 09:00:00', config('billing.timezone')));
    app(BillingRenewalService::class)->processDue();
}

/** Runs the daily billing engine and hands back its summary. */
function billingSummaryOn(string $date): array
{
    Carbon::setTestNow(Carbon::parse($date . ' 09:00:00', config('billing.timezone')));

    return app(BillingRenewalService::class)->processDue();
}

/** Runs the queued job for the one reminder that exists. */
function deliverReminder(?BillingReminderLog $log = null): void
{
    $log ??= BillingReminderLog::sole();

    app()->call([new SendBillingReminderEmail($log->id), 'handle']);
}

/**
 * Mail::fake() captures a Mailable before it is built, so its subject is not
 * populated yet. Building it is what the mailer would do on the way out.
 */
function subjectOf(BillingRenewalReminderMail $mail): string
{
    return (string) $mail->build()->subject;
}

/**
 * The test environment runs the queue on `sync`, which would execute the job at
 * the moment of dispatch — the exact opposite of what production does, and it
 * would make every "the world changed before the worker ran" test impossible to
 * write. Faking the queue restores the real shape: dispatch now, deliver later,
 * on purpose.
 */
beforeEach(fn () => Queue::fake());

afterEach(fn () => Carbon::setTestNow());

// ------------------------------------------------------------------ queue ---

it('queues the email instead of making the scheduler wait for SMTP', function () {
    Queue::fake();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30'); // H-30

    // The command finishes without a mail host being involved at all.
    Queue::assertPushed(SendBillingReminderEmail::class, 1);

    $log = BillingReminderLog::sole();

    // Still pending: enqueued is not delivered, and the record must not claim
    // otherwise until the worker has actually sent it.
    expect($log->status)->toBe(BillingReminderLog::PENDING)
        ->and($log->sent_at)->toBeNull()
        ->and($log->attempts)->toBe(1);
});

it('carries only the reminder id across the queue', function () {
    Queue::fake();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');

    Queue::assertPushed(SendBillingReminderEmail::class, function ($job) {
        // Minutes may pass before a worker picks this up, so everything else is
        // reloaded then rather than frozen now.
        return $job->reminderLogId === BillingReminderLog::sole()->id;
    });
});

it('runs the whole billing command without a live mail host', function () {
    Mail::fake();
    Queue::fake();
    emailSubscription(emailClient());

    Carbon::setTestNow(Carbon::parse('2026-09-30 09:00:00', config('billing.timezone')));
    $exit = Illuminate\Support\Facades\Artisan::call('billing:process-renewals');

    expect($exit)->toBe(0);
    Mail::assertNothingSent();
});

// -------------------------------------------------------------- thresholds --

it('sends one email per yearly threshold', function () {
    Mail::fake();
    emailSubscription(emailClient());

    foreach (['2026-09-30' => 30, '2026-10-23' => 7, '2026-10-27' => 3] as $day => $threshold) {
        billingRunOn($day);
        deliverReminder(BillingReminderLog::where('days_before', $threshold)->sole());
    }

    Mail::assertSent(BillingRenewalReminderMail::class, 3);

    expect(BillingReminderLog::where('status', BillingReminderLog::SENT)->count())->toBe(3)
        ->and(BillingReminderLog::pluck('days_before')->sort()->values()->all())->toBe([3, 7, 30]);
});

it('sends one email per monthly threshold', function () {
    Mail::fake();
    emailSubscription(emailClient(), [
        'name' => 'SEO Retainer',
        'service_type' => 'seo',
        'billing_cycle' => BillingSubscription::CYCLE_MONTHLY,
        'amount' => 3500000,
        'start_date' => '2026-09-30',
        'next_renewal_date' => '2026-10-30',
    ]);

    foreach (['2026-10-23' => 7, '2026-10-27' => 3, '2026-10-29' => 1] as $day => $threshold) {
        billingRunOn($day);
        deliverReminder(BillingReminderLog::where('days_before', $threshold)->sole());
    }

    Mail::assertSent(BillingRenewalReminderMail::class, 3);
    expect(BillingReminderLog::pluck('days_before')->sort()->values()->all())->toBe([1, 3, 7]);
});

it('sends one email on the day the invoice is raised, not two', function () {
    Mail::fake();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30'); // H-30 raises the invoice AND is the first notice
    deliverReminder();

    // The renewal notice and the invoice notification are the same message.
    Mail::assertSent(BillingRenewalReminderMail::class, 1);
    Mail::assertSentCount(1);
});

// -------------------------------------------------------------- recipient ---

it('prefers the billing address over the main contact', function () {
    Mail::fake();
    emailSubscription(emailClient(['billing_email' => 'finance@abc.test']));

    billingRunOn('2026-09-30');
    deliverReminder();

    Mail::assertSent(BillingRenewalReminderMail::class, fn ($mail) => $mail->hasTo('finance@abc.test'));
    expect(BillingReminderLog::sole()->recipient)->toBe('finance@abc.test');
});

it('falls back to the client main email', function () {
    Mail::fake();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');
    deliverReminder();

    Mail::assertSent(BillingRenewalReminderMail::class, fn ($mail) => $mail->hasTo('budi@abc.test'));
});

it('fails safely when the client has no address at all', function () {
    Mail::fake();
    emailSubscription(emailClient(['email' => null]));

    billingRunOn('2026-09-30');

    $log = BillingReminderLog::sole();

    // The engine catches this before it ever reaches the queue, and the rest of
    // the batch is unaffected.
    expect($log->status)->toBe(BillingReminderLog::FAILED)
        ->and($log->error_message)->toContain('email');

    Mail::assertNothingSent();
});

// ---------------------------------------------------------------- content ---

it('writes a subject that says what the email is about', function () {
    Mail::fake();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30'); // H-30, the first notice
    deliverReminder();
    billingRunOn('2026-10-23'); // H-7
    deliverReminder(BillingReminderLog::where('days_before', 7)->sole());
    billingRunOn('2026-10-27'); // H-3
    deliverReminder(BillingReminderLog::where('days_before', 3)->sole());

    $subjects = collect(Mail::sent(BillingRenewalReminderMail::class))
        ->map(fn ($mail) => subjectOf($mail))->values();

    expect($subjects[0])->toContain('Pemberitahuan Perpanjangan')
        ->toContain('Hosting + Domain')
        ->toContain('INV-2026-000001')
        ->and($subjects[1])->toContain('Jatuh Tempo 7 Hari Lagi')
        ->and($subjects[2])->toContain('Jatuh Tempo 3 Hari Lagi');
});

it('says "besok" on the last monthly threshold', function () {
    Mail::fake();
    emailSubscription(emailClient(), [
        'name' => 'SEO Retainer',
        'billing_cycle' => BillingSubscription::CYCLE_MONTHLY,
        'start_date' => '2026-09-30',
        'next_renewal_date' => '2026-10-30',
    ]);

    billingRunOn('2026-10-23');
    billingRunOn('2026-10-29'); // H-1
    deliverReminder(BillingReminderLog::where('days_before', 1)->sole());

    $mail = collect(Mail::sent(BillingRenewalReminderMail::class))
        ->first(fn ($m) => str_contains(subjectOf($m), 'Besok'));

    expect($mail)->not->toBeNull()
        ->and(subjectOf($mail))->toContain('Besok');
});

it('puts the invoice detail a client needs in the body', function () {
    Mail::fake();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');
    deliverReminder();

    $rendered = collect(Mail::sent(BillingRenewalReminderMail::class))->first()->render();

    expect($rendered)
        ->toContain('INV-2026-000001')
        ->toContain('Hosting + Domain')
        ->toContain('Rp 2.500.000')        // formatted for a human, stored as a number
        ->toContain('30 Oct 2026')         // due date
        ->toContain('Tahunan')             // billing cycle
        ->toContain('Budi')                // billing name, falling back to contact
        // no invented payment link while there is no client portal
        ->not->toContain('href="http');
});

it('shows each line when an invoice has several', function () {
    Mail::fake();
    $client = emailClient();
    $sub = emailSubscription($client);

    billingRunOn('2026-09-30');

    $invoice = Invoice::sole();
    $invoice->items()->create(['description' => 'Domain abc.com', 'quantity' => 1, 'unit_price' => 300000, 'total' => 300000, 'position' => 1]);

    deliverReminder();

    expect(collect(Mail::sent(BillingRenewalReminderMail::class))->first()->render())
        ->toContain('Domain abc.com')
        ->toContain('Rincian');
});

// ------------------------------------------------------------ idempotency ---

it('sends once even if the queue delivers the job twice', function () {
    Mail::fake();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');

    deliverReminder();
    deliverReminder(); // at-least-once delivery is normal for queues

    Mail::assertSent(BillingRenewalReminderMail::class, 1);
    expect(BillingReminderLog::sole()->status)->toBe(BillingReminderLog::SENT);
});

it('does nothing when the reminder was already sent', function () {
    Mail::fake();
    emailSubscription(emailClient());
    billingRunOn('2026-09-30');

    $log = BillingReminderLog::sole();
    $log->update(['status' => BillingReminderLog::SENT, 'sent_at' => now()]);

    deliverReminder($log);

    Mail::assertNothingSent();
});

it('retries the same reminder row rather than creating another', function () {
    Mail::fake();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection could not be established'));
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');
    deliverReminder();

    $log = BillingReminderLog::sole();
    expect($log->status)->toBe(BillingReminderLog::FAILED)
        ->and($log->attempts)->toBe(1);

    // The next daily run picks the failed row back up — same row, not a second.
    billingRunOn('2026-09-30');

    expect(BillingReminderLog::count())->toBe(1)
        ->and(BillingReminderLog::sole()->attempts)->toBe(2);
});

it('stops re-queueing a reminder that has used up its attempts', function () {
    Queue::fake();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');

    BillingReminderLog::sole()->update([
        'status' => BillingReminderLog::FAILED,
        'attempts' => BillingReminderLog::MAX_ATTEMPTS,
    ]);

    Queue::fake(); // reset the recorder
    billingRunOn('2026-09-30');

    Queue::assertNothingPushed();
    expect(BillingReminderLog::sole()->attempts)->toBe(BillingReminderLog::MAX_ATTEMPTS);
});

// -------------------------------------------------------- state changed ----

it('does not email an invoice that was paid before the worker ran', function () {
    Mail::fake();
    $client = emailClient();
    emailSubscription($client);

    billingRunOn('2026-09-30');

    // Paid in the minutes between the scheduler and the worker.
    Invoice::sole()->update(['status' => 'paid', 'paid_at' => today()]);

    deliverReminder();

    Mail::assertNothingSent();

    $log = BillingReminderLog::sole();

    // Left pending with the reason: 'sent' would be untrue, and 'failed' would
    // invite the next run to retry something that can only be cancelled again.
    expect($log->status)->toBe(BillingReminderLog::PENDING)
        ->and($log->sent_at)->toBeNull()
        ->and($log->error_message)->toContain('sudah lunas');
});

it('does not email when the subscription was paused before the worker ran', function () {
    Mail::fake();
    $sub = emailSubscription(emailClient());

    billingRunOn('2026-09-30');
    $sub->update(['status' => 'paused']);

    deliverReminder();

    Mail::assertNothingSent();
    expect(BillingReminderLog::sole()->error_message)->toContain('paused');
});

it('does not email when the subscription was cancelled before the worker ran', function () {
    Mail::fake();
    $sub = emailSubscription(emailClient());

    billingRunOn('2026-09-30');
    $sub->update(['status' => 'cancelled']);

    deliverReminder();

    Mail::assertNothingSent();
    expect(BillingReminderLog::sole()->error_message)->toContain('cancelled');
});

// ---------------------------------------------------------------- failure ---

it('marks the reminder failed when the mail host refuses it', function () {
    Mail::fake();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('535 authentication failed'));
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');
    deliverReminder();

    $log = BillingReminderLog::sole();

    expect($log->status)->toBe(BillingReminderLog::FAILED)
        ->and($log->error_message)->toContain('535')
        ->and($log->sent_at)->toBeNull();
});

it('never stores a credential in the failure message', function () {
    Mail::fake();
    Mail::shouldReceive('to')->andThrow(new RuntimeException(
        'SMTP refused: AUTH LOGIN Authorization: Bearer sk-ant-SECRET123456 failed'
    ));
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');
    deliverReminder();

    expect(BillingReminderLog::sole()->error_message)
        ->not->toContain('sk-ant-SECRET123456')
        ->toContain('[redacted]');
});

// ----------------------------------------------------------------- legacy ---

it('leaves the legacy project invoice email untouched', function () {
    Mail::fake();
    $client = emailClient(['whatsapp' => null]);
    $project = Project::create(['client_id' => $client->id, 'name' => 'Web ABC', 'client_name' => 'PT ABC', 'status' => 'request']);

    $invoice = Invoice::create([
        'project_id' => $project->id,
        'invoice_number' => 'INV-ABC-1001',
        'type' => 'dp',
        'amount' => 5000000,
        'due_date' => today(),
    ]);

    app(LegacyInvoiceReminderService::class)->remind($invoice);

    // Still the original Mailable, not the renewal one.
    Mail::assertSent(InvoiceReminderMail::class, 1);
    Mail::assertNotSent(BillingRenewalReminderMail::class);
});

it('still sends WhatsApp for a legacy project invoice', function () {
    Mail::fake();

    $whatsapp = Mockery::mock(WhatsAppService::class);
    $whatsapp->shouldReceive('send')->once()->with('628111', Mockery::type('string'));
    app()->instance(WhatsAppService::class, $whatsapp);

    $client = emailClient(['whatsapp' => '628111']);
    $project = Project::create(['client_id' => $client->id, 'name' => 'Web ABC', 'client_name' => 'PT ABC', 'status' => 'request']);

    $invoice = Invoice::create([
        'project_id' => $project->id,
        'invoice_number' => 'INV-ABC-1002',
        'type' => 'full',
        'amount' => 5000000,
        'due_date' => today(),
    ]);

    app(LegacyInvoiceReminderService::class)->remind($invoice);
});

it('never sends WhatsApp for a recurring renewal', function () {
    Mail::fake();

    $whatsapp = Mockery::mock(WhatsAppService::class);
    $whatsapp->shouldNotReceive('send');
    app()->instance(WhatsAppService::class, $whatsapp);

    emailSubscription(emailClient(['whatsapp' => '628111']));

    billingRunOn('2026-09-30');
    deliverReminder();

    // Recurring billing is email only; the dispatcher has no WhatsApp path.
    Mail::assertSent(BillingRenewalReminderMail::class, 1);

    // The mock above is the behavioural proof; this guards the structure, and
    // matches an actual import rather than the comment that explains the
    // deliberate absence.
    expect(file_get_contents(app_path('Services/Billing/MailReminderDispatcher.php')))
        ->not->toContain('use App' . chr(92) . 'Services' . chr(92) . 'WhatsAppService')
        ->and(file_get_contents(app_path('Jobs/SendBillingReminderEmail.php')))
        ->not->toContain('use App' . chr(92) . 'Services' . chr(92) . 'WhatsAppService');
});

// --------------------------------------------------------------- timezone ---

it('dates the email from the billing business day while the app stays on UTC', function () {
    Mail::fake();
    config(['app.timezone' => 'UTC', 'billing.timezone' => 'Asia/Makassar']);
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');

    // 16:30 UTC is already 23 October in Makassar: the email must say 7 days,
    // the same number the schedule used to decide to send it.
    Carbon::setTestNow(Carbon::parse('2026-10-22 16:30:00', 'UTC'));
    app(BillingRenewalService::class)->processDue();
    deliverReminder(BillingReminderLog::where('days_before', 7)->sole());

    $mail = collect(Mail::sent(BillingRenewalReminderMail::class))
        ->first(fn ($m) => str_contains(subjectOf($m), '7 Hari'));

    expect(config('app.timezone'))->toBe('UTC')
        ->and($mail)->not->toBeNull()
        ->and($mail->render())->toContain('7 hari lagi')
        ->and($mail->render())->toContain('30 Oct 2026');
});

it('keeps the dispatcher free of mail and queue internals in the engine', function () {
    // BillingRenewalService talks to an interface and nothing else; the whole
    // point of Phase 3 was to add delivery without the engine learning about it.
    $engine = file_get_contents(app_path('Services/Billing/BillingRenewalService.php'));

    expect($engine)
        ->not->toContain('Mail::')
        ->not->toContain('Mailable')
        ->not->toContain('dispatch(')
        ->toContain('ReminderDispatcher');

    expect(app(App\Services\Billing\ReminderDispatcher::class))
        ->toBeInstanceOf(MailReminderDispatcher::class);
});

// ------------------------------------------------------------- sent_at UTC --

it('stamps sent_at as a UTC instant while scheduled_for stays a business date', function () {
    Mail::fake();
    config(['app.timezone' => 'UTC', 'billing.timezone' => 'Asia/Makassar']);
    emailSubscription(emailClient());

    // 16:30 UTC is already 23 October in Makassar. The two columns on this row
    // therefore have to disagree on the day, and each has to be right in its
    // own terms.
    billingRunOn('2026-09-30');
    Carbon::setTestNow(Carbon::parse('2026-10-22 16:30:00', 'UTC'));
    app(BillingRenewalService::class)->processDue();

    $log = BillingReminderLog::where('days_before', 7)->sole();
    deliverReminder($log);

    $log->refresh();

    expect(config('app.timezone'))->toBe('UTC')
        // the calendar day the reminder belonged to, in the operating timezone
        ->and($log->scheduled_for->toDateString())->toBe('2026-10-23')
        // the moment it went out, as a UTC instant, alongside created_at
        ->and($log->sent_at->toDateTimeString())->toBe('2026-10-22 16:30:00')
        ->and($log->sent_at->toDateString())->toBe('2026-10-22');
});

it('keeps sent_at in step with the row own created_at', function () {
    Mail::fake();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');
    deliverReminder();

    $log = BillingReminderLog::sole();

    // Both are application timestamps; if one used the billing clock they would
    // sit eight hours apart on a row written seconds apart.
    expect($log->sent_at->diffInSeconds($log->created_at))->toBeLessThan(5);
});

// ---------------------------------------------------- next-day retry pass ---

it('retries a failed H-30 on the next day, as the same H-30 reminder', function () {
    Mail::fake();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused'));
    emailSubscription(emailClient());

    billingRunOn('2026-09-30'); // H-30: claimed and enqueued
    deliverReminder();          // the mail host refuses it

    $log = BillingReminderLog::sole();
    expect($log->status)->toBe(BillingReminderLog::FAILED)
        ->and($log->attempts)->toBe(1);

    // The next morning is H-29 — not a threshold day at all. Delivery retry
    // cannot wait for the next threshold, which would be three weeks away.
    billingRunOn('2026-10-01');

    $log->refresh();

    expect(BillingReminderLog::count())->toBe(1)
        // the same row, still identified as the H-30 reminder
        ->and($log->days_before)->toBe(30)
        ->and($log->scheduled_for->toDateString())->toBe('2026-09-30')
        ->and($log->attempts)->toBe(2)
        ->and($log->status)->toBe(BillingReminderLog::PENDING);

    Queue::assertPushed(SendBillingReminderEmail::class);
});

it('retries a failed monthly H-7 on the next day', function () {
    Mail::fake();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused'));
    emailSubscription(emailClient(), [
        'billing_cycle' => BillingSubscription::CYCLE_MONTHLY,
        'start_date' => '2026-09-30',
        'next_renewal_date' => '2026-10-30',
    ]);

    billingRunOn('2026-10-23'); // H-7
    deliverReminder();

    billingRunOn('2026-10-24'); // H-6

    $log = BillingReminderLog::sole();

    expect(BillingReminderLog::count())->toBe(1)
        ->and($log->days_before)->toBe(7)
        ->and($log->attempts)->toBe(2);
});

it('spends only one attempt on the day a reminder first fails', function () {
    Mail::fake();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused'));
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');
    deliverReminder();

    // The retry pass runs in the same command, and must not pick up a failure
    // that happened moments ago in this very run.
    expect(BillingReminderLog::sole()->attempts)->toBe(1);
});

it('gives up on the next day once the attempts are used up', function () {
    Mail::fake();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');

    BillingReminderLog::sole()->update([
        'status' => BillingReminderLog::FAILED,
        'attempts' => BillingReminderLog::MAX_ATTEMPTS,
        'updated_at' => Carbon::parse('2026-09-30 09:00:00'),
    ]);

    Queue::fake();
    billingRunOn('2026-10-01');

    Queue::assertNothingPushed();
    expect(BillingReminderLog::sole()->attempts)->toBe(BillingReminderLog::MAX_ATTEMPTS);
});

it('does not retry a failed reminder whose invoice was paid meanwhile', function () {
    Mail::fake();
    emailSubscription(emailClient());
    billingRunOn('2026-09-30');

    BillingReminderLog::sole()->update([
        'status' => BillingReminderLog::FAILED,
        'attempts' => 1,
        'updated_at' => Carbon::parse('2026-09-30 09:00:00'),
    ]);
    Invoice::sole()->update(['status' => 'paid', 'paid_at' => '2026-09-30']);

    Queue::fake();
    billingRunOn('2026-10-01');

    Queue::assertNothingPushed();
    expect(BillingReminderLog::sole()->attempts)->toBe(1);
});

it('does not retry a failed reminder whose subscription was paused meanwhile', function () {
    Mail::fake();
    $sub = emailSubscription(emailClient());
    billingRunOn('2026-09-30');

    BillingReminderLog::sole()->update([
        'status' => BillingReminderLog::FAILED,
        'attempts' => 1,
        'updated_at' => Carbon::parse('2026-09-30 09:00:00'),
    ]);
    $sub->update(['status' => 'paused']);

    Queue::fake();
    billingRunOn('2026-10-01');

    Queue::assertNothingPushed();
});

it('reports retries separately in the command summary', function () {
    Mail::fake();
    emailSubscription(emailClient());
    billingRunOn('2026-09-30');

    BillingReminderLog::sole()->update([
        'status' => BillingReminderLog::FAILED,
        'attempts' => 1,
        'updated_at' => Carbon::parse('2026-09-30 09:00:00'),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', config('billing.timezone')));
    $summary = app(BillingRenewalService::class)->processDue();

    expect($summary['reminders_retried'])->toBe(1)
        ->and($summary['reminders_queued'])->toBe(0);
});

// ------------------------------------------------- concurrent worker lock ---

it('guards the send with a lock keyed to the reminder', function () {
    emailSubscription(emailClient());
    billingRunOn('2026-09-30');

    $log = BillingReminderLog::sole();
    $job = new SendBillingReminderEmail($log->id);
    $middleware = $job->middleware()[0];

    expect($middleware)->toBeInstanceOf(Illuminate\Queue\Middleware\WithoutOverlapping::class)
        ->and($job->lockKey())->toBe('billing-reminder-email:' . $log->id)
        // discarded rather than re-queued: the other worker is already on it
        ->and($middleware->releaseAfter)->toBeNull()
        // and a crashed worker cannot hold it for ever
        ->and($middleware->expiresAfter)->toBeGreaterThan(0);
});

it('does not enter the send while another worker holds the lock', function () {
    Mail::fake();
    emailSubscription(emailClient());
    billingRunOn('2026-09-30');

    $log = BillingReminderLog::sole();
    $job = new SendBillingReminderEmail($log->id);
    $middleware = $job->middleware()[0];

    // Stand in for the worker that got there first. The key comes from the
    // middleware itself rather than being rebuilt here — Laravel composes it
    // from the job name and our key, and duplicating that formula in a test
    // would only assert that the test matches the framework.
    Cache::lock($middleware->getLockKey($job), 120)->get();

    $entered = false;
    $middleware->handle($job, function () use (&$entered) {
        $entered = true;
    });

    expect($entered)->toBeFalse();
    Mail::assertNothingSent();
    expect($log->fresh()->status)->toBe(BillingReminderLog::PENDING);
});

it('enters the send when no other worker holds the lock', function () {
    Mail::fake();
    emailSubscription(emailClient());
    billingRunOn('2026-09-30');

    $job = new SendBillingReminderEmail(BillingReminderLog::sole()->id);
    $entered = false;

    $job->middleware()[0]->handle($job, function () use (&$entered) {
        $entered = true;
    });

    expect($entered)->toBeTrue();
});

it('locks each reminder independently', function () {
    emailSubscription(emailClient(['company_name' => 'A', 'email' => 'a@a.test']));
    emailSubscription(emailClient(['company_name' => 'B', 'email' => 'b@b.test']));
    billingRunOn('2026-09-30');

    $logs = BillingReminderLog::orderBy('id')->get();
    $first = new SendBillingReminderEmail($logs[0]->id);
    $second = new SendBillingReminderEmail($logs[1]->id);

    // Two clients' reminders must still go out in parallel.
    expect($first->lockKey())->not->toBe($second->lockKey());

    $firstMiddleware = $first->middleware()[0];
    $secondMiddleware = $second->middleware()[0];

    expect($firstMiddleware->getLockKey($first))->not->toBe($secondMiddleware->getLockKey($second));

    Cache::lock($firstMiddleware->getLockKey($first), 120)->get();

    $entered = false;
    $secondMiddleware->handle($second, function () use (&$entered) {
        $entered = true;
    });

    expect($entered)->toBeTrue();
});

it('is a no-op when a duplicate job runs after a successful send', function () {
    Mail::fake();
    emailSubscription(emailClient());
    billingRunOn('2026-09-30');

    $log = BillingReminderLog::sole();

    deliverReminder($log);
    $sentAt = $log->fresh()->sent_at;

    // The lock has been released by now; the status check is what stops the
    // second run, and the first send's timestamp must not be overwritten.
    deliverReminder($log->fresh());

    Mail::assertSent(BillingRenewalReminderMail::class, 1);
    expect($log->fresh()->sent_at->toDateTimeString())->toBe($sentAt->toDateTimeString());
});

it('states plainly that exactly-once is not claimed', function () {
    // A crash between SMTP accepting the message and the row being marked sent
    // is not recoverable without a provider-side idempotency key. The code says
    // so rather than implying a guarantee it cannot keep.
    expect(file_get_contents(app_path('Jobs/SendBillingReminderEmail.php')))
        ->toContain('not exactly-once');
});

// ------------------------------------------------- queue dispatch failure ---

/**
 * A queue backend that refuses the job — the database being unreachable, the
 * jobs table locked, a connection dropped. It fails at exactly the seam the
 * engine talks through, which is where a real queue outage would surface.
 */
class BrokenQueueDispatcher implements ReminderDispatcher
{
    public int $calls = 0;

    public function __construct(public string $message = 'SQLSTATE[HY000] [2002] Connection refused')
    {
    }

    public function send(Invoice $invoice, BillingReminderLog $log, string $recipient): bool
    {
        $this->calls++;

        throw new RuntimeException($this->message);
    }
}

function brokenQueue(string $message = 'SQLSTATE[HY000] [2002] Connection refused'): BrokenQueueDispatcher
{
    $broken = new BrokenQueueDispatcher($message);
    app()->instance(ReminderDispatcher::class, $broken);

    return $broken;
}

/** Puts the real queue dispatcher back, as if the backend had recovered. */
function healthyQueue(): void
{
    app()->instance(ReminderDispatcher::class, new MailReminderDispatcher());
}

it('marks the reminder failed when the queue itself refuses the job', function () {
    $broken = brokenQueue();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30'); // H-30

    $log = BillingReminderLog::sole();

    // The claim survives, but as FAILED — never PENDING, because PENDING would
    // promise a queued job that does not exist and the retry pass would skip it
    // for ever.
    expect($broken->calls)->toBe(1)
        ->and($log->status)->toBe(BillingReminderLog::FAILED)
        ->and($log->attempts)->toBe(1)
        ->and($log->days_before)->toBe(30)
        ->and($log->error_message)->toContain('Connection refused');

    Queue::assertNothingPushed();
});

it('retries the same row the next day after a queue outage', function () {
    brokenQueue();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30'); // H-30, enqueue fails

    expect(BillingReminderLog::sole()->attempts)->toBe(1);

    // The queue comes back before the next morning's run.
    healthyQueue();
    Queue::fake();
    billingRunOn('2026-10-01'); // H-29 — not a threshold day; the retry pass acts

    $log = BillingReminderLog::sole();

    expect(BillingReminderLog::count())->toBe(1)
        ->and($log->days_before)->toBe(30)
        ->and($log->status)->toBe(BillingReminderLog::PENDING)
        ->and($log->attempts)->toBe(2)
        ->and($log->error_message)->toBeNull();

    Queue::assertPushed(SendBillingReminderEmail::class, 1);
});

it('puts a row back to failed when the retry enqueue also fails', function () {
    brokenQueue();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30'); // attempt 1 fails
    billingRunOn('2026-10-01'); // attempt 2, queue still broken

    $log = BillingReminderLog::sole();

    // The critical assertion: back to FAILED, not stranded PENDING.
    expect($log->status)->toBe(BillingReminderLog::FAILED)
        ->and($log->attempts)->toBe(2)
        ->and($log->error_message)->toContain('Connection refused');

    // And it is still reachable on the third morning.
    billingRunOn('2026-10-02');
    expect(BillingReminderLog::sole()->attempts)->toBe(3);

    // Then it stops, having used its allowance.
    billingRunOn('2026-10-03');
    expect(BillingReminderLog::sole()->attempts)->toBe(BillingReminderLog::MAX_ATTEMPTS);
});

it('never leaves a reminder pending without a job behind it', function () {
    brokenQueue();
    emailSubscription(emailClient());

    foreach (['2026-09-30', '2026-10-01', '2026-10-02'] as $day) {
        billingRunOn($day);

        // After every run the row is either genuinely queued or explicitly
        // failed — the one state it must never rest in is "pending with nothing
        // to deliver it".
        expect(BillingReminderLog::sole()->status)->toBe(BillingReminderLog::FAILED);
    }
});

it('sanitises a queue error before storing it', function () {
    brokenQueue('SQLSTATE[HY000]: mysql://billing:SuperSecret123@db:3306 refused, Authorization: Bearer sk-ant-QUEUE9876543');
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');

    $stored = BillingReminderLog::sole()->error_message;

    expect($stored)
        ->not->toContain('sk-ant-QUEUE9876543')
        ->toContain('[redacted]');
});

it('keeps billing the other clients when one enqueue fails', function () {
    emailSubscription(emailClient(['company_name' => 'PT Satu', 'email' => 'satu@abc.test']));
    emailSubscription(emailClient(['company_name' => 'PT Dua', 'email' => 'dua@abc.test']));
    emailSubscription(emailClient(['company_name' => 'PT Tiga', 'email' => 'tiga@abc.test']));

    // Fails for the second subscription only.
    $selective = new class implements ReminderDispatcher {
        public int $calls = 0;

        public function send(Invoice $invoice, BillingReminderLog $log, string $recipient): bool
        {
            $this->calls++;

            if ($recipient === 'dua@abc.test') {
                throw new RuntimeException('Queue connection lost');
            }

            return false;
        }
    };
    app()->instance(ReminderDispatcher::class, $selective);

    $summary = billingSummaryOn('2026-09-30');

    // All three invoices were raised and all three enqueues were attempted.
    expect(Invoice::count())->toBe(3)
        ->and($selective->calls)->toBe(3)
        ->and($summary['invoices_created'])->toBe(3);

    $statuses = BillingReminderLog::orderBy('id')->pluck('status', 'recipient');

    expect($statuses['satu@abc.test'])->toBe(BillingReminderLog::PENDING)
        ->and($statuses['dua@abc.test'])->toBe(BillingReminderLog::FAILED)
        ->and($statuses['tiga@abc.test'])->toBe(BillingReminderLog::PENDING);
});

// ------------------------------------------- retry boundary (no timestamps) --

/**
 * Makes this connection behave like MySQL DATETIME, which stores whole seconds
 * and discards the microseconds Carbon carries.
 *
 * SQLite keeps them, which is exactly why a boundary built on timestamp
 * comparison passed the test suite while being wrong in production: a row
 * failed at 09:00:00.74 during a run started at 09:00:00.74 reads back as
 * 09:00:00 and compares as EARLIER than its own run.
 */
/**
 * Runs the engine at an instant that carries microseconds, the way a real clock
 * does. Carbon::setTestNow() with a plain 'H:i:s' string has none, which would
 * hide the very difference this regression is about.
 */
function billingRunAtRealClockInstant(string $date): void
{
    Carbon::setTestNow(Carbon::parse($date . ' 09:00:00.742381', config('billing.timezone')));

    app(BillingRenewalService::class)->processDue();
}

function truncateTimestampsToSeconds(): void
{
    BillingReminderLog::saved(function (BillingReminderLog $log) {
        DB::table('billing_reminder_logs')
            ->where('id', $log->id)
            ->update(['updated_at' => $log->updated_at->startOfSecond()->toDateTimeString()]);
    });
}

afterEach(fn () => BillingReminderLog::flushEventListeners());

it('does not retry a failure created during the same run, even at second precision', function () {
    truncateTimestampsToSeconds();
    brokenQueue();
    emailSubscription(emailClient());

    // One command run, at an instant with microseconds — the run starts at
    // 09:00:00.742381 while the failure it creates is stored as 09:00:00.
    billingRunAtRealClockInstant('2026-09-30');

    $log = BillingReminderLog::sole();

    // The regression. With a timestamp boundary this reads 2 on MySQL — the
    // reminder spends two of its three attempts before anybody sees it.
    expect($log->attempts)->toBe(1)
        ->and($log->status)->toBe(BillingReminderLog::FAILED)
        // and the stored timestamp really has no microseconds, so the test is
        // proving the behaviour rather than the storage
        ->and($log->fresh()->updated_at->micro)->toBe(0);
});

it('retries a failure that existed before the run began', function () {
    truncateTimestampsToSeconds();
    brokenQueue();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30'); // fails; attempts 1
    expect(BillingReminderLog::sole()->attempts)->toBe(1);

    // A later run — a day that is not a threshold day, so only the retry pass
    // can act. The row was already FAILED when this run started, so it is a
    // candidate.
    billingRunOn('2026-10-01');

    expect(BillingReminderLog::count())->toBe(1)
        ->and(BillingReminderLog::sole()->attempts)->toBe(2);
});

it('spends one attempt per run even when today is the reminder own threshold', function () {
    truncateTimestampsToSeconds();
    brokenQueue();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30'); // H-30: claimed, enqueue fails, attempts 1

    // The same day again. The threshold pass retries the row, and the retry
    // pass must then leave it alone — otherwise one run costs two attempts.
    billingRunOn('2026-09-30');

    expect(BillingReminderLog::sole()->attempts)->toBe(2);

    billingRunOn('2026-09-30');

    expect(BillingReminderLog::sole()->attempts)->toBe(BillingReminderLog::MAX_ATTEMPTS);
});

it('recovers on a later run once the queue is healthy again', function () {
    truncateTimestampsToSeconds();
    brokenQueue();
    emailSubscription(emailClient());

    billingRunOn('2026-09-30');
    expect(BillingReminderLog::sole()->status)->toBe(BillingReminderLog::FAILED);

    healthyQueue();
    Queue::fake();
    billingRunOn('2026-10-01');

    $log = BillingReminderLog::sole();

    expect($log->status)->toBe(BillingReminderLog::PENDING)
        ->and($log->attempts)->toBe(2)
        ->and($log->days_before)->toBe(30);

    Queue::assertPushed(SendBillingReminderEmail::class, 1);
});

it('decides eligibility without reading any timestamp', function () {
    // The boundary is a frozen set of ids captured when the run starts. Nothing
    // about it depends on clock precision, storage precision or timezone — the
    // three things that made the previous approach quietly wrong on MySQL.
    $engine = file_get_contents(app_path('Services/Billing/BillingRenewalService.php'));

    // Just the eligibility query, not the methods around it — those may use
    // now() for perfectly ordinary reasons.
    $start = strpos($engine, 'public function retryFailedDeliveries');
    $end = strpos($engine, 'private function retryDelivery');
    $retryPass = substr($engine, $start, $end - $start);

    expect($retryPass)
        ->not->toContain("'updated_at'")
        ->not->toContain('now()')
        ->toContain('whereIn(\'id\'');
});
