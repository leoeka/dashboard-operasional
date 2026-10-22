<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\FinanceReminderLog;
use App\Models\Invoice;
use App\Models\Project;

class DashboardController extends Controller
{
    public static function middleware(): array
    {
        return [
            'auth',
            // atau dengan opsi khusus:
            // new Middleware('auth', only: ['index']),
        ];
    }
    public function index()
    {
        $today = today()->toDateString();

        $totalProject = Project::count();
        $projectByStatus = Project::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $requestProjectCount = Project::where('status', 'request')->count();
        $activeProjectCount = Project::where('status', 'in_progress')->count();

        $overdueInvoices = Invoice::where('status', 'unpaid')->whereDate('due_date', '<', $today);
        $overdueInvoiceCount = (clone $overdueInvoices)->count();
        $overdueInvoiceAmount = (clone $overdueInvoices)->sum('amount');

        $failedReminderCount = FinanceReminderLog::where('status', FinanceReminderLog::FAILED)->count();

        $monthlyRevenue = Invoice::where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $attentionProjects = Project::whereIn('status', ['request', 'in_progress'])
            ->latest()
            ->take(6)
            ->get();

        $recentActivities = ActivityLog::latest()->take(6)->get();

        return view('dashboard.index', compact(
            'totalProject',
            'projectByStatus',
            'requestProjectCount',
            'activeProjectCount',
            'overdueInvoiceCount',
            'overdueInvoiceAmount',
            'failedReminderCount',
            'monthlyRevenue',
            'attentionProjects',
            'recentActivities',
        ));
    }
}
