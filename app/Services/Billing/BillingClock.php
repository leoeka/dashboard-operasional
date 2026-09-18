<?php

namespace App\Services\Billing;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * What "today" means to billing.
 *
 * The application runs on UTC and should keep doing so: created_at, queue
 * timestamps and log lines stay comparable, and rows written before and after
 * any config change mean the same thing. But billing does not reason in
 * instants — it reasons in calendar days. "H-7" has to mean the same day to us
 * and to the client, and at 00:30 in Makassar the UTC clock still says
 * yesterday. Left on UTC, a reminder would fire a day late for eight hours out
 * of every twenty-four.
 *
 * So this is the one place that knows billing's zone, read from config rather
 * than named in code, and every date decision in the billing services goes
 * through it.
 */
class BillingClock
{
    public function timezone(): string
    {
        return (string) config('billing.timezone', config('app.timezone', 'UTC'));
    }

    /** The current instant, expressed in the billing zone. */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /** The business date: what a person in the operating timezone would call today. */
    public function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }

    /**
     * A stored date column as a plain calendar date.
     *
     * Date columns (next_renewal_date, due_date, billing_period_*) are business
     * dates, not instants. Re-parsing from the Y-m-d string strips whatever
     * timezone the model cast attached, so comparing two of them can never be
     * thrown off by an eight-hour offset.
     */
    public function businessDate(CarbonInterface|string $date): CarbonImmutable
    {
        $value = $date instanceof CarbonInterface ? $date->format('Y-m-d') : CarbonImmutable::parse($date)->format('Y-m-d');

        return CarbonImmutable::parse($value);
    }
}
