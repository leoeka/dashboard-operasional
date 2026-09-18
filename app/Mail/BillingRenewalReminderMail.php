<?php

namespace App\Mail;

use App\Models\BillingReminderLog;
use App\Models\Invoice;
use App\Services\Billing\BillingClock;
use App\Services\Billing\ReminderPolicy;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The renewal notice a client actually receives.
 *
 * Separate from InvoiceReminderMail rather than shared with it: that one is
 * written around a project — it names the project and its DP/Pelunasan type —
 * and a hosting renewal has neither. Reusing it would have meant threading
 * conditionals through a template that legacy invoices still depend on.
 *
 * The first threshold of a cycle (H-30 yearly, H-7 monthly) is the same message
 * that announces the invoice, so a client gets one email that day, not an
 * "invoice created" and a "reminder" arriving together.
 */
class BillingRenewalReminderMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public BillingReminderLog $log,
        private BillingClock $clock = new BillingClock(),
        private ReminderPolicy $policy = new ReminderPolicy(),
    ) {
    }

    public function build()
    {
        return $this->subject($this->subjectLine())
            ->view('emails.billing-renewal-reminder', $this->viewData());
    }

    /**
     * Wording follows the threshold, because the same sentence cannot do both
     * jobs: a month out this is information a client files away, and the day
     * before it is something they need to act on. Never alarming — a renewal
     * notice is a service, not a demand.
     */
    private function subjectLine(): string
    {
        $service = $this->serviceName();
        $number = $this->invoice->invoice_number;
        $days = $this->log->days_before;

        if ($this->isFirstNotice()) {
            return "Pemberitahuan Perpanjangan {$service} – Invoice {$number}";
        }

        return match ($days) {
            1 => "Invoice {$number} Jatuh Tempo Besok – {$service}",
            default => "Pengingat Invoice {$number} – Jatuh Tempo {$days} Hari Lagi",
        };
    }

    /** The opening line, in the same register as the subject. */
    private function intro(): string
    {
        $days = $this->log->days_before;

        if ($this->isFirstNotice()) {
            return 'Layanan Anda akan segera memasuki periode perpanjangan. Berikut invoice untuk periode berikutnya sebagai informasi awal.';
        }

        return match ($days) {
            1 => 'Invoice berikut jatuh tempo besok. Mohon diselesaikan agar layanan Anda berjalan tanpa jeda.',
            3 => 'Invoice berikut akan jatuh tempo dalam 3 hari. Kami informasikan agar Anda punya waktu menyiapkannya.',
            default => 'Kami ingin mengingatkan bahwa invoice berikut akan segera jatuh tempo.',
        };
    }

    /** Whether this threshold is the one that also introduced the invoice. */
    private function isFirstNotice(): bool
    {
        $subscription = $this->invoice->subscription;

        return $subscription
            && $this->log->days_before === $this->policy->invoiceCreationThreshold($subscription);
    }

    private function serviceName(): string
    {
        return $this->invoice->subscription?->name
            // The subscription can be gone (deleted; the invoice survives by
            // design) — the line item still records what was billed.
            ?? $this->invoice->items->first()?->description
            ?? 'Layanan';
    }

    private function viewData(): array
    {
        $subscription = $this->invoice->subscription;
        $client = $this->invoice->billableClient();

        return [
            'invoice' => $this->invoice,
            'clientName' => $client?->billingName() ?: ($client?->company_name ?? 'Pelanggan'),
            'companyName' => $client?->company_name,
            'serviceName' => $this->serviceName(),
            'serviceType' => $subscription?->service_type,
            'billingCycle' => $subscription?->isYearly() ? 'Tahunan' : ($subscription ? 'Bulanan' : null),
            'amount' => $this->rupiah($this->invoice->amount),
            'currency' => $this->invoice->currency,
            'renewalDate' => $subscription?->next_renewal_date,
            'dueDate' => $this->invoice->due_date,
            'daysRemaining' => $this->daysRemaining(),
            'periodStart' => $this->invoice->billing_period_start,
            'periodEnd' => $this->invoice->billing_period_end,
            'intro' => $this->intro(),
            'items' => $this->invoice->items,
        ];
    }

    /**
     * Counted from the billing business date, so a client in the operating
     * timezone reads the same number of days we do.
     */
    private function daysRemaining(): int
    {
        return (int) $this->clock->businessDate($this->clock->today())
            ->diffInDays($this->clock->businessDate($this->invoice->due_date), false);
    }

    private function rupiah(mixed $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}
