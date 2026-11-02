<?php

namespace App\Console\Commands;

use App\Services\Finance\FinanceRenewalService;
use Illuminate\Console\Command;

/**
 * Titik masuk scheduled command harian untuk proses perpanjangan layanan & pengingat (Finance Renewals).
 */
class ProcessFinanceRenewals extends Command
{
    protected $signature = 'finance:process-renewals';

    protected $aliases = ['billing:process-renewals'];

    protected $description = 'Buat invoice renewal yang jatuh tempo, antrikan reminder-nya, dan retry reminder yang gagal';

    public function handle(FinanceRenewalService $renewals): int
    {
        $summary = $renewals->processDue();

        $this->info('Finance renewal run selesai:');

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

        return self::SUCCESS;
    }
}

