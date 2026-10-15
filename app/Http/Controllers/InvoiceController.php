<?php

namespace App\Http\Controllers;

use App\Models\Invoice;

/**
 * Invoice actions. Payment and reminders are no longer here: payments are
 * settled outside this system, and the only reminders sent are the renewal
 * schedule's own, which the billing engine owns end to end.
 */
class InvoiceController extends Controller
{
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
