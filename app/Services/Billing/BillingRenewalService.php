<?php

namespace App\Services\Billing;

use App\Models\BillingReminderLog;
use App\Models\BillingSubscription;
use App\Models\Invoice;
use App\Exceptions\ProviderException;
use App\Models\InvoiceItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The recurring billing engine: raise the invoice, then remind about it.
 *
 * Run daily. For each active subscription it works out how many calendar days
 * remain until the renewal date, then answers two separate questions:
 *
 * - Should the invoice for this period exist by now? True from the creation
 *   threshold onward (H-30 yearly, H-7 monthly), so a run that missed the first
 *   day still raises it — an invoice is a debt, not an appointment.
 * - Is today one of this cycle's reminder days? An exact match only. A missed
 *   reminder stays missed rather than arriving late and claiming a day it never
 *   went out on.
 *
 * The two things this has to get right are both about running twice:
 *
 * - One invoice per subscription per billing period. Guaranteed by taking a row
 *   lock on the subscription and re-checking inside the same transaction, so a
 *   second run waits and then finds the invoice the first one made.
 * - One reminder per threshold. Guaranteed by the unique key on
 *   (invoice_id, days_before): inserting the log row IS the claim, and a
 *   duplicate insert fails at the database rather than relying on a timestamp.
 *
 * Nothing is sent from here — see ReminderDispatcher.
 */
class BillingRenewalService
{
    /** Bounded: enough to lose a race to a concurrent run, not enough to spin. */
    private const NUMBER_ATTEMPTS = 5;

    public function __construct(
        private ReminderPolicy $policy,
        private BillingCycle $cycle,
        private InvoiceNumberGenerator $numbers,
        private ReminderDispatcher $dispatcher,
        private BillingClock $clock,
    ) {
    }

    /**
     * Processes every subscription that could need attention today.
     *
     * One failing subscription must not stop the batch — a client with a broken
     * record should not silently cost every other client their reminder. Each
     * is processed in its own transaction, so a failure rolls back only itself.
     *
     * @return array<string, int> counters for the command's summary
     */
    public function processDue(): array
    {
        // Which reminders were ALREADY failed when this run began, captured as
        // ids before anything is written.
        //
        // This used to compare `updated_at` against the run's start time, which
        // is wrong on MySQL: a DATETIME column stores whole seconds while
        // Carbon's now() carries microseconds, so a row failed at 09:00:00.7
        // during this very run reads back as 09:00:00 and compares as EARLIER
        // than the run. The reminder would then be retried immediately and burn
        // two of its three attempts in one morning — invisibly, because SQLite
        // keeps the microseconds and the tests stayed green.
        //
        // A frozen set of ids depends on no clock, no precision and no timezone.
        $retryCandidateIds = BillingReminderLog::query()
            ->where('status', BillingReminderLog::FAILED)
            ->where('attempts', '<', BillingReminderLog::MAX_ATTEMPTS)
            ->pluck('id');

        /** @var array<int, int> $handledReminderIds reminders the threshold pass touches during this run */
        $handledReminderIds = [];

        $summary = [
            'subscriptions_checked' => 0,
            'invoices_created' => 0,
            'reminders_queued' => 0,
            'reminders_sent' => 0,
            'reminders_retried' => 0,
            'skipped_paid' => 0,
            'errors' => 0,
        ];

        BillingSubscription::active()
            ->whereNotNull('next_renewal_date')
            ->with(['client', 'project'])
            ->chunkById(100, function ($subscriptions) use (&$summary, &$handledReminderIds) {
                foreach ($subscriptions as $subscription) {
                    $summary['subscriptions_checked']++;

                    try {
                        $result = $this->processSubscription($subscription);

                        foreach (['invoices_created', 'reminders_queued', 'reminders_sent', 'skipped_paid'] as $key) {
                            $summary[$key] += $result[$key];
                        }

                        // Whichever reminder this subscription's threshold pass
                        // dealt with, so the retry pass below can leave it alone.
                        if ($result['reminder_log_id']) {
                            $handledReminderIds[] = $result['reminder_log_id'];
                        }
                    } catch (\Throwable $e) {
                        $summary['errors']++;

                        // Identifiers only — never the client's details or any
                        // provider payload.
                        Log::error('Billing renewal gagal untuk satu subscription; batch dilanjutkan.', [
                            'subscription_id' => $subscription->id,
                            'client_id' => $subscription->client_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // Still AFTER the threshold pass, and excluding whatever that pass
        // already touched.
        //
        // The status filter alone is not enough: when today IS a reminder's
        // threshold day, claimAndSend() may have retried it and left it FAILED
        // again, and the retry pass would then spend a second attempt on the
        // same row in the same run.
        $summary['reminders_retried'] = $this->retryFailedDeliveries(
            $retryCandidateIds->diff($handledReminderIds)
        );

        return $summary;
    }

    /**
     * Re-enqueues reminders whose delivery failed, on whatever day the next run
     * happens to be.
     *
     * Delivery retry cannot hang off the threshold calendar. If H-30's email is
     * refused by the mail host, the next chance to try again would otherwise be
     * H-7 — three weeks later, for a message that was about a renewal a month
     * away. So this is a separate pass with no date condition at all: any FAILED
     * row with attempts left is tried again tomorrow.
     *
     * It re-enqueues THE SAME ROW. No new log is created, `days_before` is not
     * touched, and the reminder keeps its original identity — an H-30 reminder
     * delivered late is still the H-30 reminder, not an H-29 one.
     *
     * $candidateIds bounds it to the reminders that were already failed when
     * the run began, so a failure created during this run waits for the next
     * one — one delivery attempt per reminder per run. Passing null means "no
     * boundary", which is what a manual, standalone retry wants.
     *
     * The status and attempts filters are applied again here rather than being
     * trusted from the frozen list: a candidate may have been picked up by the
     * threshold pass in the meantime, and must not be enqueued twice.
     *
     * @param  iterable<int>|null  $candidateIds
     */
    public function retryFailedDeliveries(?iterable $candidateIds = null): int
    {
        $retried = 0;

        $query = BillingReminderLog::where('status', BillingReminderLog::FAILED)
            ->where('attempts', '<', BillingReminderLog::MAX_ATTEMPTS);

        if ($candidateIds !== null) {
            $ids = collect($candidateIds);

            if ($ids->isEmpty()) {
                return 0;
            }

            $query->whereIn('id', $ids);
        }

        $query
            ->with(['invoice.subscription', 'invoice.client', 'invoice.project.client'])
            ->chunkById(100, function ($logs) use (&$retried) {
                foreach ($logs as $log) {
                    try {
                        if ($this->retryDelivery($log)) {
                            $retried++;
                        }
                    } catch (\Throwable $e) {
                        Log::error('Retry reminder gagal; batch dilanjutkan.', [
                            'reminder_log_id' => $log->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $retried;
    }

    /**
     * Revalidates before re-enqueueing: a reminder that failed to send is not
     * automatically a reminder that still ought to go out. The invoice may have
     * been settled or cancelled in the meantime, or the subscription paused.
     */
    private function retryDelivery(BillingReminderLog $log): bool
    {
        $invoice = $log->invoice;

        if (!$invoice || $invoice->status === 'paid' || $invoice->status === 'cancelled') {
            return false;
        }

        $subscription = $invoice->subscription;

        if ($subscription && !$subscription->isActive()) {
            return false;
        }

        if ($subscription && !$subscription->auto_reminder) {
            return false;
        }

        $recipient = $invoice->billableClient()?->billingEmail();

        if (!$recipient) {
            // Counted as an attempt so a client with no address anywhere stops
            // being retried every single day.
            $log->update([
                'attempts' => $log->attempts + 1,
                'error_message' => 'Client tidak memiliki alamat email billing maupun email utama.',
            ]);

            return false;
        }

        // PENDING means "a job exists for this". Setting it before the dispatch
        // and leaving it there if the dispatch throws would make that untrue,
        // and the row would sit PENDING for ever: the retry pass only looks at
        // FAILED rows, so nothing would ever pick it up again.
        $log->update([
            'status' => BillingReminderLog::PENDING,
            'attempts' => $log->attempts + 1,
            'recipient' => $recipient,
            'error_message' => null,
        ]);

        try {
            $this->dispatcher->send($invoice, $log, $recipient);
        } catch (\Throwable $e) {
            // Put it back where the next daily run can find it. attempts is NOT
            // rolled back — the enqueue was attempted and it failed, and
            // pretending otherwise would let a permanently broken queue retry
            // for ever.
            $log->update([
                'status' => BillingReminderLog::FAILED,
                'error_message' => ProviderException::sanitise($e->getMessage()),
            ]);

            return false;
        }

        return true;
    }

    /**
     * One subscription, atomically.
     *
     * @return array<string, int>
     */
    public function processSubscription(BillingSubscription $subscription): array
    {
        $result = ['invoices_created' => 0, 'reminders_queued' => 0, 'reminders_sent' => 0, 'skipped_paid' => 0, 'reminder_log_id' => null];

        $daysUntil = $this->cycle->daysUntil($subscription->next_renewal_date);

        // Two separate questions, on purpose.
        //
        // The reminder must land on an exact day — H-7 means H-7, and a missed
        // one stays missed rather than arriving late and wrong.
        //
        // The invoice is not a promise about a day; it is a debt owed from the
        // creation threshold onward. Tying it to the same exact match made a
        // single missed run (a server down on H-30) mean no invoice for the
        // whole period, and the H-7 run then reminding about nothing.
        $threshold = $this->policy->thresholdDueOn($subscription, $daysUntil);
        $invoiceDue = $this->policy->invoiceDue($subscription, $daysUntil);

        if (!$invoiceDue && $threshold === null) {
            return $result;
        }

        // The invoice decision is the part that must not race; delivery happens
        // afterwards, outside the lock, so a slow dispatcher cannot hold a row.
        [$invoice, $created] = DB::transaction(function () use ($subscription, $invoiceDue) {
            $locked = BillingSubscription::whereKey($subscription->getKey())->lockForUpdate()->first();

            if (!$locked || !$locked->isActive()) {
                return [null, false];
            }

            $period = $this->cycle->periodFor($locked, $locked->next_renewal_date);
            $existing = $this->existingInvoiceFor($locked, $period['start']);

            if ($existing) {
                return [$existing, false];
            }

            // auto_invoice off means a human raises the invoice. A reminder must
            // never become the reason an invoice appears, so there is nothing to
            // do until one exists.
            if (!$invoiceDue || !$locked->auto_invoice) {
                return [null, false];
            }

            return [$this->createRenewalInvoice($locked, $period), true];
        });

        if ($created) {
            $result['invoices_created']++;
        }

        if (!$invoice) {
            return $result;
        }

        if ($invoice->status === 'paid') {
            // Settled early: the remaining thresholds are not sent, and no log
            // is written for them.
            $result['skipped_paid']++;

            return $result;
        }

        // Past the invoice; from here on only an exact threshold day sends
        // anything. A catch-up run that raised the invoice on H-29 writes no
        // reminder log at all, so the missed H-30 stays visibly missed instead
        // of being backfilled as though it had gone out on time.
        if ($threshold === null || !$subscription->auto_reminder) {
            return $result;
        }

        ['outcome' => $outcome, 'log_id' => $logId] = $this->claimAndSend($invoice, $subscription, $threshold);

        $result['reminder_log_id'] = $logId;

        if ($outcome === 'sent') {
            $result['reminders_sent']++;
        } elseif ($outcome === 'queued') {
            $result['reminders_queued']++;
        }

        return $result;
    }

    /**
     * The invoice already covering this period, if any.
     *
     * Matched on subscription + period start + purpose rather than on dates
     * alone, so a manually raised invoice for the same period is reused instead
     * of being duplicated.
     */
    private function existingInvoiceFor(BillingSubscription $subscription, \Carbon\CarbonInterface $periodStart): ?Invoice
    {
        return Invoice::where('billing_subscription_id', $subscription->getKey())
            ->where('purpose', Invoice::PURPOSE_RENEWAL)
            ->whereDate('billing_period_start', $periodStart->toDateString())
            ->first();
    }

    /**
     * @param array{start: \Carbon\CarbonInterface, end: \Carbon\CarbonInterface} $period
     */
    private function createRenewalInvoice(BillingSubscription $subscription, array $period): Invoice
    {
        $amount = $subscription->amount;

        for ($attempt = 1; $attempt <= self::NUMBER_ATTEMPTS; $attempt++) {
            try {
                $invoice = Invoice::create([
                    // A renewal keeps its project when it has one; `purpose` is
                    // what marks it as a renewal, not the absence of a project.
                    'project_id' => $subscription->project_id,
                    'client_id' => $subscription->client_id,
                    'billing_subscription_id' => $subscription->getKey(),
                    'invoice_number' => $this->numbers->next(),
                    'type' => 'full',
                    'purpose' => Invoice::PURPOSE_RENEWAL,
                    // Business date, not the UTC instant: an invoice issued at
                    // 00:30 in the operating timezone belongs to that day.
                    'issue_date' => $this->clock->today(),
                    'amount' => $amount,
                    'subtotal' => $amount,
                    'discount' => 0,
                    'tax' => 0,
                    'currency' => $subscription->currency,
                    'due_date' => $subscription->next_renewal_date,
                    'billing_period_start' => $period['start'],
                    'billing_period_end' => $period['end'],
                    'status' => 'unpaid',
                ]);

                break;
            } catch (UniqueConstraintViolationException $e) {
                // Another process took that number between our read and our
                // insert. Ask for the next one and try again.
                if ($attempt === self::NUMBER_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        // A snapshot, not a reference. If the subscription's price changes next
        // month this line keeps the figure the client was actually billed.
        InvoiceItem::create([
            'invoice_id' => $invoice->getKey(),
            'description' => $this->lineDescription($subscription, $period),
            'quantity' => 1,
            'unit_price' => $amount,
            'total' => $amount,
            'position' => 0,
        ]);

        return $invoice;
    }

    private function lineDescription(BillingSubscription $subscription, array $period): string
    {
        return $subscription->name . ' — '
            . $period['start']->translatedFormat('d M Y') . ' s/d ' . $period['end']->translatedFormat('d M Y');
    }

    /**
     * Claims this threshold and asks the dispatcher to deliver it.
     *
     * The claim is the insert: the unique key on (invoice_id, days_before)
     * means a second run cannot create a second row, and a row that already
     * exists is proof the threshold was handled. A previous FAILED row is the
     * one exception — it is retried in place, up to its attempt limit, rather
     * than duplicated.
     *
     * @return array{outcome: string, log_id: ?int} outcome is 'sent' | 'queued' | 'skipped';
     *         log_id names the row acted on so the caller can keep the retry
     *         pass off it for the rest of this run.
     */
    private function claimAndSend(Invoice $invoice, BillingSubscription $subscription, int $threshold): array
    {
        $recipient = $subscription->client?->billingEmail();

        try {
            $log = BillingReminderLog::create([
                'invoice_id' => $invoice->getKey(),
                'billing_subscription_id' => $subscription->getKey(),
                'days_before' => $threshold,
                'scheduled_for' => $this->clock->today(),
                'status' => BillingReminderLog::PENDING,
                'recipient' => $recipient,
            ]);
        } catch (UniqueConstraintViolationException) {
            $log = BillingReminderLog::where('invoice_id', $invoice->getKey())
                ->where('days_before', $threshold)
                ->first();

            if (!$log || !$log->canRetry()) {
                return ['outcome' => 'skipped', 'log_id' => $log?->getKey()];
            }
        }

        if (!$recipient) {
            $log->update([
                'status' => BillingReminderLog::FAILED,
                'attempts' => $log->attempts + 1,
                'error_message' => 'Client tidak memiliki alamat email billing maupun email utama.',
            ]);

            return ['outcome' => 'skipped', 'log_id' => $log->getKey()];
        }

        try {
            $delivered = $this->dispatcher->send($invoice, $log, $recipient);
        } catch (\Throwable $e) {
            // Includes the queue backend refusing the job, not just a mail
            // problem. Either way the row must end up FAILED rather than
            // PENDING: a PENDING row with no job behind it is invisible to the
            // retry pass and would never be sent or reported again.
            //
            // attempts still counts this one — the enqueue was genuinely tried.
            $log->update([
                'status' => BillingReminderLog::FAILED,
                'attempts' => $log->attempts + 1,
                'recipient' => $recipient,
                // A queue/database error can carry a DSN, password included.
                'error_message' => ProviderException::sanitise($e->getMessage()),
            ]);

            return ['outcome' => 'skipped', 'log_id' => $log->getKey()];
        }

        $log->update([
            'status' => $delivered ? BillingReminderLog::SENT : BillingReminderLog::PENDING,
            'sent_at' => $delivered ? now() : null,
            'attempts' => $log->attempts + 1,
            'recipient' => $recipient,
            'error_message' => null,
        ]);

        return ['outcome' => $delivered ? 'sent' : 'queued', 'log_id' => $log->getKey()];
    }
}
