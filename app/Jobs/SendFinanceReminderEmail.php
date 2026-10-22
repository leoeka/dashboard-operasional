<?php

namespace App\Jobs;

use App\Exceptions\ProviderException;
use App\Mail\FinanceRenewalReminderMail;
use App\Models\FinanceReminderLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mengirimkan email pengingat perpanjangan ke alamat email klien.
 */
class SendFinanceReminderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    private const LOCK_SECONDS = 120;

    public function __construct(public int $reminderLogId)
    {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->lockKey()))
                ->dontRelease()
                ->expireAfter(self::LOCK_SECONDS),
        ];
    }

    public function lockKey(): string
    {
        return 'finance-reminder-email:' . $this->reminderLogId;
    }

    public function handle(): void
    {
        $log = FinanceReminderLog::with(['invoice.subscription', 'invoice.client', 'invoice.project.client', 'invoice.items'])
            ->find($this->reminderLogId);

        if (!$log) {
            return;
        }

        if ($log->wasSent()) {
            return;
        }

        $invoice = $log->invoice;

        if (!$invoice) {
            return;
        }

        if ($reason = $this->reasonNotToSend($log)) {
            $log->update(['error_message' => $reason]);

            return;
        }

        $recipient = $invoice->billableClient()?->billingEmail();

        if (!$recipient) {
            $this->fail($log, 'Client tidak memiliki alamat email billing maupun email utama.');

            return;
        }

        try {
            Mail::to($recipient)->send(new FinanceRenewalReminderMail($invoice, $log));
        } catch (Throwable $e) {
            $this->fail($log, ProviderException::sanitise($e->getMessage()));

            return;
        }

        DB::transaction(fn () => $log->update([
            'status' => FinanceReminderLog::SENT,
            'sent_at' => now(),
            'recipient' => $recipient,
            'error_message' => null,
        ]));
    }

    private function reasonNotToSend(FinanceReminderLog $log): ?string
    {
        if ($log->invoice->status === 'paid') {
            return 'Dibatalkan: invoice sudah lunas sebelum email terkirim.';
        }

        if ($log->invoice->status === 'cancelled') {
            return 'Dibatalkan: invoice dibatalkan sebelum email terkirim.';
        }

        $subscription = $log->invoice->subscription;

        if ($subscription && !$subscription->isActive()) {
            return "Dibatalkan: langganan berstatus {$subscription->status} sebelum email terkirim.";
        }

        return null;
    }

    private function fail(FinanceReminderLog $log, string $reason): void
    {
        $log->update([
            'status' => FinanceReminderLog::FAILED,
            'error_message' => $reason,
        ]);

        Log::warning('Pengiriman reminder renewal gagal.', [
            'reminder_log_id' => $log->id,
            'invoice_id' => $log->invoice_id,
            'attempts' => $log->attempts,
            'reason' => $reason,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $log = FinanceReminderLog::find($this->reminderLogId);

        if ($log && !$log->wasSent()) {
            $log->update([
                'status' => FinanceReminderLog::FAILED,
                'error_message' => ProviderException::sanitise($e->getMessage()),
            ]);
        }
    }
}
