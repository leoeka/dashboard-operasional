<?php

namespace App\Mail;

use App\Models\FinanceReminderLog;
use App\Models\Invoice;
use App\Services\Finance\FinanceClock;
use App\Services\Finance\ReminderPolicy;
use App\Services\InvoicePdfService;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Surat pengingat perpanjangan layanan yang dikirimkan ke klien.
 */
class FinanceRenewalReminderMail extends Mailable
{
    use SerializesModels;

    private ?int $daysLeft = null;

    public function __construct(
        public Invoice $invoice,
        public FinanceReminderLog $log,
        private FinanceClock $clock = new FinanceClock(),
        private ReminderPolicy $policy = new ReminderPolicy(),
    ) {
    }

    public function build()
    {
        return $this->subject($this->subjectLine())
            ->view('emails.finance-renewal-reminder', $this->viewData())
            ->attachData(
                app(InvoicePdfService::class)->render($this->invoice),
                $this->invoice->invoice_number . '.pdf',
                ['mime' => 'application/pdf']
            );
    }

    private function subjectLine(): string
    {
        $service = $this->serviceName();
        $number = $this->invoice->invoice_number;
        $days = $this->daysRemaining();

        if ($this->isFirstNotice()) {
            return "Pemberitahuan Perpanjangan {$service} – Invoice {$number}";
        }

        return match (true) {
            $days < 0 => "Invoice {$number} Telah Melewati Jatuh Tempo – {$service}",
            $days === 0 => "Invoice {$number} Jatuh Tempo Hari Ini – {$service}",
            $days === 1 => "Invoice {$number} Jatuh Tempo Besok – {$service}",
            default => "Pengingat Invoice {$number} – Jatuh Tempo {$days} Hari Lagi",
        };
    }

    private function heading(): string
    {
        if ($this->isFirstNotice()) {
            return 'Perpanjangan Layanan';
        }

        return $this->daysRemaining() < 0
            ? 'Invoice Melewati Jatuh Tempo'
            : 'Pengingat Pembayaran';
    }

    private function intro(): string
    {
        $days = $this->daysRemaining();
        $service = $this->serviceName();
        $due = $this->date($this->invoice->due_date);
        $amount = $this->rupiah($this->invoice->amount);

        if ($this->isFirstNotice()) {
            return "Layanan {$service} Anda akan diperpanjang pada {$due}. "
                . "Kami telah menerbitkan invoice perpanjangan sebesar {$amount}, "
                . 'yang jatuh tempo pada tanggal tersebut. Email ini kami kirim lebih awal '
                . 'agar Anda dapat menyiapkan pembayaran.';
        }

        return match (true) {
            $days < 0 => "Invoice perpanjangan {$service} sebesar {$amount} telah melewati jatuh tempo "
                . 'pada ' . $due . ' (' . abs($days) . ' hari yang lalu) dan pembayarannya belum kami terima. '
                . 'Mohon segera diselesaikan agar layanan Anda tetap berjalan tanpa gangguan.',
            $days === 0 => "Invoice perpanjangan {$service} sebesar {$amount} jatuh tempo hari ini ({$due}). "
                . 'Mohon pembayaran diselesaikan hari ini agar layanan Anda berjalan tanpa jeda.',
            $days === 1 => "Invoice perpanjangan {$service} sebesar {$amount} jatuh tempo besok ({$due}). "
                . 'Mohon pembayaran diselesaikan agar layanan Anda berjalan tanpa jeda.',
            default => "Kami mengingatkan bahwa invoice perpanjangan {$service} sebesar {$amount} "
                . "akan jatuh tempo pada {$due} ({$days} hari lagi). "
                . 'Mohon pembayaran dapat diselesaikan sebelum tanggal tersebut.',
        };
    }

    private function dueNote(): string
    {
        $days = $this->daysRemaining();

        return match (true) {
            $days < 0 => 'terlambat ' . abs($days) . ' hari',
            $days === 0 => 'hari ini',
            $days === 1 => 'besok',
            default => "{$days} hari lagi",
        };
    }

    private function isFirstNotice(): bool
    {
        $subscription = $this->invoice->subscription;

        return $subscription
            && $this->log->days_before === $this->policy->invoiceCreationThreshold($subscription);
    }

    private function serviceName(): string
    {
        return $this->invoice->subscription?->name
            ?? $this->invoice->items->first()?->description
            ?? 'Layanan';
    }

    private function viewData(): array
    {
        $subscription = $this->invoice->subscription;
        $client = $this->invoice->billableClient();
        $days = $this->daysRemaining();

        return [
            'invoice' => $this->invoice,
            'heading' => $this->heading(),
            'clientName' => $client?->billingName() ?: ($client?->company_name ?? 'Pelanggan'),
            'serviceName' => $this->serviceName(),
            'serviceType' => $subscription?->service_type,
            'billingCycle' => $subscription?->isYearly() ? 'Tahunan' : ($subscription ? 'Bulanan' : null),
            'amount' => $this->rupiah($this->invoice->amount),
            'renewalDate' => $this->date($subscription?->next_renewal_date),
            'dueDate' => $this->date($this->invoice->due_date),
            'dueNote' => $this->dueNote(),
            'isOverdue' => $days < 0,
            'isDueSoon' => $days >= 0 && $days <= 1,
            'period' => $this->period(),
            'intro' => $this->intro(),
            'items' => $this->invoice->items,
            'doc' => app(InvoicePdfService::class)->data($this->invoice),
        ];
    }

    private function period(): ?string
    {
        $start = $this->date($this->invoice->billing_period_start);
        $end = $this->date($this->invoice->billing_period_end);

        return ($start && $end) ? "{$start} – {$end}" : null;
    }

    private function daysRemaining(): int
    {
        return $this->daysLeft ??= (int) $this->clock->businessDate($this->clock->today())
            ->diffInDays($this->clock->businessDate($this->invoice->due_date), false);
    }

    private function date(mixed $date): ?string
    {
        return $date
            ? Carbon::parse($date)->locale('id')->translatedFormat('j F Y')
            : null;
    }

    private function rupiah(mixed $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}

