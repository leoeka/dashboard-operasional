<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Billing\BillingClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Invoice actions. Payment and reminders are no longer here: payments are
 * settled outside this system, and the only reminders sent are the renewal
 * schedule's own, which the billing engine owns end to end.
 *
 * The exception is the settlement marker below. Money still does not pass
 * through here; what an operator records is only that a bill they chased
 * outside the app was paid, so the reminder stops nagging and the number on
 * Reports means something.
 */
class InvoiceController extends Controller
{
    /**
     * Mark a bill as settled off-system, on a date the operator chooses.
     *
     * Deliberately narrower than the old payment form it replaces: no amount,
     * no method, no row in `payments`. Nominal is the dangerous thing to type
     * by hand, and the invoice already carries the amount it asked for.
     *
     * The date is compared through BillingClock, not today(). The app runs on
     * UTC while the business runs on config('billing.timezone'), so a WIB
     * operator at 03.00 on the 18th would have had "the 18th" rejected as a
     * future date by a UTC check.
     *
     * Nothing here touches the subscription. Periods roll on the calendar, not
     * on settlement, because payment happens where this system cannot see it.
     */
    public function markPaid(Request $request, Invoice $invoice, BillingClock $clock)
    {
        if ($invoice->status === 'cancelled') {
            return back()->with('error', 'Invoice dibatalkan tidak dapat ditandai lunas.');
        }

        if ($invoice->status === 'paid') {
            return back()->with('error', 'Invoice ini sudah ditandai lunas.');
        }

        $validated = $request->validate(
            ['paid_on' => ['required', 'date']],
            [],
            ['paid_on' => 'Tanggal bayar'],
        );

        $paidOn = CarbonImmutable::parse($validated['paid_on']);

        // Both sides go through businessDate(), not just the submitted one.
        // today() is midnight in the billing zone and parse() is midnight in
        // the app's UTC, so compared as instants the plain date would look
        // eight hours ahead of itself and today's payment would be rejected as
        // tomorrow's. Invoice::isOverdue() wraps both sides for the same reason.
        if ($clock->businessDate($paidOn)->greaterThan($clock->businessDate($clock->today()))) {
            return back()->with('error', 'Tanggal bayar tidak boleh jatuh di masa depan.');
        }

        $invoice->update(['status' => 'paid', 'paid_at' => $paidOn->format('Y-m-d')]);

        return back()->with('success', 'Invoice ditandai lunas.');
    }

    /**
     * Undo a marker typed by mistake, or one made before the money arrived.
     *
     * Back to `unpaid`, not to whatever it was: a renewal invoice is created
     * unpaid and nothing else writes that column.
     */
    public function markUnpaid(Invoice $invoice)
    {
        if ($invoice->status !== 'paid') {
            return back()->with('error', 'Invoice ini belum ditandai lunas.');
        }

        $invoice->update(['status' => 'unpaid', 'paid_at' => null]);

        return back()->with('success', 'Penanda lunas dibatalkan.');
    }

    /**
     * Deleting an invoice is only ever allowed for an unpaid project invoice —
     * the one case it was originally written for.
     *
     * Two things are off limits. A settled invoice is financial history, and a
     * renewal invoice is part of the recurring billing record. Both also have
     * children that would go with them: line items and the reminder logs
     * proving which emails were sent, all cascading on delete.
     *
     * The buttons are already hidden for those cases, but hiding a button is
     * not a rule — a direct request would still have gone through.
     */
    public function destroy(Invoice $invoice)
    {
        if ($invoice->isRenewal() || $invoice->status === 'paid') {
            return back()->with(
                'error',
                'Invoice yang sudah lunas atau invoice perpanjangan tidak dapat dihapus karena merupakan riwayat keuangan.'
            );
        }

        $invoice->delete();

        return back()->with('success', 'Invoice deleted.');
    }
}
