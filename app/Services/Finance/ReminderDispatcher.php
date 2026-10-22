<?php

namespace App\Services\Finance;

use App\Models\FinanceReminderLog;
use App\Models\Invoice;

/**
 * Interface pengiriman pengingat perpanjangan ke klien.
 */
interface ReminderDispatcher
{
    public function send(Invoice $invoice, FinanceReminderLog $log, string $recipient): bool;
}

