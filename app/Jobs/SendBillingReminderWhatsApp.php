<?php

namespace App\Jobs;

use App\Models\BillingReminderLog;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Mengirim WhatsApp untuk satu reminder renewal. Terpisah dari job email:
 * kegagalan WA tidak boleh menandai reminder email sebagai FAILED.
 * Hanya id log yang masuk antrian; semuanya dimuat ulang saat worker jalan.
 */
class SendBillingReminderWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $reminderLogId)
    {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('billing-reminder-wa:' . $this->reminderLogId))
                ->dontRelease()
                ->expireAfter(120),
        ];
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        $log = BillingReminderLog::with(['invoice.subscription', 'invoice.client', 'invoice.project.client'])
            ->find($this->reminderLogId);

        // Sudah terkirim: job ganda menjadi no-op.
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

        $layanan = $subscription?->name ? " untuk layanan {$subscription->name}" : '';

        $message = "Halo {$client->contact_name}, invoice perpanjangan {$invoice->invoice_number}{$layanan} sebesar Rp"
            . number_format((float) $invoice->amount, 0, ',', '.')
            . " jatuh tempo {$invoice->due_date->translatedFormat('d M Y')}. Mohon segera diselesaikan.";

        if ($whatsapp->send($client->whatsapp, $message)) {
            $log->update(['whatsapp_sent_at' => now()]);
        }
        // Gagal: sudah tercatat di log oleh WhatsAppService; tidak dicoba ulang otomatis.
    }
}