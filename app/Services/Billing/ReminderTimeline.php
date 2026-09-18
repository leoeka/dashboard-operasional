<?php

namespace App\Services\Billing;

use App\Models\BillingReminderLog;
use App\Models\BillingSubscription;
use App\Models\Invoice;

/**
 * What happened, and what is still due to happen, to one renewal invoice's
 * reminders.
 *
 * Entirely derived. Nothing here is stored: the schedule comes from
 * ReminderPolicy, the dates from the invoice's own billing period, and the
 * outcomes from the reminder logs that already exist. A stored timeline would
 * be a second copy of the truth, free to drift from the logs the scheduler
 * actually writes.
 *
 * It is observability, so it errs towards saying less. A missing log proves
 * exactly one thing — that no reminder was recorded for that threshold — and
 * not why. Where the absence has an explanation the data can actually support
 * (the day has not arrived; the invoice was already settled), the timeline says
 * so; where it does not, it reports the absence and stops there rather than
 * raising an alarm the operator cannot act on.
 *
 * It lives in a service rather than in Blade so the thresholds stay in one
 * place. A template that wrote out [30, 7, 3] itself would silently disagree
 * with the engine the day the schedule changed.
 */
class ReminderTimeline
{
    public function __construct(
        private ReminderPolicy $policy,
        private BillingClock $clock,
    ) {
    }

    /**
     * @return array<int, array{
     *     threshold: int, label: string, due_on: \Carbon\CarbonImmutable,
     *     state: string, state_label: string, tone: string, log: ?BillingReminderLog
     * }>
     */
    public function for(Invoice $invoice): array
    {
        $subscription = $invoice->subscription;

        // Only a renewal has a schedule. A project DP is reminded about ad hoc,
        // with no thresholds to plot.
        if (!$invoice->isRenewal() || !$subscription) {
            return [];
        }

        // The renewal date this invoice covers. billing_period_start is that
        // date by construction; due_date is the same day and stands in for
        // older rows that predate the period columns.
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

    /**
     * What to say about one threshold.
     *
     * A log settles the question outright — sent, failed, or claimed and not yet
     * delivered. Without one the honest answer depends on the calendar and on
     * whether the invoice still needs chasing at all:
     *
     * - not_required: the invoice was settled on or before this threshold's day,
     *                 so the engine deliberately stopped reminding. Calling that
     *                 "missed" would report the system working as designed as a
     *                 fault.
     * - upcoming:     the day has not arrived.
     * - due_today:    the day is today. The daily run fires at 09:00 in the
     *                 billing zone, so a threshold cannot be judged on the very
     *                 day it falls due.
     * - disabled:     the subscription is not set to send reminders, so nothing
     *                 is expected for a day still to come.
     * - missed:       the day passed with nothing recorded. The label stays
     *                 neutral — the absent row proves no reminder was logged, it
     *                 does not prove why.
     */
    private function resolveState(
        ?BillingReminderLog $log,
        $dueOn,
        $today,
        Invoice $invoice,
        BillingSubscription $subscription,
    ): array {
        if ($log?->wasSent()) {
            return ['sent', 'Terkirim', 'emerald'];
        }

        if ($log && $log->status === BillingReminderLog::FAILED) {
            return [
                'failed',
                'Gagal · percobaan ' . $log->attempts . '/' . BillingReminderLog::MAX_ATTEMPTS,
                'red',
            ];
        }

        if ($log) {
            // Claimed and queued, not yet delivered.
            return ['pending', 'Menunggu kirim', 'amber'];
        }

        // Checked before the calendar, and against this threshold's own day
        // rather than the invoice's status alone: a payment that arrives after a
        // threshold has already passed does not retroactively explain why that
        // earlier reminder was never sent.
        $paidOn = $this->settlementDate($invoice);

        if ($paidOn && $paidOn->lessThanOrEqualTo($dueOn)) {
            return ['not_required', 'Tidak diperlukan', 'slate'];
        }

        if ($dueOn->greaterThanOrEqualTo($today)) {
            // Nothing is overdue about a day that has not finished, and nothing
            // is expected at all when the subscription has reminders switched
            // off. Neither is a failure.
            if (!$subscription->auto_reminder) {
                return ['disabled', 'Reminder nonaktif', 'slate'];
            }

            return $dueOn->greaterThan($today)
                ? ['upcoming', 'Belum waktunya', 'slate']
                : ['due_today', 'Dijadwalkan hari ini', 'blue'];
        }

        return ['missed', 'Tidak tercatat', 'amber'];
    }

    /**
     * The business date the invoice was settled on, or null if it was not — or
     * if it was settled without a date being recorded, which proves nothing
     * either way.
     */
    private function settlementDate(Invoice $invoice): ?\Carbon\CarbonImmutable
    {
        if ($invoice->status !== 'paid' || !$invoice->paid_at) {
            return null;
        }

        return $this->clock->businessDate($invoice->paid_at);
    }
}
