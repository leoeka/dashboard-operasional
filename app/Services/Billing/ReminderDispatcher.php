<?php

namespace App\Services\Billing;

use App\Models\BillingReminderLog;
use App\Models\Invoice;

/**
 * How a renewal reminder actually reaches the client.
 *
 * Phase 2 builds the decision — which invoice, which threshold, to whom, and
 * exactly once — without sending anything. Delivery is Phase 3, and putting it
 * behind this interface means the schedule logic can be tested with no SMTP,
 * no queue and no network.
 *
 * Contract:
 * - return true  : the reminder has gone out; the log is marked sent.
 * - return false : accepted but not delivered yet; the log stays pending.
 * - throw        : delivery failed; the log is marked failed with the reason
 *                  and may be retried, bounded by BillingReminderLog::MAX_ATTEMPTS.
 */
interface ReminderDispatcher
{
    public function send(Invoice $invoice, BillingReminderLog $log, string $recipient): bool;
}
