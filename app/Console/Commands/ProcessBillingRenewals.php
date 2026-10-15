<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingRenewalService;
use Illuminate\Console\Command;

/**
 * The single scheduled entry point for everything billing.
 *
 * One command, one path: the recurring renewal engine. Project invoices are not
 * reminded from here — payment for them is settled outside this system, so there
 * is nothing here to chase.
 *
 * All decisions live in the services. This only calls them and prints what
 * happened.
 */
class ProcessBillingRenewals extends Command
{
    protected $signature = 'billing:process-renewals';

    protected $description = 'Buat invoice renewal yang jatuh tempo, antrikan reminder-nya, dan retry reminder yang gagal';

    public function handle(BillingRenewalService $renewals): int
    {
        $summary = $renewals->processDue();

        $this->info('Billing run selesai:');

        foreach ([
            'subscriptions_checked' => 'subscriptions checked',
            'invoices_created' => 'invoices created',
            'reminders_queued' => 'reminders queued',
            'reminders_sent' => 'reminders sent',
            'reminders_retried' => 'reminders retried',
            'skipped_paid' => 'skipped (already paid)',
            'periods_rolled' => 'periods rolled forward',
            'subscriptions_expired' => 'subscriptions expired',
            'errors' => 'errors',
        ] as $key => $label) {
            $this->line(sprintf('  %-32s %d', $label . ':', $summary[$key] ?? 0));
        }

        // A failing subscription is reported, not fatal: the rest of the batch
        // still ran, and a non-zero exit would make cron treat the whole run as
        // lost.
        return self::SUCCESS;
    }
}
