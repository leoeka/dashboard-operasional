<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>
</head>

{{--
    Inline styles and a table layout on purpose: email clients strip <style>
    blocks and have no CSS grid. All wording and dates are prepared in
    BillingRenewalReminderMail so this file only lays them out.

    The invoice card (logo, Invoiced To / Pay To, item table, total, note) is the
    same partial the PDF attachment uses: pdf/partials/invoice-document.blade.php.
    $doc comes from InvoicePdfService::data(), added in BillingRenewalReminderMail.
    Payment instructions are part of that card (Pay To and NOTE), so they are not
    repeated below.
--}}

<body style="margin:0; padding:24px 12px; background:#f1f5f9; font-family: Arial, Helvetica, sans-serif; color:#1e293b;">

    <div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:8px; padding:28px 24px;">

        <h2 style="margin:0 0 16px; font-size:19px; color:{{ $isOverdue ? '#b91c1c' : '#0f172a' }};">{{ $heading }}</h2>

        <p style="margin:0 0 10px; font-size:14px;">Yth. {{ $clientName }},</p>
        <p style="margin:0 0 12px; font-size:14px; line-height:1.6;">{{ $intro }}</p>

        <p style="margin:0 0 20px; font-size:14px;">
            Jatuh tempo: <strong>{{ $dueDate }}</strong>
            <span style="color:{{ $isOverdue ? '#b91c1c' : ($isDueSoon ? '#b45309' : '#64748b') }};">({{ $dueNote }})</span>
        </p>

        <div style="margin:0 0 16px; padding:18px 16px; border:1px solid #e2e8f0; border-radius:6px;">
            @include('pdf.partials.invoice-document', [
                'doc' => $doc,
                'logoSrc' => $message->embed(public_path('images/logo_exito_bali_ads.png')),
            ])
        </div>

        <p style="margin:0 0 20px; font-size:14px; line-height:1.6;">
            Invoice juga kami lampirkan dalam format PDF.
            Jika pembayaran sudah dilakukan, mohon abaikan email ini.
        </p>

        <p style="margin:0; font-size:14px; line-height:1.6; color:#475569;">
            Terima kasih,<br>
            <strong style="color:#1e293b;">{{ config('mail.from.name', 'PT. Exito Bali Digital') }}</strong>
        </p>
    </div>
</body>

</html>