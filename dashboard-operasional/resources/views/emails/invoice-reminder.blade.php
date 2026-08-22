<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
</head>

<body style="font-family: Arial, sans-serif; color: #1e293b; padding: 20px;">
    <h2>Payment Reminder</h2>
    <p>Dear {{ $invoice->project->client->contact_name ?? $invoice->project->client_name }},</p>
    <p>We would like to remind you that the following invoice is still outstanding:</p>

    <table style="border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding:6px 12px; color:#64748b;">Invoice No.</td>
            <td style="padding:6px 12px;"><strong>{{ $invoice->invoice_number }}</strong></td>
        </tr>
        <tr>
            <td style="padding:6px 12px; color:#64748b;">Project</td>
            <td style="padding:6px 12px;">{{ $invoice->project->name }}</td>
        </tr>
        <tr>
            <td style="padding:6px 12px; color:#64748b;">Type</td>
            <td style="padding:6px 12px;">{{ $invoice->typeLabel() }}</td>
        </tr>
        <tr>
            <td style="padding:6px 12px; color:#64748b;">Amount</td>
            <td style="padding:6px 12px;"><strong>Rp{{ number_format($invoice->amount, 0, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <td style="padding:6px 12px; color:#64748b;">Due Date</td>
            <td style="padding:6px 12px;">{{ $invoice->due_date->translatedFormat('d M Y') }}</td>
        </tr>
    </table>

    <p>Please complete the payment as soon as possible. Thank you for your cooperation.</p>
    <p>Best regards,<br>PT. Exito Bali Digital</p>
</body>

</html>