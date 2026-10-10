<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Builds the invoice card shown in the reminder email and attached as a PDF.
 *
 * One place maps the models to the layout (pdf/partials/invoice-document), so the
 * email body and the PDF can never drift apart. Payment details come from
 * config('billing.payment'), the same source the email already used.
 */
class InvoicePdfService
{
    public function data(Invoice $invoice): array
    {
        $invoice->loadMissing(['client', 'project.client', 'subscription', 'items']);

        $client = $invoice->billableClient();
        $pay = config('billing.payment', []);

        $serviceName = $invoice->subscription?->name
            ?? $invoice->project?->name
            ?? ($invoice->isRenewal() ? 'Renewal' : 'Invoice');

        $discount = (float) $invoice->discount;
        $tax = (float) $invoice->tax;

        return [
            'number' => $invoice->invoice_number,
            'date' => $this->date($invoice->issue_date),
            'due_date' => $this->date($invoice->due_date),
            'payment_method' => 'Bank Transfer',

            'client' => [
                // Company name first (as in the sample); the billing contact is the fallback.
                'name' => $client?->company_name ?: ($client?->billingName() ?: 'Pelanggan'),
                'address' => $client?->address,
                'phone' => $client?->billing_phone ?: $client?->phone,
                'email' => $client?->billing_email ?: $client?->email,
            ],

            'company' => [
                'name' => config('mail.from.name', 'PT. Exito Bali Digital'),
                'bank' => $pay['bank_name'] ?? null,
                'account' => $pay['bank_account'] ?? null,
                'holder' => $pay['account_holder'] ?? null,
            ],

            'items' => $invoice->items->map(fn($item) => [
                'service' => $serviceName,
                'description' => $item->description,
                'rate' => $this->rupiah($item->unit_price),
                'qty' => $this->quantity($item->quantity),
                'amount' => $this->rupiah($item->total),
            ])->all(),

            // Only shown when something besides the line items affects the total.
            'subtotal' => ($discount > 0 || $tax > 0) ? $this->rupiah($invoice->subtotal) : null,
            'discount' => $discount > 0 ? '- ' . $this->rupiah($discount) : null,
            'tax' => $tax > 0 ? $this->rupiah($tax) : null,

            'total' => $this->rupiah($invoice->amount),
            'note' => $this->paymentNote($pay),
        ];
    }

    public function render(Invoice $invoice): string
    {
        return Pdf::loadView('pdf.invoice', ['doc' => $this->data($invoice)])
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->output();
    }

    /**
     * The NOTE block. Built from payment config rather than from invoices.notes,
     * which may hold internal remarks that a client should not see.
     */
    private function paymentNote(array $pay): string
    {
        $contacts = array_filter([
            !empty($pay['contact_email']) ? $pay['contact_email'] : null,
            !empty($pay['contact_whatsapp']) ? 'WhatsApp ' . $pay['contact_whatsapp'] : null,
        ]);
        $to = implode(' atau ', $contacts);

        if (!empty($pay['bank_account'])) {
            return 'Setelah melakukan pembayaran, mohon kirimkan bukti transfer'
                . ($to ? " ke {$to}." : ' kepada kami.');
        }

        return $to
            ? "Untuk informasi pembayaran, silakan hubungi kami di {$to}."
            : 'Untuk informasi pembayaran, silakan hubungi kami.';
    }

    private function rupiah(mixed $value): string
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }

    /** 1.00 -> "1", 1.50 -> "1,5". */
    private function quantity(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }

    /** Indonesian, to match the dates in the email text right above the card. Use 'en' for "October 6, 2026". */
    private function date(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->locale('id')->translatedFormat('j F Y') : null;
    }
}