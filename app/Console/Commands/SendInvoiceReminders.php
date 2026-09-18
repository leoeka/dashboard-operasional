<?php

namespace App\Console\Commands;

use App\Services\Billing\LegacyInvoiceReminderService;
use Illuminate\Console\Command;

/**
 * DEPRECATED — kept so an existing crontab or runbook entry keeps working.
 *
 * No longer scheduled: `billing:process-renewals` runs this same path as part
 * of the single daily billing run. It now delegates to the shared service, so
 * even if something still invokes it, it can only touch project invoices —
 * never a recurring renewal — and the once-a-day guard is the same one.
 */
class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = '[Deprecated] Gunakan billing:process-renewals. Kirim reminder invoice project yang belum dibayar.';

    public function handle(LegacyInvoiceReminderService $legacy): int
    {
        $summary = $legacy->sendDue();

        $this->warn('Command ini deprecated — pakai billing:process-renewals (sudah mencakup jalur ini).');
        $this->info($summary['legacy_reminders_sent'] . ' reminder invoice project terkirim.');

        return self::SUCCESS;
    }
}
