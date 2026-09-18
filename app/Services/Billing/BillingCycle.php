<?php

namespace App\Services\Billing;

use App\Models\BillingSubscription;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The date arithmetic of a recurring subscription, kept away from the database
 * so it can be reasoned about — and tested — on its own.
 *
 * The hard part is month ends. PHP's native "+1 month" overflows: 31 January
 * plus a month is 3 March, which would march a subscription forward through the
 * calendar. Carbon's no-overflow variant clamps to 28 February instead, which
 * fixes the overflow but introduces the opposite problem — the next month would
 * then be 28 March, and the renewal day would creep backwards for good.
 *
 * So nothing is ever computed from the previous renewal date. Every renewal is
 * computed from `start_date`, the anchor the client actually signed up on, plus
 * a whole number of periods. A subscription started on 31 January renews on 28
 * February and then on 31 March; a yearly one started on 29 February 2024
 * renews on 28 February in ordinary years and returns to the 29th in 2028.
 */
class BillingCycle
{
    /**
     * Defaulted rather than required so the pure date arithmetic below stays
     * constructible on its own; only daysUntil() needs to know what day it is.
     */
    public function __construct(private BillingClock $clock = new BillingClock())
    {
    }

    /**
     * The period an invoice raised for $renewalDate covers: it begins on the
     * renewal date and ends the day before the one after it, so consecutive
     * periods touch without overlapping.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function periodFor(BillingSubscription $subscription, CarbonInterface $renewalDate): array
    {
        $start = CarbonImmutable::parse($renewalDate)->startOfDay();
        $next = $this->advanceFromAnchor($subscription, $start);

        return ['start' => $start, 'end' => $next->subDay()];
    }

    /**
     * Where the renewal date moves once a period has been paid for.
     *
     * Anchored on start_date rather than added to the current renewal date, so
     * a subscription cannot drift a day earlier every month.
     */
    public function nextRenewalAfter(BillingSubscription $subscription): CarbonImmutable
    {
        return $this->advanceFromAnchor($subscription, CarbonImmutable::parse($subscription->next_renewal_date)->startOfDay());
    }

    /**
     * Whole calendar days from today to the renewal date; negative once it has
     * passed.
     *
     * Both sides are reduced to bare Y-m-d first. Comparing a stored date
     * column against a zoned "now" is where an off-by-one creeps in: at 00:30
     * Makassar the UTC instant is still the previous day, and H-7 would be read
     * as H-8. See BillingClock.
     */
    public function daysUntil(CarbonInterface $renewalDate, CarbonInterface|string|null $today = null): int
    {
        $from = $today ? $this->clock->businessDate($today) : $this->clock->businessDate($this->clock->today());

        return (int) $from->diffInDays($this->clock->businessDate($renewalDate), false);
    }

    /**
     * One period past $from, measured from the subscription's own anchor date.
     *
     * The number of elapsed periods is counted from the anchor to $from, then
     * one more is added and applied to the anchor — so the day of month always
     * comes from the anchor and never from an already-clamped intermediate date.
     */
    private function advanceFromAnchor(BillingSubscription $subscription, CarbonImmutable $from): CarbonImmutable
    {
        $anchor = CarbonImmutable::parse($subscription->start_date ?? $from)->startOfDay();

        if ($anchor->greaterThan($from)) {
            // A renewal date before the start date is not a cycle we can count
            // from; step once from the date itself.
            $anchor = $from;
        }

        if ($subscription->isYearly()) {
            $elapsed = (int) $anchor->diffInYears($from);

            // Guard against an off-by-one when $from sits a hair under a whole
            // period because of the clamping described above.
            while ($anchor->addYearsNoOverflow($elapsed)->lessThan($from)) {
                $elapsed++;
            }

            return $anchor->addYearsNoOverflow($elapsed + 1);
        }

        $elapsed = (int) $anchor->diffInMonths($from);

        while ($anchor->addMonthsNoOverflow($elapsed)->lessThan($from)) {
            $elapsed++;
        }

        return $anchor->addMonthsNoOverflow($elapsed + 1);
    }
}
