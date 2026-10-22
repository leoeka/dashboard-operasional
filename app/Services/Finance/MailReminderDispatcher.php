<?php

namespace App\Services\Finance;

use App\Jobs\SendFinanceReminderEmail;
use App\Jobs\SendFinanceReminderWhatsApp;
use App\Models\FinanceReminderLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailReminderDispatcher implements ReminderDispatcher
{
    private int $whatsappOffset = 0;

    public function send(Invoice $invoice, FinanceReminderLog $log, string $recipient): bool
    {
        SendFinanceReminderEmail::dispatch($log->getKey());

        try {
            if ($invoice->billableClient()?->whatsapp) {
                SendFinanceReminderWhatsApp::dispatch($log->getKey())
                    ->delay(now()->addSeconds($this->whatsappOffset));

                $this->whatsappOffset += $this->nextGap();
            }
        } catch (Throwable $e) {
            Log::warning('Gagal menjadwalkan WhatsApp reminder renewal.', [
                'reminder_log_id' => $log->getKey(),
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    private function nextGap(): int
    {
        $parts = array_map('intval', explode('-', (string) config('services.fonnte.delay', '60-180')));
        $min = $parts[0];
        $max = $parts[1] ?? $parts[0];

        return random_int(min($min, $max), max($min, $max));
    }
}
