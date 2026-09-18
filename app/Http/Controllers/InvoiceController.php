<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Project;
use App\Services\Billing\BillingPaymentService;
use App\Services\Billing\LegacyInvoiceReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::query()
            ->when($request->status === 'unpaid', fn($q) => $q->where('status', 'unpaid'))
            ->when($request->status === 'paid', fn($q) => $q->where('status', 'paid'))
            ->when($request->status === 'overdue', fn ($q) => $q->where('status', 'unpaid')->where('due_date', '<', now()))
            ->with('project')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $projects = Project::orderBy('name')->get();

        return view('pages.finance', compact('invoices', 'projects'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'type' => 'required|in:dp,pelunasan,full',
            'amount' => 'required|numeric',
            'due_date' => 'required|date',
        ]);

        $data['invoice_number'] = 'INV-' . strtoupper(Str::random(3)) . '-' . random_int(1000, 9999);

        $invoice = Invoice::create($data);
        $invoice->project->logActivity("Invoice created: {$invoice->invoice_number}");

        return back()->with('success', 'Invoice created successfully.');
    }

    /**
     * Settling an invoice is no longer two column writes: it records a payment,
     * closes the invoice and, for a renewal, moves the subscription to its next
     * period — all in one transaction, and only once however many times this is
     * submitted. See BillingPaymentService.
     */
    public function markPaid(Invoice $invoice, BillingPaymentService $payments)
    {
        $result = $payments->markPaid($invoice, ['recorded_by' => auth()->id()]);

        if ($result['already_paid']) {
            return back()->with('success', 'Invoice ini memang sudah lunas.');
        }

        $invoice->project?->logActivity("Invoice {$invoice->invoice_number} marked as paid");

        return back()->with('success', $result['renewal_advanced']
            ? 'Invoice lunas. Tanggal perpanjangan layanan sudah dimajukan ke periode berikutnya.'
            : 'Invoice marked as paid.');
    }

    /**
     * Manual "remind now" for project invoices. Delegates to the same service
     * the scheduler uses, so a manual send and an automatic one can never drift
     * apart in behaviour.
     */
    public function sendReminderNow(Invoice $invoice, LegacyInvoiceReminderService $legacy)
    {
        if ($invoice->isRenewal()) {
            // Renewal reminders follow the H-30/H-7/H-3 schedule and are logged
            // per threshold; sending one by hand here would sidestep that record.
            return back()->with('error', 'Invoice renewal memakai jadwal reminder otomatis, bukan reminder manual.');
        }

        if (!$legacy->remind($invoice)) {
            return back()->with('error', 'This project is not linked to a client yet.');
        }

        $invoice->project?->logActivity("Reminder for invoice {$invoice->invoice_number} sent manually");

        return back()->with('success', 'Reminder sent successfully.');
    }

    /**
     * Deleting an invoice is only ever allowed for an unpaid project invoice —
     * the one case it was originally written for.
     *
     * Two things are now off limits. A settled invoice is financial history,
     * and a renewal invoice is part of the recurring billing record. Both also
     * have children that would go with them: payments, line items and the
     * reminder logs proving which emails were sent, all cascading on delete.
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