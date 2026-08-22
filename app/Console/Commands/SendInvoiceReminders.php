<?php

namespace App\Console\Commands;

use App\Mail\InvoiceReminderMail;
use App\Models\Invoice;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders';
    protected $description = 'Kirim reminder invoice yang belum dibayar via email dan WhatsApp';

    public function handle(WhatsAppService $whatsapp)
    {
        $invoices = Invoice::where('status', 'unpaid')
            ->whereDate('due_date', '<=', now()->addDays(3))
            ->where(function ($q) {
                $q->whereNull('last_reminder_sent_at')
                    ->orWhereDate('last_reminder_sent_at', '<', now()->toDateString());
            })
            ->with('project.client')
            ->get();

        foreach ($invoices as $invoice) {
             /** @var \App\Models\Invoice $invoice */
            $client = $invoice->project->client;

            if (!$client) {
                continue;
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
            $invoice->project->logActivity("Reminder invoice {$invoice->invoice_number} dikirim");
        }

        $this->info(count($invoices) . ' reminder terkirim.');
    }
}