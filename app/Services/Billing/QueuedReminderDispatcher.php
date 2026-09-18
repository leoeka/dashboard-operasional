<?php

namespace App\Services\Billing;

use App\Models\BillingReminderLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

/**
 * The Phase 2 dispatcher: it decides and records, but sends nothing.
 *
 * Returning false leaves the reminder log `pending` with its recipient filled
 * in — a queued instruction waiting for the mail implementation in Phase 3.
 * That keeps the schedule running and observable now without a half-written
 * email going to a real client in the meantime.
 */
class QueuedReminderDispatcher implements ReminderDispatcher
{
    public function send(Invoice $invoice, BillingReminderLog $log, string $recipient): bool
    {
        Log::info('Renewal reminder queued (delivery arrives in Phase 3).', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'days_before' => $log->days_before,
            'recipient' => $recipient,
        ]);

        return false;
    }
}
