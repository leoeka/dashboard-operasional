<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Billing\ReminderTimeline;

/**
 * One invoice, in full — read-only.
 *
 * Separate from FinanceController because the detail view answers a different
 * question from the workspace tabs, and separate from InvoiceController because
 * that one owns the actions (create, mark paid, remind, delete) and this owns
 * none of them. The buttons on this page post to those same existing routes.
 */
class FinanceInvoiceController extends Controller
{
    public function show(Invoice $invoice, ReminderTimeline $timeline)
    {
        // Everything the page reads, in one pass. Without this the items table,
        // the payment list and the timeline would each trigger their own
        // queries per row.
        $invoice->load([
            'client',
            'project.client',
            'subscription',
            'items',
            'payments.recorder:id,name',
            'reminderLogs',
        ]);

        return view('finance.invoice-detail', [
            'invoice' => $invoice,
            // Empty for a project invoice, which has no schedule to plot.
            'timeline' => $timeline->for($invoice),
            'billingClient' => $invoice->billableClient(),
        ]);
    }
}
