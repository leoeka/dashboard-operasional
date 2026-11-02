<?php

namespace App\Services\Finance;

use App\Exceptions\ProviderException;
use App\Models\FinanceReminderLog;
use App\Models\FinanceSubscription;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mesin utama perpanjangan layanan dan pengingat tagihan berulang (Finance Renewal Service).
 */
class FinanceRenewalService
{
    private const NUMBER_ATTEMPTS = 5;

    public function __construct(
        private ReminderPolicy $policy,
        private FinanceCycle $cycle,
        private InvoiceNumberGenerator $numbers,
        private ReminderDispatcher $dispatcher,
        private FinanceClock $clock,
    ) {
    }

    /**
     * Memproses seluruh langganan yang memerlukan invoice atau pengingat hari ini.
     *
     * @return array<string, int>
     */
    public function processDue(): array
    {
        $retryCandidateIds = FinanceReminderLog::query()
            ->where('status', FinanceReminderLog::FAILED)
            ->where('attempts', '<', FinanceReminderLog::MAX_ATTEMPTS)
            ->pluck('id');

        /** @var array<int, int> $handledReminderIds */
        $handledReminderIds = [];

        $summary = [
            'subscriptions_checked' => 0,
            'invoices_created' => 0,
            'reminders_queued' => 0,
            'reminders_sent' => 0,
            'reminders_retried' => 0,
            'skipped_paid' => 0,
            'periods_rolled' => 0,
            'subscriptions_expired' => 0,
            'errors' => 0,
        ];

        FinanceSubscription::active()
            ->whereNotNull('next_renewal_date')
            ->with(['client', 'project'])
            ->chunkById(100, function ($subscriptions) use (&$summary, &$handledReminderIds) {
                foreach ($subscriptions as $subscription) {
                    $summary['subscriptions_checked']++;

                    try {
                        $result = $this->processSubscription($subscription);

                        foreach (['invoices_created', 'reminders_queued', 'reminders_sent', 'skipped_paid', 'periods_rolled', 'subscriptions_expired'] as $key) {
                            $summary[$key] += $result[$key];
                        }

                        if ($result['reminder_log_id']) {
                            $handledReminderIds[] = $result['reminder_log_id'];
                        }
                    } catch (Throwable $e) {
                        $summary['errors']++;

                        Log::error('Finance renewal gagal untuk satu subscription; batch dilanjutkan.', [
                            'subscription_id' => $subscription->id,
                            'client_id' => $subscription->client_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $summary['reminders_retried'] = $this->retryFailedDeliveries(
            $retryCandidateIds->diff($handledReminderIds)
        );

        return $summary;
    }

    /**
     * Mengantrekan ulang reminder yang gagal kirim.
     *
     * @param iterable<int>|null $candidateIds
     */
    public function retryFailedDeliveries(?iterable $candidateIds = null): int
    {
        $retried = 0;

        $query = FinanceReminderLog::where('status', FinanceReminderLog::FAILED)
            ->where('attempts', '<', FinanceReminderLog::MAX_ATTEMPTS);

        if ($candidateIds !== null) {
            $ids = collect($candidateIds);

            if ($ids->isEmpty()) {
                return 0;
            }

            $query->whereIn('id', $ids);
        }

        $query
            ->with(['invoice.subscription', 'invoice.client', 'invoice.project.client'])
            ->chunkById(100, function ($logs) use (&$retried) {
                foreach ($logs as $log) {
                    try {
                        if ($this->retryDelivery($log)) {
                            $retried++;
                        }
                    } catch (Throwable $e) {
                        Log::error('Retry reminder gagal; batch dilanjutkan.', [
                            'reminder_log_id' => $log->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $retried;
    }

    private function retryDelivery(FinanceReminderLog $log): bool
    {
        $invoice = $log->invoice;

        if (!$invoice || $invoice->status === 'paid' || $invoice->status === 'cancelled') {
            return false;
        }

        $subscription = $invoice->subscription;

        if ($subscription && !$subscription->isActive()) {
            return false;
        }

        if ($subscription && !$subscription->auto_reminder) {
            return false;
        }

        $recipient = $invoice->billableClient()?->billingEmail();

        if (!$recipient) {
            FinanceReminderLog::whereKey($log->id)
                ->where('status', FinanceReminderLog::FAILED)
                ->where('attempts', '<', FinanceReminderLog::MAX_ATTEMPTS)
                ->update([
                    'attempts' => DB::raw('attempts + 1'),
                    'error_message' => 'Client tidak memiliki alamat email billing maupun email utama.',
                ]);

            return false;
        }

        $claimed = FinanceReminderLog::whereKey($log->id)
            ->where('status', FinanceReminderLog::FAILED)
            ->where('attempts', '<', FinanceReminderLog::MAX_ATTEMPTS)
            ->update([
                'status' => FinanceReminderLog::PENDING,
                'attempts' => DB::raw('attempts + 1'),
                'recipient' => $recipient,
                'error_message' => null,
            ]);

        if ($claimed === 0) {
            return false;
        }

        $log->refresh();

        try {
            $this->dispatcher->send($invoice, $log, $recipient);
        } catch (Throwable $e) {
            $log->update([
                'status' => FinanceReminderLog::FAILED,
                'error_message' => ProviderException::sanitise($e->getMessage()),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Memproses satu langganan secara atomik.
     *
     * @return array<string, int|null>
     */
    public function processSubscription(FinanceSubscription $subscription): array
    {
        $result = [
            'invoices_created' => 0,
            'reminders_queued' => 0,
            'reminders_sent' => 0,
            'skipped_paid' => 0,
            'periods_rolled' => 0,
            'subscriptions_expired' => 0,
            'reminder_log_id' => null,
        ];

        if ($this->cycle->daysUntil($subscription->next_renewal_date) > $this->policy->invoiceCreationThreshold($subscription)) {
            return $result;
        }

        $outcome = DB::transaction(function () use ($subscription): array {
            /** @var FinanceSubscription|null $locked */
            $locked = FinanceSubscription::whereKey($subscription->getKey())->lockForUpdate()->first();

            if (!$locked || !$locked->isActive()) {
                return ['invoices' => 0, 'invoice' => null, 'threshold' => null, 'rolled' => 0, 'expired' => 0];
            }

            ['steps' => $rolled, 'expired' => $expired, 'closed' => $closed] = $this->rollToCurrentPeriod($locked);

            $invoices = $closed ? (int) $this->invoiceForPeriod($locked, $closed)['created'] : 0;

            if ($expired) {
                return ['invoices' => $invoices, 'invoice' => null, 'threshold' => null, 'rolled' => $rolled, 'expired' => 1];
            }

            $decision = $this->invoiceForPeriod($locked, $locked->next_renewal_date);

            return [
                'invoices' => $invoices + (int) $decision['created'],
                'invoice' => $decision['invoice'],
                'threshold' => $this->policy->thresholdDueOn($locked, $this->cycle->daysUntil($locked->next_renewal_date)),
                'rolled' => $rolled,
                'expired' => 0,
            ];
        });

        $result['invoices_created'] = $outcome['invoices'];
        $result['periods_rolled'] = $outcome['rolled'];
        $result['subscriptions_expired'] = $outcome['expired'];

        if ($outcome['expired']) {
            return $result;
        }

        $invoice = $outcome['invoice'];

        if (!$invoice) {
            return $result;
        }

        if ($invoice->status === 'paid') {
            $result['skipped_paid']++;

            return $result;
        }

        $threshold = $outcome['threshold'];

        if ($threshold === null || !$subscription->auto_reminder) {
            return $result;
        }

        ['outcome' => $delivery, 'log_id' => $logId] = $this->claimAndSend($invoice, $subscription, $threshold);

        $result['reminder_log_id'] = $logId;

        if ($delivery === 'sent') {
            $result['reminders_sent']++;
        } elseif ($delivery === 'queued') {
            $result['reminders_queued']++;
        }

        return $result;
    }

    private function invoiceForPeriod(FinanceSubscription $subscription, CarbonInterface $opens): array
    {
        if (!$this->policy->invoiceDue($subscription, $this->cycle->daysUntil($opens))) {
            return ['invoice' => null, 'created' => false];
        }

        $period = $this->cycle->periodFor($subscription, $opens);
        $existing = $this->existingInvoiceFor($subscription, $period['start']);

        if ($existing) {
            return ['invoice' => $existing, 'created' => false];
        }

        return ['invoice' => $this->createRenewalInvoice($subscription, $period), 'created' => true];
    }

    private function rollToCurrentPeriod(FinanceSubscription $subscription): array
    {
        $steps = 0;
        $closed = null;

        while ($this->cycle->daysUntil($subscription->next_renewal_date) < 0) {
            $closed = CarbonImmutable::parse($subscription->next_renewal_date)->startOfDay();
            $next = $this->cycle->nextRenewalAfter($subscription);

            if (!$subscription->coversRenewalOn($next)) {
                $subscription->update(['status' => 'expired']);

                return ['steps' => $steps, 'expired' => 1, 'closed' => $closed];
            }

            $subscription->update(['next_renewal_date' => $next->toDateString()]);
            $steps++;
        }

        return ['steps' => $steps, 'expired' => 0, 'closed' => $closed];
    }

    private function claimAndSend(Invoice $invoice, FinanceSubscription $subscription, int $threshold): array
    {
        $recipient = $invoice->billableClient()?->billingEmail();
        $dueOn = $this->clock->today();

        $log = $this->claimThreshold($invoice, $subscription, $threshold, $dueOn, $recipient);

        if (!$log) {
            return ['outcome' => 'skipped', 'log_id' => null];
        }

        if (!$recipient) {
            $log->update([
                'status' => FinanceReminderLog::FAILED,
                'error_message' => 'Client tidak memiliki alamat email billing maupun email utama.',
            ]);

            return ['outcome' => 'failed', 'log_id' => $log->id];
        }

        try {
            $sent = $this->dispatcher->send($invoice, $log, $recipient);

            if ($sent) {
                $log->update([
                    'status' => FinanceReminderLog::SENT,
                    'sent_at' => now(),
                    'error_message' => null,
                ]);

                return ['outcome' => 'sent', 'log_id' => $log->id];
            }

            return ['outcome' => 'queued', 'log_id' => $log->id];
        } catch (Throwable $e) {
            $log->update([
                'status' => FinanceReminderLog::FAILED,
                'error_message' => ProviderException::sanitise($e->getMessage()),
            ]);

            return ['outcome' => 'failed', 'log_id' => $log->id];
        }
    }

    private function claimThreshold(
        Invoice $invoice,
        FinanceSubscription $subscription,
        int $threshold,
        CarbonImmutable $dueOn,
        ?string $recipient,
    ): ?FinanceReminderLog {
        $existing = FinanceReminderLog::where('invoice_id', $invoice->id)
            ->where('days_before', $threshold)
            ->first();

        if ($existing) {
            if ($existing->wasSent()) {
                return null;
            }

            if ($existing->attempts >= FinanceReminderLog::MAX_ATTEMPTS) {
                return null;
            }

            $existing->update([
                'status' => FinanceReminderLog::PENDING,
                'attempts' => DB::raw('attempts + 1'),
                'scheduled_for' => $dueOn->toDateString(),
                'recipient' => $recipient,
                'error_message' => null,
            ]);

            return $existing->fresh();
        }

        try {
            return FinanceReminderLog::create([
                'invoice_id' => $invoice->id,
                'billing_subscription_id' => $subscription->id,
                'days_before' => $threshold,
                'scheduled_for' => $dueOn->toDateString(),
                'status' => FinanceReminderLog::PENDING,
                'attempts' => 1,
                'recipient' => $recipient,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function existingInvoiceFor(FinanceSubscription $subscription, CarbonInterface $periodStart): ?Invoice
    {
        return Invoice::where('billing_subscription_id', $subscription->id)
            ->where('purpose', 'renewal')
            ->whereDate('billing_period_start', $this->clock->businessDate($periodStart)->toDateString())
            ->first();
    }

    private function createRenewalInvoice(FinanceSubscription $subscription, array $period): Invoice
    {
        for ($attempt = 1; $attempt <= self::NUMBER_ATTEMPTS; $attempt++) {
            $number = $this->numbers->next($period['start']->year);

            try {
                return DB::transaction(function () use ($subscription, $period, $number) {
                    $invoice = Invoice::create([
                        'client_id' => $subscription->client_id,
                        'project_id' => $subscription->project_id,
                        'billing_subscription_id' => $subscription->id,
                        'invoice_number' => $number,
                        'type' => 'full',
                        'purpose' => 'renewal',
                        'issue_date' => $this->clock->today()->toDateString(),
                        'due_date' => $period['start']->toDateString(),
                        'billing_period_start' => $period['start']->toDateString(),
                        'billing_period_end' => $period['end']->toDateString(),
                        'amount' => $subscription->amount,
                        'subtotal' => $subscription->amount,
                        'total_amount' => $subscription->amount,
                        'currency' => $subscription->currency ?: 'IDR',
                        'status' => 'unpaid',
                        'notes' => "Perpanjangan otomatis untuk {$subscription->name}",
                    ]);

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $this->itemDescription($subscription, $period),
                        'quantity' => 1,
                        'unit_price' => $subscription->amount,
                        'total' => $subscription->amount,
                        'position' => 0,
                    ]);

                    return $invoice;
                });
            } catch (UniqueConstraintViolationException) {
                if ($attempt === self::NUMBER_ATTEMPTS) {
                    throw new \RuntimeException("Gagal membuat nomor invoice unik setelah {$attempt} percobaan.");
                }
            }
        }

        throw new \RuntimeException('Gagal membuat invoice perpanjangan.');
    }

    private function itemDescription(FinanceSubscription $subscription, array $period): string
    {
        $start = $period['start']->locale('id')->translatedFormat('j M Y');
        $end = $period['end']->locale('id')->translatedFormat('j M Y');

        return "{$subscription->name} — {$start} s/d {$end}";
    }
}

