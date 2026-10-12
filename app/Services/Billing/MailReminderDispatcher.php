<?php

namespace App\Services\Billing;

use App\Jobs\SendBillingReminderEmail;
use App\Jobs\SendBillingReminderWhatsApp;
use App\Models\BillingReminderLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class MailReminderDispatcher implements ReminderDispatcher
{
    /**
     * Jeda WA menumpuk selama satu run: BillingRenewalService memakai
     * satu instance dispatcher untuk seluruh batch, jadi tiap WA berikutnya
     * dijadwalkan lebih lambat dari sebelumnya.
     */
    private int $whatsappOffset = 0;

    public function send(Invoice $invoice, BillingReminderLog $log, string $recipient): bool
    {
        SendBillingReminderEmail::dispatch($log->getKey());

        // Masalah WA tidak boleh membuat jalur email dianggap gagal.
        try {
            if ($invoice->billableClient()?->whatsapp) {
                SendBillingReminderWhatsApp::dispatch($log->getKey())
                    ->delay(now()->addSeconds($this->whatsappOffset));

                $this->whatsappOffset += $this->nextGap();
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal menjadwalkan WhatsApp reminder renewal.', [
                'reminder_log_id' => $log->getKey(),
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /** Jeda acak dalam detik dari FONNTE_DELAY, mis. "60-180". */
    private function nextGap(): int
    {
        $parts = array_map('intval', explode('-', (string) config('services.fonnte.delay', '60-180')));
        $min = $parts[0];
        $max = $parts[1] ?? $parts[0];

        return random_int(min($min, $max), max($min, $max));
    }
}