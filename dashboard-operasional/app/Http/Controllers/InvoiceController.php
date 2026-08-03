<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceReminderMail;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
        $invoice->project->logActivity("Invoice dibuat: {$invoice->invoice_number}");

        return back()->with('success', 'Invoice berhasil dibuat.');
    }

    public function markPaid(Invoice $invoice)
    {
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        $invoice->project->logActivity("Invoice {$invoice->invoice_number} ditandai lunas");

        return back()->with('success', 'Invoice ditandai lunas.');
    }

    public function sendReminderNow(Invoice $invoice, WhatsAppService $whatsapp)
    {
        $client = $invoice->project->client;

        if (!$client) {
            return back()->with('error', 'Project ini belum terhubung ke data client.');
        }

        if ($client->email) {
            Mail::to($client->email)->send(new InvoiceReminderMail($invoice));
        }

        if ($client->whatsapp) {
            $message = "Halo {$client->contact_name}, invoice {$invoice->invoice_number} sebesar Rp"
                . number_format($invoice->amount, 0, ',', '.')
                . " jatuh tempo {$invoice->due_date->translatedFormat('d M Y')}. Mohon segera diselesaikan.";

            $whatsapp->send($client->whatsapp, $message);
        }

        $invoice->update(['last_reminder_sent_at' => now()]);
        $invoice->project->logActivity("Reminder invoice {$invoice->invoice_number} dikirim manual");

        return back()->with('success', 'Reminder berhasil dikirim.');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return back()->with('success', 'Invoice dihapus.');
    }
}