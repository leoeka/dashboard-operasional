<?php

namespace App\Services\Finance;

use App\Models\FinanceReminderLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class QueuedReminderDispatcher implements ReminderDispatcher
{
    public function send(Invoice $invoice, FinanceReminderLog $log, string $recipient): bool
    {
        Log::info('Renewal reminder queued.', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'days_before' => $log->days_before,
            'recipient' => $recipient,
        ]);

        return false;
    }
}

