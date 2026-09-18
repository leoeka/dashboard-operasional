<?php

namespace App\Services\Billing;

use App\Jobs\SendBillingReminderEmail;
use App\Models\BillingReminderLog;
use App\Models\Invoice;

/**
 * Hands a claimed reminder to the queue.
 *
 * Returns false rather than true on purpose: false means "accepted, not yet
 * delivered", which is exactly true here — the log stays PENDING until the
 * worker actually sends and flips it to SENT. Returning true would mark a
 * reminder delivered at the moment it was merely enqueued, and a mail host
 * refusing it ten seconds later would leave a record saying it went out.
 *
 * Email only. Recurring renewals deliberately do not touch WhatsAppService;
 * that remains the legacy project-invoice path's behaviour.
 */
class MailReminderDispatcher implements ReminderDispatcher
{
    public function send(Invoice $invoice, BillingReminderLog $log, string $recipient): bool
    {
        SendBillingReminderEmail::dispatch($log->getKey());

        return false;
    }
}
