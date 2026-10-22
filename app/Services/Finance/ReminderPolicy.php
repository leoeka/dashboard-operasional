<?php

namespace App\Services\Finance;

use App\Models\FinanceSubscription;

/**
 * Aturan jadwal penagihan dan threshold pengingat perpanjangan.
 *
 * Layanan Tahunan: H-30, H-7, H-3.
 * Layanan Bulanan: H-7, H-3, H-1.
 */
class ReminderPolicy
{
    private const THRESHOLDS = [
        FinanceSubscription::CYCLE_YEARLY => [30, 7, 3],
        FinanceSubscription::CYCLE_MONTHLY => [7, 3, 1],
    ];

    /** @return array<int, int> */
    public function thresholdsFor(FinanceSubscription $subscription): array
    {
        return self::THRESHOLDS[$subscription->billing_cycle] ?? self::THRESHOLDS[FinanceSubscription::CYCLE_YEARLY];
    }

    public function invoiceCreationThreshold(FinanceSubscription $subscription): int
    {
        return max($this->thresholdsFor($subscription));
    }

    public function thresholdDueOn(FinanceSubscription $subscription, int $daysUntilRenewal): ?int
    {
        foreach ($this->thresholdsFor($subscription) as $threshold) {
            if ($threshold === $daysUntilRenewal) {
                return $threshold;
            }
        }

        return null;
    }

    public function invoiceDue(FinanceSubscription $subscription, int $daysUntilRenewal): bool
    {
        return $daysUntilRenewal <= $this->invoiceCreationThreshold($subscription);
    }
}

