<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; font-size: 12px; }
        .header { display: table; width: 100%; border-bottom: 3px solid #2563eb; padding-bottom: 12px; margin-bottom: 20px; }
        .header .logo-cell { display: table-cell; width: 130px; vertical-align: middle; }
        .header .logo-cell img { width: 110px; }
        .header .text-cell { display: table-cell; vertical-align: middle; text-align: right; }
        .header .agency { font-size: 16px; font-weight: bold; color: #1e3a5f; }
        .header .date { color: #64748b; font-size: 11px; }

        h1 { font-size: 18px; color: #1e3a5f; margin-bottom: 4px; }
        .sub { color: #64748b; margin-bottom: 24px; }
        h2 { font-size: 14px; color: #1e40af; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 10px; text-align: left; font-size: 11px; }
        th { background: #f1f5f9; }
        .stat-box { display: inline-block; width: 30%; margin-right: 2%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; margin-bottom: 8px; }
        .stat-label { color: #64748b; font-size: 10px; }
        .stat-value { font-size: 16px; font-weight: bold; color: #1e293b; }
    </style>
</head>
<body>

    <div class="header">
        <div class="logo-cell">
            <img src="{{ public_path('images/logo-transparent.png') }}">
        </div>
        <div class="text-cell">
            <div class="agency">PT. Exito Bali Digital</div>
            <div class="date">{{ now()->translatedFormat('d/m/Y H:i') }}</div>
        </div>
    </div>

    <h1>Operational Report</h1>
    <p class="sub">Generated on {{ now()->translatedFormat('d M Y, H:i') }}</p>

    <h2>Project Summary</h2>
    <table>
        <thead>
            <tr><th>Status</th><th>Count</th></tr>
        </thead>
        <tbody>
            @foreach (['request' => 'Request', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $key => $label)
                <tr><td>{{ $label }}</td><td>{{ $projectByStatus[$key] ?? 0 }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Financial Summary</h2>
    <div class="stat-box">
        <div class="stat-label">Received This Month</div>
        <div class="stat-value">Rp{{ number_format($paidThisMonth, 0, ',', '.') }}</div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Unpaid</div>
        <div class="stat-value">Rp{{ number_format($unpaidTotal, 0, ',', '.') }}</div>
    </div>
    @if ($overdueInvoiceCount > 0)
        <p style="color:#b91c1c; margin-top:8px;">{{ $overdueInvoiceCount }} invoice(s) overdue.</p>
    @endif

    <h2>Income - Last 6 Months</h2>
    <table>
        <thead>
            <tr><th>Month</th><th>Total</th></tr>
        </thead>
        <tbody>
            @foreach ($incomeChart as $item)
                <tr><td>{{ $item['label'] }}</td><td>Rp{{ number_format($item['total'], 0, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Most Active Clients</h2>
    <p>{{ $newClientsThisMonth }} new client(s) this month.</p>
    <table>
        <thead>
            <tr><th>Company</th><th>Project Count</th></tr>
        </thead>
        <tbody>
            @forelse ($topClients as $client)
                <tr><td>{{ $client->company_name }}</td><td>{{ $client->projects_count }}</td></tr>
            @empty
                <tr><td colspan="2">No client data yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Performance</h2>
    <p>Average completed project duration: <strong>{{ $avgDuration }} days</strong></p>

</body>
</html>