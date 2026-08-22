<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Proposal;

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
        $totalProject = Project::count();
        $projectByStatus = Project::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $webProjectCount = Project::where(function ($query) {
            $query->whereNotNull('type')
                ->orWhere(function ($query) {
                    $query->where('wants_seo', false)
                        ->where('wants_backlink', false);
                });
        })->count();
        $seoProjectCount = Project::where('wants_seo', true)->count();
        $backlinkProjectCount = Project::where('wants_backlink', true)->count();
        $totalProposal = Proposal::count();
        $requestProjectCount = Project::where('status', 'request')->count();
        $activeProjectCount = Project::where('status', 'in_progress')->count();
        $unpaidInvoiceCount = Invoice::where('status', 'unpaid')->count();
        $unpaidInvoiceAmount = Invoice::where('status', 'unpaid')->sum('amount');
        $overdueInvoiceCount = Invoice::where('status', 'unpaid')
            ->whereDate('due_date', '<', today())
            ->count();
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
            'webProjectCount',
            'seoProjectCount',
            'backlinkProjectCount',
            'activeProjectCount',
            'totalProposal',
            'requestProjectCount',
            'unpaidInvoiceCount',
            'unpaidInvoiceAmount',
            'overdueInvoiceCount',
            'monthlyRevenue',
            'attentionProjects',
            'recentActivities',
        ));
    }
}
