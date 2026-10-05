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
--}}

<body style="margin:0; padding:24px 12px; background:#f1f5f9; font-family: Arial, Helvetica, sans-serif; color:#1e293b;">
    @php $pay = config('billing.payment', []); @endphp

    <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:8px; padding:28px 24px;">

        <h2 style="margin:0 0 4px; font-size:19px; color:{{ $isOverdue ? '#b91c1c' : '#0f172a' }};">{{ $heading }}</h2>
        <p style="margin:0 0 20px; font-size:13px; color:#64748b;">Invoice {{ $invoice->invoice_number }}</p>

        <p style="margin:0 0 10px; font-size:14px;">Yth. {{ $clientName }},</p>
        <p style="margin:0 0 20px; font-size:14px; line-height:1.6;">{{ $intro }}</p>

        <table style="width:100%; border-collapse:collapse; margin:0 0 20px; font-size:14px;">
            <tr>
                <td style="padding:7px 0; color:#64748b; width:42%;">Layanan</td>
                <td style="padding:7px 0;"><strong>{{ $serviceName }}</strong></td>
            </tr>
            @if ($serviceType)
                <tr>
                    <td style="padding:7px 0; color:#64748b;">Jenis</td>
                    <td style="padding:7px 0;">{{ ucfirst($serviceType) }}</td>
                </tr>
            @endif
            @if ($billingCycle)
                <tr>
                    <td style="padding:7px 0; color:#64748b;">Siklus</td>
                    <td style="padding:7px 0;">{{ $billingCycle }}</td>
                </tr>
            @endif
            @if ($period)
                <tr>
                    <td style="padding:7px 0; color:#64748b;">Periode</td>
                    <td style="padding:7px 0;">{{ $period }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding:7px 0; color:#64748b;">Jatuh Tempo</td>
                <td style="padding:7px 0;">
                    <strong>{{ $dueDate }}</strong>
                    <span style="color:{{ $isOverdue ? '#b91c1c' : ($isDueSoon ? '#b45309' : '#64748b') }};">({{ $dueNote }})</span>
                </td>
            </tr>
            <tr>
                <td style="padding:7px 0; color:#64748b; border-top:1px solid #e2e8f0;">Total Tagihan</td>
                <td style="padding:7px 0; border-top:1px solid #e2e8f0;">
                    <strong style="font-size:16px;">{{ $amount }}</strong>
                </td>
            </tr>
        </table>

        @if ($items->count() > 1)
            <p style="margin:0 0 6px; font-size:13px; color:#64748b;">Rincian:</p>
            <table style="width:100%; border-collapse:collapse; margin:0 0 20px; font-size:13px;">
                @foreach ($items as $item)
                    <tr>
                        <td style="padding:5px 0; color:#334155;">{{ $item->description }}</td>
                        <td style="padding:5px 0; text-align:right; white-space:nowrap;">
                            Rp {{ number_format((float) $item->total, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif

        {{-- Payment instructions come from config/billing.php (payment.*), filled from .env. --}}
        @if (!empty($pay['bank_account']))
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:14px 16px; margin:0 0 20px; font-size:14px; line-height:1.6;">
                <p style="margin:0 0 6px; font-size:13px; color:#64748b;">Pembayaran melalui transfer ke:</p>
                <strong>{{ $pay['bank_name'] ?? '' }}</strong> {{ $pay['bank_account'] }}<br>
                a.n. {{ $pay['account_holder'] ?? '' }}
            </div>
        @endif

        <p style="margin:0 0 20px; font-size:14px; line-height:1.6;">
            @if (!empty($pay['bank_account']))
                Setelah melakukan pembayaran, mohon kirimkan bukti transfer
                @if (!empty($pay['contact_email']))
                    ke {{ $pay['contact_email'] }}@if (!empty($pay['contact_whatsapp'])) atau WhatsApp {{ $pay['contact_whatsapp'] }}@endif.
                @else
                    kepada kami.
                @endif
            @elseif (!empty($pay['contact_email']))
                Untuk informasi pembayaran, silakan hubungi kami di {{ $pay['contact_email'] }}@if (!empty($pay['contact_whatsapp'])) atau WhatsApp {{ $pay['contact_whatsapp'] }}@endif.
            @else
                Untuk informasi pembayaran, silakan hubungi kami.
            @endif
            Jika pembayaran sudah dilakukan, mohon abaikan email ini.
        </p>

        <p style="margin:0; font-size:14px; line-height:1.6; color:#475569;">
            Terima kasih,<br>
            <strong style="color:#1e293b;">{{ config('mail.from.name', 'PT. Exito Bali Digital') }}</strong>
        </p>
    </div>
</body>

</html>