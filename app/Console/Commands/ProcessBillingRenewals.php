<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingRenewalService;
use App\Services\Billing\LegacyInvoiceReminderService;
use Illuminate\Console\Command;

/**
 * The single scheduled entry point for everything billing.
 *
 * Two paths run from here — recurring renewals and the legacy project-invoice
 * reminders — deliberately in one command rather than two scheduled commands.
 * Two schedulers that can both reach the same invoices table is exactly how a
 * client ends up with two reminders for one invoice; one command makes that
 * impossible to configure by accident.
 *
 * All decisions live in the services. This only calls them and prints what
 * happened.
 */
class ProcessBillingRenewals extends Command
{
    protected $signature = 'billing:process-renewals';

    protected $description = 'Buat invoice renewal yang jatuh tempo, antrikan reminder-nya, dan kirim reminder invoice project (legacy)';

    public function handle(BillingRenewalService $renewals, LegacyInvoiceReminderService $legacy): int
    {
        $summary = $renewals->processDue();
        $summary += $legacy->sendDue();

        $this->info('Billing run selesai:');

        foreach ([
            'subscriptions_checked' => 'subscriptions checked',
            'invoices_created' => 'invoices created',
            'reminders_queued' => 'reminders queued',
            'reminders_sent' => 'reminders sent',
            'reminders_retried' => 'reminders retried',
            'skipped_paid' => 'skipped (already paid)',
            'legacy_reminders_sent' => 'legacy project reminders sent',
            'errors' => 'errors',
            'legacy_errors' => 'legacy errors',
        ] as $key => $label) {
            $this->line(sprintf('  %-32s %d', $label . ':', $summary[$key] ?? 0));
        }

        // A failing subscription is reported, not fatal: the rest of the batch
        // still ran, and a non-zero exit would make cron treat the whole run as
        // lost.
        return self::SUCCESS;
    }
}
