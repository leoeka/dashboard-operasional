<?php

namespace App\Services\Billing;

use App\Models\BillingSubscription;

/**
 * When renewal reminders go out, and when the invoice behind them is raised.
 *
 * One place, on purpose. These numbers were the thing most likely to end up
 * copied into a command, a controller and a Blade file and then drift apart;
 * changing the schedule should be an edit here and nowhere else.
 *
 * A yearly service gives the client a month's warning because renewing hosting
 * or a domain often needs a budget decision. A monthly retainer does not — a
 * 30-day notice on a 30-day cycle would arrive before the previous period had
 * even finished.
 */
class ReminderPolicy
{
    /** Days before the renewal date, always largest first. */
    private const THRESHOLDS = [
        BillingSubscription::CYCLE_YEARLY => [30, 7, 3],
        BillingSubscription::CYCLE_MONTHLY => [7, 3, 1],
    ];

    /** @return array<int, int> */
    public function thresholdsFor(BillingSubscription $subscription): array
    {
        return self::THRESHOLDS[$subscription->billing_cycle] ?? self::THRESHOLDS[BillingSubscription::CYCLE_YEARLY];
    }

    /**
     * The first threshold is also when the invoice is raised: H-30 for yearly,
     * H-7 for monthly. Every later reminder points at that same invoice — a
     * reminder is a nudge about a bill that already exists, never a reason to
     * issue another one.
     */
    public function invoiceCreationThreshold(BillingSubscription $subscription): int
    {
        return max($this->thresholdsFor($subscription));
    }

    /**
     * The threshold that today's run should act on, or null if today is not a
     * reminder day.
     *
     * Exact matching, not "within range": a run on H-29 must not fire the H-30
     * reminder late, because the schedule is what the client was promised. A
     * missed day is visible as a missing reminder log rather than silently
     * blurring into the next one.
     */
    public function thresholdDueOn(BillingSubscription $subscription, int $daysUntilRenewal): ?int
    {
        foreach ($this->thresholdsFor($subscription) as $threshold) {
            if ($threshold === $daysUntilRenewal) {
                return $threshold;
            }
        }

        return null;
    }

    /**
     * Whether the invoice for this period ought to exist by now.
     *
     * Deliberately a RANGE, where thresholdDueOn() is an exact match. Those are
     * two different questions and conflating them made the invoice depend on
     * the scheduler being alive on one specific morning: if H-30 was missed the
     * invoice was never raised at all, and the H-7 run then reminded about a
     * bill that did not exist.
     *
     * A reminder is a promise about a date and cannot be sent late. An invoice
     * is a debt that is simply owed from the creation threshold onward, so it
     * is raised on the first run that sees the subscription — H-29, H-12, or
     * even past the renewal date if nobody has paid.
     */
    public function invoiceDue(BillingSubscription $subscription, int $daysUntilRenewal): bool
    {
        return $daysUntilRenewal <= $this->invoiceCreationThreshold($subscription);
    }
}
