<?php

namespace App\Services\Finance;

use App\Models\FinanceReminderLog;
use App\Models\FinanceSubscription;
use App\Models\Invoice;
use Carbon\CarbonImmutable;

/**
 * Garis waktu pengingat tagihan perpanjangan.
 */
class ReminderTimeline
{
    public function __construct(
        private ReminderPolicy $policy,
        private FinanceClock $clock,
    ) {
    }

    /**
     * @return array<int, array{
     *     threshold: int, label: string, due_on: CarbonImmutable,
     *     state: string, state_label: string, tone: string, log: ?FinanceReminderLog
     * }>
     */
    public function for(Invoice $invoice): array
    {
        $subscription = $invoice->subscription;

        if (!$invoice->isRenewal() || !$subscription) {
            return [];
        }

        $renewalDate = $this->clock->businessDate($invoice->billing_period_start ?? $invoice->due_date);
        $today = $this->clock->businessDate($this->clock->today());
        $logs = $invoice->reminderLogs->keyBy('days_before');

        $timeline = [];

        foreach ($this->policy->thresholdsFor($subscription) as $threshold) {
            $dueOn = $renewalDate->subDays($threshold);
            $log = $logs->get($threshold);

            [$state, $stateLabel, $tone] = $this->resolveState($log, $dueOn, $today, $invoice, $subscription);

            $timeline[] = [
                'threshold' => $threshold,
                'label' => 'H-' . $threshold,
                'due_on' => $dueOn,
                'state' => $state,
                'state_label' => $stateLabel,
                'tone' => $tone,
                'log' => $log,
            ];
        }

        return $timeline;
    }

    private function resolveState(
        ?FinanceReminderLog $log,
        $dueOn,
        $today,
        Invoice $invoice,
        FinanceSubscription $subscription,
    ): array {
        if ($log?->wasSent()) {
            return ['sent', 'Terkirim', 'emerald'];
        }

        if ($log && $log->status === FinanceReminderLog::FAILED) {
            return [
                'failed',
                'Gagal · percobaan ' . $log->attempts . '/' . FinanceReminderLog::MAX_ATTEMPTS,
                'red',
            ];
        }

        if ($log) {
            return ['pending', 'Menunggu kirim', 'amber'];
        }

        $paidOn = $this->settlementDate($invoice);

        if ($paidOn && $paidOn->lessThanOrEqualTo($dueOn)) {
            return ['not_required', 'Tidak diperlukan', 'slate'];
        }

        if ($dueOn->greaterThanOrEqualTo($today)) {
            if (!$subscription->auto_reminder) {
                return ['disabled', 'Reminder nonaktif', 'slate'];
            }

            return $dueOn->greaterThan($today)
                ? ['upcoming', 'Belum waktunya', 'slate']
                : ['due_today', 'Dijadwalkan hari ini', 'blue'];
        }

        return ['missed', 'Tidak tercatat', 'amber'];
    }

    private function settlementDate(Invoice $invoice): ?CarbonImmutable
    {
        if ($invoice->status !== 'paid' || !$invoice->paid_at) {
            return null;
        }

        return $this->clock->businessDate($invoice->paid_at);
    }
}

