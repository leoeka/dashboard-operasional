<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
</head>

<body style="font-family: Arial, sans-serif; color: #1e293b; padding: 20px;">
    <h2>Reminder Pembayaran</h2>
    <p>Yth. {{ $invoice->project->client->contact_name ?? $invoice->project->client_name }},</p>
    <p>Kami ingin mengingatkan bahwa invoice berikut belum diselesaikan:</p>

    <table style="border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding:6px 12px; color:#64748b;">No. Invoice</td>
            <td style="padding:6px 12px;"><strong>{{ $invoice->invoice_number }}</strong></td>
        </tr>
        <tr>
            <td style="padding:6px 12px; color:#64748b;">Project</td>
            <td style="padding:6px 12px;">{{ $invoice->project->name }}</td>
        </tr>
        <tr>
            <td style="padding:6px 12px; color:#64748b;">Jenis</td>
            <td style="padding:6px 12px;">{{ $invoice->typeLabel() }}</td>
        </tr>
        <tr>
            <td style="padding:6px 12px; color:#64748b;">Jumlah</td>
            <td style="padding:6px 12px;"><strong>Rp{{ number_format($invoice->amount, 0, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <td style="padding:6px 12px; color:#64748b;">Jatuh Tempo</td>
            <td style="padding:6px 12px;">{{ $invoice->due_date->translatedFormat('d M Y') }}</td>
        </tr>
    </table>

    <p>Mohon segera melakukan pembayaran. Terima kasih atas kerja samanya.</p>
    <p>Salam,<br>PT. Exito Bali Digital</p>
</body>

</html>