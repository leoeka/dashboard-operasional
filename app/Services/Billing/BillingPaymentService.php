<?php

namespace App\Services\Billing;

use App\Models\BillingSubscription;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Settling an invoice, and what that means for the subscription behind it.
 *
 * Marking paid used to be two column writes in a controller. For a renewal it
 * has to be more than that: money is recorded, the invoice is closed, and the
 * subscription moves to its next period — and those three must happen together
 * or not at all.
 *
 * The hazard is doing it twice. A double-clicked button, a retried request or a
 * second operator would otherwise record two payments and push the renewal date
 * two periods into the future, quietly skipping a year of billing. So the
 * invoice row is locked and its status re-read inside the transaction: the
 * second caller sees `paid` and does nothing.
 */
class BillingPaymentService
{
    public function __construct(private BillingCycle $cycle, private BillingClock $clock)
    {
    }

    /**
     * @param array{paid_on?: mixed, amount?: mixed, method?: string, reference?: ?string, notes?: ?string, recorded_by?: ?int} $details
     * @return array{invoice: Invoice, payment: ?Payment, already_paid: bool, renewal_advanced: bool}
     */
    public function markPaid(Invoice $invoice, array $details = []): array
    {
        return DB::transaction(function () use ($invoice, $details) {
            /** @var Invoice $locked */
            $locked = Invoice::whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            // The idempotency barrier. Everything below runs once per invoice,
            // however many times this is called.
            if ($locked->status === 'paid') {
                return [
                    'invoice' => $locked,
                    'payment' => null,
                    'already_paid' => true,
                    'renewal_advanced' => false,
                ];
            }

            $amount = $details['amount'] ?? $locked->amount;

            if ((float) $amount <= 0) {
                throw new \InvalidArgumentException('Nominal pembayaran harus lebih besar dari nol.');
            }

            $payment = Payment::create([
                'invoice_id' => $locked->getKey(),
                // A payment recorded late in the evening belongs to that
                // business day, not to the next UTC one.
                'paid_on' => $details['paid_on'] ?? $this->clock->today(),
                'amount' => $amount,
                'currency' => $locked->currency ?: 'IDR',
                'method' => $details['method'] ?? 'bank_transfer',
                'reference' => $details['reference'] ?? null,
                'notes' => $details['notes'] ?? null,
                'recorded_by' => $details['recorded_by'] ?? null,
            ]);

            $locked->update([
                'status' => 'paid',
                'paid_at' => $payment->paid_on,
            ]);

            $advanced = $this->advanceSubscription($locked);

            return [
                'invoice' => $locked->refresh(),
                'payment' => $payment,
                'already_paid' => false,
                'renewal_advanced' => $advanced,
            ];
        });
    }

    /**
     * Moves the subscription to its next period once its renewal invoice is
     * settled.
     *
     * Only for renewals — a project DP being paid says nothing about a hosting
     * cycle. The subscription is locked too, and the move is skipped if its
     * renewal date has already passed the period this invoice covered, which is
     * what makes a replayed payment harmless.
     */
    private function advanceSubscription(Invoice $invoice): bool
    {
        if (!$invoice->isRenewal() || !$invoice->billing_subscription_id) {
            return false;
        }

        /** @var BillingSubscription|null $subscription */
        $subscription = BillingSubscription::whereKey($invoice->billing_subscription_id)->lockForUpdate()->first();

        if (!$subscription) {
            return false;
        }

        // Already moved on — for instance because this invoice was settled once
        // before, or because a later period has since been billed.
        if ($invoice->billing_period_start
            && $subscription->next_renewal_date->gt($invoice->billing_period_start)) {
            return false;
        }

        $subscription->update([
            'next_renewal_date' => $this->cycle->nextRenewalAfter($subscription),
        ]);

        return true;
    }
}
