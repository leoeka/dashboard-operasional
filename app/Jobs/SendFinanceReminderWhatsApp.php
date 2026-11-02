<?php

namespace App\Jobs;

use App\Models\FinanceReminderLog;
use App\Services\InvoicePdfService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim WhatsApp untuk satu reminder renewal. Terpisah dari job email:
 * kegagalan WA tidak boleh menandai reminder email sebagai FAILED.
 */
class SendFinanceReminderWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $reminderLogId)
    {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('finance-reminder-wa:' . $this->reminderLogId))
                ->dontRelease()
                ->expireAfter(120),
        ];
    }

    public function handle(WhatsAppService $whatsapp, InvoicePdfService $invoicePdf): void
    {
        $log = FinanceReminderLog::with(['invoice.subscription', 'invoice.client', 'invoice.project.client'])
            ->find($this->reminderLogId);

        if (!$log || $log->whatsapp_sent_at || !$log->invoice) {
            return;
        }

        $invoice = $log->invoice;

        if (in_array($invoice->status, ['paid', 'cancelled'], true)) {
            return;
        }

        $subscription = $invoice->subscription;

        if ($subscription && (!$subscription->isActive() || !$subscription->auto_reminder)) {
            return;
        }

        $client = $invoice->billableClient();

        if (!$client || !$client->whatsapp) {
            return;
        }

        try {
            $pdf = $invoicePdf->render($invoice);
        } catch (Throwable $e) {
            Log::error('WhatsApp reminder: gagal membuat PDF invoice.', [
                'reminder_log_id' => $this->reminderLogId,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $safeNumber = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $invoice->invoice_number), '-');
        $filename = 'Invoice-' . ($safeNumber ?: $invoice->id) . '.pdf';
        $caption = "Invoice {$invoice->invoice_number}";

        if ($whatsapp->sendDocument($client->whatsapp, $pdf, $filename, $caption)) {
            $log->update(['whatsapp_sent_at' => now()]);
        }
    }
}

