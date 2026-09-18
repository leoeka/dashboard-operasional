<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>
</head>

{{--
    Inline styles and a table layout on purpose: email clients strip <style>
    blocks and have no CSS grid, so anything cleverer would render differently
    for every recipient. Kept close to the existing invoice reminder so both
    emails read as coming from the same company.
--}}

<body style="margin:0; padding:24px 12px; background:#f1f5f9; font-family: Arial, Helvetica, sans-serif; color:#1e293b;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:8px; padding:28px 24px;">

        <h2 style="margin:0 0 4px; font-size:19px; color:#0f172a;">Perpanjangan Layanan</h2>
        <p style="margin:0 0 20px; font-size:13px; color:#64748b;">Invoice {{ $invoice->invoice_number }}</p>

        <p style="margin:0 0 6px; font-size:14px;">Yth. {{ $clientName }},</p>
        <p style="margin:0 0 20px; font-size:14px; line-height:1.6;">{{ $intro }}</p>

        <table style="width:100%; border-collapse:collapse; margin:0 0 20px; font-size:14px;">
            <tr>
                <td style="padding:7px 0; color:#64748b; width:45%;">Layanan</td>
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
            @if ($periodStart && $periodEnd)
                <tr>
                    <td style="padding:7px 0; color:#64748b;">Periode</td>
                    <td style="padding:7px 0;">
                        {{ $periodStart->translatedFormat('d M Y') }} &ndash; {{ $periodEnd->translatedFormat('d M Y') }}
                    </td>
                </tr>
            @endif
            @if ($renewalDate)
                <tr>
                    <td style="padding:7px 0; color:#64748b;">Tanggal Perpanjangan</td>
                    <td style="padding:7px 0;">{{ $renewalDate->translatedFormat('d M Y') }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding:7px 0; color:#64748b;">Jatuh Tempo</td>
                <td style="padding:7px 0;">
                    <strong>{{ $dueDate->translatedFormat('d M Y') }}</strong>
                    @if ($daysRemaining > 0)
                        <span style="color:#64748b;">({{ $daysRemaining }} hari lagi)</span>
                    @elseif ($daysRemaining === 0)
                        <span style="color:#b45309;">(hari ini)</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding:7px 0; color:#64748b; border-top:1px solid #e2e8f0;">Total</td>
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

        {{--
            No payment link: there is no client portal yet, and inventing a URL
            that 404s would be worse than saying nothing. Replace this block
            once a real invoice page exists.
        --}}
        <p style="margin:0 0 20px; font-size:14px; line-height:1.6;">
            Silakan hubungi kami atau lakukan pembayaran sesuai informasi yang telah disepakati.
            Jika pembayaran sudah dilakukan, mohon abaikan email ini.
        </p>

        <p style="margin:0; font-size:14px; line-height:1.6; color:#475569;">
            Terima kasih,<br>
            <strong style="color:#1e293b;">{{ config('mail.from.name', 'PT. Exito Bali Digital') }}</strong>
        </p>
    </div>
</body>

</html>
