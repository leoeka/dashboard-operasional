<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>
</head>

<body style="margin:0; padding:24px 12px; background:#f1f5f9; font-family: Arial, Helvetica, sans-serif; color:#1e293b;">

    <div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:8px; padding:28px 24px;">

        <h2 style="margin:0 0 16px; font-size:19px; color:{{ $isOverdue ? '#b91c1c' : '#0f172a' }};">{{ $heading }}</h2>

        <p style="margin:0 0 10px; font-size:14px;">Yth. {{ $clientName }},</p>
        <p style="margin:0 0 12px; font-size:14px; line-height:1.6;">{{ $intro }}</p>

        <p style="margin:0 0 20px; font-size:14px;">
            Jatuh tempo: <strong>{{ $dueDate }}</strong>
            <span style="color:{{ $isOverdue ? '#b91c1c' : ($isDueSoon ? '#b45309' : '#64748b') }};">({{ $dueNote }})</span>
        </p>

        @if ($billingCycle)
            <p style="margin:0 0 12px; font-size:14px;">Siklus: <strong>{{ $billingCycle }}</strong></p>
        @endif

        @if ($items->count() > 1)
            <p style="margin:0 0 6px; font-size:13px; color:#64748b;">Rincian:</p>
        @endif

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
