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
     * The first renewal date strictly after $from, measured from the
     * subscription's own anchor date.
     *
     * The day of month always comes from the anchor and never from an
     * already-clamped intermediate date. The steps:
     *
     * 1. Find the first anniversary of the anchor that falls AFTER $from.
     * 2. If $from itself is an anniversary (the normal case), that one is the
     *    answer.
     * 3. If $from is NOT on the anchor's grid — e.g. start_date is 5 October but
     *    the first renewal was entered as 14 October — the anchor says nothing
     *    useful about this date. Counting "the next anniversary" would then
     *    return a date less than a full cycle away, or, as this used to do,
     *    skip an entire extra cycle (14 Oct 2026 -> 4 Oct 2028 for a yearly
     *    plan). So $from becomes its own anchor and the period is exactly one
     *    cycle long.
     */
    private function advanceFromAnchor(BillingSubscription $subscription, CarbonImmutable $from): CarbonImmutable
    {
        $anchor = CarbonImmutable::parse($subscription->start_date ?? $from)->startOfDay();

        if ($anchor->greaterThan($from)) {
            // A renewal date before the start date is not a cycle we can count
            // from; step once from the date itself.
            $anchor = $from;
        }

        $yearly = $subscription->isYearly();

        $at = fn (int $periods): CarbonImmutable => $yearly
            ? $anchor->addYearsNoOverflow($periods)
            : $anchor->addMonthsNoOverflow($periods);

        $elapsed = (int) ($yearly ? $anchor->diffInYears($from) : $anchor->diffInMonths($from));

        // The whole-period count above can land one too high or one too low
        // around clamped month ends, so settle it by comparison, not trust.
        while ($elapsed > 0 && $at($elapsed)->greaterThan($from)) {
            $elapsed--;
        }

        while (!$at($elapsed)->greaterThan($from)) {
            $elapsed++;
        }

        // $at($elapsed) is now the first anniversary strictly after $from.
        if (!$at($elapsed - 1)->equalTo($from)) {
            return $yearly ? $from->addYearsNoOverflow(1) : $from->addMonthsNoOverflow(1);
        }

        return $at($elapsed);
    }
}