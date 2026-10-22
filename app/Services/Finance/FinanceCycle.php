<?php

namespace App\Services\Finance;

use App\Models\FinanceSubscription;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Aritmatika kalender siklus langganan berulang pada modul Finance.
 * Menghitung batas periode dan tanggal perpanjangan dari start_date
 * untuk mencegah tanggal bergeser (drift) tiap bulannya.
 */
class FinanceCycle
{
    public function __construct(private FinanceClock $clock = new FinanceClock())
    {
    }

    /**
     * Periode yang dicakup oleh invoice untuk $renewalDate.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function periodFor(FinanceSubscription $subscription, CarbonInterface $renewalDate): array
    {
        $start = CarbonImmutable::parse($renewalDate)->startOfDay();
        $next = $this->advanceFromAnchor($subscription, $start);

        return ['start' => $start, 'end' => $next->subDay()];
    }

    /**
     * Tanggal perpanjangan berikutnya setelah periode saat ini.
     */
    public function nextRenewalAfter(FinanceSubscription $subscription): CarbonImmutable
    {
        return $this->advanceFromAnchor($subscription, CarbonImmutable::parse($subscription->next_renewal_date)->startOfDay());
    }

    /**
     * Jumlah hari kalender dari hari ini ke tanggal perpanjangan.
     */
    public function daysUntil(CarbonInterface $renewalDate, CarbonInterface|string|null $today = null): int
    {
        $from = $today ? $this->clock->businessDate($today) : $this->clock->businessDate($this->clock->today());

        return (int) $from->diffInDays($this->clock->businessDate($renewalDate), false);
    }

    private function advanceFromAnchor(FinanceSubscription $subscription, CarbonImmutable $from): CarbonImmutable
    {
        $anchor = CarbonImmutable::parse($subscription->start_date ?? $from)->startOfDay();

        if ($anchor->greaterThan($from)) {
            $anchor = $from;
        }

        $yearly = $subscription->isYearly();

        $at = fn (int $periods): CarbonImmutable => $yearly
            ? $anchor->addYearsNoOverflow($periods)
            : $anchor->addMonthsNoOverflow($periods);

        $elapsed = (int) ($yearly ? $anchor->diffInYears($from) : $anchor->diffInMonths($from));

        while ($elapsed > 0 && $at($elapsed)->greaterThan($from)) {
            $elapsed--;
        }

        while (!$at($elapsed)->greaterThan($from)) {
            $elapsed++;
        }

        if (!$at($elapsed - 1)->equalTo($from)) {
            return $yearly ? $from->addYearsNoOverflow(1) : $from->addMonthsNoOverflow(1);
        }

        return $at($elapsed);
    }
}

