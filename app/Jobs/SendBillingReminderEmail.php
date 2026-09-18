<?php

namespace App\Jobs;

use App\Exceptions\ProviderException;
use App\Mail\BillingRenewalReminderMail;
use App\Models\BillingReminderLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers one renewal reminder, off the scheduler's back.
 *
 * The daily billing run decides WHICH reminders are due and claims each one;
 * this only sends. Keeping SMTP out of the scheduler matters because a slow or
 * unreachable mail host would otherwise hold up every other subscription in the
 * batch, and a command that takes minutes is a command cron will eventually run
 * twice.
 *
 * Only the reminder log id crosses the queue. Everything else is reloaded when
 * the worker picks it up, because minutes may pass in between and the world can
 * change: the invoice may have been paid, the subscription paused, the billing
 * address corrected. A serialised snapshot would send the email the world no
 * longer justifies.
 *
 * KNOWN LIMIT — this is not exactly-once, and cannot be. If the mail host
 * accepts the message and the process dies before the row is marked sent, the
 * next run has no way to tell that apart from a message that never arrived, and
 * will send again. Closing that window needs an idempotency key on the provider
 * side, which SMTP does not offer. What is guaranteed here is narrower and
 * worth stating plainly: no concurrent duplicate send, a repeated job is a
 * no-op, retries are bounded, and `sent` is final.
 */
class SendBillingReminderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One attempt per dispatch, deliberately.
     *
     * Retries belong to the billing run, not to the queue: BillingReminderLog
     * is the single source of truth for how many times a reminder has been
     * tried, and letting Laravel retry silently would increment nothing and
     * could send twice. A failed reminder is picked up by the next daily run,
     * which re-enqueues the same row until MAX_ATTEMPTS.
     */
    public int $tries = 1;

    /** How long a worker may hold the send lock before it is assumed dead. */
    private const LOCK_SECONDS = 120;

    public function __construct(public int $reminderLogId)
    {
    }

    /**
     * Only one worker may be inside the send for a given reminder.
     *
     * `if ($log->wasSent()) return;` is enough for two jobs that run one after
     * the other, but not for two that run at the same time: both would read
     * PENDING, both would pass the check, and both would send. Queues deliver
     * the same message more than once often enough that this is a real case,
     * not a theoretical one.
     *
     * The key is the reminder row, so different reminders still run in
     * parallel. dontRelease() discards the loser rather than re-queueing it —
     * the winner is already doing the work, and with $tries = 1 a release would
     * only turn into a spurious failure. The expiry keeps a crashed worker from
     * holding the lock for good.
     *
     * Deliberately a cache lock rather than lockForUpdate(): a database row
     * lock held open across an SMTP conversation would keep a transaction alive
     * for as long as the mail host takes to answer.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->lockKey()))
                ->dontRelease()
                ->expireAfter(self::LOCK_SECONDS),
        ];
    }

    public function lockKey(): string
    {
        return 'billing-reminder-email:' . $this->reminderLogId;
    }

    public function handle(): void
    {
        $log = BillingReminderLog::with(['invoice.subscription', 'invoice.client', 'invoice.project.client', 'invoice.items'])
            ->find($this->reminderLogId);

        if (!$log) {
            return; // the log was removed with its invoice; nothing to send
        }

        // Already delivered. The guard that makes a duplicated job harmless —
        // queue drivers can deliver a message more than once.
        if ($log->wasSent()) {
            return;
        }

        $invoice = $log->invoice;

        if (!$invoice) {
            return;
        }

        if ($reason = $this->reasonNotToSend($log)) {
            // Left PENDING on purpose rather than marked sent or failed.
            //
            // `sent` would be a lie — nothing went out. `failed` would invite
            // the next billing run to retry it, and it would keep being
            // cancelled for the same reason until it burned through
            // MAX_ATTEMPTS. Pending with canRetry() false means it stops here,
            // the record still shows the threshold was claimed, and the reason
            // is readable.
            $log->update(['error_message' => $reason]);

            return;
        }

        $recipient = $invoice->billableClient()?->billingEmail();

        if (!$recipient) {
            $this->fail($log, 'Client tidak memiliki alamat email billing maupun email utama.');

            return;
        }

        try {
            Mail::to($recipient)->send(new BillingRenewalReminderMail($invoice, $log));
        } catch (\Throwable $e) {
            // A mail error can quote the SMTP conversation back, credentials
            // included; the same scrubbing the AI providers use applies here.
            $this->fail($log, ProviderException::sanitise($e->getMessage()));

            return;
        }

        // Written in one statement so a crash cannot leave a row that is
        // half-sent.
        DB::transaction(fn () => $log->update([
            'status' => BillingReminderLog::SENT,
            // A UTC instant, NOT a business date. `scheduled_for` is the
            // calendar day the reminder belonged to and uses the billing
            // timezone; `sent_at` records the moment it actually went out and
            // belongs with created_at, queue and log timestamps, all of which
            // the application keeps on UTC. Mixing the two would make two
            // columns on the same row mean different things by eight hours.
            'sent_at' => now(),
            'recipient' => $recipient,
            'error_message' => null,
        ]));
    }

    /**
     * Whether the world has moved on since this reminder was queued.
     *
     * A client who paid an hour ago should not then receive a reminder about
     * it, and a subscription somebody paused should stop generating mail
     * immediately rather than at the end of its current cycle.
     */
    private function reasonNotToSend(BillingReminderLog $log): ?string
    {
        if ($log->invoice->status === 'paid') {
            return 'Dibatalkan: invoice sudah lunas sebelum email terkirim.';
        }

        if ($log->invoice->status === 'cancelled') {
            return 'Dibatalkan: invoice dibatalkan sebelum email terkirim.';
        }

        $subscription = $log->invoice->subscription;

        if ($subscription && !$subscription->isActive()) {
            return "Dibatalkan: langganan berstatus {$subscription->status} sebelum email terkirim.";
        }

        return null;
    }

    /**
     * Records a delivery failure WITHOUT touching `attempts`.
     *
     * The billing run increments attempts when it enqueues; if this incremented
     * too, one cycle would count as two and a reminder would be abandoned after
     * half the intended tries.
     */
    private function fail(BillingReminderLog $log, string $reason): void
    {
        $log->update([
            'status' => BillingReminderLog::FAILED,
            'error_message' => $reason,
        ]);

        Log::warning('Pengiriman reminder renewal gagal.', [
            'reminder_log_id' => $log->id,
            'invoice_id' => $log->invoice_id,
            'attempts' => $log->attempts,
            'reason' => $reason,
        ]);
    }

    /** Queue-level failure (worker crash, timeout) lands here rather than nowhere. */
    public function failed(\Throwable $e): void
    {
        $log = BillingReminderLog::find($this->reminderLogId);

        if ($log && !$log->wasSent()) {
            $log->update([
                'status' => BillingReminderLog::FAILED,
                'error_message' => ProviderException::sanitise($e->getMessage()),
            ]);
        }
    }
}
