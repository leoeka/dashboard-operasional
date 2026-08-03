<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;

class ReportController extends Controller
{
    public function index()
    {
        return view('pages.reports', $this->gatherData());
    }

    public function downloadPdf()
    {
        $data = $this->gatherData();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.report', $data);

        return $pdf->download('Laporan-' . now()->format('Y-m-d') . '.pdf');
    }

    public function downloadExcel()
    {
        $data = $this->gatherData();
        $filename = 'Laporan-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // BOM supaya Excel baca karakter (mis. "Rp") dengan benar
            fputs($handle, "\xEF\xBB\xBF");

            $statusLabels = [
                'request' => 'Request',
                'proposal' => 'Proposal',
                'mockup' => 'Mockup',
                'development' => 'Development',
                'qa' => 'QA',
                'done' => 'Selesai',
            ];

            fputcsv($handle, ['RINGKASAN PROJECT']);
            fputcsv($handle, ['Status', 'Jumlah']);
            foreach ($statusLabels as $key => $label) {
                fputcsv($handle, [$label, $data['projectByStatus'][$key] ?? 0]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['RINGKASAN KEUANGAN']);
            fputcsv($handle, ['Item', 'Nilai']);
            fputcsv($handle, ['Masuk Bulan Ini', $data['paidThisMonth']]);
            fputcsv($handle, ['Belum Dibayar', $data['unpaidTotal']]);
            fputcsv($handle, ['Invoice Terlambat', $data['overdueInvoiceCount']]);
            fputcsv($handle, []);

            fputcsv($handle, ['PEMASUKAN 6 BULAN TERAKHIR']);
            fputcsv($handle, ['Bulan', 'Total']);
            foreach ($data['incomeChart'] as $item) {
                fputcsv($handle, [$item['label'], $item['total']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['CLIENT PALING AKTIF']);
            fputcsv($handle, ['Perusahaan', 'Jumlah Project']);
            foreach ($data['topClients'] as $client) {
                fputcsv($handle, [$client->company_name, $client->projects_count]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['PERFORMA']);
            fputcsv($handle, ['Rata-rata Durasi Project Selesai (hari)', $data['avgDuration']]);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function gatherData(): array
    {
        // ===== RINGKASAN PROJECT =====
        $projectByStatus = Project::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $overdueCount = Project::whereDate('deadline', '<', now())
            ->whereNotIn('status', ['done'])
            ->count();

        // ===== RINGKASAN KEUANGAN =====
        $paidThisMonth = Invoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $unpaidTotal = Invoice::where('status', 'unpaid')->sum('amount');
        $overdueInvoiceCount = Invoice::where('status', 'unpaid')->whereDate('due_date', '<', now())->count();

        // Grafik pemasukan 6 bulan terakhir
        $incomeChart = collect(range(5, 0))->map(function ($i) {
            $month = now()->subMonths($i);
            $total = Invoice::where('status', 'paid')
                ->whereMonth('paid_at', $month->month)
                ->whereYear('paid_at', $month->year)
                ->sum('amount');

            return ['label' => $month->translatedFormat('M Y'), 'total' => (float) $total];
        });
        $maxIncome = $incomeChart->max('total') ?: 1;

        // ===== RINGKASAN CLIENT =====
        $topClients = Client::withCount('projects')
            ->orderByDesc('projects_count')
            ->take(5)
            ->get();

        $newClientsThisMonth = Client::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // ===== PERFORMA =====
        $doneProjects = Project::where('status', 'done')->get();
        $avgDuration = $doneProjects->isNotEmpty()
            ? round($doneProjects->avg(fn($p) => $p->created_at->diffInDays($p->updated_at)))
            : 0;

        return compact(
            'projectByStatus',
            'overdueCount',
            'paidThisMonth',
            'unpaidTotal',
            'overdueInvoiceCount',
            'incomeChart',
            'maxIncome',
            'topClients',
            'newClientsThisMonth',
            'avgDuration',
        );
    }
}