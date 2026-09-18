<?php

namespace App\Services\Billing;

use App\Mail\InvoiceReminderMail;
use App\Models\Invoice;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The reminder behaviour project invoices have always had, lifted out of the
 * command unchanged — except for one thing that now matters enormously.
 *
 * The old query was "every unpaid invoice due within three days". The moment
 * renewal invoices began landing in the same table, that query would have
 * picked them up too: a client would get the renewal engine's H-3 reminder AND
 * this one, for the same invoice, on the same morning. So this path is now
 * restricted to `purpose = project_payment`, and the two can no longer both
 * claim an invoice.
 *
 * Everything else is deliberately as it was: DP / Pelunasan / Full invoices
 * still get email and WhatsApp, still at most once a day, still keyed on
 * last_reminder_sent_at. Recurring renewals send email only and key on
 * billing_reminder_logs instead.
 */
class LegacyInvoiceReminderService
{
    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    /** @return array<string, int> */
    public function sendDue(): array
    {
        $summary = ['legacy_reminders_sent' => 0, 'legacy_errors' => 0];

        $invoices = Invoice::where('status', 'unpaid')
            // The line that keeps renewals out of this path.
            ->where('purpose', Invoice::PURPOSE_PROJECT)
            ->whereDate('due_date', '<=', now()->addDays(3))
            ->where(function ($query) {
                $query->whereNull('last_reminder_sent_at')
                    ->orWhereDate('last_reminder_sent_at', '<', now()->toDateString());
            })
            ->with('project.client')
            ->get();

        foreach ($invoices as $invoice) {
            try {
                if ($this->remind($invoice)) {
                    $summary['legacy_reminders_sent']++;
                }
            } catch (\Throwable $e) {
                $summary['legacy_errors']++;

                Log::error('Legacy invoice reminder gagal; batch dilanjutkan.', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /** Unchanged behaviour, including WhatsApp, which recurring renewals do not use. */
    public function remind(Invoice $invoice): bool
    {
        $client = $invoice->project?->client;

        if (!$client) {
            return false;
        }

        if ($client->email) {
            Mail::to($client->email)->send(new InvoiceReminderMail($invoice));
        }

        if ($client->whatsapp) {
            $message = "Halo {$client->contact_name}, invoice {$invoice->invoice_number} sebesar Rp"
                . number_format((float) $invoice->amount, 0, ',', '.')
                . " jatuh tempo {$invoice->due_date->translatedFormat('d M Y')}. Mohon segera diselesaikan.";

            $this->whatsapp->send($client->whatsapp, $message);
        }

        $invoice->update(['last_reminder_sent_at' => now()]);
        $invoice->project?->logActivity("Reminder invoice {$invoice->invoice_number} dikirim");

        return true;
    }
}
